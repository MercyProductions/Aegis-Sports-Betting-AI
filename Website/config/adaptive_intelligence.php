<?php

require_once __DIR__ . '/intelligence_evolution.php';

function lineforge_adaptive_dimension_average(array $signals, string $name, float $fallback = 0.0): float
{
    $values = [];
    foreach ($signals as $signal) {
        foreach ((array) ($signal['dimensions'] ?? []) as $dimension) {
            if (strcasecmp((string) ($dimension['name'] ?? ''), $name) === 0 && is_numeric($dimension['score'] ?? null)) {
                $values[] = (float) $dimension['score'];
            }
        }
    }

    return $values ? (array_sum($values) / count($values)) : $fallback;
}

function lineforge_adaptive_count(array $warehouse, string $key): int
{
    return max(0, (int) ($warehouse['counts'][$key] ?? 0));
}

function lineforge_adaptive_support_score(int $current, int $target): float
{
    if ($target <= 0) {
        return 0.0;
    }

    return lineforge_evolution_clamp(($current / $target) * 100);
}

function lineforge_adaptive_layer(
    string $id,
    string $name,
    float $score,
    float $uncertainty,
    string $status,
    string $measurement,
    string $detail,
    string $failureMode,
    array $dependencies,
    string $calibrationPath
): array {
    return [
        'id' => $id,
        'name' => $name,
        'score' => round(lineforge_evolution_clamp($score), 1),
        'uncertainty' => round(lineforge_evolution_clamp($uncertainty), 1),
        'status' => $status,
        'measurement' => $measurement,
        'detail' => $detail,
        'failureMode' => $failureMode,
        'dependencies' => $dependencies,
        'calibrationPath' => $calibrationPath,
    ];
}

function lineforge_adaptive_layers(array $evolution, array $warehouse, array $calibration, array $marketInference, array $arbitrage): array
{
    $signals = is_array($evolution['signals'] ?? null) ? (array) $evolution['signals'] : [];
    $summary = (array) ($evolution['summary'] ?? []);
    $marketStructure = (array) ($evolution['marketStructure'] ?? []);
    $metrics = (array) ($marketStructure['metrics'] ?? []);
    $arbitrageSummary = (array) ($arbitrage['summary'] ?? []);
    $oddsRows = is_array($arbitrage['normalizedOdds'] ?? null) ? (array) $arbitrage['normalizedOdds'] : [];

    $closed = (int) ($calibration['closedSamples'] ?? 0);
    $featureRows = (int) ($summary['featureRows'] ?? 0);
    $signalCount = count($signals);
    $ingestionRuns = lineforge_adaptive_count($warehouse, 'ingestionRuns');
    $predictionRows = lineforge_adaptive_count($warehouse, 'predictions');
    $lineRows = lineforge_adaptive_count($warehouse, 'lineMovementRows');
    $providerHealthRows = lineforge_adaptive_count($warehouse, 'providerHealthRows');
    $volatilityRows = lineforge_adaptive_count($warehouse, 'volatilityRows');
    $gameRows = lineforge_adaptive_count($warehouse, 'games');
    $warehouseOddsRows = lineforge_adaptive_count($warehouse, 'oddsRows');

    $avgReadiness = $signalCount ? array_sum(array_map(static fn(array $signal): float => (float) ($signal['readinessScore'] ?? 0), $signals)) / $signalCount : 0.0;
    $volatility = (float) ($metrics['volatilityScore'] ?? $marketInference['volatilityScore'] ?? 0);
    $staleShare = (float) ($metrics['staleShare'] ?? 0);
    $disagreement = (int) ($metrics['disagreementFlags'] ?? ($marketInference['marketDisagreement']['flaggedOutcomes'] ?? 0));
    $rapidMoves = (int) ($metrics['rapidMoves'] ?? ($marketInference['lineVelocity']['rapidMoves'] ?? 0));
    $providerHealth = (string) ($arbitrageSummary['providerHealth'] ?? 'needs_data');

    $providerReliabilityScore = $providerHealthRows > 0
        ? lineforge_evolution_clamp(50 + lineforge_adaptive_support_score($providerHealthRows, 500) * 0.45 - ($providerHealth !== 'operational' && $providerHealth !== 'needs_data' ? 18 : 0))
        : 28;
    $historicalDepth = lineforge_evolution_clamp(
        lineforge_adaptive_support_score($predictionRows, 1000) * 0.25
        + lineforge_adaptive_support_score($lineRows, 500) * 0.25
        + lineforge_adaptive_support_score($gameRows, 6000) * 0.25
        + lineforge_adaptive_support_score($ingestionRuns, 200) * 0.25
    );

    $layers = [
        lineforge_adaptive_layer(
            'probability_estimation',
            'Probability estimation',
            $closed >= 100 ? lineforge_evolution_clamp(68 + (($closed - 100) / 20)) : lineforge_evolution_clamp(28 + $featureRows * 1.5),
            $closed >= 100 ? max(18, 44 - ($closed / 25)) : max(58, 92 - ($closed / 2)),
            $closed >= 100 ? 'calibrating' : 'evidence_gated',
            (string) $closed . ' closed labels',
            'Estimates remain transparent baseline probabilities until replay-safe labels support statistical training.',
            'If calibration support is weak, this layer can fail without taking down volatility, liquidity, or provider reliability layers.',
            ['feature pipeline', 'calibration history', 'prediction replay'],
            'Brier score, calibration curve, rolling-window validation',
        ),
        lineforge_adaptive_layer(
            'volatility_estimation',
            'Volatility estimation',
            $volatilityRows > 0 ? lineforge_evolution_clamp(50 + lineforge_adaptive_support_score($volatilityRows, 250) * 0.4) : 30,
            $volatilityRows > 0 ? max(24, 68 - lineforge_adaptive_support_score($volatilityRows, 250) * 0.4) : 76,
            $volatility >= 65 ? 'elevated' : ($volatilityRows > 0 ? 'observed' : 'collecting_memory'),
            round($volatility, 1) . '/100 pressure',
            'Tracks market turbulence, rapid movement, and disagreement pressure independent of win probability.',
            'If volatility spikes, confidence can be down-weighted while market monitoring still stays live.',
            ['line movement timeline', 'volatility archive', 'market disagreement'],
            'Volatility-state transitions and movement clustering',
        ),
        lineforge_adaptive_layer(
            'execution_feasibility',
            'Execution feasibility',
            lineforge_adaptive_dimension_average($signals, 'Execution feasibility', 25),
            empty($oddsRows) ? 74 : max(24, 62 - count($oddsRows)),
            empty($oddsRows) ? 'public_context_only' : 'manual_review',
            empty($oddsRows) ? 'no live execution feed' : count($oddsRows) . ' odds rows',
            'Scores whether a signal can be reviewed safely after provider health, liquidity, stale data, and compliance checks.',
            'This layer blocks execution recommendations without blocking research visibility.',
            ['provider health', 'liquidity intelligence', 'stale-data isolation', 'risk governance'],
            'Execution replay, stale-line recheck, slippage simulation',
        ),
        lineforge_adaptive_layer(
            'liquidity_intelligence',
            'Liquidity intelligence',
            lineforge_adaptive_dimension_average($signals, 'Liquidity', $warehouseOddsRows > 0 ? 45 : 18),
            ($warehouseOddsRows + count($oddsRows)) > 0 ? 48 : 82,
            ($warehouseOddsRows + count($oddsRows)) > 0 ? 'thin_observed' : 'data_sparse',
            (string) ($warehouseOddsRows + count($oddsRows)) . ' odds rows',
            'Measures market depth from normalized odds and available line coverage, not from guessed sportsbook limits.',
            'Low liquidity suppresses execution feasibility and raises manual-verification requirements.',
            ['normalized odds', 'provider adapters', 'market matching'],
            'Limit tracking, depth snapshots, fill/slippage validation',
        ),
        lineforge_adaptive_layer(
            'provider_reliability',
            'Provider reliability',
            $providerReliabilityScore,
            $providerHealthRows > 0 ? max(18, 64 - lineforge_adaptive_support_score($providerHealthRows, 500) * 0.4) : 84,
            $providerHealthRows > 0 ? 'observed' : 'waiting',
            (string) $providerHealthRows . ' health rows',
            'Weights providers by availability and health history before trusting market data or execution routing.',
            'Provider degradation can isolate a source without forcing the entire platform offline.',
            ['provider health archive', 'adapter health checks', 'licensed feed status'],
            'Latency, uptime, stale-rate, and mismatch scoring',
        ),
        lineforge_adaptive_layer(
            'market_pressure',
            'Market pressure',
            lineforge_adaptive_dimension_average($signals, 'Market pressure', lineforge_evolution_clamp($volatility + ($disagreement * 8) + ($rapidMoves * 6))),
            $lineRows > 0 ? 42 : 78,
            ($volatility >= 65 || $disagreement > 0 || $rapidMoves > 0) ? 'active_pressure' : 'quiet_watch',
            (string) $disagreement . ' disagreement / ' . (string) $rapidMoves . ' rapid moves',
            'Separates pressure, disagreement, and movement behavior from probability estimates.',
            'Pressure can invalidate timing-sensitive reviews without declaring a prediction wrong.',
            ['market disagreement engine', 'line velocity', 'volatility archive'],
            'Pressure persistence and provider sequencing models',
        ),
        lineforge_adaptive_layer(
            'timing_sensitivity',
            'Timing sensitivity',
            lineforge_adaptive_dimension_average($signals, 'Timing sensitivity', 45),
            $signalCount > 0 ? 44 : 76,
            $signalCount > 0 ? 'observed' : 'waiting',
            (string) $signalCount . ' signal objects',
            'Estimates how fragile a review is based on live state, start-time proximity, and stale-data exposure.',
            'High timing sensitivity can force recheck and manual confirmation while preserving research context.',
            ['event schedule', 'game state', 'stale-data monitor'],
            'Timing-response models and event-state triggers',
        ),
        lineforge_adaptive_layer(
            'calibration_confidence',
            'Calibration confidence',
            lineforge_adaptive_dimension_average($signals, 'Calibration confidence', lineforge_evolution_clamp(10 + ($closed / 2), 8, 45)),
            $closed >= 100 ? max(20, 60 - ($closed / 20)) : max(62, 95 - ($closed / 2)),
            $closed >= 100 ? 'measuring' : 'evidence_gated',
            (string) $closed . '/100 minimum labels',
            'Measures how much real outcome history supports confidence estimates.',
            'Calibration can fail closed and prevent model promotion even if the UI still displays research signals.',
            ['closed outcomes', 'prediction replay', 'calibration buckets'],
            'Calibration curves, Brier score, drift monitors',
        ),
        lineforge_adaptive_layer(
            'historical_similarity',
            'Historical similarity',
            $historicalDepth,
            max(24, 92 - $historicalDepth * 0.65),
            $historicalDepth >= 55 ? 'pattern_ready' : 'collecting_memory',
            (string) $predictionRows . ' predictions / ' . (string) $lineRows . ' line rows',
            'Prepares similarity matching so Lineforge can later ask whether current market environments resemble prior ones.',
            'Similarity remains advisory and can be ignored when history is too thin.',
            ['event warehouse', 'line movement archive', 'prediction snapshots'],
            'Nearest-neighbor replay, volatility archetypes, provider movement clusters',
        ),
        lineforge_adaptive_layer(
            'anomaly_detection',
            'Anomaly detection',
            lineforge_evolution_clamp(35 + ($ingestionRuns > 0 ? 18 : 0) + min(22, count((array) ($evolution['triggers'] ?? [])) * 6)),
            $ingestionRuns > 0 ? 56 : 88,
            count((array) ($evolution['triggers'] ?? [])) > 0 ? 'triggered_watch' : 'quiet_watch',
            (string) count((array) ($evolution['triggers'] ?? [])) . ' active triggers',
            'Detects stale-data, provider health, volatility, and replay-memory anomalies before they contaminate higher-level intelligence.',
            'An anomaly can isolate a provider, market, or signal instead of corrupting the whole workspace.',
            ['observability', 'provider health', 'stale-data monitor', 'market regime detector'],
            'False-confidence detection and signal-failure clustering',
        ),
    ];

    return $layers;
}

function lineforge_adaptive_composition(array $layers, array $evolution, array $warehouse): array
{
    $scores = array_map(static fn(array $layer): float => (float) ($layer['score'] ?? 0), $layers);
    $uncertainties = array_map(static fn(array $layer): float => (float) ($layer['uncertainty'] ?? 100), $layers);
    $avgScore = $scores ? array_sum($scores) / count($scores) : 0.0;
    $avgUncertainty = $uncertainties ? array_sum($uncertainties) / count($uncertainties) : 100.0;
    $highUncertainty = count(array_filter($layers, static fn(array $layer): bool => (float) ($layer['uncertainty'] ?? 0) >= 65));
    $degraded = count(array_filter($layers, static fn(array $layer): bool => in_array((string) ($layer['status'] ?? ''), ['data_sparse', 'evidence_gated', 'public_context_only', 'waiting'], true)));
    $activeRegime = (string) ($evolution['summary']['activeRegime'] ?? 'unknown');
    $composite = lineforge_evolution_clamp($avgScore - ($avgUncertainty * 0.18));

    if ($activeRegime === 'public_context_only') {
        $status = 'degraded_public_mode';
        $message = 'Adaptive coordination is active, but market layers are operating without normalized sportsbook odds.';
    } elseif ($highUncertainty >= 4) {
        $status = 'collecting_memory';
        $message = 'The network is collecting enough historical support before it upgrades adaptive decisions.';
    } else {
        $status = 'coordinated_watch';
        $message = 'Independent intelligence layers are coordinated with uncertainty and failure isolation.';
    }

    return [
        'status' => $status,
        'compositeReadiness' => round($composite, 1),
        'averageLayerScore' => round($avgScore, 1),
        'averageUncertainty' => round($avgUncertainty, 1),
        'highUncertaintyLayers' => $highUncertainty,
        'degradedLayers' => $degraded,
        'message' => $message,
        'rules' => [
            [
                'name' => 'Volatility down-weights confidence',
                'status' => ((string) ($evolution['summary']['activeRegime'] ?? '') === 'high_volatility') ? 'active' : 'armed',
                'effect' => 'Volatility can reduce signal visibility and require replay review without changing the original feature row.',
            ],
            [
                'name' => 'Provider reliability gates execution',
                'status' => lineforge_adaptive_count($warehouse, 'providerHealthRows') > 0 ? 'active' : 'collecting',
                'effect' => 'Provider health affects execution feasibility and stale-data isolation before any live-money posture.',
            ],
            [
                'name' => 'Calibration gates model promotion',
                'status' => ((int) ($evolution['summary']['trainableRows'] ?? 0)) >= 100 ? 'ready' : 'evidence_gated',
                'effect' => 'No statistical model can replace the transparent baseline until replay-safe labels exist.',
            ],
            [
                'name' => 'Historical similarity stays advisory',
                'status' => lineforge_adaptive_count($warehouse, 'predictions') >= 1000 ? 'pattern_ready' : 'collecting_memory',
                'effect' => 'Pattern matching can explain context but cannot become an accuracy claim without validation.',
            ],
        ],
    ];
}

function lineforge_adaptive_market_behavior(array $evolution, array $marketInference, array $warehouse): array
{
    $metrics = (array) ($evolution['marketStructure']['metrics'] ?? []);
    $regime = (string) ($evolution['marketStructure']['activeRegime'] ?? 'unknown');
    $volatility = (float) ($metrics['volatilityScore'] ?? $marketInference['volatilityScore'] ?? 0);
    $disagreement = (int) ($metrics['disagreementFlags'] ?? 0);
    $rapidMoves = (int) ($metrics['rapidMoves'] ?? 0);
    $lineRows = lineforge_adaptive_count($warehouse, 'lineMovementRows');

    return [
        'state' => $regime,
        'question' => 'What kind of market environment is this?',
        'models' => [
            ['name' => 'Regime classification', 'status' => $regime, 'value' => str_replace('_', ' ', $regime), 'detail' => (string) ($evolution['marketStructure']['detail'] ?? 'Market regime pending.')],
            ['name' => 'Volatility-state transition', 'status' => $lineRows >= 250 ? 'sequence_ready' : 'collecting_memory', 'value' => round($volatility, 1) . '/100', 'detail' => 'Needs deeper line-movement history before transition probabilities are trusted.'],
            ['name' => 'Provider reaction sequencing', 'status' => $lineRows >= 500 ? 'candidate_ready' : 'planned', 'value' => (string) $lineRows . ' line rows', 'detail' => 'Future model will compare which provider moves first and whether others follow.'],
            ['name' => 'Market shock detection', 'status' => ($volatility >= 65 || $rapidMoves >= 3) ? 'watch' : 'quiet', 'value' => (string) $rapidMoves . ' rapid moves', 'detail' => 'Shock detection watches rapid moves, stale-line risk, and provider disagreement together.'],
            ['name' => 'Disagreement persistence', 'status' => $disagreement > 0 ? 'active' : 'quiet', 'value' => (string) $disagreement . ' flags', 'detail' => 'Persistent disagreement is treated as a risk condition, not verified sharp money.'],
        ],
    ];
}

function lineforge_adaptive_historical_patterns(array $warehouse): array
{
    $predictionRows = lineforge_adaptive_count($warehouse, 'predictions');
    $gameRows = lineforge_adaptive_count($warehouse, 'games');
    $lineRows = lineforge_adaptive_count($warehouse, 'lineMovementRows');
    $providerRows = lineforge_adaptive_count($warehouse, 'providerHealthRows');
    $depth = lineforge_evolution_clamp(
        lineforge_adaptive_support_score($predictionRows, 1200) * 0.28
        + lineforge_adaptive_support_score($gameRows, 8000) * 0.24
        + lineforge_adaptive_support_score($lineRows, 600) * 0.28
        + lineforge_adaptive_support_score($providerRows, 800) * 0.20
    );

    return [
        'status' => $depth >= 60 ? 'similarity_ready' : 'collecting_memory',
        'depthScore' => round($depth, 1),
        'patterns' => [
            ['name' => 'Historical event similarity', 'status' => $gameRows >= 5000 ? 'candidate_ready' : 'collecting', 'value' => (string) $gameRows . ' game rows', 'detail' => 'Compares sport, schedule, rest, game state, and context once labels mature.'],
            ['name' => 'Volatility pattern matching', 'status' => $lineRows >= 300 ? 'candidate_ready' : 'collecting', 'value' => (string) $lineRows . ' line rows', 'detail' => 'Clusters movement shape and volatility persistence.'],
            ['name' => 'Provider movement archetypes', 'status' => $providerRows >= 600 ? 'candidate_ready' : 'collecting', 'value' => (string) $providerRows . ' provider rows', 'detail' => 'Builds provider behavior profiles from health, freshness, and movement history.'],
            ['name' => 'Signal-environment classification', 'status' => $predictionRows >= 1000 ? 'candidate_ready' : 'collecting', 'value' => (string) $predictionRows . ' prediction rows', 'detail' => 'Groups signal conditions before measuring later outcomes.'],
        ],
        'questions' => [
            'Have we seen market environments like this before?',
            'How did similar volatility conditions behave historically?',
            'Which provider behavior patterns became unreliable?',
        ],
    ];
}

function lineforge_adaptive_jsonl_count(string $scope): int
{
    if (!function_exists('lineforge_warehouse_jsonl_dir')) {
        return 0;
    }

    $dir = lineforge_warehouse_jsonl_dir($scope);
    $files = glob($dir . '/*.jsonl') ?: [];
    $count = 0;
    foreach ($files as $file) {
        $handle = @fopen($file, 'rb');
        if (!$handle) {
            continue;
        }
        while (!feof($handle)) {
            if (trim((string) fgets($handle)) !== '') {
                $count++;
            }
        }
        fclose($handle);
    }

    return $count;
}

function lineforge_adaptive_operator_memory(): array
{
    $artifacts = [
        ['scope' => 'operator-research-sessions', 'name' => 'Annotated research sessions', 'detail' => 'Research notes tied to events, markets, and replay timestamps.'],
        ['scope' => 'operator-market-tags', 'name' => 'Tagged market events', 'detail' => 'Manual tags for shocks, stale lines, lineup impact, and provider anomalies.'],
        ['scope' => 'operator-replay-bookmarks', 'name' => 'Replay bookmarks', 'detail' => 'Saved moments for later review and training dataset curation.'],
        ['scope' => 'operator-execution-journals', 'name' => 'Execution journals', 'detail' => 'Paper/live decision retrospectives with risk and provider context.'],
        ['scope' => 'operator-decision-retrospectives', 'name' => 'Decision retrospectives', 'detail' => 'Post-outcome review records for what the operator saw and decided.'],
    ];

    $rows = [];
    foreach ($artifacts as $artifact) {
        $count = lineforge_adaptive_jsonl_count($artifact['scope']);
        $rows[] = [
            'name' => $artifact['name'],
            'status' => $count > 0 ? 'capturing' : 'ready_empty',
            'value' => (string) $count . ' records',
            'detail' => $artifact['detail'],
        ];
    }

    return [
        'status' => count(array_filter($rows, static fn(array $row): bool => (string) ($row['status'] ?? '') === 'capturing')) > 0 ? 'capturing' : 'ready_empty',
        'artifacts' => $rows,
        'message' => 'Operator memory is structured so human research becomes auditable knowledge, not hidden model magic.',
    ];
}

function lineforge_adaptive_self_evaluation(array $layers, array $evolution, array $warehouse, array $calibration): array
{
    $closed = (int) ($calibration['closedSamples'] ?? 0);
    $calibrationError = $calibration['calibrationError'] ?? null;
    $triggers = is_array($evolution['triggers'] ?? null) ? (array) $evolution['triggers'] : [];
    $highReadinessLowCalibration = count(array_filter($layers, static fn(array $layer): bool => (float) ($layer['score'] ?? 0) >= 65 && (float) ($layer['uncertainty'] ?? 0) >= 65));
    $staleShare = (float) ($evolution['marketStructure']['metrics']['staleShare'] ?? 0);

    $monitors = [
        [
            'name' => 'Calibration drift',
            'status' => $closed >= 100 ? 'measuring' : 'data_gated',
            'value' => $calibrationError === null ? (string) $closed . ' labels' : (string) $calibrationError . '% error',
            'detail' => 'Requires closed samples before drift alerts can be trusted.',
        ],
        [
            'name' => 'Model degradation',
            'status' => ((int) ($evolution['summary']['trainableRows'] ?? 0)) >= 100 ? 'armed' : 'no_statistical_model',
            'value' => (string) ($evolution['summary']['modelReadiness'] ?? 'collecting_labels'),
            'detail' => 'No deployed statistical model exists yet, so degradation monitoring remains gated.',
        ],
        [
            'name' => 'Provider reliability decay',
            'status' => lineforge_adaptive_count($warehouse, 'providerHealthRows') > 0 ? 'observing' : 'waiting',
            'value' => (string) lineforge_adaptive_count($warehouse, 'providerHealthRows') . ' rows',
            'detail' => 'Provider stale rates and health changes can reduce trust independently of signal quality.',
        ],
        [
            'name' => 'Stale-data impact',
            'status' => $staleShare >= 0.4 ? 'degraded' : ($staleShare > 0 ? 'watch' : 'normal'),
            'value' => round($staleShare * 100, 1) . '%',
            'detail' => 'Stale data can down-rank markets and block execution feasibility.',
        ],
        [
            'name' => 'False-confidence detection',
            'status' => $highReadinessLowCalibration > 0 ? 'watch' : 'quiet',
            'value' => (string) $highReadinessLowCalibration . ' layers',
            'detail' => 'Flags high-scoring layers that still carry high uncertainty.',
        ],
        [
            'name' => 'Signal-failure clustering',
            'status' => $closed >= 250 ? 'candidate_ready' : 'collecting_labels',
            'value' => (string) $closed . ' outcomes',
            'detail' => 'Clusters failed signals only after enough replay-safe outcomes exist.',
        ],
        [
            'name' => 'Anomaly detection',
            'status' => count($triggers) > 0 ? 'triggered_watch' : 'quiet_watch',
            'value' => (string) count($triggers) . ' triggers',
            'detail' => 'Uses observability and market-regime alerts to detect unreliable conditions.',
        ],
    ];

    $problemCount = count(array_filter($monitors, static fn(array $monitor): bool => in_array((string) ($monitor['status'] ?? ''), ['degraded', 'watch', 'triggered_watch'], true)));

    return [
        'status' => $problemCount > 0 ? 'self_evaluating_watch' : 'self_evaluating',
        'problemCount' => $problemCount,
        'monitors' => $monitors,
        'question' => 'Where is Lineforge becoming unreliable?',
    ];
}

function lineforge_adaptive_resilience(array $composition, array $evolution, array $warehouse): array
{
    $workerStatus = (string) ($warehouse['operational']['worker']['status'] ?? 'not_started');
    $driver = (string) ($warehouse['driver'] ?? 'unknown');
    $regime = (string) ($evolution['summary']['activeRegime'] ?? 'unknown');
    $staleShare = (float) ($evolution['marketStructure']['metrics']['staleShare'] ?? 0);

    $systems = [
        ['name' => 'Graceful degradation', 'status' => $regime === 'public_context_only' ? 'active' : 'armed', 'value' => str_replace('_', ' ', $regime), 'detail' => 'Public intelligence mode continues when premium/live feeds are unavailable.'],
        ['name' => 'Storage fallback', 'status' => $driver === 'jsonl_fallback' ? 'fallback_active' : 'primary_active', 'value' => str_replace('_', ' ', $driver), 'detail' => 'JSONL fallback keeps replay and audit timelines alive until SQLite/Postgres is enabled.'],
        ['name' => 'Worker continuity', 'status' => $workerStatus === 'ready' ? 'ready' : 'needs_scheduler', 'value' => str_replace('_', ' ', $workerStatus), 'detail' => 'Background ingestion should remain scheduler-driven instead of page-load dependent.'],
        ['name' => 'Stale-data isolation', 'status' => $staleShare >= 0.25 ? 'active' : 'armed', 'value' => round($staleShare * 100, 1) . '% stale', 'detail' => 'Stale markets are isolated before they influence execution or model promotion.'],
        ['name' => 'Provider fallback orchestration', 'status' => 'armed', 'value' => (string) lineforge_adaptive_count($warehouse, 'providerHealthRows') . ' health rows', 'detail' => 'Provider-specific failure should reduce trust locally rather than collapse the workspace.'],
    ];

    return [
        'status' => (string) ($composition['status'] ?? 'collecting_memory'),
        'systems' => $systems,
        'message' => 'Phase 4 resilience keeps the platform useful when specific feeds, workers, or intelligence layers degrade.',
    ];
}

function lineforge_adaptive_explanations(array $layers, array $composition, array $evolution, array $historicalPatterns): array
{
    $explanations = [];
    $layerById = [];
    foreach ($layers as $layer) {
        $layerById[(string) ($layer['id'] ?? '')] = $layer;
    }

    $probability = (array) ($layerById['probability_estimation'] ?? []);
    $liquidity = (array) ($layerById['liquidity_intelligence'] ?? []);
    $provider = (array) ($layerById['provider_reliability'] ?? []);
    $historical = (array) ($layerById['historical_similarity'] ?? []);

    $explanations[] = [
        'title' => 'Why the adaptive network is conservative',
        'status' => (string) ($composition['status'] ?? 'collecting_memory'),
        'reason' => (string) ($composition['message'] ?? 'The network is collecting support before adaptive decisions are promoted.'),
        'impact' => 'Lineforge can coordinate layers and display research context, but it avoids model promotion or live execution claims while uncertainty is high.',
    ];
    $explanations[] = [
        'title' => 'Why probability remains evidence-gated',
        'status' => (string) ($probability['status'] ?? 'evidence_gated'),
        'reason' => (string) ($probability['measurement'] ?? 'Closed labels are sparse.'),
        'impact' => 'The transparent baseline stays active until enough outcomes support replay-safe statistical validation.',
    ];
    $explanations[] = [
        'title' => 'Why execution feasibility can fail independently',
        'status' => (string) ($liquidity['status'] ?? 'data_sparse'),
        'reason' => (string) ($liquidity['detail'] ?? 'Liquidity intelligence is sparse.'),
        'impact' => 'A signal can remain visible for research while execution remains blocked or manual-only.',
    ];
    $explanations[] = [
        'title' => 'Why provider reliability affects trust',
        'status' => (string) ($provider['status'] ?? 'waiting'),
        'reason' => (string) ($provider['measurement'] ?? 'Provider health history is still building.'),
        'impact' => 'Provider degradation reduces data trust locally rather than creating a single platform-wide failure.',
    ];
    $explanations[] = [
        'title' => 'Why historical similarity is still advisory',
        'status' => (string) ($historical['status'] ?? 'collecting_memory'),
        'reason' => (string) ($historical['measurement'] ?? ($historicalPatterns['status'] ?? 'collecting memory')),
        'impact' => 'Similarity can support explanations later, but it cannot become an accuracy claim without outcome validation.',
    ];

    if (!empty($evolution['marketStructure']['detail'])) {
        $explanations[] = [
            'title' => 'Current market-state explanation',
            'status' => (string) ($evolution['summary']['activeRegime'] ?? 'unknown'),
            'reason' => (string) $evolution['marketStructure']['detail'],
            'impact' => 'The active market regime influences signal visibility, execution feasibility, and model weighting.',
        ];
    }

    return $explanations;
}

function lineforge_adaptive_record_snapshot(array $layers, array $composition, array $selfEvaluation, array $resilience): void
{
    lineforge_warehouse_append_once('warehouse-adaptive-network', [
        'type' => 'adaptive_network_snapshot',
        'status' => (string) ($composition['status'] ?? 'unknown'),
        'compositeReadiness' => (float) ($composition['compositeReadiness'] ?? 0),
        'averageUncertainty' => (float) ($composition['averageUncertainty'] ?? 0),
        'layers' => array_map(static fn(array $layer): array => [
            'id' => (string) ($layer['id'] ?? ''),
            'score' => (float) ($layer['score'] ?? 0),
            'uncertainty' => (float) ($layer['uncertainty'] ?? 0),
            'status' => (string) ($layer['status'] ?? ''),
        ], $layers),
        'selfEvaluationStatus' => (string) ($selfEvaluation['status'] ?? 'unknown'),
        'resilienceStatus' => (string) ($resilience['status'] ?? 'unknown'),
    ], 240);
}

function lineforge_adaptive_build_state(array $evolution, array $warehouse, array $calibration, array $marketInference, array $arbitrage): array
{
    $layers = lineforge_adaptive_layers($evolution, $warehouse, $calibration, $marketInference, $arbitrage);
    $composition = lineforge_adaptive_composition($layers, $evolution, $warehouse);
    $marketBehavior = lineforge_adaptive_market_behavior($evolution, $marketInference, $warehouse);
    $historicalPatterns = lineforge_adaptive_historical_patterns($warehouse);
    $operatorMemory = lineforge_adaptive_operator_memory();
    $selfEvaluation = lineforge_adaptive_self_evaluation($layers, $evolution, $warehouse, $calibration);
    $resilience = lineforge_adaptive_resilience($composition, $evolution, $warehouse);
    $explanations = lineforge_adaptive_explanations($layers, $composition, $evolution, $historicalPatterns);
    lineforge_adaptive_record_snapshot($layers, $composition, $selfEvaluation, $resilience);

    return [
        'summary' => [
            'status' => 'adaptive_infrastructure',
            'networkStatus' => (string) ($composition['status'] ?? 'collecting_memory'),
            'compositeReadiness' => (float) ($composition['compositeReadiness'] ?? 0),
            'layers' => count($layers),
            'highUncertaintyLayers' => (int) ($composition['highUncertaintyLayers'] ?? 0),
            'degradedLayers' => (int) ($composition['degradedLayers'] ?? 0),
            'selfEvaluationStatus' => (string) ($selfEvaluation['status'] ?? 'unknown'),
            'resilienceStatus' => (string) ($resilience['status'] ?? 'unknown'),
            'explanations' => count($explanations),
        ],
        'layers' => $layers,
        'composition' => $composition,
        'marketBehavior' => $marketBehavior,
        'historicalPatterns' => $historicalPatterns,
        'operatorMemory' => $operatorMemory,
        'selfEvaluation' => $selfEvaluation,
        'resilience' => $resilience,
        'explanations' => $explanations,
        'principles' => [
            'independent layers over one prediction engine',
            'uncertainty exposed per layer',
            'failure isolation before orchestration',
            'historical similarity as explanation before prediction',
            'self-evaluation before model promotion',
            'degraded-mode operation as a first-class state',
        ],
    ];
}
