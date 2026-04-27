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
- [x] Per-game Odds API match status exists.
- [x] Unmatched scoreboard games and unmatched odds events are reported in Health Center diagnostics.
- [x] Prediction rows expose data trust, source timestamp, and confidence band.
- [x] Provider health, CSV, PDF, and workspace exports include odds matching diagnostics.
- [x] Per-sport Odds API status cards exist in the Health Center and exports.
- [x] Setup status detects incomplete readiness and routes first-use sessions into the setup wizard.
- [x] Dedicated guard tests cover paper-only/manual handoff blocks and package secret hygiene.
- [x] Source-state badges identify matched, stale, fallback, no-odds, no-match, unsupported, and needs-key rows.
- [x] Optional injury, lineup, news, and props adapters have named JSON schema contracts.
- [x] Optional adapter validation rejects malformed JSON, empty arrays, missing fields, wrong types, stale timestamps, and contract mismatches.
- [x] Adapter contract tests cover empty URLs, invalid URLs, HTTP errors, network errors, reachable invalid schemas, and reachable valid schemas.
- [x] Odds API validation tests cover success, 401/403, 429, network errors, malformed bodies, quota headers, and secret-free config.
- [x] UI screenshot smoke mode and script cover Dashboard, Health, Settings, Reports, Watchlist, and Scenario Lab.
- [x] Optional feed sample fixtures exist for injury, lineup, news, and player props contracts.
- [x] Schema-valid optional feed rows are mapped into model-source, metrics, factors, prediction input count, missing-data penalty, and confidence rows.
- [x] Auth-offline recovery panel exists with Check Auth, Open Auth URL, and Open Website actions.
- [~] Top navigation and sidebar are grouped around core workflows with denser research/operations sections.
- [x] Installer/signing readiness script exists and is included in release audit output.
- [x] Config schema versioning and legacy config migration exist.
- [x] Compliance/provider-terms checklist exists and is surfaced in Settings.
- [x] Premium empty states use clearer state chips and stronger visual hierarchy.
- [x] Startup integrity checks are surfaced in Setup, Health, and Settings.
- [x] Team alias expansion and compact-name matching improve Odds API event matching.
- [x] Settings validation blocks invalid adapter URLs and corrects unsafe/risky ranges before save.
- [x] Safe diagnostic bundle export exists and excludes provider secrets.
- [x] Report Center has export filters for sport, league/team text, date window, provider, watchlist-only scope, and market type.
- [x] Source refresh ledger tracks last successful and failed refresh status for scoreboard, Odds API, Kalshi, and optional feeds.
- [x] Release audit fails fast when build, smoke, adapter, odds, package, guard, or installer-readiness steps return a non-zero exit code.
- [x] Per-source refresh latency is tracked for the core sports board and configured optional feed adapters.
- [x] Health Center and exports include a before/after refresh change summary for event slate, status, source state, confidence, line movement, book lines, exchange links, and alerts.

## P0 - Absolutely Needed Before Wider Use

- [x] Add visible app version/build label in the top bar, Settings, Health Center, README, release notes, and release audit.
- [x] Add a single version constant used by the UI, release audit, and package notes.
- [ ] Validate a real Odds API key in the app.
- [ ] Confirm Odds API live lines actually match scoreboard games for NBA, NFL, MLB, NHL, soccer, tennis, and UFC.
- [x] Add provider mismatch diagnostics when Odds API events cannot be matched to scoreboard events.
- [x] Add per-sport Odds API status: configured, reachable, matched events, available lines, failed calls, quota, and last status.
- [x] Add source timestamp to every game row, prediction row, market link, report export, and Health Center entry.
- [x] Add stale/fallback badges anywhere stale, fallback, scoreboard-only, no-odds, or no-feed data is shown.
- [x] Define exact schemas for injury, lineup, news, and props feed adapters.
- [x] Build real parsers for injury feed data.
- [x] Build real parsers for lineup feed data.
- [x] Build real parsers for news feed data.
- [x] Build real parsers for player props feed data.
- [x] Add parser validation for malformed JSON, empty arrays, missing required fields, wrong types, and stale timestamps.
- [x] Add strict behavior for bad external data: do not crash, do not fake values, mark the source as invalid.
- [x] Add a first-run setup wizard state so new users are guided through auth, Odds API, feed setup, safety, and first refresh.
- [x] Add a "Setup Complete" state only when minimum safe requirements are met.
- [x] Add config migration/versioning so older config files are upgraded cleanly.
- [x] Add tests for paper-only mode, manual handoff lock, missing acknowledgements, final-game block, confidence floor, ticket limit, and exposure limit.
- [x] Add tests proving API secrets never appear in root config, release config, zip, reports, diagnostics, or package notes.
- [x] Add tests for Odds API validation responses: success, 401/403, 429, network failure, malformed body.
- [x] Add tests for provider URL validation: empty, invalid URL, HTTP error, network error, reachable.
- [x] Add a compliance checklist covering wagering wording, eligibility, legal location, Kalshi/event contracts, data licensing, and risk language.
- [~] Confirm provider terms of use for ESPN, The Odds API, Kalshi, and any future injury/lineup/news/props feeds.
- [ ] Keep all real-money actions manual only.
- [ ] Do not add unattended betting, auto-submit, background order placement, auto-pilot wagering, or automatic Kalshi order execution.

## P1 - Needs Improvement

- [x] Improve team-name normalization for Odds API matching.
- [x] Add team alias tables by league.
- [x] Match odds events by start time and league, not just team names.
- [x] Detect duplicate odds events and choose the closest match.
- [x] Show unmatched games in Health Center with reason codes.
- [x] Show unmatched odds events in Health Center with reason codes.
- [x] Add "last successful source refresh" and "last failed source refresh" for every provider.
- [x] Track refresh latency by provider, not just total refresh latency.
- [~] Track Odds API quota headers over time.
- [ ] Show provider quality by sport in Health Center.
- [~] Show source coverage by sport: events loaded, matched lines, missing lines, stale rows.
- [x] Add "what changed since last refresh" summary for line movement, confidence, game status, source state, and alerts.
- [x] Separate "model confidence" from "data trust" more clearly across the UI.
- [x] Add confidence bands: audit-only, monitor, lean, strong lean, high uncertainty.
- [ ] Add per-pick explanation summary: what raised confidence, what lowered confidence, what is missing, and what must be manually checked.
- [ ] Add confidence calibration using historical final results by sport, league, and market.
- [ ] Add calibration chart: predicted confidence versus actual win rate.
- [ ] Add backtesting filters by date range, sport, league, market type, confidence band, and data coverage.
- [ ] Add CLV tracking for saved scenario reads when comparable current lines exist.
- [x] Add export filters: sport, league, date range, provider, watchlist only, and market type.
- [ ] Improve PDF report formatting with clearer headings, timestamp, app version, source status, and summary tables.
- [x] Add a safe diagnostic bundle export that excludes secrets.
- [ ] Add local data cleanup screen for provider health, market snapshots, audit rows, reports, notifications, scenario journal, exposure ledger, and slip audit.
- [~] Add better warning banners for missing Odds API key, fallback scoreboard, stale data, no optional feeds, and expired auth.
- [x] Add clearer empty states explaining exactly what is missing and what to configure.
- [x] Add Settings validation before save for invalid URLs, invalid numeric ranges, and risky safety combinations.
- [x] Add crash-safe file writes for important exports and config writes using temp file plus replace.
- [x] Add startup integrity checks for config parse, AppData write permission, release package state, and missing config.
- [x] Add better error messages when auth database/server is offline.
- [x] Add "Open Website Auth" or "Start Auth Server" helper if the local auth service is missing.

## P2 - Premium Product Suggestions

- [x] Redesign top navigation around core workflows: Dashboard, Health, Picks, Markets, Scenario, Reports, Settings.
- [x] Reduce sidebar density by grouping advanced tools.
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
- [x] Add automated screenshot smoke tests for Dashboard, Health Center, Settings, Reports, Watchlist, and Scenario Lab.
- [ ] Add high-contrast/readability mode.
- [ ] Add keyboard navigation and better focus states.
- [ ] Add better responsive behavior for smaller screens.
- [~] Add installer after release zip workflow is stable.
- [~] Add code signing before wider distribution.
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

- [x] Release build passes with 0 errors.
- [x] Smoke tests pass.
- [x] Release audit passes.
- [x] App launches from x64/Release.
- [x] Auth accepted or clear auth-offline message shown.
- [ ] Native sports sync completes.
- [ ] Health Center writes provider-health.tsv.
- [x] Root config contains no secrets.
- [x] Release config contains no secrets.
- [x] Zip contains no PDB/debug files.
- [x] Zip contains no AppData runtime files.
- [x] README and release notes match current behavior.
- [x] Installer/signing readiness report is generated.

## Active Next Work Order

1. Validate real Odds API key/live line matching when a key is available.
2. Complete provider legal/terms review with counsel or provider approval before public distribution.
3. Create the actual installer after signing certificate/tooling is selected.
4. Add provider adapter interfaces if the feed code keeps growing.
5. Show provider quality by sport in Health Center.
6. Add per-pick explanation summary: what raised confidence, what lowered confidence, what is missing, and what must be manually checked.
7. Add local data cleanup screen for provider health, market snapshots, audit rows, reports, notifications, scenario journal, exposure ledger, and slip audit.
