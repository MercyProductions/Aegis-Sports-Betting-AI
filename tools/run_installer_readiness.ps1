param(
    [string]$Configuration = "Release",
    [string]$Platform = "x64"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$distRoot = Join-Path $root "dist"
$dist = Join-Path $distRoot "AegisSportsBettingAI-$Configuration"
$zip = Join-Path $distRoot "AegisSportsBettingAI-$Configuration.zip"
$readinessPath = Join-Path $dist "INSTALLER_READINESS.txt"
$versionHeader = Join-Path $root "src\AppVersion.h"
$errors = New-Object System.Collections.Generic.List[string]
$warnings = New-Object System.Collections.Generic.List[string]

function Get-VersionValue($name) {
    $text = Get-Content $script:versionHeader -Raw
    if ($text -notmatch "$name\[\]\s*=\s*`"([^`"]+)`"") {
        throw "Could not read $name from $script:versionHeader"
    }
    return $Matches[1]
}

function Add-Error($message) {
    $script:errors.Add($message)
}

function Add-Warning($message) {
    $script:warnings.Add($message)
}

function Test-RequiredFile($path, $label) {
    if (!(Test-Path $path)) {
        Add-Error "$label is missing: $path"
        return $false
    }
    return $true
}

function Get-SignToolPath {
    $cmd = Get-Command signtool.exe -ErrorAction SilentlyContinue
    if ($cmd) {
        return $cmd.Source
    }

    $kitsRoot = "${env:ProgramFiles(x86)}\Windows Kits\10\bin"
    if (!(Test-Path $kitsRoot)) {
        return ""
    }

    $candidate = Get-ChildItem -Path $kitsRoot -Directory -ErrorAction SilentlyContinue |
        Sort-Object Name -Descending |
        ForEach-Object { Join-Path $_.FullName "x64\signtool.exe" } |
        Where-Object { Test-Path $_ } |
        Select-Object -First 1

    if ($candidate) {
        return $candidate
    }
    return ""
}

function Get-RedactedValue($value) {
    if ([string]::IsNullOrWhiteSpace($value)) {
        return "not configured"
    }
    if ($value.Length -le 8) {
        return "configured"
    }
    return "$($value.Substring(0, 4))...$($value.Substring($value.Length - 4))"
}

$appVersion = Get-VersionValue "kAppVersion"
$appCodename = Get-VersionValue "kAppCodename"
$appBuildDate = Get-VersionValue "kAppBuildDate"

if (!(Test-Path $dist)) {
    Add-Error "Release package folder is missing. Run tools\package_release.ps1 first."
}

Test-RequiredFile (Join-Path $dist "AegisSportsBettingAI.exe") "Packaged executable" | Out-Null
Test-RequiredFile (Join-Path $dist "AegisSportsBettingAI.config.ini") "Packaged config" | Out-Null
Test-RequiredFile (Join-Path $dist "README.md") "Packaged README" | Out-Null
Test-RequiredFile (Join-Path $dist "RELEASE_NOTES.txt") "Release notes" | Out-Null
Test-RequiredFile (Join-Path $dist "docs\COMPLIANCE_CHECKLIST.md") "Compliance checklist" | Out-Null
Test-RequiredFile $zip "Release zip" | Out-Null

$configPath = Join-Path $dist "AegisSportsBettingAI.config.ini"
if (Test-Path $configPath) {
    $configText = Get-Content $configPath -Raw
    if ($configText -notmatch "paper_only_mode=true") {
        Add-Error "Packaged config must default to paper_only_mode=true."
    }
    if ($configText -notmatch "odds_api_key=\s*(\r?\n|$)") {
        Add-Error "Packaged config contains an Odds API secret."
    }
    if ($configText -notmatch "kalshi_private_key=\s*(\r?\n|$)") {
        Add-Error "Packaged config contains a Kalshi private key."
    }
}

$fixturesPath = Join-Path $dist "fixtures\optional-feeds"
if (!(Test-Path (Join-Path $fixturesPath "injuries.sample.json")) -or
    !(Test-Path (Join-Path $fixturesPath "lineups.sample.json")) -or
    !(Test-Path (Join-Path $fixturesPath "news.sample.json")) -or
    !(Test-Path (Join-Path $fixturesPath "props.sample.json"))) {
    Add-Warning "Optional feed sample fixtures are not all present in the package."
}

$auditPath = Join-Path $dist "RELEASE_AUDIT.txt"
if (Test-Path $auditPath) {
    $auditText = Get-Content $auditPath -Raw
    if ($auditText -notmatch "Result:\s*passed") {
        Add-Error "Release audit exists but is not marked passed."
    }
}
else {
    Add-Warning "RELEASE_AUDIT.txt is not present yet. Run tools\release_audit.ps1 before public upload."
}

if (Test-Path $zip) {
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $archive = [System.IO.Compression.ZipFile]::OpenRead($zip)
    try {
        $entries = $archive.Entries | ForEach-Object { $_.FullName }
        if ($entries -notcontains "AegisSportsBettingAI.exe") {
            Add-Error "Zip does not contain AegisSportsBettingAI.exe."
        }
        if ($entries -notcontains "AegisSportsBettingAI.config.ini") {
            Add-Error "Zip does not contain AegisSportsBettingAI.config.ini."
        }
        if (-not ($entries | Where-Object { $_ -eq "docs/COMPLIANCE_CHECKLIST.md" -or $_ -eq "docs\COMPLIANCE_CHECKLIST.md" })) {
            Add-Error "Zip does not contain docs/COMPLIANCE_CHECKLIST.md."
        }
        $badEntries = $entries | Where-Object { $_ -match "\.pdb$|AppData|credentials|remember|journal|snapshot|diagnostics" }
        if ($badEntries) {
            Add-Error "Zip contains debug, credential, or local runtime artifacts: $($badEntries -join ', ')"
        }
    }
    finally {
        $archive.Dispose()
    }
}

$signToolPath = Get-SignToolPath
$certThumbprint = $env:AEGIS_CODESIGN_CERT_SHA1
$certPath = $env:AEGIS_CODESIGN_CERT_PATH
$timestampUrl = $env:AEGIS_CODESIGN_TIMESTAMP_URL
$installerTool = $env:AEGIS_INSTALLER_TOOL

if ([string]::IsNullOrWhiteSpace($timestampUrl)) {
    $timestampUrl = "https://timestamp.digicert.com"
    Add-Warning "AEGIS_CODESIGN_TIMESTAMP_URL is not set; default timestamp URL will be documented."
}

if ([string]::IsNullOrWhiteSpace($signToolPath)) {
    Add-Warning "signtool.exe was not found. Install the Windows SDK before code signing."
}

if ([string]::IsNullOrWhiteSpace($certThumbprint) -and [string]::IsNullOrWhiteSpace($certPath)) {
    Add-Warning "No code-signing certificate is configured. Set AEGIS_CODESIGN_CERT_SHA1 or AEGIS_CODESIGN_CERT_PATH before wider distribution."
}

if ([string]::IsNullOrWhiteSpace($installerTool)) {
    Add-Warning "No installer tool is configured. Set AEGIS_INSTALLER_TOOL to the selected Inno Setup, WiX, MSIX, or winget packaging path."
}

$result = if ($errors.Count -eq 0) { "package-ready" } else { "blocked" }
$readiness = @(
    "Aegis Sports Betting AI Installer Readiness",
    "Version: $appVersion",
    "Codename: $appCodename",
    "Build date: $appBuildDate",
    "Configuration: $Configuration|$Platform",
    "Timestamp: $(Get-Date -Format s)",
    "",
    "Package folder: $dist",
    "Release zip: $zip",
    "Package result: $result",
    "",
    "Code-signing readiness",
    "signtool.exe: $(if ($signToolPath) { $signToolPath } else { 'not found' })",
    "Certificate thumbprint: $(Get-RedactedValue $certThumbprint)",
    "Certificate file: $(Get-RedactedValue $certPath)",
    "Timestamp URL: $timestampUrl",
    "",
    "Installer readiness",
    "Installer tool: $(if ($installerTool) { $installerTool } else { 'not configured' })",
    "Recommended installer contents: AegisSportsBettingAI.exe, AegisSportsBettingAI.config.ini, README.md, RELEASE_NOTES.txt, INSTALLER_READINESS.txt, docs\COMPLIANCE_CHECKLIST.md, fixtures\optional-feeds",
    "Recommended defaults: paper-only mode on, no embedded secrets, per-user DPAPI credentials only.",
    "",
    "Required next steps before public distribution",
    "1. Buy or assign a code-signing certificate.",
    "2. Sign AegisSportsBettingAI.exe and the final installer with SHA256 timestamping.",
    "3. Choose installer technology and create a Start Menu shortcut plus uninstall entry.",
    "4. Run tools\release_audit.ps1 after signing/installer changes.",
    "5. Upload only the audited zip/installer and never local AppData or credential files."
)

if ($warnings.Count -gt 0) {
    $readiness += ""
    $readiness += "Warnings"
    $readiness += ($warnings | ForEach-Object { "- $_" })
}

if ($errors.Count -gt 0) {
    $readiness += ""
    $readiness += "Blocking issues"
    $readiness += ($errors | ForEach-Object { "- $_" })
}

$readiness += ""
$readiness += "Result: $result"

if (Test-Path $dist) {
    $readiness | Set-Content -Encoding UTF8 $readinessPath
}

if ($errors.Count -gt 0) {
    $errors | ForEach-Object { Write-Error $_ }
    exit 1
}

Write-Host "Installer readiness complete: $result"
if ($warnings.Count -gt 0) {
    $warnings | ForEach-Object { Write-Warning $_ }
}
Write-Host "Wrote $readinessPath"
