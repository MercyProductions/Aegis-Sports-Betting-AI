# Aegis Sports Betting AI

Native C++/Win32/DirectX11 Dear ImGui desktop client for Aegis Sports Betting AI.

Version: `0.6.0` / `Health Center` / build date `2026-04-27`.

## Current Data Flow

- Authentication uses the website auth bridge at `/api/auth/login.php`.
- Sports data is rebuilt inside the desktop app from direct provider hosts, currently ESPN public scoreboard feeds.
- Sportsbook prices use The Odds API when a key is saved and validated.
- Odds matching diagnostics report unmatched scoreboard games, unmatched odds events, per-game match status, quota headers, source timestamps, and data-trust labels.
- The Health Center includes per-sport Odds API status cards for configured/reachable/matched/available/failed/quota/last-status checks.
- The Health Center includes a source refresh ledger with per-source success/failure time and latency, plus a before/after refresh change summary.
- The setup wizard exposes a Setup Complete state and opens first-use sessions there until minimum safe requirements are met.
- Source-state badges mark matched, stale, fallback, no-odds, no-match, unsupported, and needs-key rows across the board.
- Optional injury, lineup, news, and player-prop adapters must match named JSON contracts before their data is treated as valid.
- Schema-valid optional feed rows are mapped into confidence inputs; invalid or stale feed rows stay in diagnostics only.
- Auth-offline failures show recovery actions for checking the auth service and opening the configured auth/website URLs.
- Navigation is grouped around core workflows: Dashboard, Health, Picks, Markets, Scenario, Reports, and Settings.
- Kalshi is linked for manual event-contract research and provider handoff only.
- The app does not automatically place wagers or exchange orders.

## Build

Open `AegisSportsBettingAI.sln` in Visual Studio 2022, or run:

```powershell
& "C:\Program Files\Microsoft Visual Studio\2022\Community\MSBuild\Current\Bin\amd64\MSBuild.exe" .\AegisSportsBettingAI.sln /p:Configuration=Release /p:Platform=x64 /m
```

The executable is written to:

```text
x64\Release\AegisSportsBettingAI.exe
```

## Setup Checklist

1. Start the website auth server at `auth_base_url`.
2. Sign in once, or launch through the Aegis Launcher.
3. Save and validate an Odds API key for live sportsbook comparison.
4. Configure optional injury, lineup, news, and player-prop feed URLs if available, then use **Validate Feeds** to confirm the endpoints are reachable and schema-valid.
5. Review responsible-use and legal/location reminders in Settings.
6. Keep `paper_only_mode=true` unless you intentionally want manual provider handoff previews.

Manual provider handoff stays locked until paper-only mode is disabled, responsible-use and legal/location acknowledgements are saved, and the live confirmation checkbox is checked.

## Reports

Reports can export:

- `aegis-workspace-export.tsv`
- `aegis-report.csv`
- `aegis-report.pdf`
- `aegis-provider-health.csv`
- `diagnostic-bundle\summary.txt`
- `diagnostic-bundle\settings-redacted.ini`

Local journals and diagnostics are written under:

```text
%LOCALAPPDATA%\Aegis\Sports Betting AI
```

Local journals are bounded for long-running sessions: market snapshots and prediction audits keep the most recent 5,000 rows, notifications keep 1,000 rows, and scenario, exposure, and slip audit files keep 2,000 rows.

The Health Center records source, adapter, refresh latency, odds matching diagnostics, and data-trust snapshots in `provider-health.tsv` for local troubleshooting.

Reports can be filtered before export by sport, league/team text, date scope, provider, watchlist-only rows, and market type. Filtered exports include matching predictions, market history, source refresh rows, and refresh-change summaries.

Config files include `config_schema_version=2`. Legacy configs are normalized on load, plaintext provider secrets are moved into Windows DPAPI user storage, and the cleaned config is rewritten with a temp-file replace. User-facing report exports also write through temporary files before replacing the final artifact.

Startup integrity checks are surfaced in Setup, Health, and Settings. They verify config presence/schema, AppData write permission, release executable state, compliance documentation, and secret hygiene.

Settings validate optional adapter URLs, numeric ranges, exposure limits, and safety-mode combinations before saving.

The Health Center can export a safe diagnostic bundle with redacted settings, startup integrity, source health, and odds diagnostics.

Compliance and provider-term review is tracked in `docs\COMPLIANCE_CHECKLIST.md` and surfaced in Settings. The checklist is packaged into release builds for review before upload.

## Optional Feed Contracts

Each optional feed uses an Aegis JSON envelope with `schemaVersion`, `generatedAt`, optional `provider`, and `items`. The app rejects malformed JSON, empty item arrays, missing required fields, wrong field types, stale timestamps, and contract mismatches instead of fabricating model inputs.

- Injury: `aegis.injuries.v1`
- Lineup: `aegis.lineups.v1`
- News: `aegis.news.v1`
- Player props: `aegis.props.v1`

Sample fixtures live in:

```text
fixtures\optional-feeds
```

## Packaging

Build Release first, then run:

```powershell
.\tools\package_release.ps1
```

The package script creates a clean `dist` folder and excludes debug symbols, screenshots, local AppData, credentials, and generated reports.

For a full release check, run:

```powershell
.\tools\release_audit.ps1
```

The release audit builds, runs smoke tests, packages, checks for secrets/debug artifacts/runtime data, writes `RELEASE_AUDIT.txt`, and refreshes the zip.

It also writes `INSTALLER_READINESS.txt` into the release folder. That file confirms package hygiene and lists any remaining signing or installer-tooling steps before wider distribution.

To check the current package readiness without rebuilding:

```powershell
.\tools\run_installer_readiness.ps1
```

## Smoke Tests

After a Release build:

```powershell
.\tools\run_smoke_tests.ps1
```

The smoke tests check release artifacts, config hygiene, key feature surfaces, and source hygiene markers.

For safety-specific checks:

```powershell
.\tools\run_guard_tests.ps1
```

Guard tests cover paper-only/manual handoff blocks, ticket/exposure/confidence limits, and release-package secret hygiene.

For optional feed contract checks:

```powershell
.\tools\run_adapter_contract_tests.ps1
```

For Odds API validation checks:

```powershell
.\tools\run_odds_api_tests.ps1
```

For UI screenshot smoke checks after a Release build:

```powershell
.\tools\run_ui_screenshot_smoke.ps1
```
