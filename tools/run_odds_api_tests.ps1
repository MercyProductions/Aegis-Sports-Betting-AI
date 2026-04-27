param(
    [string]$Configuration = "Release",
    [string]$Platform = "x64"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$errors = New-Object System.Collections.Generic.List[string]

function Assert-Odds($condition, $message) {
    if (-not $condition) {
        $script:errors.Add($message)
    }
}

function Assert-Match($text, $pattern, $message) {
    Assert-Odds ($text -match $pattern) $message
}

$sportsPath = Join-Path $root "src\SportsData.cpp"
$mainPath = Join-Path $root "src\Main.cpp"
$headerPath = Join-Path $root "src\SportsData.h"
$configPath = Join-Path $root "AegisSportsBettingAI.config.ini"

Assert-Odds (Test-Path $sportsPath) "SportsData.cpp is missing."
Assert-Odds (Test-Path $mainPath) "Main.cpp is missing."
Assert-Odds (Test-Path $headerPath) "SportsData.h is missing."
Assert-Odds (Test-Path $configPath) "Root config is missing."

$sports = Get-Content $sportsPath -Raw
$main = Get-Content $mainPath -Raw
$header = Get-Content $headerPath -Raw
$config = Get-Content $configPath -Raw

Assert-Match $header "OddsValidationResult" "OddsValidationResult should be declared."
Assert-Match $header "ValidateOddsApiKey" "ValidateOddsApiKey should be declared."
Assert-Match $sports "ValidateOddsApiKey" "Odds API validation function is missing."
Assert-Match $sports "https://api\.the-odds-api\.com/v4/sports/" "Odds API validation should call the sports list endpoint."
Assert-Match $sports "response\.error" "Odds API validation should handle network failure."
Assert-Match $sports "status_code\s*==\s*401" "Odds API validation should handle HTTP 401."
Assert-Match $sports "status_code\s*==\s*403" "Odds API validation should handle HTTP 403."
Assert-Match $sports "status_code\s*==\s*429" "Odds API validation should handle HTTP 429."
Assert-Match $sports "status_code\s*<\s*200\s*\|\|\s*response\.status_code\s*>=\s*300" "Odds API validation should handle non-success HTTP responses."
Assert-Match $sports "ParseJson\(response\.body\)" "Odds API validation should parse response body."
Assert-Match $sports "!parsed\.ok\s*\|\|\s*!parsed\.value\.IsArray\(\)" "Odds API validation should reject malformed/non-array bodies."
Assert-Match $sports "result\.ok\s*=\s*true" "Odds API validation should set success state."
Assert-Match $sports "requests_remaining" "Odds API validation should expose remaining quota."
Assert-Match $sports "requests_used" "Odds API validation should expose used quota."
Assert-Match $sports "requests_last" "Odds API validation should expose last-call quota."
Assert-Match $main "ValidateOddsKey" "Settings should expose Odds API validation."
Assert-Match $main "odds_validation_" "UI should retain Odds API validation state."
Assert-Match $main "Odds API Health" "UI should render Odds API health."
Assert-Match $config 'odds_api_key=\s*(\r?\n|$)' "Root config must not contain an Odds API key."

if ($errors.Count -gt 0) {
    $errors | ForEach-Object { Write-Error $_ }
    exit 1
}

Write-Host "Odds API tests passed."
