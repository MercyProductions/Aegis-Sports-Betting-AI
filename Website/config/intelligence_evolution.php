<?php

require_once __DIR__ . '/intelligence_warehouse.php';

function lineforge_evolution_number($value, float $fallback = 0.0): float
{
    if (is_numeric($value)) {
        return (float) $value;
    }

    $clean = preg_replace('/[^0-9.\-]+/', '', (string) $value);
    return is_numeric($clean) ? (float) $clean : $fallback;
}

function lineforge_evolution_clamp(float $value, float $min = 0.0, float $max = 100.0): float
{
    return max($min, min($max, $value));
}

function lineforge_evolution_game_map(array $games): array
{
    $map = [];
    foreach ($games as $game) {
        $id = (string) ($game['id'] ?? '');
        if ($id !== '') {
            $map[$id] = $game;
        }
    }

    return $map;
}

function lineforge_evolution_feature_rows(array $games, array $predictions, array $marketAccess, array $arbitrage, array $marketInference): array
{
    $gamesById = lineforge_evolution_game_map($games);
    $oddsRows = is_array($arbitrage['normalizedOdds'] ?? null) ? (array) $arbitrage['normalizedOdds'] : [];
    $oddsByEvent = [];
    foreach ($oddsRows as $row) {
        $eventId = (string) ($row['eventId'] ?? '');
        if ($eventId !== '') {
            $oddsByEvent[$eventId][] = $row;
        }
    }

    $volatility = (float) ($marketInference['volatilityScore'] ?? 0);
    $disagreement = (int) ($marketInference['marketDisagreement']['flaggedOutcomes'] ?? 0);
    $rapidMoves = (int) ($marketInference['lineVelocity']['rapidMoves'] ?? 0);
    $rows = [];

    foreach (array_slice($predictions, 0, 120) as $prediction) {
        $gameId = (string) ($prediction['gameId'] ?? '');
        $game = (array) ($gamesById[$gameId] ?? []);
        $eventOdds = (array) ($oddsByEvent[$gameId] ?? []);
        $dataQuality = (array) ($prediction['dataQuality'] ?? []);
        $links = is_array($prediction['marketLinks'] ?? null) ? (array) $prediction['marketLinks'] : [];
        $liveLinks = count(array_filter($links, static fn(array $link): bool => !empty($link['available'])));
        $startTime = strtotime((string) ($game['startTime'] ?? $prediction['startTime'] ?? '')) ?: 0;
        $minutesToStart = $startTime > 0 ? (int) round(($startTime - time()) / 60) : null;
        $statusKey = strtolower((string) ($game['statusKey'] ?? $prediction['statusKey'] ?? 'scheduled'));
        $confidence = (int) lineforge_evolution_number($prediction['confidenceValue'] ?? $prediction['confidence'] ?? 0);
        $featureRow = [
            'rowId' => substr(hash('sha256', $gameId . '|' . ($prediction['market'] ?? '') . '|' . ($prediction['pick'] ?? '')), 0, 16),
            'gameId' => $gameId,
            'matchup' => (string) ($prediction['matchup'] ?? $game['matchup'] ?? ''),
            'league' => (string) ($prediction['league'] ?? $game['league'] ?? ''),
            'statusKey' => $statusKey,
            'market' => (string) ($prediction['market'] ?? ''),
            'pick' => (string) ($prediction['pick'] ?? ''),
            'features' => [
                'confidence_estimate' => lineforge_evolution_clamp($confidence),
                'data_quality_score' => lineforge_evolution_clamp((float) ($dataQuality['score'] ?? 0)),
                'data_quality_cap' => lineforge_evolution_clamp((float) ($dataQuality['confidenceCap'] ?? 0)),
                'market_link_count' => count($links),
                'available_market_links' => $liveLinks,
                'normalized_odds_rows' => count($eventOdds),
                'available_lines' => (int) ($marketAccess['availableLines'] ?? 0),
                'bookmakers' => (int) ($marketAccess['bookmakers'] ?? 0),
                'volatility_score' => lineforge_evolution_clamp($volatility),
                'market_disagreement_flags' => $disagreement,
                'rapid_move_flags' => $rapidMoves,
                'edge_percent' => lineforge_evolution_number($prediction['edge'] ?? 0),
                'is_live' => $statusKey === 'live' ? 1 : 0,
                'is_final' => $statusKey === 'final' ? 1 : 0,
                'minutes_to_start' => $minutesToStart,
            ],
            'label' => [
                'available' => $statusKey === 'final',
                'source' => $statusKey === 'final' ? 'final_score_mapping_required' : 'open_event',
            ],
        ];
        $rows[] = $featureRow;
    }

    return $rows;
}

function lineforge_evolution_model_readiness(array $featureRows, array $calibration, array $warehouse): array
{
    $closedSamples = (int) ($calibration['closedSamples'] ?? 0);
    $featureRowCount = count($featureRows);
    $warehousePredictions = (int) ($warehouse['counts']['predictions'] ?? 0);
    $trainableRows = $closedSamples;

    $model = static function (string $name, string $family, int $minimumRows, array $features, string $purpose) use ($trainableRows): array {
        $ready = $trainableRows >= $minimumRows;
        return [
            'name' => $name,
            'family' => $family,
            'status' => $ready ? 'ready_for_training' : 'data_gated',
            'minimumClosedRows' => $minimumRows,
            'currentClosedRows' => $trainableRows,
            'purpose' => $purpose,
            'features' => $features,
            'message' => $ready
                ? 'Enough closed samples exist to train and validate this model family.'
                : 'Blocked until enough closed, replayable outcomes exist. No fake model accuracy is shown.',
        ];
    };

    return [
        'status' => $trainableRows >= 100 ? 'training_ready' : 'collecting_labels',
        'featureRows' => $featureRowCount,
        'warehousePredictionRows' => $warehousePredictions,
        'trainableRows' => $trainableRows,
        'featureCount' => $featureRows ? count((array) ($featureRows[0]['features'] ?? [])) : 0,
        'activeBaseline' => [
            'name' => 'transparent_heuristic_v1',
            'status' => 'active_baseline',
            'message' => 'Current production estimates remain transparent heuristic scores until validated statistical models have enough labels.',
        ],
        'candidates' => [
            $model('logistic_regression_v0', 'probabilistic_linear', 100, ['confidence_estimate', 'data_quality_score', 'available_market_links', 'volatility_score', 'edge_percent'], 'Transparent first statistical model with interpretable coefficients.'),
            $model('gradient_boosting_v0', 'tree_boosting', 500, ['confidence_estimate', 'data_quality_score', 'normalized_odds_rows', 'market_disagreement_flags', 'rapid_move_flags', 'minutes_to_start'], 'Nonlinear market and data-quality interaction model.'),
            $model('xgboost_lightgbm_adapter', 'boosted_trees_future', 1000, ['full_feature_set', 'market_timing', 'provider_reliability', 'calibration_history'], 'Future high-performance training path once the warehouse has enough labeled history.'),
        ],
        'validationPlan' => [
            'rolling training windows',
            'train/test split by event date',
            'replay evaluation only with information available at prediction time',
            'Brier score and calibration curve reporting',
            'feature importance review before deployment',
            'model version and inference audit logging',
        ],
    ];
}

function lineforge_evolution_market_structure(array $arbitrage, array $marketInference, array $warehouse): array
{
    $summary = (array) ($arbitrage['summary'] ?? []);
    $oddsRows = is_array($arbitrage['normalizedOdds'] ?? null) ? (array) $arbitrage['normalizedOdds'] : [];
    $averageAge = $summary['averageFreshnessSeconds'] ?? null;
    $staleRows = count(array_filter($oddsRows, static fn(array $row): bool => (int) ($row['ageSeconds'] ?? 99999) > 180));
    $staleShare = $oddsRows ? ($staleRows / count($oddsRows)) : 0.0;
    $volatility = (float) ($marketInference['volatilityScore'] ?? 0);
    $disagreementFlags = (int) ($marketInference['marketDisagreement']['flaggedOutcomes'] ?? 0);
    $rapidMoves = (int) ($marketInference['lineVelocity']['rapidMoves'] ?? 0);
    $connectedBooks = (int) ($summary['connectedBooks'] ?? 0);

    if (!$oddsRows) {
        $regime = 'public_context_only';
        $detail = 'No normalized sportsbook odds are available, so Lineforge is operating in public intelligence mode.';
    } elseif ($staleShare >= 0.4) {
        $regime = 'stale_data_risk';
        $detail = 'A large share of odds rows are stale; market interpretation should be conservative.';
    } elseif ($volatility >= 65 || $rapidMoves >= 3) {
        $regime = 'high_volatility';
        $detail = 'Rapid movement and volatility require tighter stale-data penalties and manual review.';
    } elseif ($disagreementFlags > 0) {
        $regime = 'provider_disagreement';
        $detail = 'Provider disagreement is active; compare book-specific context before treating consensus as stable.';
    } else {
        $regime = 'stable_watch';
        $detail = 'No major volatility or disagreement conditions are active in the current snapshot.';
    }

    return [
        'activeRegime' => $regime,
        'detail' => $detail,
        'classifications' => [
            ['name' => 'Volatility', 'value' => round($volatility, 1) . '/100', 'status' => $volatility >= 65 ? 'elevated' : ($volatility >= 35 ? 'watch' : 'normal')],
            ['name' => 'Provider agreement', 'value' => (string) $disagreementFlags . ' flags', 'status' => $disagreementFlags > 0 ? 'disagreement' : 'stable'],
            ['name' => 'Stale-line risk', 'value' => round($staleShare * 100, 1) . '%', 'status' => $staleShare >= 0.4 ? 'high' : ($staleShare > 0 ? 'watch' : 'low')],
            ['name' => 'Connected books', 'value' => (string) $connectedBooks, 'status' => $connectedBooks >= 2 ? 'comparable' : 'thin'],
            ['name' => 'Replay memory', 'value' => (string) ($warehouse['counts']['lineMovementRows'] ?? 0) . ' rows', 'status' => ((int) ($warehouse['counts']['lineMovementRows'] ?? 0)) > 0 ? 'building' : 'waiting'],
        ],
        'metrics' => [
            'averageOddsAgeSeconds' => $averageAge,
            'staleShare' => round($staleShare, 3),
            'rapidMoves' => $rapidMoves,
            'disagreementFlags' => $disagreementFlags,
            'volatilityScore' => round($volatility, 1),
        ],
        'nextModels' => [
            'market regime classifier',
            'volatility clustering detector',
            'provider latency profile',
            'consensus drift tracker',
            'stale-line anomaly detector',
        ],
    ];
}

function lineforge_evolution_timing_sensitivity(array $game): float
{
    $statusKey = strtolower((string) ($game['statusKey'] ?? 'scheduled'));
    if ($statusKey === 'live') {
        return 88;
    }
    if ($statusKey === 'final') {
        return 8;
    }

    $start = strtotime((string) ($game['startTime'] ?? '')) ?: 0;
    if ($start <= 0) {
        return 45;
    }

    $minutes = ($start - time()) / 60;
    if ($minutes < 0) {
        return 70;
    }
    if ($minutes <= 30) {
        return 82;
    }
    if ($minutes <= 180) {
        return 62;
    }

    return 38;
}

function lineforge_evolution_signal_objects(array $featureRows, array $games, array $marketStructure, array $calibration): array
{
    $gamesById = lineforge_evolution_game_map($games);
    $closed = (int) ($calibration['closedSamples'] ?? 0);
    $calibrationError = lineforge_evolution_number($calibration['calibrationError'] ?? 35, 35);
    $calibrationConfidence = $closed >= 100
        ? lineforge_evolution_clamp(78 - $calibrationError)
        : lineforge_evolution_clamp(10 + ($closed / 2), 8, 45);
    $volatilityScore = (float) ($marketStructure['metrics']['volatilityScore'] ?? 0);
    $disagreement = (int) ($marketStructure['metrics']['disagreementFlags'] ?? 0);
    $staleShare = (float) ($marketStructure['metrics']['staleShare'] ?? 0);

    $signals = [];
    foreach (array_slice($featureRows, 0, 12) as $row) {
        $features = (array) ($row['features'] ?? []);
        $game = (array) ($gamesById[(string) ($row['gameId'] ?? '')] ?? []);
        $providerAgreement = lineforge_evolution_clamp(100 - ($disagreement * 12) - ($staleShare * 45));
        $liquidity = lineforge_evolution_clamp(((float) ($features['normalized_odds_rows'] ?? 0) * 5) + ((float) ($features['available_market_links'] ?? 0) * 12) + ((float) ($features['available_lines'] ?? 0) * 0.8), 5, 100);
        $movementStability = lineforge_evolution_clamp(100 - $volatilityScore - ((float) ($features['rapid_move_flags'] ?? 0) * 8));
        $timing = lineforge_evolution_timing_sensitivity($game);
        $executionFeasibility = lineforge_evolution_clamp(25 + ($liquidity * 0.28) + ($providerAgreement * 0.2) + ($movementStability * 0.12), 10, 82);
        $marketPressure = lineforge_evolution_clamp($volatilityScore + ((float) ($features['rapid_move_flags'] ?? 0) * 7) + ($disagreement * 5));
        $readiness = lineforge_evolution_clamp(
            ((float) ($features['confidence_estimate'] ?? 0) * 0.18)
            + ((float) ($features['data_quality_score'] ?? 0) * 0.18)
            + ($providerAgreement * 0.14)
            + ($liquidity * 0.11)
            + ($calibrationConfidence * 0.16)
            + ($movementStability * 0.12)
            + ($executionFeasibility * 0.11)
        );

        $signals[] = [
            'id' => (string) ($row['rowId'] ?? ''),
            'name' => (string) ($row['matchup'] ?? 'Signal'),
            'market' => (string) ($row['market'] ?? ''),
            'pick' => (string) ($row['pick'] ?? ''),
            'readinessScore' => round($readiness, 1),
            'regime' => (string) ($marketStructure['activeRegime'] ?? 'unknown'),
            'dimensions' => [
                ['name' => 'Research confidence', 'score' => round((float) ($features['confidence_estimate'] ?? 0), 1), 'detail' => 'Transparent baseline estimate, not a promise.'],
                ['name' => 'Volatility', 'score' => round($volatilityScore, 1), 'detail' => 'Market turbulence and rapid movement pressure.'],
                ['name' => 'Provider agreement', 'score' => round($providerAgreement, 1), 'detail' => 'Cross-provider agreement after stale-data penalties.'],
                ['name' => 'Data quality', 'score' => round((float) ($features['data_quality_score'] ?? 0), 1), 'detail' => 'Source depth, freshness, injuries, and market context.'],
                ['name' => 'Liquidity', 'score' => round($liquidity, 1), 'detail' => 'Available lines and normalized odds depth.'],
                ['name' => 'Calibration confidence', 'score' => round($calibrationConfidence, 1), 'detail' => 'How much closed history supports the estimate.'],
                ['name' => 'Movement stability', 'score' => round($movementStability, 1), 'detail' => 'Penalty for rapid movement and volatile regimes.'],
                ['name' => 'Timing sensitivity', 'score' => round($timing, 1), 'detail' => 'Live/near-start events are more fragile.'],
                ['name' => 'Execution feasibility', 'score' => round($executionFeasibility, 1), 'detail' => 'Paper/manual feasibility after liquidity and provider checks.'],
                ['name' => 'Market pressure', 'score' => round($marketPressure, 1), 'detail' => 'Movement, volatility, and disagreement pressure.'],
            ],
            'explanation' => 'Signal object combines confidence, data quality, market structure, calibration support, and execution feasibility. It is designed for review, not blind action.',
        ];
    }

    return $signals;
}

function lineforge_evolution_triggers(array $marketStructure, array $warehouse, array $arbitrage): array
{
    $metrics = (array) ($marketStructure['metrics'] ?? []);
    $summary = (array) ($arbitrage['summary'] ?? []);
    $triggers = [];
    if ((float) ($metrics['staleShare'] ?? 0) >= 0.25) {
        $triggers[] = ['name' => 'Stale data watch', 'severity' => 'warning', 'detail' => 'A meaningful share of odds rows are stale. Execution and EV review should require recheck.'];
    }
    if ((float) ($metrics['volatilityScore'] ?? 0) >= 65) {
        $triggers[] = ['name' => 'Volatility threshold', 'severity' => 'warning', 'detail' => 'Market volatility exceeded the Phase 3 watch threshold.'];
    }
    if ((int) ($metrics['disagreementFlags'] ?? 0) > 0) {
        $triggers[] = ['name' => 'Provider disagreement', 'severity' => 'info', 'detail' => 'Cross-provider disagreement is active and should be investigated before using consensus.'];
    }
    if (($summary['providerHealth'] ?? '') !== 'operational' && ($summary['providerHealth'] ?? '') !== '') {
        $triggers[] = ['name' => 'Provider health watch', 'severity' => 'warning', 'detail' => 'Provider health is not fully operational. Market intelligence should be degraded.'];
    }
    if (empty($warehouse['replay']['available'])) {
        $triggers[] = ['name' => 'Replay memory warming', 'severity' => 'info', 'detail' => 'Historical replay needs more warehouse snapshots before backtesting becomes useful.'];
    }

    return $triggers;
}

function lineforge_evolution_observability(array $warehouse, array $marketStructure, int $refreshSeconds): array
{
    $counts = (array) ($warehouse['counts'] ?? []);
    $workerStatus = (string) ($warehouse['operational']['worker']['status'] ?? 'not_started');
    $staleShare = (float) ($marketStructure['metrics']['staleShare'] ?? 0);
    $health = $workerStatus === 'ready' && $staleShare < 0.4 ? 'operational_watch' : 'degraded_watch';

    return [
        'status' => $health,
        'monitors' => [
            ['name' => 'Ingestion runs', 'value' => (string) ($counts['ingestionRuns'] ?? 0), 'status' => ((int) ($counts['ingestionRuns'] ?? 0)) > 0 ? 'active' : 'waiting'],
            ['name' => 'Worker', 'value' => str_replace('_', ' ', $workerStatus), 'status' => $workerStatus === 'ready' ? 'active' : 'waiting'],
            ['name' => 'Stale odds', 'value' => round($staleShare * 100, 1) . '%', 'status' => $staleShare >= 0.4 ? 'warning' : 'normal'],
            ['name' => 'Refresh cadence', 'value' => max(30, $refreshSeconds) . 's', 'status' => 'configured'],
            ['name' => 'Replay', 'value' => str_replace('_', ' ', (string) ($warehouse['replay']['status'] ?? 'waiting')), 'status' => !empty($warehouse['replay']['available']) ? 'active' : 'waiting'],
        ],
        'message' => 'Phase 3 observability watches ingestion, workers, stale data, replay readiness, and market regime health.',
    ];
}

function lineforge_evolution_record_feature_snapshot(array $featureRows, array $marketStructure, array $signals, array $observability): void
{
    if (!$featureRows && !$signals) {
        return;
    }

    lineforge_warehouse_append_once('warehouse-feature-pipeline', [
        'type' => 'feature_pipeline_snapshot',
        'featureRows' => count($featureRows),
        'signalObjects' => count($signals),
        'activeRegime' => (string) ($marketStructure['activeRegime'] ?? 'unknown'),
        'observabilityStatus' => (string) ($observability['status'] ?? 'unknown'),
        'rows' => array_slice($featureRows, 0, 80),
    ], 240);
}

function lineforge_evolution_build_state(array $games, array $predictions, array $marketAccess, array $arbitrage, array $warehouse, array $calibration, array $marketInference, int $refreshSeconds): array
{
    $featureRows = lineforge_evolution_feature_rows($games, $predictions, $marketAccess, $arbitrage, $marketInference);
    $modelReadiness = lineforge_evolution_model_readiness($featureRows, $calibration, $warehouse);
    $marketStructure = lineforge_evolution_market_structure($arbitrage, $marketInference, $warehouse);
    $signalObjects = lineforge_evolution_signal_objects($featureRows, $games, $marketStructure, $calibration);
    $triggers = lineforge_evolution_triggers($marketStructure, $warehouse, $arbitrage);
    $observability = lineforge_evolution_observability($warehouse, $marketStructure, $refreshSeconds);
    lineforge_evolution_record_feature_snapshot($featureRows, $marketStructure, $signalObjects, $observability);

    return [
        'summary' => [
            'status' => 'transparent_learning_layer',
            'featureRows' => count($featureRows),
            'trainableRows' => (int) ($modelReadiness['trainableRows'] ?? 0),
            'featureCount' => (int) ($modelReadiness['featureCount'] ?? 0),
            'modelReadiness' => (string) ($modelReadiness['status'] ?? 'collecting_labels'),
            'activeRegime' => (string) ($marketStructure['activeRegime'] ?? 'unknown'),
            'signalObjects' => count($signalObjects),
            'triggerCount' => count($triggers),
            'observabilityStatus' => (string) ($observability['status'] ?? 'unknown'),
        ],
        'featurePipeline' => [
            'status' => count($featureRows) > 0 ? 'extracting_features' : 'waiting_for_predictions',
            'rows' => array_slice($featureRows, 0, 40),
            'schema' => [
                'confidence_estimate',
                'data_quality_score',
                'market_link_count',
                'available_market_links',
                'normalized_odds_rows',
                'available_lines',
                'bookmakers',
                'volatility_score',
                'market_disagreement_flags',
                'rapid_move_flags',
                'edge_percent',
                'status flags',
                'minutes_to_start',
            ],
            'message' => 'Feature extraction is active. Statistical model training remains blocked until enough closed labels exist.',
        ],
        'modelReadiness' => $modelReadiness,
        'marketStructure' => $marketStructure,
        'signals' => $signalObjects,
        'triggers' => $triggers,
        'observability' => $observability,
        'researchLab' => [
            'status' => !empty($warehouse['replay']['available']) ? 'replay_ready' : 'collecting_memory',
            'capabilities' => [
                ['name' => 'Replay engine', 'status' => (string) ($warehouse['replay']['status'] ?? 'waiting_for_snapshots'), 'detail' => 'Reconstruct what the system knew at a selected snapshot.'],
                ['name' => 'Backtesting', 'status' => ((int) ($modelReadiness['trainableRows'] ?? 0)) >= 100 ? 'ready_for_first_tests' : 'data_gated', 'detail' => 'Requires closed samples and replay-safe features.'],
                ['name' => 'Model comparison', 'status' => 'data_gated', 'detail' => 'Logistic regression is the first candidate once closed labels exist.'],
                ['name' => 'Calibration studio', 'status' => (string) ($calibration['status'] ?? 'collecting_baseline'), 'detail' => (string) ($calibration['message'] ?? 'Collecting closed samples.')],
                ['name' => 'Operator annotations', 'status' => 'planned', 'detail' => 'Future research notes should become model features only after audit review.'],
            ],
        ],
        'memory' => [
            'warehouseCounts' => (array) ($warehouse['counts'] ?? []),
            'providerReliability' => (array) ($warehouse['providerReliability'] ?? []),
            'knownWeaknesses' => [
                'Closed labels are still sparse, so model training is gated.',
                'SQLite is unavailable in the current PHP runtime, so JSONL fallback is the active warehouse.',
                'Provider odds depth depends on configured official/licensed feeds.',
                'Sharp-money language remains inference-only unless directly sourced.',
            ],
        ],
        'philosophy' => [
            'transparency over hype',
            'calibration over certainty',
            'governance over automation',
            'workflows over picks',
            'operator tooling over gambling entertainment',
            'institutional discipline over retail emotion',
        ],
    ];
}
