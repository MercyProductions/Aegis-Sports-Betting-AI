# Aegis Sports Betting AI

Native C++/Win32/DirectX11 Dear ImGui desktop client for Aegis Sports Betting AI.

Version: `0.6.0` / `Health Center` / build date `2026-04-27`.

## Current Data Flow

- Authentication uses the website auth bridge at `/api/auth/login.php`.
- Sports data is rebuilt inside the desktop app from direct provider hosts, currently ESPN public scoreboard feeds.
- Sportsbook prices use The Odds API when a key is saved and validated.
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
4. Configure optional injury, lineup, news, and player-prop feed URLs if available, then use **Validate Feeds** to confirm the endpoints are reachable.
5. Review responsible-use and legal/location reminders in Settings.
6. Keep `paper_only_mode=true` unless you intentionally want manual provider handoff previews.

Manual provider handoff stays locked until paper-only mode is disabled, responsible-use and legal/location acknowledgements are saved, and the live confirmation checkbox is checked.

## Reports

Reports can export:

- `aegis-workspace-export.tsv`
- `aegis-report.csv`
- `aegis-report.pdf`
- `aegis-provider-health.csv`

Local journals and diagnostics are written under:

```text
%LOCALAPPDATA%\Aegis\Sports Betting AI
```

Local journals are bounded for long-running sessions: market snapshots and prediction audits keep the most recent 5,000 rows, notifications keep 1,000 rows, and scenario, exposure, and slip audit files keep 2,000 rows.

The Health Center records source, adapter, refresh latency, and data-trust snapshots in `provider-health.tsv` for local troubleshooting.

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

## Smoke Tests

After a Release build:

```powershell
.\tools\run_smoke_tests.ps1
```

The smoke tests check release artifacts, config hygiene, key feature surfaces, and source hygiene markers.
