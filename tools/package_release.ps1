param(
    [string]$Configuration = "Release",
    [string]$Platform = "x64"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$release = Join-Path $root "$Platform\$Configuration"
$distRoot = Join-Path $root "dist"
$dist = Join-Path $distRoot "AegisSportsBettingAI-$Configuration"
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

if (!(Test-Path (Join-Path $release "AegisSportsBettingAI.exe"))) {
    throw "Release executable not found. Build $Configuration|$Platform first."
}

if (Test-Path $dist) {
    Remove-Item -Recurse -Force -LiteralPath $dist
}
New-Item -ItemType Directory -Force -Path $dist | Out-Null
Copy-Item -Force (Join-Path $release "AegisSportsBettingAI.exe") (Join-Path $dist "AegisSportsBettingAI.exe")
Copy-Item -Force (Join-Path $release "AegisSportsBettingAI.config.ini") (Join-Path $dist "AegisSportsBettingAI.config.ini")
Copy-Item -Force (Join-Path $root "README.md") (Join-Path $dist "README.md")
if (Test-Path (Join-Path $root "fixtures")) {
    Copy-Item -Recurse -Force (Join-Path $root "fixtures") (Join-Path $dist "fixtures")
}
if (Test-Path (Join-Path $root "docs")) {
    Copy-Item -Recurse -Force (Join-Path $root "docs") (Join-Path $dist "docs")
}

$notes = @"
Aegis Sports Betting AI
Version: $appVersion
Codename: $appCodename
Build date: $appBuildDate

This package intentionally excludes:
- PDB/debug symbols
- Local screenshots
- Local AppData journals, credentials, diagnostics, and reports

Secrets are stored per Windows user with DPAPI after the app saves settings.
Legacy configs are migrated to config_schema_version=2 and rewritten without plaintext provider secrets.
Optional feed sample fixtures are included under fixtures\optional-feeds.
Compliance checklist is included under docs\COMPLIANCE_CHECKLIST.md.
User-facing report exports are written through temp-file replacement to avoid partial final files.
Run tools\release_audit.ps1 to generate RELEASE_AUDIT.txt and INSTALLER_READINESS.txt before public upload.
"@
$notes | Set-Content -Encoding UTF8 (Join-Path $dist "RELEASE_NOTES.txt")

$zip = Join-Path $distRoot "AegisSportsBettingAI-$Configuration.zip"
if (Test-Path $zip) {
    Remove-Item -Force $zip
}
Compress-Archive -Path (Join-Path $dist "*") -DestinationPath $zip
Write-Host "Packaged $dist"
Write-Host "Created $zip"
