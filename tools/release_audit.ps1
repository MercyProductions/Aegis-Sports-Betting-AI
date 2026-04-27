param(
    [string]$Configuration = "Release",
    [string]$Platform = "x64"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$release = Join-Path $root "$Platform\$Configuration"
$distRoot = Join-Path $root "dist"
$dist = Join-Path $distRoot "AegisSportsBettingAI-$Configuration"
$zip = Join-Path $distRoot "AegisSportsBettingAI-$Configuration.zip"
$msbuild = "C:\Program Files\Microsoft Visual Studio\2022\Community\MSBuild\Current\Bin\amd64\MSBuild.exe"
$versionHeader = Join-Path $root "src\AppVersion.h"

function Get-VersionValue($name) {
    $text = Get-Content $script:versionHeader -Raw
    if ($text -notmatch "$name\[\]\s*=\s*`"([^`"]+)`"") {
        throw "Could not read $name from $script:versionHeader"
    }
    return $Matches[1]
}

$appVersion = Get-VersionValue "kAppVersion"
$appCodename = Get-VersionValue "kAppCodename"
$appBuildDate = Get-VersionValue "kAppBuildDate"

if (!(Test-Path $msbuild)) {
    throw "MSBuild not found at $msbuild"
}

function Assert-LastExit($name) {
    if ($LASTEXITCODE -ne 0) {
        throw "$name failed with exit code $LASTEXITCODE"
    }
}

Write-Host "== Build $Configuration|$Platform =="
& $msbuild (Join-Path $root "AegisSportsBettingAI.sln") /p:Configuration=$Configuration /p:Platform=$Platform /m
Assert-LastExit "MSBuild"

Write-Host "== Smoke tests =="
& (Join-Path $root "tools\run_smoke_tests.ps1")
Assert-LastExit "Smoke tests"

Write-Host "== Adapter contract tests =="
& (Join-Path $root "tools\run_adapter_contract_tests.ps1") -Configuration $Configuration -Platform $Platform
Assert-LastExit "Adapter contract tests"

Write-Host "== Odds API tests =="
& (Join-Path $root "tools\run_odds_api_tests.ps1") -Configuration $Configuration -Platform $Platform
Assert-LastExit "Odds API tests"

Write-Host "== Package =="
& (Join-Path $root "tools\package_release.ps1") -Configuration $Configuration -Platform $Platform
Assert-LastExit "Package"

Write-Host "== Guard tests =="
& (Join-Path $root "tools\run_guard_tests.ps1") -Configuration $Configuration -Platform $Platform -RequirePackage
Assert-LastExit "Guard tests"

$errors = New-Object System.Collections.Generic.List[string]
function Assert-Audit($condition, $message) {
    if (-not $condition) {
        $script:errors.Add($message)
    }
}

Assert-Audit (Test-Path (Join-Path $release "AegisSportsBettingAI.exe")) "Release executable missing."
Assert-Audit (Test-Path (Join-Path $dist "AegisSportsBettingAI.exe")) "Packaged executable missing."
Assert-Audit (Test-Path $zip) "Release zip missing."

$rootConfig = Get-Content (Join-Path $root "AegisSportsBettingAI.config.ini") -Raw
$distConfig = Get-Content (Join-Path $dist "AegisSportsBettingAI.config.ini") -Raw
$releaseNotes = Get-Content (Join-Path $dist "RELEASE_NOTES.txt") -Raw
Assert-Audit ($rootConfig -match "odds_api_key=\s*(\r?\n|$)") "Root config contains an Odds API secret."
Assert-Audit ($distConfig -match "odds_api_key=\s*(\r?\n|$)") "Packaged config contains an Odds API secret."
Assert-Audit ($distConfig -match "kalshi_private_key=\s*(\r?\n|$)") "Packaged config contains a Kalshi private key."
Assert-Audit ($distConfig -match "paper_only_mode=true") "Packaged config should default to paper-only mode."
Assert-Audit ($releaseNotes -match [regex]::Escape("Version: $appVersion")) "Release notes do not include app version."
Assert-Audit ($releaseNotes -match [regex]::Escape("Codename: $appCodename")) "Release notes do not include codename."

$distFiles = Get-ChildItem $dist -Recurse -File
Assert-Audit (-not ($distFiles | Where-Object { $_.Extension -eq ".pdb" })) "Package contains PDB/debug symbols."
Assert-Audit (-not ($distFiles | Where-Object { $_.Name -match "diagnostics|remember|audit|journal|report|snapshot" })) "Package contains local runtime data."

Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::OpenRead($zip)
try {
    $entries = $archive.Entries | ForEach-Object { $_.FullName }
    Assert-Audit ($entries -contains "AegisSportsBettingAI.exe") "Zip does not contain executable."
    Assert-Audit ($entries -contains "AegisSportsBettingAI.config.ini") "Zip does not contain config."
    Assert-Audit (-not ($entries | Where-Object { $_ -match "\.pdb$|diagnostics|remember|audit|journal|report|snapshot" })) "Zip contains debug or local runtime data."
}
finally {
    $archive.Dispose()
}

$auditPath = Join-Path $dist "RELEASE_AUDIT.txt"
$audit = @(
    "Aegis Sports Betting AI Release Audit",
    "Version: $appVersion",
    "Codename: $appCodename",
    "Build date: $appBuildDate",
    "Configuration: $Configuration|$Platform",
    "Timestamp: $(Get-Date -Format s)",
    "Build: passed",
    "Smoke tests: passed",
    "Adapter contract tests: passed",
    "Odds API tests: passed",
    "Guard tests: passed",
    "Package hygiene: passed",
    "Installer readiness: generated",
    "Zip: $zip"
)

if ($errors.Count -gt 0) {
    $errors | ForEach-Object { Write-Error $_ }
    $audit += "Result: failed"
    $audit | Set-Content -Encoding UTF8 $auditPath
    exit 1
}

$audit += "Result: passed"
$audit | Set-Content -Encoding UTF8 $auditPath

Write-Host "== Installer readiness =="
& (Join-Path $root "tools\run_installer_readiness.ps1") -Configuration $Configuration -Platform $Platform
Assert-LastExit "Installer readiness"

if (Test-Path $zip) {
    Remove-Item -Force -LiteralPath $zip
}
Compress-Archive -Path (Join-Path $dist "*") -DestinationPath $zip
Write-Host "Release audit passed."
Write-Host "Wrote $auditPath"
