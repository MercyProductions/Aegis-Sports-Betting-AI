# Lineforge Operational Infrastructure

Lineforge is moving from a page-driven prototype toward an operational intelligence platform. The first Phase 2 infrastructure layer is intentionally conservative: it records normalized timelines today through JSONL fallback storage, while keeping the schema ready for SQLite and later Postgres.

## Current Warehouse Layers

- `warehouse-runs`: ingestion cycle metadata, source hash, row counts, operational metrics.
- `warehouse-event-timeline`: normalized game state snapshots for historical reconstruction.
- `warehouse-market-timeline`: normalized odds rows for market replay, stale-line review, and provider comparison.
- `warehouse-prediction-timeline`: prediction snapshots preserving what Lineforge knew at the time.
- `warehouse-provider-health`: provider health and data-access status history.
- `warehouse-volatility`: volatility, positive-EV, middle, stale-odds, and provider-health summaries.
- `warehouse-line-movement`: compact odds rows and line movement metrics for later sequencing analysis.
- `warehouse-worker-runs`: background collector run summaries.

## Calibration Posture

Calibration is evidence-driven. Lineforge does not claim accuracy from open games or duplicated live snapshots.

The current validation layer tracks:

- closed prediction samples
- Brier score
- hit rate
- average confidence
- calibration error by confidence bucket
- drift status
- data-quality penalty share

Strong calibration claims should remain gated until at least 100 closed mapped samples exist.

## Worker

Run a single ingestion cycle:

```powershell
php Website/tools/lineforge-worker.php --cycles=1 --tracked-games=50 --models=8 --refresh-seconds=60
```

Run from a scheduler by setting `--cycles=1` and letting the scheduler repeat. Long-running loops are supported with `--cycles` and `--sleep`, but an external scheduler is easier to monitor and restart.

The worker uses a lock file at `Website/storage/lineforge-worker.lock` so overlapping collectors do not double-write snapshots.

## Database Roadmap

1. Enable PHP SQLite support for the current standalone runtime.
2. Let the existing warehouse helper write SQLite tables instead of JSONL fallback.
3. Add retention compaction and replay query endpoints.
4. Move the same schema to Postgres when team workspaces, permissions, and larger historical analysis require it.

## Product Boundary

This layer improves intelligence collection, replay, calibration, and auditability. It does not add unsupported sportsbook automation or live-money execution. Execution remains paper-first, manual-first, and provider-compliant.
