param(
    [string]$Configuration = "Release",
    [string]$Platform = "x64",
    [switch]$RequirePackage
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$release = Join-Path $root "$Platform\$Configuration"
$dist = Join-Path (Join-Path $root "dist") "AegisSportsBettingAI-$Configuration"
$zip = Join-Path (Join-Path $root "dist") "AegisSportsBettingAI-$Configuration.zip"
$errors = New-Object System.Collections.Generic.List[string]

function Assert-Guard($condition, $message) {
    if (-not $condition) {
        $script:errors.Add($message)
    }
}

function Get-FunctionBody([string]$text, [string]$signature) {
    $start = $text.IndexOf($signature)
    if ($start -lt 0) {
        return ""
    }
    $brace = $text.IndexOf("{", $start)
    if ($brace -lt 0) {
        return ""
    }
    $depth = 0
    for ($i = $brace; $i -lt $text.Length; $i++) {
        if ($text[$i] -eq "{") {
            $depth++
        }
        elseif ($text[$i] -eq "}") {
            $depth--
            if ($depth -eq 0) {
                return $text.Substring($start, $i - $start + 1)
            }
        }
    }
    return ""
}

function Assert-Match($text, $pattern, $message) {
    Assert-Guard ($text -match $pattern) $message
}

$mainPath = Join-Path $root "src\Main.cpp"
$configPath = Join-Path $root "AegisSportsBettingAI.config.ini"
$gitignorePath = Join-Path $root ".gitignore"

Assert-Guard (Test-Path $mainPath) "Main.cpp is missing."
Assert-Guard (Test-Path $configPath) "Root config is missing."
Assert-Guard (Test-Path $gitignorePath) ".gitignore is missing."

$main = Get-Content $mainPath -Raw
$config = Get-Content $configPath -Raw
$gitignore = Get-Content $gitignorePath -Raw

$scenarioCanStage = Get-FunctionBody $main "bool ScenarioCanStage"
$scenarioGuardrail = Get-FunctionBody $main "std::string ScenarioGuardrailLabel"
$actionLabel = Get-FunctionBody $main "std::string ActionLabel"
$submitSlip = Get-FunctionBody $main "void SubmitSlipPreview"
$openProvider = Get-FunctionBody $main "void OpenScenarioProvider"

Assert-Guard ($scenarioCanStage.Length -gt 0) "ScenarioCanStage is missing."
Assert-Guard ($scenarioGuardrail.Length -gt 0) "ScenarioGuardrailLabel is missing."
Assert-Guard ($actionLabel.Length -gt 0) "ActionLabel is missing."
Assert-Guard ($submitSlip.Length -gt 0) "SubmitSlipPreview is missing."
Assert-Guard ($openProvider.Length -gt 0) "OpenScenarioProvider is missing."

Assert-Match $scenarioCanStage 'status_key\s*==\s*"final"' "Scenario staging must block final games."
Assert-Match $scenarioCanStage 'scenario_stake_\s*>\s*config_\.max_ticket_amount' "Scenario staging must block stakes above ticket limit."
Assert-Match $scenarioCanStage 'TodayPreviewExposure\(\)\s*\+\s*scenario_stake_\s*>\s*config_\.daily_exposure_limit' "Scenario staging must block daily exposure overflow."
Assert-Match $scenarioCanStage 'confidence_value\s*<\s*config_\.min_ticket_confidence' "Scenario staging must block confidence below floor."

Assert-Match $scenarioGuardrail 'final games are audit-only' "Guardrail label must explain final-game block."
Assert-Match $scenarioGuardrail 'stake is above the local ticket limit' "Guardrail label must explain ticket-limit block."
Assert-Match $scenarioGuardrail 'daily exposure limit would be exceeded' "Guardrail label must explain exposure-limit block."
Assert-Match $scenarioGuardrail 'confidence is below the configured floor' "Guardrail label must explain confidence-floor block."
Assert-Match $scenarioGuardrail 'paper slip preview' "Guardrail label must preserve paper-only language."
Assert-Match $scenarioGuardrail 'manual provider handoff' "Guardrail label must describe live mode as manual handoff only."

Assert-Match $submitSlip 'blocked_final_game' "Slip submission must audit final-game blocks."
Assert-Match $submitSlip 'blocked_ticket_limit' "Slip submission must audit ticket-limit blocks."
Assert-Match $submitSlip 'blocked_daily_exposure_limit' "Slip submission must audit daily exposure blocks."
Assert-Match $submitSlip 'blocked_confidence_floor' "Slip submission must audit confidence-floor blocks."
Assert-Match $submitSlip 'blocked_paper_only_mode' "Live handoff must be blocked by paper-only mode."
Assert-Match $submitSlip 'blocked_missing_disclosures' "Live handoff must require responsible-use and legal/location acknowledgements."
Assert-Match $submitSlip 'blocked_missing_confirmation' "Live handoff must require explicit live confirmation."
Assert-Match $submitSlip 'manual_provider_handoff' "Live mode must remain a manual provider handoff."
Assert-Match $submitSlip 'OpenExternalUrl' "Provider access should open an external provider rather than place an order."

Assert-Match $actionLabel 'Paper only: live provider handoff disabled' "Action label must surface paper-only lock."
Assert-Match $actionLabel 'complete safety acknowledgements' "Action label must surface missing disclosure lock."
Assert-Match $actionLabel 'confirm manual provider handoff' "Action label must surface manual confirmation lock."
Assert-Match $openProvider 'No order is placed by Aegis' "Provider opening must state that no order is placed."

Assert-Guard ($main -notmatch '(?i)unattended wager|placeOrder|submitOrder|createOrder') "Source appears to contain unattended betting/order execution language."
Assert-Guard ($main -notmatch '(?i)/orders|/portfolio/orders|/trade|/trades') "Source appears to contain order/trade API endpoints."

Assert-Match $config 'paper_only_mode=true' "Root config must default to paper-only mode."
Assert-Match $config 'require_live_confirmation=true' "Root config must require live confirmation by default."
Assert-Match $config 'responsible_use_accepted=false' "Root config must require responsible-use acknowledgement by default."
Assert-Match $config 'legal_location_confirmed=false' "Root config must require legal/location acknowledgement by default."
Assert-Match $config 'odds_api_key=\s*(\r?\n|$)' "Root config must not contain an Odds API key."
Assert-Match $config 'kalshi_private_key=\s*(\r?\n|$)' "Root config must not contain a Kalshi private key."

foreach ($name in @("remember.dat", "odds-api-key.dat", "kalshi-credentials.dat", "diagnostics.log", "provider-health.tsv", "market-snapshots.tsv", "prediction-audit.tsv", "slip-audit.tsv", "exposure-ledger.tsv")) {
    Assert-Match $gitignore ([regex]::Escape($name)) ".gitignore must exclude $name."
}

$releaseConfig = Join-Path $release "AegisSportsBettingAI.config.ini"
if (Test-Path $releaseConfig) {
    $releaseConfigText = Get-Content $releaseConfig -Raw
    Assert-Match $releaseConfigText 'odds_api_key=\s*(\r?\n|$)' "Release config must not contain an Odds API key."
    Assert-Match $releaseConfigText 'kalshi_private_key=\s*(\r?\n|$)' "Release config must not contain a Kalshi private key."
    Assert-Match $releaseConfigText 'paper_only_mode=true' "Release config must default to paper-only mode."
}

if ($RequirePackage -or (Test-Path $dist)) {
    Assert-Guard (Test-Path $dist) "Packaged dist folder is missing."
    if (Test-Path $dist) {
        $distConfigPath = Join-Path $dist "AegisSportsBettingAI.config.ini"
        Assert-Guard (Test-Path $distConfigPath) "Packaged config is missing."
        if (Test-Path $distConfigPath) {
            $distConfig = Get-Content $distConfigPath -Raw
            Assert-Match $distConfig 'odds_api_key=\s*(\r?\n|$)' "Packaged config must not contain an Odds API key."
            Assert-Match $distConfig 'kalshi_private_key=\s*(\r?\n|$)' "Packaged config must not contain a Kalshi private key."
            Assert-Match $distConfig 'paper_only_mode=true' "Packaged config must default to paper-only mode."
        }
        $badDist = Get-ChildItem $dist -Recurse -File | Where-Object {
            $_.Name -match '(?i)(remember|odds-api-key|kalshi-credentials|diagnostics|provider-health|market-snapshots|prediction-audit|notifications|scenario-journal|slip-audit|exposure-ledger|aegis-report|workspace-export)' -or
            $_.Extension -match '(?i)\.pdb$'
        }
        Assert-Guard (-not $badDist) "Packaged dist contains secrets, runtime data, reports, or debug symbols."
    }
}

if ($RequirePackage -or (Test-Path $zip)) {
    Assert-Guard (Test-Path $zip) "Release zip is missing."
    if (Test-Path $zip) {
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        $archive = [System.IO.Compression.ZipFile]::OpenRead($zip)
        try {
            $entries = $archive.Entries | ForEach-Object { $_.FullName }
            $badZip = $entries | Where-Object {
                $_ -match '(?i)(remember|odds-api-key|kalshi-credentials|diagnostics|provider-health|market-snapshots|prediction-audit|notifications|scenario-journal|slip-audit|exposure-ledger|aegis-report|workspace-export|\.pdb$)'
            }
            Assert-Guard (-not $badZip) "Release zip contains secrets, runtime data, reports, or debug symbols."
        }
        finally {
            $archive.Dispose()
        }
    }
}

if ($errors.Count -gt 0) {
    $errors | ForEach-Object { Write-Error $_ }
    exit 1
}

Write-Host "Guard tests passed."
