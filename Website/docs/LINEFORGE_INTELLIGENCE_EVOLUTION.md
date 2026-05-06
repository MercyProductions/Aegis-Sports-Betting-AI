# Lineforge Intelligence Evolution

Phase 3 moves Lineforge from operational platform infrastructure toward adaptive market intelligence. The layer is intentionally evidence-gated: it builds feature pipelines, model readiness checks, market structure classifications, signal objects, and observability without claiming model accuracy before closed historical samples exist.

## Current Implementation

- `Website/config/intelligence_evolution.php`: transparent learning layer that extracts feature rows from public/free data, predictions, market access, arbitrage, calibration, and warehouse history.
- `featurePipeline`: normalized event-level features for later model datasets.
- `modelReadiness`: training gate for logistic regression, probabilistic linear baselines, gradient boosting, and future XGBoost/LightGBM adapters.
- `marketStructure`: regime classification for public-context-only states, stale data risk, volatility, provider disagreement, and stable watch conditions.
- `signals`: multi-dimensional intelligence objects instead of single confidence numbers.
- `triggers`: operational event triggers for stale data, provider degradation, volatility, disagreement, and replay-memory readiness.
- `observability`: ingestion, worker, stale-data, refresh-cadence, and replay-memory health summary.

## Feature Pipeline

Current feature rows include:

- model confidence estimate
- data-quality score and cap
- market link coverage
- normalized odds row count
- available line count
- bookmaker count
- volatility score
- market disagreement flags
- rapid movement flags
- edge estimate
- live/final status
- minutes to start
- closed-label availability

These rows are written into the warehouse as `warehouse-feature-pipeline` snapshots when the public intelligence state is built.

## Model Readiness Gates

Lineforge currently keeps `transparent_heuristic_v1` as the active baseline. Statistical models remain gated until historical labels exist:

- `logistic_regression_v0`: minimum 100 closed samples.
- `gradient_boosting_v0`: minimum 500 closed samples.
- `xgboost_lightgbm_adapter`: minimum 1000 closed samples.

Validation must remain time-based and replayable:

- rolling training windows
- train/test split by event date
- replay evaluation using only what the system knew at the time
- Brier score and calibration curve tracking
- feature importance review
- model versioning and inference audit logs

## Signal Objects

Signals now expose dimensions rather than a single magic number:

- research confidence
- volatility
- provider agreement
- data quality
- liquidity
- calibration confidence
- movement stability
- timing sensitivity
- execution feasibility
- market pressure

This keeps the product honest. A high research-confidence value can still be blocked by weak calibration confidence, stale data, low liquidity, or poor execution feasibility.

## Market Understanding

The current market-structure layer is a first pass. It classifies current conditions and prepares the app for later models:

- market regime detection
- volatility clustering
- line movement sequencing
- provider latency profiles
- stale-line anomaly detection
- consensus drift tracking

Do not describe inferred movement as verified sharp money unless a licensed source directly verifies it. Use terms such as rapid movement, provider disagreement, line velocity, market pressure, or early sharp-book movement.

## Observability

Lineforge should monitor itself. The current layer checks:

- latest ingestion cycle
- worker posture
- stale odds share
- refresh cadence
- replay memory depth

Future work should add worker queues, provider latency histograms, ingestion traces, execution traces, and calibration drift alerts.

## Product Boundary

Phase 3 improves intelligence quality, model legitimacy, and operational trust. It does not add unsupported sportsbook execution, bypass provider rules, or present unvalidated predictions as reliable accuracy. The product philosophy remains: transparency over hype, calibration over certainty, governance over automation, and measurement over marketing.
