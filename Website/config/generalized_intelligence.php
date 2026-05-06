<?php

require_once __DIR__ . '/adaptive_intelligence.php';

function lineforge_generalized_count(array $warehouse, string $key): int
{
    return lineforge_adaptive_count($warehouse, $key);
}

function lineforge_generalized_status_slug(string $value): string
{
    return preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($value))) ?: 'unknown';
}

function lineforge_generalized_readiness(float $base, float $uncertaintyPenalty, float $historyBonus = 0.0): float
{
    return round(lineforge_evolution_clamp($base + $historyBonus - $uncertaintyPenalty), 1);
}

function lineforge_generalized_domain_adapters(array $adaptive, array $warehouse): array
{
    $summary = (array) ($adaptive['summary'] ?? []);
    $composite = (float) ($summary['compositeReadiness'] ?? 0);
    $ingestionRuns = lineforge_generalized_count($warehouse, 'ingestionRuns');
    $predictions = lineforge_generalized_count($warehouse, 'predictions');
    $providerRows = lineforge_generalized_count($warehouse, 'providerHealthRows');
    $lineRows = lineforge_generalized_count($warehouse, 'lineMovementRows');
    $sportsReady = lineforge_generalized_readiness($composite + 18, (float) ($summary['highUncertaintyLayers'] ?? 0) * 2, min(16, $ingestionRuns / 8));

    return [
        [
            'id' => 'sports_markets',
            'name' => 'Sports markets',
            'status' => 'active_domain',
            'readiness' => $sportsReady,
            'detail' => 'Current Lineforge domain adapter using sports events, market pressure, provider health, execution governance, replay, and calibration.',
            'sharedPrimitives' => ['event', 'signal', 'state', 'workflow', 'memory', 'governance'],
            'evidence' => (string) $predictions . ' predictions / ' . (string) $lineRows . ' line snapshots',
        ],
        [
            'id' => 'prediction_markets',
            'name' => 'Prediction markets',
            'status' => 'adapter_blueprint',
            'readiness' => lineforge_generalized_readiness(28, 0, min(22, $providerRows / 30)),
            'detail' => 'Can reuse event contracts, exchange-style prices, provider health, order governance, and calibration once official provider data is connected.',
            'sharedPrimitives' => ['market event', 'price signal', 'execution state', 'audit workflow'],
            'evidence' => 'Kalshi-style official API architecture exists; generalized adapter still needs domain mapping.',
        ],
        [
            'id' => 'financial_markets',
            'name' => 'Financial markets',
            'status' => 'planned_domain',
            'readiness' => 18.0,
            'detail' => 'Could reuse volatility, liquidity, provider reliability, anomaly detection, and governance, but needs licensed market-data adapters.',
            'sharedPrimitives' => ['market event', 'volatility signal', 'liquidity state', 'risk workflow'],
            'evidence' => 'No financial feed adapter is active.',
        ],
        [
            'id' => 'crypto_markets',
            'name' => 'Crypto markets',
            'status' => 'planned_domain',
            'readiness' => 16.0,
            'detail' => 'Could reuse provider disagreement, stale-data isolation, liquidity intelligence, and execution simulation with exchange-approved APIs.',
            'sharedPrimitives' => ['provider event', 'pressure signal', 'stale state', 'execution workflow'],
            'evidence' => 'No exchange adapter is active.',
        ],
        [
            'id' => 'operational_telemetry',
            'name' => 'Operational telemetry',
            'status' => 'foundation_ready',
            'readiness' => lineforge_generalized_readiness(34, 0, min(28, $ingestionRuns / 4)),
            'detail' => 'The observability, anomaly, worker, stale-data, and resilience primitives can generalize into infrastructure monitoring.',
            'sharedPrimitives' => ['provider event', 'anomaly signal', 'degraded state', 'incident workflow'],
            'evidence' => (string) $ingestionRuns . ' ingestion runs and provider health archives.',
        ],
        [
            'id' => 'risk_forecasting',
            'name' => 'Risk and forecasting',
            'status' => 'adapter_blueprint',
            'readiness' => lineforge_generalized_readiness(24, 0, min(20, $predictions / 80)),
            'detail' => 'Can reuse probabilistic calibration, replay, confidence transparency, and governance once domain outcomes are defined.',
            'sharedPrimitives' => ['forecast event', 'confidence signal', 'uncertain state', 'review workflow'],
            'evidence' => 'Needs domain-specific labels and outcome mapping.',
        ],
    ];
}

function lineforge_generalized_primitives(array $adaptive, array $warehouse): array
{
    $adaptiveLayers = is_array($adaptive['layers'] ?? null) ? (array) $adaptive['layers'] : [];
    $selfEvaluation = is_array($adaptive['selfEvaluation']['monitors'] ?? null) ? (array) $adaptive['selfEvaluation']['monitors'] : [];
    $governanceRows = lineforge_adaptive_jsonl_count('execution-audit') + lineforge_adaptive_jsonl_count('operator-decision-retrospectives');

    return [
        'events' => [
            ['name' => 'Market event', 'status' => 'active', 'value' => (string) lineforge_generalized_count($warehouse, 'lineMovementRows'), 'detail' => 'Line movement, provider disagreement, odds state, and volatility snapshots.'],
            ['name' => 'Game event', 'status' => 'active', 'value' => (string) lineforge_generalized_count($warehouse, 'games'), 'detail' => 'Sports schedule, scoreboard, status, and event context.'],
            ['name' => 'Provider event', 'status' => 'active', 'value' => (string) lineforge_generalized_count($warehouse, 'providerHealthRows'), 'detail' => 'Provider health, availability, fallback, stale-data, and degradation observations.'],
            ['name' => 'Volatility event', 'status' => 'active', 'value' => (string) lineforge_generalized_count($warehouse, 'volatilityRows'), 'detail' => 'Pressure and movement summaries for later regime modeling.'],
            ['name' => 'Anomaly event', 'status' => 'armed', 'value' => (string) ($adaptive['summary']['highUncertaintyLayers'] ?? 0), 'detail' => 'High uncertainty, stale data, provider degradation, and triggered watch conditions.'],
            ['name' => 'Execution event', 'status' => 'governed', 'value' => (string) $governanceRows, 'detail' => 'Paper/live execution history should remain audit-first and provider-compliant.'],
        ],
        'signals' => [
            ['name' => 'Confidence', 'status' => 'evidence_gated', 'value' => (string) ($adaptive['summary']['compositeReadiness'] ?? 0) . '/100', 'detail' => 'Generalized confidence remains calibration-gated.'],
            ['name' => 'Pressure', 'status' => 'active', 'value' => 'market pressure', 'detail' => 'Pressure is separated from probability so it can influence timing and workflow without claiming accuracy.'],
            ['name' => 'Disagreement', 'status' => 'active', 'value' => 'provider spread', 'detail' => 'Cross-source disagreement works across markets, providers, and telemetry systems.'],
            ['name' => 'Timing', 'status' => 'active', 'value' => 'fragility', 'detail' => 'Timing sensitivity generalizes to starts, closes, deadlines, outages, and event transitions.'],
            ['name' => 'Liquidity', 'status' => 'data_sparse', 'value' => (string) lineforge_generalized_count($warehouse, 'oddsRows') . ' odds rows', 'detail' => 'Liquidity is domain-specific and should fail independently.'],
            ['name' => 'Reliability', 'status' => 'active', 'value' => (string) lineforge_generalized_count($warehouse, 'providerHealthRows') . ' rows', 'detail' => 'Provider reliability becomes a universal trust signal.'],
            ['name' => 'Anomaly', 'status' => 'armed', 'value' => (string) count($selfEvaluation) . ' monitors', 'detail' => 'Anomaly signals feed self-evaluation and degraded-mode operations.'],
            ['name' => 'Execution feasibility', 'status' => 'governed', 'value' => 'manual-first', 'detail' => 'Execution feasibility is a governance signal, not a call to automate.'],
        ],
        'states' => [
            ['name' => 'Stable', 'status' => 'defined', 'detail' => 'No major volatility, provider, stale-data, or governance warnings.'],
            ['name' => 'Volatile', 'status' => 'defined', 'detail' => 'Movement or pressure requires tighter review and confidence down-weighting.'],
            ['name' => 'Degraded', 'status' => 'active', 'detail' => 'Public/degraded mode is already first-class in Phase 4.'],
            ['name' => 'Uncertain', 'status' => 'active', 'detail' => 'High uncertainty is exposed and penalizes composite readiness.'],
            ['name' => 'Stale', 'status' => 'armed', 'detail' => 'Stale data can isolate signals, markets, providers, or workspaces.'],
            ['name' => 'Suspended', 'status' => 'defined', 'detail' => 'Execution or evaluation should pause when provider/domain rules require it.'],
        ],
        'workflows' => [
            ['name' => 'Ingest', 'status' => 'active', 'detail' => 'Collect observations from domain adapters.'],
            ['name' => 'Normalize', 'status' => 'active', 'detail' => 'Map domain-specific inputs into universal primitives.'],
            ['name' => 'Evaluate', 'status' => 'active', 'detail' => 'Score independent signal layers and uncertainty.'],
            ['name' => 'Calibrate', 'status' => 'evidence_gated', 'detail' => 'Promote confidence only after replay-safe outcomes.'],
            ['name' => 'Orchestrate', 'status' => 'active', 'detail' => 'Coordinate dependencies, risk, and workspace state.'],
            ['name' => 'Execute', 'status' => 'governed', 'detail' => 'Paper/manual/provider-compliant execution only.'],
            ['name' => 'Audit', 'status' => 'active', 'detail' => 'Append-only reasoning, provider, execution, and operator history.'],
            ['name' => 'Replay', 'status' => 'active', 'detail' => 'Reconstruct what the system knew at a point in time.'],
        ],
    ];
}

function lineforge_generalized_orchestration(array $adaptive): array
{
    $rules = is_array($adaptive['composition']['rules'] ?? null) ? (array) $adaptive['composition']['rules'] : [];
    $dependencyGraph = [
        ['from' => 'provider reliability', 'to' => 'execution feasibility', 'effect' => 'degrade or block'],
        ['from' => 'volatility', 'to' => 'confidence visibility', 'effect' => 'down-weight'],
        ['from' => 'stale data', 'to' => 'signal freshness', 'effect' => 'invalidate or recheck'],
        ['from' => 'calibration confidence', 'to' => 'model promotion', 'effect' => 'gate'],
        ['from' => 'historical similarity', 'to' => 'explanation', 'effect' => 'context only'],
        ['from' => 'governance', 'to' => 'execution', 'effect' => 'manual approval'],
    ];

    return [
        'status' => (string) ($adaptive['summary']['networkStatus'] ?? 'collecting_memory'),
        'pipelines' => [
            ['name' => 'Signal dependency graph', 'status' => 'active', 'value' => (string) count($dependencyGraph) . ' edges', 'detail' => 'Shows how one layer can influence another without hiding uncertainty.'],
            ['name' => 'Cross-system calibration', 'status' => 'evidence_gated', 'value' => 'labels required', 'detail' => 'Confidence transfer across domains requires domain-specific outcome labels.'],
            ['name' => 'Adaptive weighting', 'status' => 'armed', 'value' => 'rule-based', 'detail' => 'Current weighting is transparent rules; learned weighting waits for calibration data.'],
            ['name' => 'Uncertainty propagation', 'status' => 'active', 'value' => (string) ($adaptive['summary']['highUncertaintyLayers'] ?? 0) . ' high', 'detail' => 'Layer uncertainty reduces composite readiness and workspace confidence.'],
            ['name' => 'Cascading risk evaluation', 'status' => 'governed', 'value' => 'manual-first', 'detail' => 'Risk gates can stop execution while leaving research workflows visible.'],
        ],
        'rules' => $rules,
        'dependencyGraph' => $dependencyGraph,
    ];
}

function lineforge_generalized_contextual_memory(array $adaptive, array $warehouse): array
{
    $operatorArtifacts = is_array($adaptive['operatorMemory']['artifacts'] ?? null) ? (array) $adaptive['operatorMemory']['artifacts'] : [];
    $operatorRows = array_sum(array_map(static function (array $artifact): int {
        return (int) preg_replace('/[^0-9]+/', '', (string) ($artifact['value'] ?? '0'));
    }, $operatorArtifacts));

    return [
        'status' => lineforge_generalized_count($warehouse, 'ingestionRuns') > 0 ? 'memory_active' : 'memory_warming',
        'stores' => [
            ['name' => 'Event memory', 'status' => 'active', 'value' => (string) lineforge_generalized_count($warehouse, 'games'), 'detail' => 'Historical event observations and timeline reconstruction.'],
            ['name' => 'Workflow memory', 'status' => 'active', 'value' => (string) lineforge_generalized_count($warehouse, 'ingestionRuns'), 'detail' => 'Ingestion runs and processing posture over time.'],
            ['name' => 'Provider memory', 'status' => 'active', 'value' => (string) lineforge_generalized_count($warehouse, 'providerHealthRows'), 'detail' => 'Provider reliability, degradation, and fallback history.'],
            ['name' => 'Operator memory', 'status' => $operatorRows > 0 ? 'capturing' : 'ready_empty', 'value' => (string) $operatorRows, 'detail' => 'Notes, tags, bookmarks, journals, and retrospectives.'],
            ['name' => 'Signal memory', 'status' => 'active', 'value' => (string) lineforge_generalized_count($warehouse, 'featurePipelineRows'), 'detail' => 'Feature pipeline snapshots and signal object history.'],
            ['name' => 'Calibration memory', 'status' => 'evidence_gated', 'value' => '0 closed labels', 'detail' => 'Calibration memory remains gated until outcomes are mapped.'],
            ['name' => 'Execution memory', 'status' => 'governed', 'value' => (string) lineforge_adaptive_jsonl_count('execution-audit'), 'detail' => 'Execution history must remain auditable and provider-compliant.'],
            ['name' => 'Anomaly memory', 'status' => 'active', 'value' => (string) lineforge_generalized_count($warehouse, 'adaptiveNetworkRows'), 'detail' => 'Adaptive network snapshots preserve unreliable conditions.'],
        ],
    ];
}

function lineforge_generalized_adaptive_workspaces(array $adaptive): array
{
    $network = (string) ($adaptive['summary']['networkStatus'] ?? 'collecting_memory');
    $highUncertainty = (int) ($adaptive['summary']['highUncertaintyLayers'] ?? 0);
    $selfStatus = (string) ($adaptive['summary']['selfEvaluationStatus'] ?? 'self_evaluating');

    return [
        'status' => $network === 'degraded_public_mode' ? 'degraded_workspace_active' : 'context_aware',
        'workspaces' => [
            ['name' => 'Volatility workspace', 'status' => 'armed', 'trigger' => 'Volatility or market pressure exceeds threshold.', 'detail' => 'Prioritizes movement, pressure, and timing sensitivity.'],
            ['name' => 'Degraded provider workspace', 'status' => $network === 'degraded_public_mode' ? 'active' : 'armed', 'trigger' => 'Provider data unavailable, stale, or degraded.', 'detail' => 'Surfaces fallback mode, provider health, and source trust.'],
            ['name' => 'Execution-review workspace', 'status' => 'governed', 'trigger' => 'Any live or simulated execution candidate.', 'detail' => 'Requires risk, provider, stale-data, and manual approval checks.'],
            ['name' => 'Calibration workspace', 'status' => $highUncertainty > 0 ? 'watch' : 'armed', 'trigger' => 'Drift, false-confidence risk, or enough closed labels.', 'detail' => 'Shows calibration curves, Brier score, replay evidence, and model gates.'],
            ['name' => 'Anomaly workspace', 'status' => $selfStatus === 'self_evaluating_watch' ? 'active' : 'armed', 'trigger' => 'Self-evaluation finds unreliable conditions.', 'detail' => 'Routes operators to stale data, provider issues, and high-uncertainty layers.'],
            ['name' => 'Replay workspace', 'status' => 'active', 'trigger' => 'Operator reviews a historical decision or signal.', 'detail' => 'Reconstructs what the system knew at the time.'],
        ],
    ];
}

function lineforge_generalized_self_optimization(array $adaptive, array $warehouse): array
{
    $runs = lineforge_generalized_count($warehouse, 'ingestionRuns');
    $providerRows = lineforge_generalized_count($warehouse, 'providerHealthRows');
    $highUncertainty = (int) ($adaptive['summary']['highUncertaintyLayers'] ?? 0);

    return [
        'status' => 'optimization_watch',
        'systems' => [
            ['name' => 'Ingestion optimization', 'status' => $runs >= 100 ? 'baseline_ready' : 'collecting', 'value' => (string) $runs . ' runs', 'detail' => 'Use run history to prioritize useful collectors and reduce stale data.'],
            ['name' => 'Refresh prioritization', 'status' => 'armed', 'value' => 'cadence-aware', 'detail' => 'Future polling should focus on volatile, near-start, degraded, or operator-pinned contexts.'],
            ['name' => 'Provider prioritization', 'status' => $providerRows >= 500 ? 'baseline_ready' : 'collecting', 'value' => (string) $providerRows . ' rows', 'detail' => 'Provider health history should influence source trust and request budgets.'],
            ['name' => 'Confidence recalibration', 'status' => 'evidence_gated', 'value' => 'labels required', 'detail' => 'Confidence cannot self-optimize before closed outcomes exist.'],
            ['name' => 'Anomaly response', 'status' => $highUncertainty > 0 ? 'watch' : 'armed', 'value' => (string) $highUncertainty . ' high-uncertainty layers', 'detail' => 'High uncertainty can route workspaces and suppress execution posture.'],
            ['name' => 'Alert prioritization', 'status' => 'planned', 'value' => 'operator queue', 'detail' => 'Future alerts should rank by risk, freshness, provider trust, and workflow context.'],
        ],
    ];
}

function lineforge_generalized_institutional_tooling(): array
{
    return [
        'status' => 'institutional_blueprint',
        'systems' => [
            ['name' => 'Organizations', 'status' => 'planned', 'detail' => 'Shared workspaces, provider configuration, billing, and audit boundaries.'],
            ['name' => 'Operator roles', 'status' => 'planned', 'detail' => 'Viewer, analyst, reviewer, admin, and execution-approver permissions.'],
            ['name' => 'Analyst collaboration', 'status' => 'planned', 'detail' => 'Shared notes, tags, watchlists, and replay sessions.'],
            ['name' => 'Governance workflows', 'status' => 'foundation_ready', 'detail' => 'Manual-first execution and audit logs already define the safety posture.'],
            ['name' => 'Approval chains', 'status' => 'planned', 'detail' => 'Live-money or high-risk actions should require explicit review and limits.'],
            ['name' => 'Intelligence review boards', 'status' => 'planned', 'detail' => 'Institutional review surfaces for models, calibration drift, and provider reliability.'],
            ['name' => 'Shared replay sessions', 'status' => 'foundation_ready', 'detail' => 'Warehouse replay primitives can become collaborative review sessions.'],
            ['name' => 'Institutional auditing', 'status' => 'foundation_ready', 'detail' => 'Append-only warehouse and execution governance create the audit foundation.'],
        ],
    ];
}

function lineforge_generalized_governance(array $adaptive): array
{
    return [
        'status' => 'governance_first',
        'systems' => [
            ['name' => 'Explainability', 'status' => 'active', 'detail' => 'Adaptive explanations expose why confidence, execution, provider trust, and similarity are gated.'],
            ['name' => 'Confidence transparency', 'status' => 'active', 'detail' => 'Confidence is separated from calibration confidence, data quality, and uncertainty.'],
            ['name' => 'Audit systems', 'status' => 'active', 'detail' => 'Warehouse snapshots preserve what the system knew, not only what the UI displayed.'],
            ['name' => 'Operator override', 'status' => 'planned', 'detail' => 'Future operators should be able to override with explicit reason and audit trail.'],
            ['name' => 'Execution restrictions', 'status' => 'active', 'detail' => 'Unsupported providers remain data-only and live execution stays manual/provider-compliant.'],
            ['name' => 'Risk governance', 'status' => 'active', 'detail' => 'Risk limits, stale-data checks, provider health, and manual confirmation remain execution gates.'],
            ['name' => 'Uncertainty visibility', 'status' => ((int) ($adaptive['summary']['highUncertaintyLayers'] ?? 0)) > 0 ? 'active' : 'armed', 'detail' => 'High uncertainty remains visible and reduces network readiness.'],
            ['name' => 'Automated safety degradation', 'status' => 'active', 'detail' => 'Degraded public mode is a first-class safety state, not an error hidden from the operator.'],
        ],
    ];
}

function lineforge_generalized_record_snapshot(array $summary, array $domains, array $primitives, array $governance): void
{
    lineforge_warehouse_append_once('warehouse-generalized-core', [
        'type' => 'generalized_core_snapshot',
        'status' => (string) ($summary['status'] ?? 'unknown'),
        'coreReadiness' => (float) ($summary['coreReadiness'] ?? 0),
        'domainAdapters' => count($domains),
        'eventPrimitives' => count((array) ($primitives['events'] ?? [])),
        'signalPrimitives' => count((array) ($primitives['signals'] ?? [])),
        'governanceSystems' => count((array) ($governance['systems'] ?? [])),
    ], 240);
}

function lineforge_generalized_build_state(array $adaptive, array $evolution, array $warehouse, array $calibration, array $marketInference): array
{
    $domains = lineforge_generalized_domain_adapters($adaptive, $warehouse);
    $primitives = lineforge_generalized_primitives($adaptive, $warehouse);
    $orchestration = lineforge_generalized_orchestration($adaptive);
    $memory = lineforge_generalized_contextual_memory($adaptive, $warehouse);
    $workspaces = lineforge_generalized_adaptive_workspaces($adaptive);
    $optimization = lineforge_generalized_self_optimization($adaptive, $warehouse);
    $institutional = lineforge_generalized_institutional_tooling();
    $governance = lineforge_generalized_governance($adaptive);

    $activeDomains = count(array_filter($domains, static fn(array $domain): bool => (string) ($domain['status'] ?? '') === 'active_domain'));
    $coreReadiness = lineforge_generalized_readiness(
        (float) ($adaptive['summary']['compositeReadiness'] ?? 0) + 20,
        ((float) ($adaptive['summary']['highUncertaintyLayers'] ?? 0) * 1.5),
        min(18, lineforge_generalized_count($warehouse, 'ingestionRuns') / 10)
    );
    $status = $activeDomains > 0 ? 'domain_agnostic_foundation' : 'blueprint_only';

    $summary = [
        'status' => $status,
        'coreReadiness' => $coreReadiness,
        'activeDomains' => $activeDomains,
        'domainAdapters' => count($domains),
        'eventPrimitives' => count((array) ($primitives['events'] ?? [])),
        'signalPrimitives' => count((array) ($primitives['signals'] ?? [])),
        'workflowPrimitives' => count((array) ($primitives['workflows'] ?? [])),
        'memoryStores' => count((array) ($memory['stores'] ?? [])),
        'workspaceTriggers' => count((array) ($workspaces['workspaces'] ?? [])),
        'governanceSystems' => count((array) ($governance['systems'] ?? [])),
        'calibrationStatus' => (string) ($calibration['status'] ?? 'collecting_baseline'),
        'adaptiveNetworkStatus' => (string) ($adaptive['summary']['networkStatus'] ?? 'collecting_memory'),
    ];

    lineforge_generalized_record_snapshot($summary, $domains, $primitives, $governance);

    return [
        'summary' => $summary,
        'domains' => $domains,
        'primitives' => $primitives,
        'orchestration' => $orchestration,
        'memory' => $memory,
        'adaptiveWorkspaces' => $workspaces,
        'selfOptimization' => $optimization,
        'institutionalTooling' => $institutional,
        'governance' => $governance,
        'identity' => [
            'name' => 'adaptive probabilistic decision infrastructure',
            'statement' => 'Lineforge can evolve beyond sports by preserving uncertainty, calibration, orchestration, replayability, governance, institutional workflows, and accumulated operational memory as reusable primitives.',
            'doctrine' => 'Improve signal quality, calibration, resilience, orchestration, transparency, operator trust, and workflow clarity before adding more model surface area.',
            'highestGoal' => 'Help humans make better decisions under uncertainty without pretending uncertainty disappears.',
            'continuousRefinement' => 'Mature systems evolve through continuous refinement, not giant rewrites, dramatic pivots, or endless feature explosions.',
            'signalStandard' => 'Optimize for signal over stimulation: fewer meaningless metrics, fewer fake confidence numbers, less dashboard clutter, and more contextual intelligence.',
            'maturityDoctrine' => 'The most valuable asset is the accumulated intelligence ecosystem: historical memory, calibration history, workflow design, operational knowledge, institutional reasoning, trust systems, and context.',
            'maturityAxiom' => 'Refinement without corruption: become more truthful, resilient, adaptive, and trustworthy over time.',
            'not' => [
                'a generic betting app',
                'a black-box prediction oracle',
                'an unsupported execution automation system',
                'a confidence-hiding decision engine',
                'a more-AI-for-its-own-sake roadmap',
                'a feature-bloat operating surface',
                'an engagement-optimized attention trap',
            ],
            'principles' => [
                'uncertainty before certainty',
                'calibration before model claims',
                'governance before execution',
                'memory before adaptation',
                'orchestration before automation',
                'domain adapters over one-off rewrites',
                'operator reasoning over blind recommendations',
                'graceful degradation over hidden failure',
                'historical context before expansion',
                'refinement without corruption',
                'signal over stimulation',
                'operational elegance over visual drama',
            ],
            'qualityTargets' => [
                'signal quality',
                'adaptability',
                'calibration',
                'resilience',
                'orchestration',
                'operator trust',
                'workflow clarity',
                'intelligence transparency',
                'probabilistic reasoning',
                'decision support',
            ],
            'accumulatedEcosystem' => [
                'historical memory',
                'calibration history',
                'workflow design',
                'operational knowledge',
                'institutional reasoning patterns',
                'trust systems',
                'accumulated context',
            ],
            'refinementGuardrails' => [
                'resist hype cycles',
                'resist fake certainty',
                'resist feature bloat',
                'resist unnecessary automation',
                'resist black-box opacity',
                'resist complexity without purpose',
                'resist engagement optimization',
                'resist noise amplification',
            ],
            'failureModes' => [
                'overexpansion',
                'loss of clarity',
                'loss of trust',
                'loss of discipline',
                'catastrophic confidence failure',
            ],
            'driftRisks' => [
                'overconfidence',
                'opacity',
                'automation addiction',
                'engagement optimization',
                'noise amplification',
                'complexity theater',
            ],
            'enduringQualities' => [
                'calmer',
                'clearer',
                'more disciplined',
                'more explainable',
                'more resilient',
                'more context-aware',
            ],
            'operatorThinkingSupport' => [
                'surface relevant context',
                'expose uncertainty honestly',
                'preserve historical reasoning',
                'coordinate workflows',
                'detect degradation',
                'improve calibration',
                'prevent avoidable mistakes',
            ],
            'operatorContract' => [
                'expose uncertainty honestly',
                'degrade gracefully',
                'preserve context',
                'remain explainable',
                'help humans reason better',
                'avoid fake certainty',
                'survive degraded environments',
                'preserve operator trust',
            ],
        ],
    ];
}
