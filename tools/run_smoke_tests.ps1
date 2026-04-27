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
Assert-True (Test-Path (Join-Path $root "tools\run_guard_tests.ps1")) "Guard test script is missing."
Assert-True (Test-Path (Join-Path $root "tools\run_adapter_contract_tests.ps1")) "Adapter contract test script is missing."
Assert-True (Test-Path (Join-Path $root "tools\run_odds_api_tests.ps1")) "Odds API test script is missing."
Assert-True (Test-Path (Join-Path $root "tools\run_ui_screenshot_smoke.ps1")) "UI screenshot smoke script is missing."
Assert-True (Test-Path (Join-Path $root "tools\run_installer_readiness.ps1")) "Installer readiness script is missing."
Assert-True (Test-Path (Join-Path $root "src\AppVersion.h")) "App version header is missing."
Assert-True (Test-Path (Join-Path $release "AegisSportsBettingAI.exe")) "Release executable is missing."
Assert-True (Test-Path (Join-Path $release "AegisSportsBettingAI.config.ini")) "Release config is missing."
Assert-True (Test-Path $config) "Root config is missing."
Assert-True (Test-Path (Join-Path $root "fixtures\optional-feeds\injuries.sample.json")) "Injury feed fixture is missing."
Assert-True (Test-Path (Join-Path $root "fixtures\optional-feeds\lineups.sample.json")) "Lineup feed fixture is missing."
Assert-True (Test-Path (Join-Path $root "fixtures\optional-feeds\news.sample.json")) "News feed fixture is missing."
Assert-True (Test-Path (Join-Path $root "fixtures\optional-feeds\props.sample.json")) "Props feed fixture is missing."
Assert-True (Test-Path (Join-Path $root "docs\COMPLIANCE_CHECKLIST.md")) "Compliance checklist is missing."

$configText = Get-Content $config -Raw
Assert-True ($configText -match "config_schema_version=2") "Root config should include the current schema version."
Assert-True ($configText -match "paper_only_mode=true") "Default config should ship in paper-only mode."
Assert-True ($configText -match "odds_api_key=\s*(\r?\n|$)") "Root config should not contain an Odds API secret."
Assert-True ($configText -match "kalshi_private_key=\s*(\r?\n|$)") "Root config should not contain a Kalshi private key."
Assert-True ($configText -match "responsible_use_accepted=false") "Default config should require responsible-use acknowledgement."

$src = Get-ChildItem (Join-Path $root "src") -Include *.cpp,*.h -Recurse
$todoHits = $src | Select-String -Pattern "TODO|FIXME|HACK|XXX" -CaseSensitive:$false
Assert-True (-not $todoHits) "Source contains TODO/FIXME/HACK/XXX markers."

$main = Get-Content (Join-Path $root "src\Main.cpp") -Raw
$versionHeader = Get-Content (Join-Path $root "src\AppVersion.h") -Raw
$platformHeader = Get-Content (Join-Path $root "src\Platform.h") -Raw
Assert-True ($versionHeader -match 'kAppVersion\[\]\s*=\s*"[^"]+"') "App version constant is missing."
Assert-True ($versionHeader -match 'kAppCodename\[\]\s*=\s*"[^"]+"') "App codename constant is missing."
Assert-True ($versionHeader -match 'kAppBuildDate\[\]\s*=\s*"[^"]+"') "App build date constant is missing."
Assert-True ($platformHeader -match 'kConfigSchemaVersion') "Config schema constant is missing."
Assert-True ($main -match "AppVersionLabel") "App version label is missing from UI code."
Assert-True ($main -match "Config schema") "Config schema status row is missing from UI code."
Assert-True ($main -match "ComplianceChecklistRows") "Compliance checklist UI rows are missing."
Assert-True ($main -match "Open Compliance") "Open Compliance action is missing."
Assert-True ($main -match "EmptyStateStatus") "Premium empty-state status chips are missing."
Assert-True ($main -match "CommitTempFile") "Crash-safe export file replacement is missing."
Assert-True ($main -match "BuildStartupIntegrityRows") "Startup integrity checks are missing."
Assert-True ($main -match "Startup Integrity") "Startup integrity UI card is missing."
Assert-True ($main -match "SettingsValidationRows") "Settings validation rows are missing."
Assert-True ($main -match "Settings not saved") "Settings save validation block is missing."
Assert-True ($main -match "ExportDiagnosticBundle") "Safe diagnostic bundle export is missing."
Assert-True ($main -match "settings-redacted.ini") "Diagnostic bundle redacted settings file is missing."
Assert-True ($main -match "RenderReportFilters") "Report export filters are missing."
Assert-True ($main -match "ReportFilteredPredictions") "Filtered report prediction export is missing."
Assert-True ($main -match "ProviderRefreshRows") "Per-provider refresh ledger rows are missing."
Assert-True ($main -match "provider_latency_ms") "Per-provider refresh latency tracking is missing."
Assert-True ($main -match "RefreshChangeRows") "Refresh change summary rows are missing."
Assert-True ($main -match "BuildRefreshChangeSummary") "Before/after refresh change comparison is missing."
Assert-True ($main -match "RenderSetup") "Setup wizard surface is missing."
Assert-True ($main -match "RenderHealth") "Provider health center is missing."
Assert-True ($main -match "RenderProps") "Player props workspace is missing."
Assert-True ($main -match "ExportCsvReport") "CSV export is missing."
Assert-True ($main -match "ExportPdfReport") "PDF export is missing."
Assert-True ($main -match "BacktestRowsByMarket") "Backtest grouping is missing."
Assert-True ($main -match "ProviderSportQualityRows") "Provider sport quality rows are missing."
Assert-True ($main -match "ValidateDataAdapters") "Data adapter validation is missing."
Assert-True ($main -match "AdapterStatus") "Data adapter health rows are missing."
Assert-True ($main -match "Adapter Schema Contracts") "Adapter schema contract UI is missing."
Assert-True ($main -match "CollectConfiguredOptionalFeeds") "Configured optional feed collection is missing."
Assert-True ($main -match "ApplyOptionalFeedSignals") "Optional feed signal application is missing."
Assert-True ($main -match "Odds Matching Diagnostics") "Odds matching diagnostics UI is missing."
Assert-True ($main -match "Per-Sport Odds Status") "Per-sport odds status UI is missing."
Assert-True ($main -match "CountOddsIssueGames") "Odds mismatch counting is missing."
Assert-True ($main -match "SetupComplete") "Setup complete state is missing."
Assert-True ($main -match "SetupStatusRows") "Setup status rows are missing."
Assert-True ($main -match "RenderStateBadge") "Source state badge renderer is missing."
Assert-True ($main -match "SourceBadgeText") "Game source badge classifier is missing."
Assert-True ($main -match "PredictionBadgeText") "Prediction source badge classifier is missing."
Assert-True ($main -match "LinkBadgeText") "Provider link badge classifier is missing."
Assert-True ($main -match "EnableScreenshotSmoke") "Screenshot smoke mode is missing."
Assert-True ($main -match "--screenshot-smoke") "Screenshot smoke command flag is missing."
Assert-True ($main -match "RenderAuthRecoveryPanel") "Auth recovery panel is missing."
Assert-True ($main -match "ProbeAuthService") "Auth service probe helper is missing."
Assert-True ($main -match "Check Auth") "Auth recovery check button is missing."
Assert-True ($main -match "odds_diagnostic") "Provider health export should include odds diagnostics."
Assert-True ($main -match "provider_sport_status") "Provider health export should include per-sport odds status."
Assert-True ($main -match "blocked_missing_disclosures") "Manual handoff disclosure guard is missing."
Assert-True ($main -match "blocked_missing_confirmation") "Manual handoff confirmation guard is missing."
Assert-True ($main -match "blocked_daily_exposure_limit") "Daily exposure guard is missing."
Assert-True ($main -match "blocked_ticket_limit") "Ticket limit guard is missing."
Assert-True ($main -match "blocked_confidence_floor") "Confidence floor guard is missing."
Assert-True ($main -match "PruneLocalTsvFile") "Local app journal retention guard is missing."
Assert-True ($main -match "ProviderHealthFile") "Provider health telemetry file is missing."
Assert-True ($main -match "ExportProviderHealthReport") "Provider health export is missing."

$platform = Get-Content (Join-Path $root "src\Platform.cpp") -Raw
Assert-True ($platform -match "config_schema_version") "Config schema version read/write is missing."
Assert-True ($platform -match "NormalizeConfig") "Config normalization/migration helper is missing."
Assert-True ($platform -match "MoveFileExW") "Crash-safe config replacement is missing."
Assert-True ($platform -match "config_migration") "Config migration diagnostic is missing."

$complianceDoc = Get-Content (Join-Path $root "docs\COMPLIANCE_CHECKLIST.md") -Raw
Assert-True ($complianceDoc -match "Disney/ESPN Terms of Use") "Compliance doc should track Disney/ESPN terms."
Assert-True ($complianceDoc -match "The Odds API Terms") "Compliance doc should track The Odds API terms."
Assert-True ($complianceDoc -match "Kalshi API Documentation") "Compliance doc should track Kalshi API terms."
Assert-True ($complianceDoc -match "CFTC Prediction Markets") "Compliance doc should track event-contract guidance."

$sportsData = Get-Content (Join-Path $root "src\SportsData.cpp") -Raw
Assert-True ($sportsData -match "PruneTsvFile") "Local TSV retention guard is missing."
Assert-True ($sportsData -match "BuildMarketDiagnostics") "Odds API mismatch diagnostics builder is missing."
Assert-True ($sportsData -match "BuildPerSportOddsStatus") "Per-sport odds status builder is missing."
Assert-True ($sportsData -match "FindMatchingOddsEvent") "Odds event matcher is missing."
Assert-True ($sportsData -match "AddKnownTeamAliases") "Team alias table for odds matching is missing."
Assert-True ($sportsData -match "CompactName") "Compact team-name matching is missing."
Assert-True ($sportsData -match "confidence_band") "Confidence band plumbing is missing."
Assert-True ($sportsData -match "OptionalFeedSchemaRows") "Optional feed schema contracts are missing."
Assert-True ($sportsData -match "ValidateOptionalFeedBody") "Optional feed parser validation is missing."
Assert-True ($sportsData -match "ApplyOptionalFeedSignals") "Optional feed confidence signal mapping is missing."
Assert-True ($sportsData -match "malformed_json") "Optional feed malformed JSON validation is missing."
Assert-True ($sportsData -match "empty_array") "Optional feed empty-array validation is missing."
Assert-True ($sportsData -match "missing_required_field") "Optional feed missing-field validation is missing."
Assert-True ($sportsData -match "wrong_type") "Optional feed wrong-type validation is missing."
Assert-True ($sportsData -match "stale_timestamp") "Optional feed stale timestamp validation is missing."

$guardTests = Get-Content (Join-Path $root "tools\run_guard_tests.ps1") -Raw
Assert-True ($guardTests -match "ScenarioCanStage") "Guard tests should cover scenario staging."
Assert-True ($guardTests -match "SubmitSlipPreview") "Guard tests should cover slip submission."
Assert-True ($guardTests -match "blocked_paper_only_mode") "Guard tests should cover paper-only mode."
Assert-True ($guardTests -match "kalshi-credentials.dat") "Guard tests should cover local credential files."

$adapterTests = Get-Content (Join-Path $root "tools\run_adapter_contract_tests.ps1") -Raw
Assert-True ($adapterTests -match "aegis\.injuries\.v1") "Adapter tests should cover injury contract."
Assert-True ($adapterTests -match "stale_timestamp") "Adapter tests should cover stale timestamps."
Assert-True ($adapterTests -match "Schema valid") "Adapter tests should cover valid schemas."
Assert-True ($adapterTests -match "Schema invalid") "Adapter tests should cover invalid schemas."

$oddsTests = Get-Content (Join-Path $root "tools\run_odds_api_tests.ps1") -Raw
Assert-True ($oddsTests -match "401") "Odds tests should cover 401."
Assert-True ($oddsTests -match "403") "Odds tests should cover 403."
Assert-True ($oddsTests -match "429") "Odds tests should cover 429."
Assert-True ($oddsTests -match "ParseJson") "Odds tests should cover malformed bodies."

$screenshotTests = Get-Content (Join-Path $root "tools\run_ui_screenshot_smoke.ps1") -Raw
Assert-True ($screenshotTests -match "dashboard") "Screenshot tests should cover Dashboard."
Assert-True ($screenshotTests -match "health") "Screenshot tests should cover Health."
Assert-True ($screenshotTests -match "settings") "Screenshot tests should cover Settings."
Assert-True ($screenshotTests -match "reports") "Screenshot tests should cover Reports."

if ($errors.Count -gt 0) {
    $errors | ForEach-Object { Write-Error $_ }
    exit 1
}

Write-Host "Smoke tests passed."
