<?php

function lineforge_warehouse_path(): string
{
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir . '/lineforge-intelligence.sqlite';
}

function lineforge_warehouse_sqlite_available(): bool
{
    return class_exists('SQLite3');
}

function lineforge_warehouse_default_state(string $status = 'sqlite_unavailable'): array
{
    return [
        'available' => false,
        'driver' => lineforge_warehouse_sqlite_available() ? 'sqlite3' : 'unavailable',
        'status' => $status,
        'path' => lineforge_warehouse_path(),
        'retentionDays' => 180,
        'dedupePolicy' => 'run hash plus row hash',
        'message' => lineforge_warehouse_sqlite_available()
            ? 'SQLite is available but no warehouse cycle has been recorded yet.'
            : 'SQLite is not enabled in this PHP runtime. Normalized JSONL warehouse timelines remain active as fallback storage.',
        'counts' => [
            'ingestionRuns' => 0,
            'games' => 0,
            'oddsRows' => 0,
            'predictions' => 0,
            'providerHealthRows' => 0,
            'volatilityRows' => 0,
            'lineMovementRows' => 0,
            'featurePipelineRows' => 0,
            'adaptiveNetworkRows' => 0,
            'generalizedCoreRows' => 0,
            'injuryNewsRows' => 0,
        ],
        'latestRunAt' => '',
        'calibration' => lineforge_warehouse_empty_calibration(),
        'replay' => [
            'available' => false,
            'status' => 'waiting_for_snapshots',
            'message' => 'Replay mode activates after warehouse snapshots exist.',
            'latestSnapshotAt' => '',
            'supportedQueries' => ['event_timeline', 'market_timeline', 'prediction_replay', 'provider_health_history'],
        ],
        'operational' => [
            'worker' => [
                'status' => 'not_started',
                'message' => 'Run Website/tools/lineforge-worker.php to collect intelligence outside page-load requests.',
            ],
            'storageMode' => lineforge_warehouse_sqlite_available() ? 'sqlite' : 'jsonl_fallback',
            'retentionPolicy' => '180 day hot warehouse, compact daily JSONL timelines, database-ready schema.',
        ],
    ];
}

function lineforge_warehouse_open()
{
    if (!lineforge_warehouse_sqlite_available()) {
        return null;
    }

    try {
        $db = new SQLite3(lineforge_warehouse_path());
        $db->busyTimeout(1500);
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA foreign_keys = ON');
        lineforge_warehouse_migrate($db);
        return $db;
    } catch (Throwable $_error) {
        return null;
    }
}

function lineforge_warehouse_migrate($db): void
{
    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS ingestion_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    run_key TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL,
    source_hash TEXT NOT NULL,
    game_count INTEGER NOT NULL DEFAULT 0,
    odds_count INTEGER NOT NULL DEFAULT 0,
    prediction_count INTEGER NOT NULL DEFAULT 0,
    provider_health_count INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'recorded'
);
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS game_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    row_hash TEXT NOT NULL UNIQUE,
    snapshot_at TEXT NOT NULL,
    game_id TEXT NOT NULL,
    league TEXT NOT NULL DEFAULT '',
    status_key TEXT NOT NULL DEFAULT '',
    start_time TEXT NOT NULL DEFAULT '',
    matchup TEXT NOT NULL DEFAULT '',
    payload_json TEXT NOT NULL
);
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS odds_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    row_hash TEXT NOT NULL UNIQUE,
    snapshot_at TEXT NOT NULL,
    provider_key TEXT NOT NULL DEFAULT '',
    event_id TEXT NOT NULL DEFAULT '',
    market_type TEXT NOT NULL DEFAULT '',
    line_key TEXT NOT NULL DEFAULT '',
    selection_key TEXT NOT NULL DEFAULT '',
    decimal_odds REAL NOT NULL DEFAULT 0,
    implied_probability REAL NOT NULL DEFAULT 0,
    age_seconds INTEGER,
    payload_json TEXT NOT NULL
);
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS prediction_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    row_hash TEXT NOT NULL UNIQUE,
    snapshot_at TEXT NOT NULL,
    game_id TEXT NOT NULL DEFAULT '',
    matchup TEXT NOT NULL DEFAULT '',
    market TEXT NOT NULL DEFAULT '',
    pick TEXT NOT NULL DEFAULT '',
    confidence INTEGER NOT NULL DEFAULT 0,
    data_quality INTEGER NOT NULL DEFAULT 0,
    status_key TEXT NOT NULL DEFAULT '',
    payload_json TEXT NOT NULL
);
SQL);

    $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS provider_health_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    row_hash TEXT NOT NULL UNIQUE,
    snapshot_at TEXT NOT NULL,
    provider_key TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT '',
    payload_json TEXT NOT NULL
);
SQL);
}

function lineforge_warehouse_row_hash(string $scope, array $row): string
{
    return hash('sha256', $scope . '|' . json_encode($row, JSON_UNESCAPED_SLASHES));
}

function lineforge_warehouse_empty_calibration(): array
{
    return [
        'status' => 'collecting_baseline',
        'sampleCount' => 0,
        'closedSamples' => 0,
        'minimumClosedSamples' => 100,
        'brierScore' => null,
        'hitRate' => null,
        'averageConfidence' => null,
        'calibrationError' => null,
        'driftStatus' => 'insufficient_samples',
        'expectedValueStatus' => 'waiting_for_closed_samples',
        'message' => 'Collecting closed historical predictions before showing accuracy or calibration claims.',
        'buckets' => [],
    ];
}

function lineforge_warehouse_jsonl_dir(string $scope = ''): string
{
    $base = dirname(__DIR__) . '/storage/intelligence';
    $safeScope = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(trim($scope))) ?: '';
    $dir = $safeScope !== '' ? $base . '/' . $safeScope : $base;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return is_dir($dir) ? $dir : dirname(__DIR__) . '/storage';
}

function lineforge_warehouse_jsonl_path(string $scope, ?string $date = null): string
{
    return lineforge_warehouse_jsonl_dir($scope) . '/' . ($date ?: gmdate('Y-m-d')) . '.jsonl';
}

function lineforge_warehouse_append_once(string $scope, array $record, int $ttlSeconds = 180): bool
{
    $dir = lineforge_warehouse_jsonl_dir($scope);
    $fingerprintPath = $dir . '/.last_snapshot.json';
    $hash = hash('sha256', json_encode($record, JSON_UNESCAPED_SLASHES));
    $last = is_file($fingerprintPath) ? json_decode((string) @file_get_contents($fingerprintPath), true) : [];
    if (
        is_array($last)
        && (string) ($last['hash'] ?? '') === $hash
        && (time() - (int) ($last['time'] ?? 0)) < max(30, $ttlSeconds)
    ) {
        return false;
    }

    $record['snapshotAt'] = $record['snapshotAt'] ?? gmdate('c');
    $line = json_encode($record, JSON_UNESCAPED_SLASHES) . "\n";
    $written = @file_put_contents(lineforge_warehouse_jsonl_path($scope), $line, FILE_APPEND | LOCK_EX) !== false;
    if ($written) {
        @file_put_contents($fingerprintPath, json_encode(['hash' => $hash, 'time' => time()], JSON_UNESCAPED_SLASHES));
    }

    return $written;
}

function lineforge_warehouse_tail_lines(string $path, int $limit): array
{
    $limit = max(1, min(2000, $limit));
    $size = @filesize($path);
    if ($size === false || $size <= 0) {
        return [];
    }

    $handle = @fopen($path, 'rb');
    if (!$handle) {
        return [];
    }

    $lines = [];
    $buffer = '';
    $position = $size;
    $chunkSize = 32768;
    $bytesRead = 0;
    $maxBytes = 5 * 1024 * 1024;

    while ($position > 0 && count($lines) < $limit && $bytesRead < $maxBytes) {
        $readSize = min($chunkSize, $position, $maxBytes - $bytesRead);
        $position -= $readSize;
        if (@fseek($handle, $position) !== 0) {
            break;
        }

        $chunk = (string) @fread($handle, $readSize);
        if ($chunk === '') {
            break;
        }

        $bytesRead += $readSize;
        $buffer = $chunk . $buffer;
        $parts = explode("\n", $buffer);
        $buffer = array_shift($parts);
        for ($index = count($parts) - 1; $index >= 0 && count($lines) < $limit; $index -= 1) {
            $line = trim((string) $parts[$index]);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
    }

    if (count($lines) < $limit) {
        $line = trim($buffer);
        if ($line !== '') {
            $lines[] = $line;
        }
    }

    @fclose($handle);

    return $lines;
}

function lineforge_warehouse_read_recent(string $scope, int $days = 14, int $limit = 500): array
{
    $records = [];
    $days = max(1, min(90, $days));
    $limit = max(1, min(1000, $limit));
    for ($offset = 0; $offset < $days; $offset += 1) {
        $path = lineforge_warehouse_jsonl_path($scope, gmdate('Y-m-d', time() - ($offset * 86400)));
        if (!is_file($path)) {
            continue;
        }

        $remaining = $limit - count($records);
        $lines = lineforge_warehouse_tail_lines($path, min(600, max($remaining * 2, $remaining + 20)));
        foreach ($lines as $line) {
            $decoded = json_decode((string) $line, true);
            if (is_array($decoded)) {
                $records[] = $decoded;
                if (count($records) >= $limit) {
                    return $records;
                }
            }
        }
    }

    return $records;
}

function lineforge_warehouse_recent_line_count(string $scope, int $days = 30): int
{
    $count = 0;
    $days = max(1, min(180, $days));
    for ($offset = 0; $offset < $days; $offset += 1) {
        $path = lineforge_warehouse_jsonl_path($scope, gmdate('Y-m-d', time() - ($offset * 86400)));
        if (!is_file($path)) {
            continue;
        }

        $handle = @fopen($path, 'rb');
        if (!$handle) {
            continue;
        }

        while (($line = fgets($handle)) !== false) {
            if (trim($line) !== '') {
                $count += 1;
            }
        }

        @fclose($handle);
    }

    return $count;
}

function lineforge_warehouse_team_aliases(array $team): array
{
    $aliases = [];
    foreach (['id', 'abbr', 'short', 'name', 'displayName'] as $key) {
        $value = trim((string) ($team[$key] ?? ''));
        if ($value !== '') {
            $aliases[] = strtolower($value);
        }
    }

    return array_values(array_unique($aliases));
}

function lineforge_warehouse_pick_matches_aliases(string $pick, array $aliases): bool
{
    $pick = strtolower($pick);
    foreach ($aliases as $alias) {
        $alias = trim(strtolower((string) $alias));
        if ($alias !== '' && str_contains($pick, $alias)) {
            return true;
        }
    }

    return false;
}

function lineforge_warehouse_prediction_outcome(array $prediction, array $game): ?bool
{
    if ((string) ($game['statusKey'] ?? '') !== 'final') {
        return null;
    }

    $away = (array) ($game['away'] ?? []);
    $home = (array) ($game['home'] ?? []);
    $awayScore = (int) ($away['score'] ?? 0);
    $homeScore = (int) ($home['score'] ?? 0);
    $winnerSide = !empty($away['winner']) ? 'away' : (!empty($home['winner']) ? 'home' : '');
    if ($winnerSide === '' && $awayScore !== $homeScore) {
        $winnerSide = $awayScore > $homeScore ? 'away' : 'home';
    }
    if ($winnerSide === '') {
        return null;
    }

    $pick = strtolower((string) ($prediction['pick'] ?? $prediction['predictedWinner'] ?? ''));
    $winnerAliases = lineforge_warehouse_team_aliases($winnerSide === 'away' ? $away : $home);
    $loserAliases = lineforge_warehouse_team_aliases($winnerSide === 'away' ? $home : $away);
    $matchesWinner = lineforge_warehouse_pick_matches_aliases($pick, $winnerAliases);
    $matchesLoser = lineforge_warehouse_pick_matches_aliases($pick, $loserAliases);
    if ($matchesWinner && !$matchesLoser) {
        return true;
    }
    if ($matchesLoser && !$matchesWinner) {
        return false;
    }

    return null;
}

function lineforge_warehouse_compact_games(array $games): array
{
    return array_map(static fn(array $game): array => [
        'id' => (string) ($game['id'] ?? ''),
        'league' => (string) ($game['league'] ?? ''),
        'leagueKey' => (string) ($game['leagueKey'] ?? ''),
        'sportGroup' => (string) ($game['sportGroup'] ?? ''),
        'statusKey' => (string) ($game['statusKey'] ?? ''),
        'statusLabel' => (string) ($game['statusLabel'] ?? ''),
        'startTime' => (string) ($game['startTime'] ?? ''),
        'fetchedAt' => (string) ($game['fetchedAt'] ?? ''),
        'matchup' => (string) ($game['matchup'] ?? ''),
        'venue' => (string) ($game['venue'] ?? ''),
        'away' => [
            'id' => (string) ($game['away']['id'] ?? ''),
            'abbr' => (string) ($game['away']['abbr'] ?? ''),
            'name' => (string) ($game['away']['name'] ?? ''),
            'score' => (int) ($game['away']['score'] ?? 0),
            'winner' => !empty($game['away']['winner']),
        ],
        'home' => [
            'id' => (string) ($game['home']['id'] ?? ''),
            'abbr' => (string) ($game['home']['abbr'] ?? ''),
            'name' => (string) ($game['home']['name'] ?? ''),
            'score' => (int) ($game['home']['score'] ?? 0),
            'winner' => !empty($game['home']['winner']),
        ],
        'spread' => (array) ($game['spread'] ?? []),
        'total' => (array) ($game['total'] ?? []),
    ], array_slice($games, 0, 180));
}

function lineforge_warehouse_compact_predictions(array $predictions): array
{
    return array_map(static fn(array $prediction): array => [
        'gameId' => (string) ($prediction['gameId'] ?? ''),
        'matchup' => (string) ($prediction['matchup'] ?? ''),
        'league' => (string) ($prediction['league'] ?? ''),
        'market' => (string) ($prediction['market'] ?? ''),
        'pick' => (string) ($prediction['pick'] ?? ''),
        'predictedWinner' => (string) ($prediction['predictedWinner'] ?? ''),
        'predictedWinnerSide' => (string) ($prediction['predictedWinnerSide'] ?? ''),
        'confidenceValue' => (int) ($prediction['confidenceValue'] ?? 0),
        'fairProbability' => (string) ($prediction['fairProbability'] ?? ''),
        'fairOdds' => (string) ($prediction['fairOdds'] ?? ''),
        'edge' => (string) ($prediction['edge'] ?? ''),
        'expectedValue' => (string) ($prediction['expectedValue'] ?? ''),
        'risk' => (string) ($prediction['risk'] ?? ''),
        'statusKey' => (string) ($prediction['statusKey'] ?? ''),
        'dataQuality' => [
            'score' => (int) ($prediction['dataQuality']['score'] ?? 0),
            'label' => (string) ($prediction['dataQuality']['label'] ?? ''),
            'confidenceCap' => (int) ($prediction['dataQuality']['confidenceCap'] ?? 0),
        ],
    ], array_slice($predictions, 0, 180));
}

function lineforge_warehouse_compact_odds(array $arbitrage): array
{
    $rows = is_array($arbitrage['normalizedOdds'] ?? null) ? (array) $arbitrage['normalizedOdds'] : [];
    return array_map(static fn(array $row): array => [
        'marketGroupId' => (string) ($row['marketGroupId'] ?? ''),
        'eventId' => (string) ($row['eventId'] ?? ''),
        'eventName' => (string) ($row['eventName'] ?? ''),
        'sportKey' => (string) ($row['sportKey'] ?? ''),
        'providerKey' => (string) ($row['providerKey'] ?? ''),
        'providerName' => (string) ($row['providerName'] ?? ''),
        'marketType' => (string) ($row['marketType'] ?? ''),
        'lineKey' => (string) ($row['lineKey'] ?? ''),
        'period' => (string) ($row['period'] ?? 'full_game'),
        'selectionKey' => (string) ($row['selectionKey'] ?? ''),
        'selection' => (string) ($row['selection'] ?? ''),
        'oddsAmerican' => (int) ($row['oddsAmerican'] ?? 0),
        'decimalOdds' => (float) ($row['decimalOdds'] ?? 0),
        'rawImpliedProbability' => (float) ($row['rawImpliedProbability'] ?? 0),
        'noVigProbability' => (float) ($row['noVigProbability'] ?? 0),
        'lastUpdated' => (string) ($row['lastUpdated'] ?? ''),
        'ageSeconds' => is_numeric($row['ageSeconds'] ?? null) ? (int) $row['ageSeconds'] : null,
        'sourceConfidence' => (float) ($row['sourceConfidence'] ?? 0),
    ], array_slice($rows, 0, 1000));
}

function lineforge_warehouse_operational_metrics(array $games, array $predictions, array $marketAccess, array $arbitrage, array $oddsRows): array
{
    $liveGames = count(array_filter($games, static fn(array $game): bool => (string) ($game['statusKey'] ?? '') === 'live'));
    $finalGames = count(array_filter($games, static fn(array $game): bool => (string) ($game['statusKey'] ?? '') === 'final'));
    $staleOdds = count(array_filter($oddsRows, static fn(array $row): bool => (int) ($row['ageSeconds'] ?? 99999) > 180));
    $bookCount = count(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row['providerKey'] ?? ''), $oddsRows))));
    $marketCount = count(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row['marketGroupId'] ?? ''), $oddsRows))));
    $averageAge = $oddsRows
        ? (int) round(array_sum(array_map(static fn(array $row): int => (int) ($row['ageSeconds'] ?? 600), $oddsRows)) / count($oddsRows))
        : null;

    return [
        'liveGames' => $liveGames,
        'finalGames' => $finalGames,
        'predictions' => count($predictions),
        'normalizedOdds' => count($oddsRows),
        'matchedMarkets' => $marketCount,
        'connectedBooks' => $bookCount,
        'staleOdds' => $staleOdds,
        'averageOddsAgeSeconds' => $averageAge,
        'activeArbs' => (int) ($arbitrage['summary']['activeArbs'] ?? 0),
        'positiveEv' => count((array) ($arbitrage['positiveEv'] ?? [])),
        'middles' => count((array) ($arbitrage['middles'] ?? [])),
        'providerHealth' => (string) ($arbitrage['summary']['providerHealth'] ?? 'needs_data'),
        'availableLines' => (int) ($marketAccess['availableLines'] ?? 0),
    ];
}

function lineforge_warehouse_insert_json($db, string $table, array $columns): void
{
    $names = array_keys($columns);
    $placeholders = array_map(static fn(string $name): string => ':' . $name, $names);
    $sql = 'INSERT OR IGNORE INTO ' . $table . ' (' . implode(',', $names) . ') VALUES (' . implode(',', $placeholders) . ')';
    $statement = $db->prepare($sql);
    if (!$statement) {
        return;
    }

    foreach ($columns as $name => $value) {
        $statement->bindValue(':' . $name, $value);
    }
    $statement->execute();
}

function lineforge_warehouse_record_jsonl_cycle(array $games, array $predictions, array $marketAccess, array $arbitrage): array
{
    $snapshotAt = gmdate('c');
    $gameRows = lineforge_warehouse_compact_games($games);
    $predictionRows = lineforge_warehouse_compact_predictions($predictions);
    $oddsRows = lineforge_warehouse_compact_odds($arbitrage);
    $providerHealth = is_array($arbitrage['providerHealth']['providers'] ?? null) ? (array) $arbitrage['providerHealth']['providers'] : [];
    if (!$gameRows && !$predictionRows && !$oddsRows && !$providerHealth) {
        return lineforge_warehouse_summary_jsonl([], '');
    }

    $metrics = lineforge_warehouse_operational_metrics($games, $predictions, $marketAccess, $arbitrage, $oddsRows);
    $sourceHash = hash('sha256', json_encode([
        'games' => array_slice($gameRows, 0, 120),
        'predictions' => array_slice($predictionRows, 0, 120),
        'odds' => array_slice($oddsRows, 0, 800),
        'providerHealth' => $providerHealth,
        'metrics' => $metrics,
    ], JSON_UNESCAPED_SLASHES));
    $runKey = gmdate('YmdHi') . '_' . substr($sourceHash, 0, 16);

    $written = [
        'ingestionRuns' => lineforge_warehouse_append_once('warehouse-runs', [
            'type' => 'warehouse_run',
            'runKey' => $runKey,
            'snapshotAt' => $snapshotAt,
            'sourceHash' => $sourceHash,
            'driver' => 'jsonl_fallback',
            'counts' => [
                'games' => count($gameRows),
                'oddsRows' => count($oddsRows),
                'predictions' => count($predictionRows),
                'providerHealthRows' => count($providerHealth),
            ],
            'metrics' => $metrics,
        ], 120),
        'games' => lineforge_warehouse_append_once('warehouse-event-timeline', [
            'type' => 'event_timeline_snapshot',
            'runKey' => $runKey,
            'snapshotAt' => $snapshotAt,
            'count' => count($gameRows),
            'games' => $gameRows,
        ], 180),
        'oddsRows' => $oddsRows ? lineforge_warehouse_append_once('warehouse-market-timeline', [
            'type' => 'market_timeline_snapshot',
            'runKey' => $runKey,
            'snapshotAt' => $snapshotAt,
            'count' => count($oddsRows),
            'rows' => $oddsRows,
        ], 120) : false,
        'predictions' => lineforge_warehouse_append_once('warehouse-prediction-timeline', [
            'type' => 'prediction_replay_snapshot',
            'runKey' => $runKey,
            'snapshotAt' => $snapshotAt,
            'count' => count($predictionRows),
            'predictions' => $predictionRows,
        ], 180),
        'providerHealthRows' => lineforge_warehouse_append_once('warehouse-provider-health', [
            'type' => 'provider_health_snapshot',
            'runKey' => $runKey,
            'snapshotAt' => $snapshotAt,
            'count' => count($providerHealth),
            'providers' => $providerHealth,
            'overall' => (string) ($metrics['providerHealth'] ?? 'needs_data'),
        ], 180),
        'volatilityRows' => lineforge_warehouse_append_once('warehouse-volatility', [
            'type' => 'volatility_snapshot',
            'runKey' => $runKey,
            'snapshotAt' => $snapshotAt,
            'metrics' => [
                'activeArbs' => $metrics['activeArbs'],
                'positiveEv' => $metrics['positiveEv'],
                'middles' => $metrics['middles'],
                'staleOdds' => $metrics['staleOdds'],
                'averageOddsAgeSeconds' => $metrics['averageOddsAgeSeconds'],
                'providerHealth' => $metrics['providerHealth'],
            ],
        ], 180),
        'lineMovementRows' => lineforge_warehouse_append_once('warehouse-line-movement', [
            'type' => 'line_movement_snapshot',
            'runKey' => $runKey,
            'snapshotAt' => $snapshotAt,
            'metrics' => [
                'normalizedOdds' => count($oddsRows),
                'matchedMarkets' => $metrics['matchedMarkets'],
                'connectedBooks' => $metrics['connectedBooks'],
                'averageOddsAgeSeconds' => $metrics['averageOddsAgeSeconds'],
            ],
            'rows' => array_slice($oddsRows, 0, 160),
        ], 180),
    ];

    return lineforge_warehouse_summary_jsonl($written, $snapshotAt);
}

function lineforge_warehouse_jsonl_count(string $scope, string $collectionKey = ''): int
{
    if ($collectionKey === '') {
        return lineforge_warehouse_recent_line_count($scope, 30);
    }

    $records = lineforge_warehouse_read_recent($scope, 14, 40);
    return array_sum(array_map(static fn(array $record): int => count((array) ($record[$collectionKey] ?? [])), $records));
}

function lineforge_warehouse_calibration_jsonl(): array
{
    $predictionRecords = lineforge_warehouse_read_recent('warehouse-prediction-timeline', 45, 120);
    $eventRecords = lineforge_warehouse_read_recent('warehouse-event-timeline', 30, 36);
    if (!$predictionRecords || !$eventRecords) {
        return lineforge_warehouse_empty_calibration();
    }

    $gamesById = [];
    foreach ($eventRecords as $record) {
        foreach ((array) ($record['games'] ?? []) as $game) {
            if (!is_array($game)) {
                continue;
            }
            $gameId = (string) ($game['id'] ?? '');
            if ($gameId !== '' && (string) ($game['statusKey'] ?? '') === 'final') {
                $gamesById[$gameId] = $game;
            }
        }
    }

    $latestPredictions = [];
    foreach ($predictionRecords as $record) {
        $snapshotAt = (string) ($record['snapshotAt'] ?? '');
        foreach ((array) ($record['predictions'] ?? []) as $prediction) {
            if (!is_array($prediction)) {
                continue;
            }
            $gameId = (string) ($prediction['gameId'] ?? '');
            if ($gameId === '') {
                continue;
            }
            $key = $gameId . '|' . strtolower((string) ($prediction['market'] ?? '')) . '|' . strtolower((string) ($prediction['pick'] ?? ''));
            if (!isset($latestPredictions[$key]) || strcmp($snapshotAt, (string) ($latestPredictions[$key]['snapshotAt'] ?? '')) > 0) {
                $prediction['snapshotAt'] = $snapshotAt;
                $latestPredictions[$key] = $prediction;
            }
        }
    }

    $buckets = [];
    $closed = 0;
    $hits = 0;
    $brierSum = 0.0;
    $confidenceSum = 0.0;
    $dataQualitySum = 0.0;
    $stalePenaltySamples = 0;
    $recentBrier = [];

    foreach ($latestPredictions as $prediction) {
        $game = $gamesById[(string) ($prediction['gameId'] ?? '')] ?? null;
        if (!$game) {
            continue;
        }

        $outcome = lineforge_warehouse_prediction_outcome($prediction, $game);
        if ($outcome === null) {
            continue;
        }

        $confidence = max(1, min(99, (int) ($prediction['confidenceValue'] ?? 0)));
        $probability = $confidence / 100;
        $actual = $outcome ? 1.0 : 0.0;
        $brier = ($probability - $actual) ** 2;
        $bucket = (int) (floor($confidence / 10) * 10);
        $bucketKey = $bucket . '-' . min(99, $bucket + 9);
        if (!isset($buckets[$bucketKey])) {
            $buckets[$bucketKey] = [
                'bucket' => $bucketKey,
                'samples' => 0,
                'closedSamples' => 0,
                'hits' => 0,
                'confidenceSum' => 0.0,
                'brierSum' => 0.0,
            ];
        }

        $buckets[$bucketKey]['samples'] += 1;
        $buckets[$bucketKey]['closedSamples'] += 1;
        $buckets[$bucketKey]['hits'] += $outcome ? 1 : 0;
        $buckets[$bucketKey]['confidenceSum'] += $probability;
        $buckets[$bucketKey]['brierSum'] += $brier;
        $closed += 1;
        $hits += $outcome ? 1 : 0;
        $brierSum += $brier;
        $confidenceSum += $probability;
        $dataQuality = (int) ($prediction['dataQuality']['score'] ?? 0);
        $dataQualitySum += $dataQuality;
        if ($dataQuality > 0 && $dataQuality < 60) {
            $stalePenaltySamples += 1;
        }
        $recentBrier[] = $brier;
    }

    if ($closed === 0) {
        return lineforge_warehouse_empty_calibration();
    }

    $calibrationErrorSum = 0.0;
    $bucketViews = [];
    foreach ($buckets as $bucket) {
        $samples = max(1, (int) $bucket['closedSamples']);
        $avgConfidence = (float) $bucket['confidenceSum'] / $samples;
        $hitRate = (float) $bucket['hits'] / $samples;
        $bucketError = abs($avgConfidence - $hitRate);
        $calibrationErrorSum += $bucketError;
        $bucketViews[] = [
            'bucket' => $bucket['bucket'],
            'samples' => (int) $bucket['samples'],
            'closedSamples' => (int) $bucket['closedSamples'],
            'hitRate' => round($hitRate * 100, 1),
            'averageConfidence' => round($avgConfidence * 100, 1),
            'brierScore' => round(((float) $bucket['brierSum'] / $samples), 4),
            'calibrationError' => round($bucketError * 100, 1),
        ];
    }

    $half = (int) floor(count($recentBrier) / 2);
    $driftStatus = 'insufficient_samples';
    if ($half >= 10) {
        $older = array_slice($recentBrier, $half);
        $newer = array_slice($recentBrier, 0, $half);
        $olderAvg = array_sum($older) / max(1, count($older));
        $newerAvg = array_sum($newer) / max(1, count($newer));
        $driftStatus = abs($newerAvg - $olderAvg) >= 0.04 ? 'drift_watch' : 'stable';
    }

    $brierScore = $brierSum / $closed;
    $hitRate = $hits / $closed;
    $averageConfidence = $confidenceSum / $closed;
    $calibrationError = $calibrationErrorSum / max(1, count($bucketViews));

    return [
        'status' => $closed >= 100 ? 'calibrating' : 'collecting_baseline',
        'sampleCount' => count($latestPredictions),
        'closedSamples' => $closed,
        'minimumClosedSamples' => 100,
        'brierScore' => round($brierScore, 4),
        'hitRate' => round($hitRate * 100, 1),
        'averageConfidence' => round($averageConfidence * 100, 1),
        'calibrationError' => round($calibrationError * 100, 1),
        'averageDataQuality' => round($dataQualitySum / $closed, 1),
        'staleDataPenaltyShare' => round(($stalePenaltySamples / $closed) * 100, 1),
        'driftStatus' => $driftStatus,
        'expectedValueStatus' => $closed >= 100 ? 'ready_for_ev_review' : 'waiting_for_closed_samples',
        'message' => $closed >= 100
            ? 'Closed prediction samples are ready for calibration review. Treat metrics as audit evidence, not a guarantee.'
            : 'Collecting closed historical predictions before showing strong accuracy or calibration claims.',
        'buckets' => $bucketViews,
    ];
}

function lineforge_warehouse_provider_reliability_jsonl(): array
{
    $records = lineforge_warehouse_read_recent('warehouse-provider-health', 30, 300);
    $providers = [];
    foreach ($records as $record) {
        foreach ((array) ($record['providers'] ?? []) as $key => $provider) {
            $provider = is_array($provider) ? $provider : ['status' => (string) $provider];
            $providerKey = (string) ($provider['key'] ?? $provider['providerKey'] ?? $provider['providerName'] ?? $provider['name'] ?? $provider['label'] ?? $key);
            if (is_numeric($providerKey)) {
                $providerKey = (string) ($provider['providerName'] ?? $provider['name'] ?? $provider['label'] ?? ('provider_' . $providerKey));
            }
            if ($providerKey === '') {
                continue;
            }
            if (!isset($providers[$providerKey])) {
                $providers[$providerKey] = ['provider' => $providerKey, 'samples' => 0, 'healthy' => 0, 'degraded' => 0];
            }
            $status = strtolower((string) ($provider['status'] ?? $provider['state'] ?? $record['overall'] ?? ''));
            $providers[$providerKey]['samples'] += 1;
            if (str_contains($status, 'operational') || str_contains($status, 'connected') || str_contains($status, 'active')) {
                $providers[$providerKey]['healthy'] += 1;
            } else {
                $providers[$providerKey]['degraded'] += 1;
            }
        }
    }

    return array_values(array_map(static function (array $provider): array {
        $samples = max(1, (int) $provider['samples']);
        $provider['reliabilityScore'] = round(((int) $provider['healthy'] / $samples) * 100, 1);
        return $provider;
    }, $providers));
}

function lineforge_warehouse_summary_jsonl(array $written = [], string $snapshotAt = ''): array
{
    $calibration = lineforge_warehouse_calibration_jsonl();
    $runs = lineforge_warehouse_read_recent('warehouse-runs', 30, 120);
    $latestRunAt = $snapshotAt;
    if ($latestRunAt === '' && $runs) {
        $latestRunAt = (string) ($runs[0]['snapshotAt'] ?? '');
    }

    $counts = [
        'ingestionRuns' => lineforge_warehouse_jsonl_count('warehouse-runs'),
        'games' => lineforge_warehouse_jsonl_count('warehouse-event-timeline', 'games'),
        'oddsRows' => lineforge_warehouse_jsonl_count('warehouse-market-timeline', 'rows'),
        'predictions' => lineforge_warehouse_jsonl_count('warehouse-prediction-timeline', 'predictions'),
        'providerHealthRows' => lineforge_warehouse_jsonl_count('warehouse-provider-health', 'providers'),
        'volatilityRows' => lineforge_warehouse_jsonl_count('warehouse-volatility'),
        'lineMovementRows' => lineforge_warehouse_jsonl_count('warehouse-line-movement'),
        'featurePipelineRows' => lineforge_warehouse_jsonl_count('warehouse-feature-pipeline'),
        'adaptiveNetworkRows' => lineforge_warehouse_jsonl_count('warehouse-adaptive-network'),
        'generalizedCoreRows' => lineforge_warehouse_jsonl_count('warehouse-generalized-core'),
        'injuryNewsRows' => 0,
    ];

    return [
        'available' => true,
        'driver' => 'jsonl_fallback',
        'status' => 'active_fallback',
        'path' => lineforge_warehouse_jsonl_dir(),
        'retentionDays' => 180,
        'dedupePolicy' => 'timeline scope hash plus TTL guard',
        'message' => 'SQLite is unavailable, so Lineforge is writing normalized JSONL warehouse timelines until the database extension is enabled.',
        'counts' => $counts,
        'latestRunAt' => $latestRunAt,
        'lastWrite' => $written,
        'calibration' => $calibration,
        'providerReliability' => lineforge_warehouse_provider_reliability_jsonl(),
        'replay' => [
            'available' => $counts['ingestionRuns'] > 0,
            'status' => $counts['ingestionRuns'] > 0 ? 'ready_for_recent_replay' : 'waiting_for_snapshots',
            'message' => $counts['ingestionRuns'] > 0
                ? 'Recent warehouse timelines can reconstruct what the system knew at a selected snapshot.'
                : 'Replay mode activates after warehouse snapshots exist.',
            'latestSnapshotAt' => $latestRunAt,
            'supportedQueries' => ['event_timeline', 'market_timeline', 'prediction_replay', 'provider_health_history', 'calibration_review'],
        ],
        'operational' => [
            'worker' => [
                'status' => $counts['ingestionRuns'] > 0 ? 'ready' : 'not_started',
                'message' => 'Run Website/tools/lineforge-worker.php from a scheduler to collect intelligence outside frontend sessions.',
            ],
            'storageMode' => 'jsonl_fallback',
            'retentionPolicy' => '180 day hot warehouse, compact daily JSONL timelines, database-ready schema.',
        ],
    ];
}

function lineforge_warehouse_record_cycle(array $games, array $predictions, array $marketAccess, array $arbitrage): array
{
    $db = lineforge_warehouse_open();
    if (!$db) {
        return lineforge_warehouse_record_jsonl_cycle($games, $predictions, $marketAccess, $arbitrage);
    }

    $snapshotAt = gmdate('c');
    $oddsRows = is_array($arbitrage['normalizedOdds'] ?? null) ? (array) $arbitrage['normalizedOdds'] : [];
    $providerHealth = is_array($arbitrage['providerHealth']['providers'] ?? null) ? (array) $arbitrage['providerHealth']['providers'] : [];
    $sourceHash = hash('sha256', json_encode([
        'games' => array_slice($games, 0, 120),
        'predictions' => array_slice($predictions, 0, 120),
        'odds' => array_slice($oddsRows, 0, 800),
        'providerHealth' => $providerHealth,
    ], JSON_UNESCAPED_SLASHES));
    $runKey = gmdate('YmdHi') . '_' . substr($sourceHash, 0, 16);

    try {
        $db->exec('BEGIN');
        lineforge_warehouse_insert_json($db, 'ingestion_runs', [
            'run_key' => $runKey,
            'created_at' => $snapshotAt,
            'source_hash' => $sourceHash,
            'game_count' => count($games),
            'odds_count' => count($oddsRows),
            'prediction_count' => count($predictions),
            'provider_health_count' => count($providerHealth),
            'status' => 'recorded',
        ]);

        foreach (array_slice($games, 0, 180) as $game) {
            $payload = json_encode($game, JSON_UNESCAPED_SLASHES);
            lineforge_warehouse_insert_json($db, 'game_snapshots', [
                'row_hash' => lineforge_warehouse_row_hash('game', [(string) ($game['id'] ?? ''), $snapshotAt, $payload]),
                'snapshot_at' => $snapshotAt,
                'game_id' => (string) ($game['id'] ?? ''),
                'league' => (string) ($game['league'] ?? ''),
                'status_key' => (string) ($game['statusKey'] ?? ''),
                'start_time' => (string) ($game['startTime'] ?? ''),
                'matchup' => (string) ($game['matchup'] ?? ''),
                'payload_json' => $payload ?: '{}',
            ]);
        }

        foreach (array_slice($oddsRows, 0, 1000) as $row) {
            $payload = json_encode($row, JSON_UNESCAPED_SLASHES);
            lineforge_warehouse_insert_json($db, 'odds_snapshots', [
                'row_hash' => lineforge_warehouse_row_hash('odds', [
                    $row['providerKey'] ?? '',
                    $row['eventId'] ?? '',
                    $row['marketType'] ?? '',
                    $row['lineKey'] ?? '',
                    $row['selectionKey'] ?? '',
                    $snapshotAt,
                    $row['decimalOdds'] ?? 0,
                ]),
                'snapshot_at' => $snapshotAt,
                'provider_key' => (string) ($row['providerKey'] ?? ''),
                'event_id' => (string) ($row['eventId'] ?? ''),
                'market_type' => (string) ($row['marketType'] ?? ''),
                'line_key' => (string) ($row['lineKey'] ?? ''),
                'selection_key' => (string) ($row['selectionKey'] ?? ''),
                'decimal_odds' => (float) ($row['decimalOdds'] ?? 0),
                'implied_probability' => (float) ($row['rawImpliedProbability'] ?? 0),
                'age_seconds' => is_numeric($row['ageSeconds'] ?? null) ? (int) $row['ageSeconds'] : null,
                'payload_json' => $payload ?: '{}',
            ]);
        }

        foreach (array_slice($predictions, 0, 160) as $prediction) {
            $payload = json_encode($prediction, JSON_UNESCAPED_SLASHES);
            lineforge_warehouse_insert_json($db, 'prediction_snapshots', [
                'row_hash' => lineforge_warehouse_row_hash('prediction', [
                    $prediction['gameId'] ?? '',
                    $prediction['market'] ?? '',
                    $prediction['pick'] ?? '',
                    $snapshotAt,
                    $prediction['confidenceValue'] ?? 0,
                ]),
                'snapshot_at' => $snapshotAt,
                'game_id' => (string) ($prediction['gameId'] ?? ''),
                'matchup' => (string) ($prediction['matchup'] ?? ''),
                'market' => (string) ($prediction['market'] ?? ''),
                'pick' => (string) ($prediction['pick'] ?? ''),
                'confidence' => (int) ($prediction['confidenceValue'] ?? 0),
                'data_quality' => (int) ($prediction['dataQuality']['score'] ?? 0),
                'status_key' => (string) ($prediction['statusKey'] ?? ''),
                'payload_json' => $payload ?: '{}',
            ]);
        }

        foreach ($providerHealth as $key => $provider) {
            $provider = is_array($provider) ? $provider : ['status' => (string) $provider];
            $payload = json_encode($provider, JSON_UNESCAPED_SLASHES);
            lineforge_warehouse_insert_json($db, 'provider_health_snapshots', [
                'row_hash' => lineforge_warehouse_row_hash('provider', [$key, $snapshotAt, $payload]),
                'snapshot_at' => $snapshotAt,
                'provider_key' => (string) ($provider['key'] ?? $provider['providerKey'] ?? $key),
                'status' => (string) ($provider['status'] ?? $provider['state'] ?? ''),
                'payload_json' => $payload ?: '{}',
            ]);
        }

        $db->exec('COMMIT');
    } catch (Throwable $_error) {
        $db->exec('ROLLBACK');
        return array_merge(lineforge_warehouse_default_state('record_failed'), [
            'available' => true,
            'driver' => 'sqlite3',
            'message' => 'SQLite warehouse is available, but this ingestion cycle failed and JSONL fallback remains active.',
        ]);
    }

    return lineforge_warehouse_summary($db);
}

function lineforge_warehouse_count($db, string $table): int
{
    try {
        $result = $db->querySingle('SELECT COUNT(*) FROM ' . $table);
        return is_numeric($result) ? (int) $result : 0;
    } catch (Throwable $_error) {
        return 0;
    }
}

function lineforge_warehouse_calibration($db): array
{
    $buckets = [];
    try {
        $result = $db->query('SELECT confidence, status_key FROM prediction_snapshots ORDER BY id DESC LIMIT 1000');
        while ($row = $result?->fetchArray(SQLITE3_ASSOC)) {
            $confidence = max(0, min(100, (int) ($row['confidence'] ?? 0)));
            $bucket = (int) (floor($confidence / 10) * 10);
            $key = $bucket . '-' . min(99, $bucket + 9);
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['bucket' => $key, 'samples' => 0, 'closedSamples' => 0];
            }
            $buckets[$key]['samples'] += 1;
            if ((string) ($row['status_key'] ?? '') === 'final') {
                $buckets[$key]['closedSamples'] += 1;
            }
        }
    } catch (Throwable $_error) {
        $buckets = [];
    }

    $samples = array_sum(array_map(static fn(array $row): int => (int) $row['samples'], $buckets));
    $closed = array_sum(array_map(static fn(array $row): int => (int) $row['closedSamples'], $buckets));

    return [
        'status' => $closed >= 100 ? 'calibrating' : 'collecting_baseline',
        'sampleCount' => $samples,
        'closedSamples' => $closed,
        'minimumClosedSamples' => 100,
        'message' => $closed >= 100
            ? 'Enough closed prediction snapshots exist to begin calibration review.'
            : 'Collecting closed historical predictions before showing accuracy or calibration claims.',
        'buckets' => array_values($buckets),
    ];
}

function lineforge_warehouse_summary($db = null): array
{
    $db = $db ?? lineforge_warehouse_open();
    if (!$db) {
        return lineforge_warehouse_default_state();
    }

    $latestRun = '';
    try {
        $latestRun = (string) ($db->querySingle('SELECT created_at FROM ingestion_runs ORDER BY id DESC LIMIT 1') ?: '');
    } catch (Throwable $_error) {
        $latestRun = '';
    }

    return [
        'available' => true,
        'driver' => 'sqlite3',
        'status' => 'active',
        'path' => lineforge_warehouse_path(),
        'retentionDays' => 180,
        'dedupePolicy' => 'run hash plus row hash',
        'message' => 'SQLite warehouse is active for historical replay, calibration datasets, and provider health history.',
        'counts' => [
            'ingestionRuns' => lineforge_warehouse_count($db, 'ingestion_runs'),
            'games' => lineforge_warehouse_count($db, 'game_snapshots'),
            'oddsRows' => lineforge_warehouse_count($db, 'odds_snapshots'),
            'predictions' => lineforge_warehouse_count($db, 'prediction_snapshots'),
            'providerHealthRows' => lineforge_warehouse_count($db, 'provider_health_snapshots'),
            'volatilityRows' => 0,
            'lineMovementRows' => 0,
            'featurePipelineRows' => 0,
            'adaptiveNetworkRows' => 0,
            'generalizedCoreRows' => 0,
            'injuryNewsRows' => 0,
        ],
        'latestRunAt' => $latestRun,
        'calibration' => lineforge_warehouse_calibration($db),
        'providerReliability' => [],
        'replay' => [
            'available' => $latestRun !== '',
            'status' => $latestRun !== '' ? 'ready_for_recent_replay' : 'waiting_for_snapshots',
            'message' => $latestRun !== ''
                ? 'SQLite warehouse snapshots can reconstruct what the system knew at a selected snapshot.'
                : 'Replay mode activates after warehouse snapshots exist.',
            'latestSnapshotAt' => $latestRun,
            'supportedQueries' => ['event_timeline', 'market_timeline', 'prediction_replay', 'provider_health_history', 'calibration_review'],
        ],
        'operational' => [
            'worker' => [
                'status' => $latestRun !== '' ? 'ready' : 'not_started',
                'message' => 'Run Website/tools/lineforge-worker.php from a scheduler to collect intelligence outside frontend sessions.',
            ],
            'storageMode' => 'sqlite',
            'retentionPolicy' => '180 day hot warehouse, WAL mode, migration-ready schema.',
        ],
    ];
}
