# Aegis Sports Betting AI Master TODO

This is the living project backlog for the Aegis Sports Betting AI desktop application. Treat this file as the source-of-truth to-do list. Items are ordered by practical priority: trust and safety first, real data correctness second, premium product polish third.

Status legend:

- [ ] Not started
- [~] In progress / partially implemented
- [x] Done

## Current Project State

- [x] Native C++/Win32/DirectX11 desktop app exists.
- [x] Website auth bridge login works.
- [x] Remembered credentials work through local encrypted storage.
- [x] Native sports board refreshes from direct provider hosts.
- [x] ESPN scoreboard integration exists.
- [x] Odds API key storage exists through Windows DPAPI.
- [x] Odds API validation exists.
- [x] Kalshi credentials storage exists through Windows DPAPI.
- [x] Kalshi remains manual-provider handoff only.
- [x] Paper-only mode defaults on.
- [x] Manual provider handoff is blocked unless safety acknowledgements and confirmation are set.
- [x] Setup wizard exists.
- [x] Health Center exists.
- [x] Provider health telemetry exists.
- [x] Data adapter URL validation exists.
- [x] Player props workspace shell exists.
- [x] Scenario Lab exists.
- [x] Watchlist / paper slip workflow exists.
- [x] Exposure ledger exists.
- [x] Reports export TSV, CSV, PDF, and provider-health CSV.
- [x] Release packaging script exists.
- [x] Release audit script exists.
- [x] Smoke tests exist.
- [x] Local journals have bounded retention.
- [x] Version/build labeling exists in the app, README, package notes, and release audit.

## P0 - Absolutely Needed Before Wider Use

- [x] Add visible app version/build label in the top bar, Settings, Health Center, README, release notes, and release audit.
- [x] Add a single version constant used by the UI, release audit, and package notes.
- [ ] Validate a real Odds API key in the app.
- [ ] Confirm Odds API live lines actually match scoreboard games for NBA, NFL, MLB, NHL, soccer, tennis, and UFC.
- [ ] Add provider mismatch diagnostics when Odds API events cannot be matched to scoreboard events.
- [ ] Add per-sport Odds API status: configured, reachable, matched events, available lines, failed calls, quota, and last status.
- [ ] Add source timestamp to every game row, prediction row, market link, report export, and Health Center entry.
- [ ] Add stale/fallback badges anywhere stale, fallback, scoreboard-only, no-odds, or no-feed data is shown.
- [ ] Define exact schemas for injury, lineup, news, and props feed adapters.
- [ ] Build real parsers for injury feed data.
- [ ] Build real parsers for lineup feed data.
- [ ] Build real parsers for news feed data.
- [ ] Build real parsers for player props feed data.
- [ ] Add parser validation for malformed JSON, empty arrays, missing required fields, wrong types, and stale timestamps.
- [ ] Add strict behavior for bad external data: do not crash, do not fake values, mark the source as invalid.
- [ ] Add a first-run setup wizard state so new users are guided through auth, Odds API, feed setup, safety, and first refresh.
- [ ] Add a "Setup Complete" state only when minimum safe requirements are met.
- [ ] Add config migration/versioning so older config files are upgraded cleanly.
- [ ] Add tests for paper-only mode, manual handoff lock, missing acknowledgements, final-game block, confidence floor, ticket limit, and exposure limit.
- [ ] Add tests proving API secrets never appear in root config, release config, zip, reports, diagnostics, or package notes.
- [ ] Add tests for Odds API validation responses: success, 401/403, 429, network failure, malformed body.
- [ ] Add tests for provider URL validation: empty, invalid URL, HTTP error, network error, reachable.
- [ ] Add a compliance checklist covering wagering wording, eligibility, legal location, Kalshi/event contracts, data licensing, and risk language.
- [ ] Confirm provider terms of use for ESPN, The Odds API, Kalshi, and any future injury/lineup/news/props feeds.
- [ ] Keep all real-money actions manual only.
- [ ] Do not add unattended betting, auto-submit, background order placement, auto-pilot wagering, or automatic Kalshi order execution.

## P1 - Needs Improvement

- [ ] Improve team-name normalization for Odds API matching.
- [ ] Add team alias tables by league.
- [ ] Match odds events by start time and league, not just team names.
- [ ] Detect duplicate odds events and choose the closest match.
- [ ] Show unmatched games in Health Center with reason codes.
- [ ] Show unmatched odds events in Health Center with reason codes.
- [ ] Add "last successful source refresh" and "last failed source refresh" for every provider.
- [ ] Track refresh latency by provider, not just total refresh latency.
- [ ] Track Odds API quota headers over time.
- [ ] Show provider quality by sport in Health Center.
- [ ] Show source coverage by sport: events loaded, matched lines, missing lines, stale rows.
- [ ] Add "what changed since last refresh" summary for line movement, confidence, game status, source state, and alerts.
- [ ] Separate "model confidence" from "data trust" more clearly across the UI.
- [ ] Add confidence bands: audit-only, monitor, lean, strong lean, high uncertainty.
- [ ] Add per-pick explanation summary: what raised confidence, what lowered confidence, what is missing, and what must be manually checked.
- [ ] Add confidence calibration using historical final results by sport, league, and market.
- [ ] Add calibration chart: predicted confidence versus actual win rate.
- [ ] Add backtesting filters by date range, sport, league, market type, confidence band, and data coverage.
- [ ] Add CLV tracking for saved scenario reads when comparable current lines exist.
- [ ] Add export filters: sport, league, date range, provider, watchlist only, and market type.
- [ ] Improve PDF report formatting with clearer headings, timestamp, app version, source status, and summary tables.
- [ ] Add a safe diagnostic bundle export that excludes secrets.
- [ ] Add local data cleanup screen for provider health, market snapshots, audit rows, reports, notifications, scenario journal, exposure ledger, and slip audit.
- [ ] Add better warning banners for missing Odds API key, fallback scoreboard, stale data, no optional feeds, and expired auth.
- [ ] Add clearer empty states explaining exactly what is missing and what to configure.
- [ ] Add Settings validation before save for invalid URLs, invalid numeric ranges, and risky safety combinations.
- [ ] Add crash-safe file writes for important exports and config writes using temp file plus replace.
- [ ] Add startup integrity checks for config parse, AppData write permission, release package state, and missing config.
- [ ] Add better error messages when auth database/server is offline.
- [ ] Add "Open Website Auth" or "Start Auth Server" helper if the local auth service is missing.

## P2 - Premium Product Suggestions

- [ ] Redesign top navigation around core workflows: Dashboard, Health, Picks, Markets, Scenario, Reports, Settings.
- [ ] Reduce sidebar density by grouping advanced tools.
- [ ] Add a real first-run welcome/setup flow instead of dropping users directly into the board.
- [ ] Add local profiles: Research Only, Conservative, Balanced, Aggressive Preview, Custom.
- [ ] Add favorite teams/leagues setup during onboarding.
- [ ] Add sport-specific dashboards for NBA, NFL, MLB, NHL, soccer, tennis, UFC, esports, and college sports.
- [ ] Add player props tables once real prop schema support exists.
- [ ] Add prop-specific checks: injury status, lineup status, expected minutes/usage, market liquidity, source timestamp.
- [ ] Add alerts by team, league, market type, provider, confidence threshold, line movement size, and source health.
- [ ] Add alert severity: info, watch, warning, critical.
- [ ] Add "why not actionable" reason to every blocked, monitor-only, or low-trust pick.
- [ ] Add side-by-side provider comparison for the same market across books.
- [ ] Add market movement timeline with opening, previous, current, best available, and timestamp.
- [ ] Add richer provider cards with logo/initial, live status, matched markets, latency, and quota.
- [ ] Add bankroll analytics only as optional reporting, never as a betting prompt.
- [ ] Add paper performance reports for scenario reads and paper tickets.
- [ ] Add import/export for settings profiles, excluding secrets.
- [ ] Add safer API-key setup screen explaining what each key unlocks.
- [ ] Add links to provider signup/docs from Settings.
- [ ] Add automated screenshot smoke tests for Dashboard, Health Center, Settings, Reports, Watchlist, and Scenario Lab.
- [ ] Add high-contrast/readability mode.
- [ ] Add keyboard navigation and better focus states.
- [ ] Add better responsive behavior for smaller screens.
- [ ] Add installer after release zip workflow is stable.
- [ ] Add code signing before wider distribution.
- [ ] Add auto-update only after package signing and release audit are stable.

## P3 - Longer-Term Architecture Ideas

- [ ] Move provider parsing out of Main.cpp/SportsData.cpp into dedicated adapter modules.
- [ ] Create interfaces for scoreboard providers, odds providers, exchange providers, injury providers, lineup providers, news providers, and props providers.
- [ ] Add fixture-based tests for each provider adapter.
- [ ] Consider SQLite for provider health, market snapshots, prediction audits, scenario reads, and exposure ledgers once TSVs become limiting.
- [ ] Add a local data migration path from TSV to SQLite if needed.
- [ ] Add model versioning so model results can be compared across releases.
- [ ] Add model evaluation reports by version.
- [ ] Add a real ML model only after the data pipeline, audits, and calibration baseline are trustworthy.
- [ ] Add scheduled morning/evening reports.
- [ ] Add multi-user support only if this becomes a shared/team tool.
- [ ] Add admin-only source configuration if distributed beyond one machine.
- [ ] Add central backend only if multiple users need shared provider keys, centralized reports, or shared model results.

## Release Gate Checklist

- [ ] Release build passes with 0 errors.
- [ ] Smoke tests pass.
- [ ] Release audit passes.
- [ ] App launches from x64/Release.
- [ ] Auth accepted or clear auth-offline message shown.
- [ ] Native sports sync completes.
- [ ] Health Center writes provider-health.tsv.
- [ ] Root config contains no secrets.
- [ ] Release config contains no secrets.
- [ ] Zip contains no PDB/debug files.
- [ ] Zip contains no AppData runtime files.
- [ ] README and release notes match current behavior.

## Active Next Work Order

1. Add Odds API provider mismatch diagnostics and unmatched-game reporting.
2. Add first-run setup wizard state and setup-complete detection.
3. Add unit-style risk guard tests and config secrecy tests.
4. Add source timestamps and stale/fallback badges throughout the UI.
5. Add real optional-feed schema contracts.
6. Add real injury, lineup, news, and props feed parsers once provider samples are available.
7. Add screenshot smoke tests for main views.
8. Improve premium UI density, grouping, and empty states.
9. Add installer/code-signing path after the release zip is stable.
