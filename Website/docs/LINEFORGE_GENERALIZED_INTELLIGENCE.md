# Lineforge Generalized Intelligence Infrastructure

Phase 5 abstracts Lineforge from a sports-market intelligence product into a reusable decision-intelligence framework. Sports remains the active domain adapter. The core engine becomes domain-agnostic: event primitives, signal primitives, state models, workflow orchestration, memory, calibration, replay, governance, and explainability can be reused across future domains.

## Current Implementation

- `Website/config/generalized_intelligence.php`: builds the generalized decision-intelligence state from the adaptive network, calibration, warehouse, and market inference layers.
- `generalizedIntelligence.summary`: core readiness, active domains, domain adapter count, event/signal/workflow primitive counts, memory stores, workspace triggers, and governance controls.
- `generalizedIntelligence.domains`: sports markets as the active domain plus blueprints for prediction markets, financial markets, crypto markets, operational telemetry, and risk/forecasting.
- `generalizedIntelligence.primitives`: universal events, signals, states, and workflows.
- `generalizedIntelligence.orchestration`: dependency graph and cross-system pipelines.
- `generalizedIntelligence.memory`: event, workflow, provider, operator, signal, calibration, execution, and anomaly memory.
- `generalizedIntelligence.adaptiveWorkspaces`: context-aware workspace triggers.
- `generalizedIntelligence.selfOptimization`: ingestion, refresh, provider, confidence, anomaly, and alert optimization posture.
- `generalizedIntelligence.governance`: explainability, audit, confidence transparency, execution restrictions, risk governance, uncertainty visibility, and safety degradation.

Generalized snapshots are written to `warehouse-generalized-core`.

## Universal Model

The generalized core uses four primitive groups:

- Event: market event, game event, provider event, volatility event, anomaly event, execution event.
- Signal: confidence, pressure, disagreement, timing, liquidity, reliability, anomaly, execution feasibility.
- State: stable, volatile, degraded, uncertain, stale, active, suspended.
- Workflow: ingest, normalize, evaluate, calibrate, orchestrate, execute, audit, replay.

These primitives make sports one domain adapter rather than the entire architecture.

## Domain Adapters

Current posture:

- Sports markets: active domain.
- Prediction markets: adapter blueprint.
- Financial markets: planned domain.
- Crypto markets: planned domain.
- Operational telemetry: foundation-ready.
- Risk and forecasting: adapter blueprint.

Any future domain must bring official/licensed data access, domain-specific normalization, outcome labels, governance rules, and replay-safe calibration before model claims are allowed.

## Orchestration

The generalized dependency graph currently models:

- provider reliability affects execution feasibility
- volatility affects confidence visibility
- stale data affects signal freshness
- calibration confidence gates model promotion
- historical similarity supports explanation only
- governance gates execution

This keeps intelligence coordinated without hiding uncertainty or turning orchestration into unsupported automation.

## Memory

The core preserves:

- event memory
- workflow memory
- provider memory
- operator memory
- signal memory
- calibration memory
- execution memory
- anomaly memory

Historical context should become the long-term platform moat, but it must remain auditable and replayable.

## Governance Boundary

Phase 5 does not add unsupported execution, hidden automation, or broad AI claims. It generalizes the infrastructure while preserving the Lineforge doctrine:

- uncertainty before certainty
- calibration before model claims
- governance before execution
- memory before adaptation
- orchestration before automation
- domain adapters over one-off rewrites

Lineforge is evolving toward adaptive probabilistic decision infrastructure, not a black-box decision oracle.
