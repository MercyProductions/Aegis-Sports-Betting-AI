$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$release = Join-Path $root "x64\Release"
$config = Join-Path $root "AegisSportsBettingAI.config.ini"
$errors = New-Object System.Collections.Generic.List[string]

function Assert-True($condition, $message) {
    if (-not $condition) {
        $script:errors.Add($message)
    }
}

Assert-True (Test-Path (Join-Path $root "AegisSportsBettingAI.sln")) "Solution file is missing."
Assert-True (Test-Path (Join-Path $root "tools\release_audit.ps1")) "Release audit script is missing."
Assert-True (Test-Path (Join-Path $root "src\AppVersion.h")) "App version header is missing."
Assert-True (Test-Path (Join-Path $release "AegisSportsBettingAI.exe")) "Release executable is missing."
Assert-True (Test-Path (Join-Path $release "AegisSportsBettingAI.config.ini")) "Release config is missing."
Assert-True (Test-Path $config) "Root config is missing."

$configText = Get-Content $config -Raw
Assert-True ($configText -match "paper_only_mode=true") "Default config should ship in paper-only mode."
Assert-True ($configText -match "odds_api_key=\s*(\r?\n|$)") "Root config should not contain an Odds API secret."
Assert-True ($configText -match "kalshi_private_key=\s*(\r?\n|$)") "Root config should not contain a Kalshi private key."
Assert-True ($configText -match "responsible_use_accepted=false") "Default config should require responsible-use acknowledgement."

$src = Get-ChildItem (Join-Path $root "src") -Include *.cpp,*.h -Recurse
$todoHits = $src | Select-String -Pattern "TODO|FIXME|HACK|XXX" -CaseSensitive:$false
Assert-True (-not $todoHits) "Source contains TODO/FIXME/HACK/XXX markers."

$main = Get-Content (Join-Path $root "src\Main.cpp") -Raw
$versionHeader = Get-Content (Join-Path $root "src\AppVersion.h") -Raw
Assert-True ($versionHeader -match 'kAppVersion\[\]\s*=\s*"[^"]+"') "App version constant is missing."
Assert-True ($versionHeader -match 'kAppCodename\[\]\s*=\s*"[^"]+"') "App codename constant is missing."
Assert-True ($versionHeader -match 'kAppBuildDate\[\]\s*=\s*"[^"]+"') "App build date constant is missing."
Assert-True ($main -match "AppVersionLabel") "App version label is missing from UI code."
Assert-True ($main -match "RenderSetup") "Setup wizard surface is missing."
Assert-True ($main -match "RenderHealth") "Provider health center is missing."
Assert-True ($main -match "RenderProps") "Player props workspace is missing."
Assert-True ($main -match "ExportCsvReport") "CSV export is missing."
Assert-True ($main -match "ExportPdfReport") "PDF export is missing."
Assert-True ($main -match "BacktestRowsByMarket") "Backtest grouping is missing."
Assert-True ($main -match "ProviderSportQualityRows") "Provider sport quality rows are missing."
Assert-True ($main -match "ValidateDataAdapters") "Data adapter validation is missing."
Assert-True ($main -match "AdapterStatus") "Data adapter health rows are missing."
Assert-True ($main -match "blocked_missing_disclosures") "Manual handoff disclosure guard is missing."
Assert-True ($main -match "PruneLocalTsvFile") "Local app journal retention guard is missing."
Assert-True ($main -match "ProviderHealthFile") "Provider health telemetry file is missing."
Assert-True ($main -match "ExportProviderHealthReport") "Provider health export is missing."

$sportsData = Get-Content (Join-Path $root "src\SportsData.cpp") -Raw
Assert-True ($sportsData -match "PruneTsvFile") "Local TSV retention guard is missing."

if ($errors.Count -gt 0) {
    $errors | ForEach-Object { Write-Error $_ }
    exit 1
}

Write-Host "Smoke tests passed."
