# Aegis Sports Betting AI Compliance Checklist

This checklist is a product safety tracker, not legal advice. Review it before public distribution, before enabling any manual provider handoff, and whenever a provider changes terms.

## Release Blocking Items

- [ ] Confirm the app is allowed in every distribution location and target user location.
- [ ] Confirm all wagering, trading, and event-contract language is accurate, conservative, and not promotional.
- [ ] Confirm the app stays manual-only: no unattended betting, no auto-submit, no background order placement, and no automatic Kalshi order execution.
- [ ] Confirm responsible-use, age/eligibility, jurisdiction, uncertainty, and loss-risk language is visible before any real-money provider handoff.
- [ ] Confirm data-provider terms allow the current use case, storage, display, screenshots, exports, and distribution.
- [ ] Confirm provider logos, names, and market data are used only where permitted.
- [ ] Confirm reports and exports do not expose API keys, cookies, DPAPI blobs, credentials, or local identity data.
- [ ] Confirm support docs tell users to verify live lines and market terms directly with the provider before acting.

## Provider Terms Tracking

| Provider | Current project use | Required review |
| --- | --- | --- |
| ESPN / Disney | Public scoreboard source for events, scores, status, and timing | Review Disney/ESPN Terms of Use and replace the source if the intended distribution or automated access is not permitted. |
| The Odds API | User-supplied sportsbook odds key, matching diagnostics, line movement, and market comparison | Review account plan, API key restrictions, redistribution rules, responsible-gambling language, and quota behavior. |
| Kalshi | Manual event-contract research, credential storage, and provider handoff only | Review Developer Agreement, market data terms, event contract rules, trading permissions, and API key handling. |
| Optional feeds | Injury, lineup, news, and prop JSON adapters | Review each vendor license for display, retention, exports, derived signals, screenshots, and redistribution. |
| CFTC / Event contracts | Background compliance context for prediction/event-contract workflows | Review current CFTC customer education and advisories before adding any execution-oriented workflow. |

## Official Review Links

- Disney/ESPN Terms of Use: https://disneytermsofuse.com/english/
- The Odds API Terms and Conditions: https://the-odds-api.com/terms-and-conditions.html
- Kalshi API Documentation and Developer Agreement entry point: https://docs.kalshi.com/welcome
- CFTC Prediction Markets customer education: https://www.cftc.gov/LearnandProtect/PredictionMarkets

## App Rules That Must Stay True

- Aegis can save paper reads and open a provider page for manual review.
- Aegis must not place a wager or exchange order for the user.
- Aegis must not run unattended trading or betting.
- Aegis must not describe probabilities, confidence, edges, or expected value as guarantees.
- Aegis must clearly label stale, fallback, no-odds, no-match, unsupported, and needs-key data.
- Aegis must keep API keys and private keys out of config, logs, reports, diagnostics, release notes, and zip files.

## Pre-Upload Verification

Run these before uploading a GitHub release:

```powershell
.\tools\release_audit.ps1
.\tools\run_ui_screenshot_smoke.ps1
```

Then review:

- `dist\AegisSportsBettingAI-Release\RELEASE_AUDIT.txt`
- `dist\AegisSportsBettingAI-Release\INSTALLER_READINESS.txt`
- `dist\AegisSportsBettingAI-Release.zip`

Do not upload local AppData, screenshots, credentials, debug symbols, raw provider responses, or personal account data.
