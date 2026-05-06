# Lineforge Product Goal

## Product Vision

Lineforge is disciplined sports intelligence infrastructure for scanning live boards, comparing research confidence against market context, and keeping users honest about data quality before they act.

The product should feel like a trading terminal for sports research:

- Fast live board with clear live, upcoming, final, cached, and fallback states.
- Research signal explanations that show what raised the calibration estimate, what lowered it, and what is missing.
- Provider links and market snapshots for manual verification only.
- Paper slip, replay, and reporting workflows for research, review, calibration, and audit.
- Responsible-use guardrails that prevent auto-wagering or unattended execution.

## Core Workflows

- Monitor the live and upcoming board across major sports.
- Filter by sport, league, team, status, and research confidence.
- Inspect a signal breakdown before trusting it.
- Compare model confidence estimate, fair odds, book context, edge, and missing data.
- Save paper reads for later performance and CLV review.
- Configure optional provider keys and data feeds without exposing secrets.
- Replay what the platform knew at prediction time before making calibration claims.
- Review execution governance, risk limits, stale-data checks, and provider health before any live-money workflow.

## Data Priorities

- Scoreboards and event status must be truthful.
- Odds and provider matches must identify stale, missing, or unmatched data.
- Injury, lineup, news, and props inputs must use explicit schemas.
- Missing data should lower research confidence rather than being guessed.
- Historical calibration, closed-sample tracking, and replay should exist before claims about model performance become stronger.
- Public/free data mode should remain useful when premium APIs are unavailable.

## Product Boundaries

Lineforge may surface sportsbook and exchange links for manual research, and may support official provider execution only where approved APIs allow it. The product must not scrape, bypass provider controls, automate unsupported sportsbooks, or run unattended betting. Any real-money decision stays behind explicit user authorization, risk limits, stale-data checks, provider health checks, location/legal eligibility verification, and audit logs.

## Product Philosophy

Lineforge is:

- Operator-focused.
- Audit-aware.
- Provider-transparent.
- Calibration-driven.
- Execution-aware.
- Paper-first.
- Institutional.

Lineforge is not:

- Guaranteed-profit AI.
- A blind prediction engine.
- Casino software.
- A hype-driven betting app.
- A fake certainty platform.

## Monetization Shape

- Free: limited tracked games, slower refresh, basic analytics.
- Pro: broader live board, faster refresh, deeper model explanations, paper slips, exports.
- Elite: larger scan universe, richer provider health, advanced calibration and alerts.

Long-term value should come from trust, auditability, and better research habits, not from hype or hidden automation.
