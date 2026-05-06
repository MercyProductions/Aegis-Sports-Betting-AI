# Lineforge Adaptive Intelligence Infrastructure

Phase 4 turns the Phase 3 feature and calibration layer into an adaptive intelligence network. The key design choice is separation: Lineforge should not behave like one monolithic prediction engine. Each intelligence layer scores independently, exposes uncertainty independently, and can fail independently.

## Current Implementation

- `Website/config/adaptive_intelligence.php`: builds the adaptive infrastructure state from Phase 3 evolution output, warehouse history, calibration, market inference, and arbitrage context.
- `adaptiveIntelligence.summary`: network status, composite readiness, layer count, high-uncertainty layers, degraded layers, self-evaluation status, resilience status, and explanation count.
- `adaptiveIntelligence.layers`: independent systems for probability, volatility, execution feasibility, liquidity, provider reliability, market pressure, timing sensitivity, calibration confidence, historical similarity, and anomaly detection.
- `adaptiveIntelligence.composition`: orchestration rules that coordinate layers without hiding uncertainty.
- `adaptiveIntelligence.selfEvaluation`: monitors for drift, stale-data impact, provider reliability decay, false-confidence risk, model degradation, signal-failure clustering, and anomaly triggers.
- `adaptiveIntelligence.resilience`: degraded-mode and fallback systems.
- `adaptiveIntelligence.explanations`: operator-readable reasoning for why the network is conservative or why a layer is gated.

Adaptive snapshots are written to `warehouse-adaptive-network`.

## Layer Rules

Each layer must include:

- score
- uncertainty
- status
- measurement
- failure mode
- dependencies
- calibration path

The layer score is not a prediction of winning. It is a readiness or quality signal for that layer. High uncertainty must be visible and should reduce composite readiness.

## Current Layers

- Probability estimation: evidence-gated until closed labels exist.
- Volatility estimation: driven by volatility rows, line movement, and market pressure.
- Execution feasibility: paper/manual-first and blocked by sparse liquidity or stale data.
- Liquidity intelligence: based on normalized odds and available line coverage, not guessed limits.
- Provider reliability: based on provider health history and adapter posture.
- Market pressure: separates movement and disagreement from probability.
- Timing sensitivity: watches live/near-start fragility.
- Calibration confidence: blocks model promotion until outcome history exists.
- Historical similarity: prepares pattern matching but remains advisory.
- Anomaly detection: watches stale data, provider health, volatility, and replay readiness.

## Orchestration

The network currently coordinates these rules:

- volatility down-weights confidence
- provider reliability gates execution feasibility
- calibration gates model promotion
- historical similarity remains advisory until validated

Future orchestration should add provider-specific reliability weighting, market-regime-specific model weighting, stale-line isolation, and execution simulation feedback.

## Self-Evaluation

Lineforge should continuously ask: where are we becoming unreliable?

The current monitors include:

- calibration drift
- model degradation
- provider reliability decay
- stale-data impact
- false-confidence detection
- signal-failure clustering
- anomaly detection

Most monitors are intentionally data-gated until enough closed and replayable history exists.

## Operator Memory

Operator memory is structured but initially empty:

- annotated research sessions
- tagged market events
- replay bookmarks
- execution journals
- decision retrospectives

These should become auditable knowledge artifacts. They should not silently become model features without review.

## Product Boundary

Phase 4 does not add unsupported sportsbook execution, hidden prediction claims, or automatic live-money behavior. Adaptive intelligence means coordinated uncertainty, self-evaluation, explainability, and resilience. It does not mean fake certainty.

The long-term moat is accumulated intelligence infrastructure: historical archives, calibration quality, provider intelligence, operator tooling, replay systems, and self-evaluation systems.
