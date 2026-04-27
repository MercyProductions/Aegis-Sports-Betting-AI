param(
    [string]$Configuration = "Release",
    [string]$Platform = "x64"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$errors = New-Object System.Collections.Generic.List[string]

function Assert-Contract($condition, $message) {
    if (-not $condition) {
        $script:errors.Add($message)
    }
}

function Assert-Match($text, $pattern, $message) {
    Assert-Contract ($text -match $pattern) $message
}

$mainPath = Join-Path $root "src\Main.cpp"
$sportsPath = Join-Path $root "src\SportsData.cpp"
$headerPath = Join-Path $root "src\SportsData.h"
$readmePath = Join-Path $root "README.md"
$fixturesPath = Join-Path $root "fixtures\optional-feeds"

Assert-Contract (Test-Path $mainPath) "Main.cpp is missing."
Assert-Contract (Test-Path $sportsPath) "SportsData.cpp is missing."
Assert-Contract (Test-Path $headerPath) "SportsData.h is missing."
Assert-Contract (Test-Path $readmePath) "README.md is missing."
Assert-Contract (Test-Path $fixturesPath) "Optional feed fixtures folder is missing."

$main = Get-Content $mainPath -Raw
$sports = Get-Content $sportsPath -Raw
$header = Get-Content $headerPath -Raw
$readme = Get-Content $readmePath -Raw

foreach ($contract in @("aegis.injuries.v1", "aegis.lineups.v1", "aegis.news.v1", "aegis.props.v1")) {
    Assert-Match $sports ([regex]::Escape($contract)) "Missing optional feed contract $contract."
    Assert-Match $readme ([regex]::Escape($contract)) "README should document optional feed contract $contract."
}

foreach ($field in @("schemaVersion", "generatedAt", "provider", "items", "updatedAt", "publishedAt")) {
    Assert-Match $sports ([regex]::Escape($field)) "Optional feed validation should mention $field."
}

foreach ($marker in @("malformed_json", "empty_body", "empty_array", "missing_required_field", "wrong_type", "wrong_contract", "stale_timestamp", "future_timestamp")) {
    Assert-Match $sports $marker "Optional feed validation marker $marker is missing."
}

Assert-Match $header "OptionalFeedValidationResult" "OptionalFeedValidationResult should be declared."
Assert-Match $header "ValidateOptionalFeedBody" "ValidateOptionalFeedBody should be declared."
Assert-Match $header "OptionalFeedSchemaRows" "OptionalFeedSchemaRows should be declared."
Assert-Match $header "ApplyOptionalFeedSignals" "ApplyOptionalFeedSignals should be declared."
Assert-Match $sports "ApplyOptionalFeedSignals" "Validated optional feeds should be mapped into model signals."
Assert-Match $main "ProbeAdapterUrl\(const std::string& key" "Adapter probe should validate by feed key."
Assert-Match $main "ValidateOptionalFeedBody\(key, response\.body\)" "Reachable adapters should run schema validation."
Assert-Match $main "CollectConfiguredOptionalFeeds" "Refresh should collect configured optional feeds."
Assert-Match $main "ApplyOptionalFeedSignals" "Refresh should apply validated optional feed signals."
Assert-Match $main "probe\.reachable" "Adapter probe should separate reachability from schema validity."
Assert-Match $main "schema-valid" "Adapter validation status should report schema-valid counts."
Assert-Match $main "Adapter Schema Contracts" "Schema contracts should be visible in the UI."
Assert-Match $main "adapter_schema" "Schema contracts should be included in exports/health snapshots."

Assert-Match $main "Not configured" "Adapter validation should cover empty URLs."
Assert-Match $main "Invalid URL" "Adapter validation should cover invalid URL values."
Assert-Match $main "Network error" "Adapter validation should cover network failures."
Assert-Match $main 'HTTP "\s*\+\s*std::to_string\(response\.status_code\)' "Adapter validation should cover HTTP errors."
Assert-Match $main "Schema invalid" "Adapter validation should surface reachable invalid-schema responses."
Assert-Match $main "Schema valid" "Adapter validation should surface reachable valid-schema responses."

foreach ($fixture in @("injuries.sample.json", "lineups.sample.json", "news.sample.json", "props.sample.json")) {
    $fixturePath = Join-Path $fixturesPath $fixture
    Assert-Contract (Test-Path $fixturePath) "Missing optional feed fixture $fixture."
    if (Test-Path $fixturePath) {
        $fixtureText = Get-Content $fixturePath -Raw
        Assert-Match $fixtureText '"schemaVersion"' "$fixture should include schemaVersion."
        Assert-Match $fixtureText '"generatedAt"' "$fixture should include generatedAt."
        Assert-Match $fixtureText '"items"' "$fixture should include items."
    }
}

if ($errors.Count -gt 0) {
    $errors | ForEach-Object { Write-Error $_ }
    exit 1
}

Write-Host "Adapter contract tests passed."
