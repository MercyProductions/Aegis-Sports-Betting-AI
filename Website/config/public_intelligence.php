<?php

require_once __DIR__ . '/intelligence_warehouse.php';
require_once __DIR__ . '/intelligence_evolution.php';
require_once __DIR__ . '/adaptive_intelligence.php';
require_once __DIR__ . '/generalized_intelligence.php';

function lineforge_public_intelligence_dir(string $scope = ''): string
{
    $base = dirname(__DIR__) . '/storage/intelligence';
    $safeScope = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(trim($scope))) ?: '';
    $dir = $safeScope !== '' ? $base . '/' . $safeScope : $base;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return is_dir($dir) ? $dir : dirname(__DIR__) . '/storage';
}

function lineforge_public_intelligence_jsonl_path(string $scope, ?string $date = null): string
{
    return lineforge_public_intelligence_dir($scope) . '/' . ($date ?: gmdate('Y-m-d')) . '.jsonl';
}

function lineforge_public_intelligence_append_once(string $scope, array $record, int $ttlSeconds = 300): bool
{
    $dir = lineforge_public_intelligence_dir($scope);
    $fingerprintPath = $dir . '/.last_snapshot.json';
    $hash = hash('sha256', json_encode($record, JSON_UNESCAPED_SLASHES));
    $last = is_file($fingerprintPath) ? json_decode((string) @file_get_contents($fingerprintPath), true) : [];
    if (
        is_array($last)
        && (string) ($last['hash'] ?? '') === $hash
        && (time() - (int) ($last['time'] ?? 0)) < max(60, $ttlSeconds)
    ) {
        return false;
    }

    $path = lineforge_public_intelligence_jsonl_path($scope);
    $record['snapshotAt'] = $record['snapshotAt'] ?? gmdate('c');
    $line = json_encode($record, JSON_UNESCAPED_SLASHES) . "\n";
    $written = @file_put_contents($path, $line, FILE_APPEND | LOCK_EX) !== false;
    if ($written) {
        @file_put_contents($fingerprintPath, json_encode(['hash' => $hash, 'time' => time()], JSON_UNESCAPED_SLASHES));
    }

    return $written;
}

function lineforge_public_intelligence_read_recent(string $scope, int $days = 7, int $limit = 300): array
{
    $records = [];
    $days = max(1, min(30, $days));
    for ($offset = 0; $offset < $days; $offset += 1) {
        $path = lineforge_public_intelligence_jsonl_path($scope, gmdate('Y-m-d', time() - ($offset * 86400)));
        if (!is_file($path)) {
            continue;
        }

        $lines = array_reverse(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []);
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

function lineforge_public_intelligence_compact_game(array $game): array
{
    return [
        'id' => (string) ($game['id'] ?? ''),
        'league' => (string) ($game['league'] ?? ''),
        'leagueKey' => (string) ($game['leagueKey'] ?? ''),
        'statusKey' => (string) ($game['statusKey'] ?? ''),
        'startTime' => (string) ($game['startTime'] ?? ''),
        'matchup' => (string) ($game['matchup'] ?? ''),
        'venue' => (string) ($game['venue'] ?? ''),
        'venueCity' => (string) ($game['venueCity'] ?? ''),
        'venueState' => (string) ($game['venueState'] ?? ''),
        'away' => [
            'id' => (string) ($game['away']['id'] ?? ''),
            'abbr' => (string) ($game['away']['abbr'] ?? ''),
            'name' => (string) ($game['away']['name'] ?? ''),
            'score' => (int) ($game['away']['score'] ?? 0),
            'record' => (string) ($game['away']['record'] ?? ''),
            'winner' => !empty($game['away']['winner']),
        ],
        'home' => [
            'id' => (string) ($game['home']['id'] ?? ''),
            'abbr' => (string) ($game['home']['abbr'] ?? ''),
            'name' => (string) ($game['home']['name'] ?? ''),
            'score' => (int) ($game['home']['score'] ?? 0),
            'record' => (string) ($game['home']['record'] ?? ''),
            'winner' => !empty($game['home']['winner']),
        ],
        'spread' => (array) ($game['spread'] ?? []),
        'total' => (array) ($game['total'] ?? []),
    ];
}

function lineforge_public_intelligence_compact_odds_row(array $row): array
{
    return [
        'marketGroupId' => (string) ($row['marketGroupId'] ?? ''),
        'eventId' => (string) ($row['eventId'] ?? ''),
        'eventName' => (string) ($row['eventName'] ?? ''),
        'providerKey' => (string) ($row['providerKey'] ?? ''),
        'providerName' => (string) ($row['providerName'] ?? ''),
        'marketType' => (string) ($row['marketType'] ?? ''),
        'lineKey' => (string) ($row['lineKey'] ?? ''),
        'selectionKey' => (string) ($row['selectionKey'] ?? ''),
        'selection' => (string) ($row['selection'] ?? ''),
        'providerOutcomeCount' => (int) ($row['providerOutcomeCount'] ?? 0),
        'oddsAmerican' => (int) ($row['oddsAmerican'] ?? 0),
        'decimalOdds' => (float) ($row['decimalOdds'] ?? 0),
        'rawImpliedProbability' => (float) ($row['rawImpliedProbability'] ?? 0),
        'noVigProbability' => (float) ($row['noVigProbability'] ?? 0),
        'lastUpdated' => (string) ($row['lastUpdated'] ?? ''),
        'ageSeconds' => is_numeric($row['ageSeconds'] ?? null) ? (int) $row['ageSeconds'] : null,
    ];
}

function lineforge_public_intelligence_record_snapshots(array $games, array $predictions, array $marketAccess, array $arbitrage): array
{
    $gameRows = array_map('lineforge_public_intelligence_compact_game', array_slice($games, 0, 120));
    $oddsRows = array_map(
        'lineforge_public_intelligence_compact_odds_row',
        array_slice(is_array($arbitrage['normalizedOdds'] ?? null) ? $arbitrage['normalizedOdds'] : [], 0, 500)
    );

    $gameWritten = lineforge_public_intelligence_append_once('game-archive', [
        'type' => 'game_archive_snapshot',
        'source' => 'public_scoreboard',
        'count' => count($gameRows),
        'games' => $gameRows,
    ], 600);

    $oddsWritten = $oddsRows
        ? lineforge_public_intelligence_append_once('odds-snapshots', [
            'type' => 'odds_snapshot',
            'source' => (string) ($marketAccess['oddsProvider'] ?? 'The Odds API'),
            'count' => count($oddsRows),
            'rows' => $oddsRows,
        ], 180)
        : false;

    $predictionWritten = lineforge_public_intelligence_append_once('prediction-snapshots', [
        'type' => 'prediction_snapshot',
        'source' => 'lineforge_public_model',
        'count' => count($predictions),
        'predictions' => array_map(static fn(array $prediction): array => [
            'gameId' => (string) ($prediction['gameId'] ?? ''),
            'pick' => (string) ($prediction['pick'] ?? ''),
            'predictedWinner' => (string) ($prediction['predictedWinner'] ?? ''),
            'predictedWinnerSide' => (string) ($prediction['predictedWinnerSide'] ?? ''),
            'market' => (string) ($prediction['market'] ?? ''),
            'confidenceValue' => (int) ($prediction['confidenceValue'] ?? 0),
            'edge' => (string) ($prediction['edge'] ?? ''),
            'risk' => (string) ($prediction['risk'] ?? ''),
        ], array_slice($predictions, 0, 80)),
    ], 600);

    $gameHistory = lineforge_public_intelligence_read_recent('game-archive', 14, 120);
    $oddsHistory = lineforge_public_intelligence_read_recent('odds-snapshots', 7, 120);

    return [
        'gameSnapshotWritten' => $gameWritten,
        'oddsSnapshotWritten' => $oddsWritten,
        'predictionSnapshotWritten' => $predictionWritten,
        'gameRowsStored' => array_sum(array_map(static fn(array $record): int => (int) ($record['count'] ?? 0), $gameHistory)),
        'oddsRowsStored' => array_sum(array_map(static fn(array $record): int => (int) ($record['count'] ?? 0), $oddsHistory)),
        'gameBatches' => count($gameHistory),
        'oddsBatches' => count($oddsHistory),
    ];
}

function lineforge_public_intelligence_team_key(array $team): string
{
    $id = trim((string) ($team['id'] ?? ''));
    if ($id !== '') {
        return 'id:' . $id;
    }

    return strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) ($team['abbr'] ?? $team['name'] ?? 'team')) ?: 'team');
}

function lineforge_public_intelligence_fatigue(array $games): array
{
    $records = lineforge_public_intelligence_read_recent('game-archive', 21, 220);
    $historyByTeam = [];
    foreach ($records as $record) {
        foreach ((array) ($record['games'] ?? []) as $archivedGame) {
            $time = strtotime((string) ($archivedGame['startTime'] ?? '')) ?: 0;
            if ($time <= 0 || !in_array((string) ($archivedGame['statusKey'] ?? ''), ['final', 'live', 'scheduled'], true)) {
                continue;
            }
            foreach (['away', 'home'] as $side) {
                $team = (array) ($archivedGame[$side] ?? []);
                $key = lineforge_public_intelligence_team_key($team);
                $historyByTeam[$key][] = [
                    'id' => (string) ($archivedGame['id'] ?? ''),
                    'time' => $time,
                    'venueCity' => (string) ($archivedGame['venueCity'] ?? ''),
                    'venueState' => (string) ($archivedGame['venueState'] ?? ''),
                    'matchup' => (string) ($archivedGame['matchup'] ?? ''),
                ];
            }
        }
    }

    $gamesAnalyzed = [];
    foreach (array_slice($games, 0, 20) as $game) {
        $start = strtotime((string) ($game['startTime'] ?? '')) ?: 0;
        if ($start <= 0) {
            continue;
        }
        $sides = [];
        foreach (['away', 'home'] as $side) {
            $team = (array) ($game[$side] ?? []);
            $history = $historyByTeam[lineforge_public_intelligence_team_key($team)] ?? [];
            $last = null;
            foreach ($history as $candidate) {
                $candidateTime = (int) ($candidate['time'] ?? 0);
                if (
                    $candidateTime > 0
                    && $candidateTime < ($start - 3600)
                    && (string) ($candidate['id'] ?? '') !== (string) ($game['id'] ?? '')
                    && (!$last || $candidateTime > (int) ($last['time'] ?? 0))
                ) {
                    $last = $candidate;
                }
            }
            $restHours = $last ? max(0, ($start - (int) $last['time']) / 3600) : null;
            $travelFlag = $last
                && (string) ($last['venueCity'] ?? '') !== ''
                && (string) ($game['venueCity'] ?? '') !== ''
                && strtolower((string) $last['venueCity']) !== strtolower((string) ($game['venueCity'] ?? ''));
            $risk = $restHours !== null && $restHours < 44 ? 'short_rest' : ($travelFlag ? 'travel_watch' : ($restHours === null ? 'building_history' : 'normal'));
            $sides[$side] = [
                'team' => (string) ($team['abbr'] ?? $team['name'] ?? ucfirst($side)),
                'restHours' => $restHours !== null ? round($restHours, 1) : null,
                'restDays' => $restHours !== null ? round($restHours / 24, 1) : null,
                'travelFlag' => (bool) $travelFlag,
                'risk' => $risk,
            ];
        }
        $gamesAnalyzed[] = [
            'event' => (string) ($game['matchup'] ?? 'Matchup'),
            'startTime' => (string) ($game['startTime'] ?? ''),
            'venue' => trim((string) ($game['venueCity'] ?? '') . ((string) ($game['venueState'] ?? '') !== '' ? ', ' . (string) $game['venueState'] : '')),
            'away' => $sides['away'],
            'home' => $sides['home'],
        ];
    }

    $shortRest = 0;
    $travel = 0;
    foreach ($gamesAnalyzed as $row) {
        foreach (['away', 'home'] as $side) {
            $shortRest += ($row[$side]['risk'] ?? '') === 'short_rest' ? 1 : 0;
            $travel += !empty($row[$side]['travelFlag']) ? 1 : 0;
        }
    }

    return [
        'gamesAnalyzed' => count($gamesAnalyzed),
        'shortRestFlags' => $shortRest,
        'travelFlags' => $travel,
        'items' => $gamesAnalyzed,
    ];
}

function lineforge_public_intelligence_market_inference(array $arbitrage): array
{
    $rows = is_array($arbitrage['normalizedOdds'] ?? null) ? $arbitrage['normalizedOdds'] : [];
    $signals = [];
    $grouped = [];
    foreach ($rows as $row) {
        $key = (string) ($row['marketGroupId'] ?? '') . '|' . (string) ($row['selectionKey'] ?? '');
        if ($key !== '|') {
            $grouped[$key][] = $row;
        }
    }

    $maxDisagreement = 0.0;
    $disagreementCount = 0;
    foreach ($grouped as $items) {
        $decimals = array_values(array_filter(array_map(static fn(array $row): float => (float) ($row['decimalOdds'] ?? 0), $items), static fn(float $value): bool => $value > 1));
        if (count($decimals) < 2) {
            continue;
        }
        $range = max($decimals) - min($decimals);
        $maxDisagreement = max($maxDisagreement, $range);
        if ($range >= 0.12) {
            $disagreementCount += 1;
        }
    }

    if ($disagreementCount > 0) {
        $signals[] = [
            'name' => 'High disagreement between books',
            'state' => 'Inferred',
            'value' => $disagreementCount . ' outcomes',
            'detail' => 'Best and worst available prices are separated enough to flag market disagreement. This is not a verified sharp-money claim.',
        ];
    }

    $movement = lineforge_public_intelligence_line_velocity($rows);
    if ($movement['rapidMoves'] > 0) {
        $signals[] = [
            'name' => 'Rapid movement detected',
            'state' => 'Inferred',
            'value' => $movement['largestMove'] . ' cents',
            'detail' => 'Historical snapshots show fast price movement in a short window. Verify current prices before acting.',
        ];
    }
    if ($movement['earlySharpBookMoves'] > 0) {
        $signals[] = [
            'name' => 'Early sharp-book movement',
            'state' => 'Inferred',
            'value' => $movement['earlySharpBookMoves'] . ' markets',
            'detail' => 'A sharper reference book moved before retail books in stored snapshots. Treat as market-pressure inference only.',
        ];
    }

    $volatilityScore = min(100, (int) round(($maxDisagreement * 120) + ($movement['rapidMoves'] * 12)));
    if ($volatilityScore >= 35) {
        $signals[] = [
            'name' => 'Volatility increasing',
            'state' => 'Watch',
            'value' => $volatilityScore . '/100',
            'detail' => 'Line disagreement and stored movement history suggest this market deserves a tighter refresh cadence.',
        ];
    }

    if (!$signals) {
        $signals[] = [
            'name' => $rows ? 'Consensus stable' : 'Public intelligence mode',
            'state' => $rows ? 'Stable' : 'Fallback',
            'value' => $rows ? count($rows) . ' odds rows' : 'No paid odds required',
            'detail' => $rows
                ? 'No rapid movement, high disagreement, or volatility pattern crossed the current threshold.'
                : 'Lineforge is using public scoreboard, summaries, weather, history cache, and internal trend storage while odds rows are unavailable.',
        ];
    }

    return [
        'signals' => $signals,
        'lineVelocity' => $movement,
        'marketDisagreement' => [
            'flaggedOutcomes' => $disagreementCount,
            'maxDecimalRange' => round($maxDisagreement, 3),
        ],
        'volatilityScore' => $volatilityScore,
    ];
}

function lineforge_public_intelligence_line_velocity(array $currentRows): array
{
    $records = lineforge_public_intelligence_read_recent('odds-snapshots', 3, 40);
    $batches = [];
    foreach ($records as $record) {
        $time = strtotime((string) ($record['snapshotAt'] ?? '')) ?: 0;
        if ($time > 0 && is_array($record['rows'] ?? null)) {
            $batches[] = ['time' => $time, 'rows' => $record['rows']];
        }
    }
    usort($batches, static fn(array $a, array $b): int => (int) $b['time'] <=> (int) $a['time']);

    $latestRows = $currentRows ?: (array) ($batches[0]['rows'] ?? []);
    $previousRows = (array) ($batches[1]['rows'] ?? []);
    $previousByKey = [];
    foreach ($previousRows as $row) {
        $key = implode('|', [
            $row['providerKey'] ?? '',
            $row['eventId'] ?? '',
            $row['marketType'] ?? '',
            $row['lineKey'] ?? '',
            $row['selectionKey'] ?? '',
        ]);
        $previousByKey[$key] = $row;
    }

    $rapidMoves = 0;
    $largestMove = 0;
    $earlySharpBookMoves = 0;
    $sharpBooks = ['pinnacle', 'circa'];
    foreach ($latestRows as $row) {
        $key = implode('|', [
            $row['providerKey'] ?? '',
            $row['eventId'] ?? '',
            $row['marketType'] ?? '',
            $row['lineKey'] ?? '',
            $row['selectionKey'] ?? '',
        ]);
        $previous = $previousByKey[$key] ?? null;
        if (!$previous) {
            continue;
        }

        $move = abs((int) ($row['oddsAmerican'] ?? 0) - (int) ($previous['oddsAmerican'] ?? 0));
        $largestMove = max($largestMove, $move);
        if ($move >= 15) {
            $rapidMoves += 1;
            if (in_array(strtolower((string) ($row['providerKey'] ?? '')), $sharpBooks, true)) {
                $earlySharpBookMoves += 1;
            }
        }
    }

    return [
        'batchesCompared' => count($batches),
        'rapidMoves' => $rapidMoves,
        'largestMove' => $largestMove,
        'earlySharpBookMoves' => $earlySharpBookMoves,
    ];
}

function lineforge_public_intelligence_module(string $key, string $name, string $mode, string $status, string $fallback, string $detail): array
{
    $safeKey = preg_replace('/[^A-Z0-9_]+/', '_', strtoupper($key)) ?: 'MODULE';
    $override = strtolower(trim((string) getenv('AEGIS_DATA_MODULE_' . $safeKey . '_MODE')));
    $allowedModes = ['disabled', 'public/free', 'partial', 'premium'];
    if (in_array($override, $allowedModes, true)) {
        $mode = $override;
    }
    if ($mode === 'disabled') {
        $status = 'Disabled';
        $detail = 'Disabled by configuration. Fallback: ' . $fallback;
    }

    return [
        'key' => strtolower((string) preg_replace('/[^a-z0-9_-]+/i', '-', $key)),
        'name' => $name,
        'mode' => $mode,
        'status' => $status,
        'fallback' => $fallback,
        'detail' => $detail,
    ];
}

function lineforge_public_intelligence_modules(array $marketAccess, array $remoteTransport, array $snapshot, array $fatigue, array $marketInference, array $predictions): array
{
    $summaryAvailable = count(array_filter($predictions, static fn(array $prediction): bool => !empty($prediction['autoContext']['summaryAvailable']))) > 0;
    $weatherAvailable = count(array_filter($predictions, static fn(array $prediction): bool => !empty($prediction['autoContext']['weather']['available']))) > 0;
    $injuryAvailable = count(array_filter($predictions, static fn(array $prediction): bool => !empty($prediction['autoContext']['injuryAvailable']))) > 0;

    return [
        lineforge_public_intelligence_module('espn_scoreboard', 'ESPN scoreboard APIs', !empty($remoteTransport['https_capable']) ? 'public/free' : 'partial', !empty($remoteTransport['https_capable']) ? 'Connected' : 'Cached fallback', 'Cached game archive and built-in fallback slate', 'Schedules, scores, standings-ish records, status, venues, and public odds snippets when available.'),
        lineforge_public_intelligence_module('espn_summary', 'ESPN summary APIs', $summaryAvailable ? 'public/free' : 'partial', $summaryAvailable ? 'Attached' : 'Partial', 'Scoreboard-only context', 'Boxscore, leaders, injuries, officials, ATS snippets, and matchup detail are parsed when public summaries return data.'),
        lineforge_public_intelligence_module('open_meteo', 'Open-Meteo', $weatherAvailable ? 'public/free' : 'partial', $weatherAvailable ? 'Weather attached' : 'Venue/geocode dependent', 'Indoor or weather-neutral factor', 'No-key venue weather for outdoor games using city/state geocoding and forecast data.'),
        lineforge_public_intelligence_module('nba_stats', 'NBA stats APIs', getenv('AEGIS_NBA_STATS_PUBLIC') ? 'public/free' : 'partial', getenv('AEGIS_NBA_STATS_PUBLIC') ? 'Enabled' : 'Ready for public connector', 'ESPN NBA scoreboard and summaries', 'Reserved for NBA public stats endpoints where access is legally and technically stable.'),
        lineforge_public_intelligence_module('nflverse', 'nflverse', getenv('AEGIS_NFLVERSE_DATA_PATH') ? 'public/free' : 'partial', getenv('AEGIS_NFLVERSE_DATA_PATH') ? 'Local dataset configured' : 'Ready for local import', 'ESPN NFL scoreboard/history', 'Designed for local nflverse schedule/play/history imports so Lineforge can build its own NFL baseline.'),
        lineforge_public_intelligence_module('public_schedules_standings', 'Public schedules and standings', 'public/free', 'Active', 'Stored game archive', 'Public schedule windows and team records feed rest, trend, and slate context.'),
        lineforge_public_intelligence_module('public_injuries_lineups', 'Public injury and lineup reports', $injuryAvailable ? 'public/free' : 'partial', $injuryAvailable ? 'Injuries parsed' : 'Partial', 'Missing-data penalty instead of invented certainty', 'Public summaries are used when available; missing reports lower readiness instead of creating fake confidence.'),
        lineforge_public_intelligence_module('odds_api_free', 'The Odds API free tier', !empty($marketAccess['oddsProviderConfigured']) ? 'public/free' : 'partial', !empty($marketAccess['oddsProviderConfigured']) ? 'Configured' : 'Needs key', 'Consensus from stored snapshots and public market snippets', 'Free-tier/legal odds feed for h2h, spreads, totals, and bookmaker comparison where available.'),
        lineforge_public_intelligence_module('public_bookmaker_odds', 'Public bookmaker odds', ((int) ($marketAccess['availableLines'] ?? 0)) > 0 ? 'public/free' : 'partial', ((int) ($marketAccess['availableLines'] ?? 0)) > 0 ? 'Available through licensed feed' : 'Data-only/legality gated', 'No scraping; provider links and licensed odds only', 'Lineforge will not scrape or automate sportsbooks; only legal public/licensed access is used.'),
        lineforge_public_intelligence_module('premium_apis', 'Premium APIs', 'premium', 'Optional later', 'Public intelligence mode remains active', 'SportsDataIO, Sportradar, paid odds feeds, and approved sportsbook feeds can upgrade depth without breaking the public stack.'),
    ];
}

function lineforge_public_intelligence_systems(array $snapshot, array $fatigue, array $marketInference): array
{
    return [
        ['name' => 'Historical odds snapshot storage', 'status' => ($snapshot['oddsRowsStored'] ?? 0) > 0 ? 'Active' : 'Waiting for odds rows', 'value' => (string) ($snapshot['oddsRowsStored'] ?? 0), 'detail' => 'Stores compact normalized odds rows for line movement and volatility analysis.'],
        ['name' => 'Historical game archive', 'status' => 'Active', 'value' => (string) ($snapshot['gameRowsStored'] ?? 0), 'detail' => 'Stores public scoreboard snapshots for trends, rest, travel, and backtesting.'],
        ['name' => 'Team trend database', 'status' => ($snapshot['gameRowsStored'] ?? 0) > 0 ? 'Building' : 'Needs history', 'value' => (string) ($snapshot['gameBatches'] ?? 0) . ' batches', 'detail' => 'Builds rolling team context from archived public game snapshots.'],
        ['name' => 'Travel/fatigue calculator', 'status' => ($fatigue['gamesAnalyzed'] ?? 0) > 0 ? 'Active' : 'Building', 'value' => (string) ($fatigue['travelFlags'] ?? 0) . ' travel flags', 'detail' => 'Uses archived team schedule and venue changes to infer travel watch spots.'],
        ['name' => 'Rest-day calculator', 'status' => ($fatigue['gamesAnalyzed'] ?? 0) > 0 ? 'Active' : 'Building', 'value' => (string) ($fatigue['shortRestFlags'] ?? 0) . ' short-rest flags', 'detail' => 'Calculates rest hours from stored schedule snapshots.'],
        ['name' => 'Line movement tracker', 'status' => (($marketInference['lineVelocity']['batchesCompared'] ?? 0) >= 2) ? 'Active' : 'Needs snapshot history', 'value' => (string) ($marketInference['lineVelocity']['largestMove'] ?? 0) . ' cents', 'detail' => 'Compares stored odds snapshots to infer line velocity.'],
        ['name' => 'Consensus odds engine', 'status' => (($marketInference['marketDisagreement']['flaggedOutcomes'] ?? 0) > 0) ? 'Disagreement found' : 'Stable/Waiting', 'value' => (string) ($marketInference['marketDisagreement']['flaggedOutcomes'] ?? 0), 'detail' => 'Compares books and no-vig consensus without giving bad/stale data equal authority.'],
        ['name' => 'Volatility tracker', 'status' => ($marketInference['volatilityScore'] ?? 0) >= 35 ? 'Elevated' : 'Normal', 'value' => (string) ($marketInference['volatilityScore'] ?? 0) . '/100', 'detail' => 'Ranks market turbulence using movement history and cross-book disagreement.'],
        ['name' => 'Steam-style movement inference', 'status' => (($marketInference['lineVelocity']['rapidMoves'] ?? 0) > 0) ? 'Movement detected' : 'No signal', 'value' => (string) ($marketInference['lineVelocity']['rapidMoves'] ?? 0), 'detail' => 'Flags rapid movement only; it does not claim verified sharp money.'],
        ['name' => 'Market disagreement engine', 'status' => (($marketInference['marketDisagreement']['flaggedOutcomes'] ?? 0) > 0) ? 'Active flags' : 'Monitoring', 'value' => (string) ($marketInference['marketDisagreement']['maxDecimalRange'] ?? 0), 'detail' => 'Highlights abnormal spread between books for manual review.'],
    ];
}

function lineforge_public_intelligence_build_state(array $games, array $predictions, array $marketAccess, array $arbitrage, array $remoteTransport, int $refreshSeconds): array
{
    $snapshot = lineforge_public_intelligence_record_snapshots($games, $predictions, $marketAccess, $arbitrage);
    $fatigue = lineforge_public_intelligence_fatigue($games);
    $marketInference = lineforge_public_intelligence_market_inference($arbitrage);
    $modules = lineforge_public_intelligence_modules($marketAccess, $remoteTransport, $snapshot, $fatigue, $marketInference, $predictions);
    $systems = lineforge_public_intelligence_systems($snapshot, $fatigue, $marketInference);
    $warehouse = lineforge_warehouse_record_cycle($games, $predictions, $marketAccess, $arbitrage);
    $calibration = is_array($warehouse['calibration'] ?? null) ? $warehouse['calibration'] : [
        'status' => 'collecting_baseline',
        'sampleCount' => 0,
        'closedSamples' => 0,
        'minimumClosedSamples' => 100,
        'message' => 'Collecting closed historical predictions before showing accuracy or calibration claims.',
        'buckets' => [],
    ];
    $warehouseCounts = is_array($warehouse['counts'] ?? null) ? $warehouse['counts'] : [];
    $warehouseDriver = (string) ($warehouse['driver'] ?? 'unavailable');
    $warehouseStatus = $warehouseDriver === 'sqlite3'
        ? 'SQLite active'
        : (!empty($warehouse['available']) ? 'JSONL fallback' : 'Unavailable');
    $systems[] = [
        'name' => 'Event and market warehouse',
        'status' => $warehouseStatus,
        'value' => (string) ($warehouseCounts['ingestionRuns'] ?? 0) . ' runs',
        'detail' => (string) ($warehouse['message'] ?? 'Historical warehouse is collecting operational snapshots.'),
    ];
    $systems[] = [
        'name' => 'Prediction replay system',
        'status' => (((int) ($warehouseCounts['predictions'] ?? 0)) > 0 || !empty($snapshot['predictionSnapshotWritten'])) ? 'Collecting' : 'Waiting',
        'value' => (string) (($warehouseCounts['predictions'] ?? 0) ?: (!empty($snapshot['predictionSnapshotWritten']) ? count($predictions) : 0)) . ' rows',
        'detail' => 'Stores what Lineforge knew at prediction time for future replay and audit review.',
    ];
    $systems[] = [
        'name' => 'Calibration history',
        'status' => (($calibration['status'] ?? '') === 'calibrating') ? 'Calibrating' : 'Collecting baseline',
        'value' => (string) ($calibration['closedSamples'] ?? 0) . '/' . (string) ($calibration['minimumClosedSamples'] ?? 100),
        'detail' => (string) ($calibration['message'] ?? 'Closed samples are required before accuracy or calibration claims.'),
    ];
    $systems[] = [
        'name' => 'Brier score tracking',
        'status' => (($calibration['brierScore'] ?? null) !== null) ? 'Measuring' : 'Waiting for finals',
        'value' => (($calibration['brierScore'] ?? null) !== null) ? (string) ($calibration['brierScore']) : 'Pending',
        'detail' => 'Grades probabilistic estimates against closed outcomes so Lineforge can measure calibration instead of claiming certainty.',
    ];
    $systems[] = [
        'name' => 'Replay reconstruction',
        'status' => (string) ($warehouse['replay']['status'] ?? 'waiting_for_snapshots'),
        'value' => (string) ($warehouse['latestRunAt'] ?? ''),
        'detail' => (string) ($warehouse['replay']['message'] ?? 'Replay mode reconstructs what the system knew at a selected snapshot.'),
    ];
    $evolution = lineforge_evolution_build_state($games, $predictions, $marketAccess, $arbitrage, $warehouse, $calibration, $marketInference, $refreshSeconds);
    $adaptive = lineforge_adaptive_build_state($evolution, $warehouse, $calibration, $marketInference, $arbitrage);
    $generalized = lineforge_generalized_build_state($adaptive, $evolution, $warehouse, $calibration, $marketInference);
    $systems[] = [
        'name' => 'Feature pipeline',
        'status' => (string) ($evolution['featurePipeline']['status'] ?? 'waiting_for_predictions'),
        'value' => (string) ($evolution['summary']['featureRows'] ?? 0) . ' rows',
        'detail' => 'Extracts transparent model features from predictions, data quality, odds depth, volatility, timing, and provider context.',
    ];
    $systems[] = [
        'name' => 'Model readiness gate',
        'status' => (string) ($evolution['summary']['modelReadiness'] ?? 'collecting_labels'),
        'value' => (string) ($evolution['summary']['trainableRows'] ?? 0) . ' labels',
        'detail' => 'Logistic regression and boosting remain blocked until enough closed replay-safe labels exist.',
    ];
    $systems[] = [
        'name' => 'Market regime detector',
        'status' => (string) ($evolution['summary']['activeRegime'] ?? 'unknown'),
        'value' => (string) ($evolution['marketStructure']['metrics']['volatilityScore'] ?? 0) . '/100',
        'detail' => (string) ($evolution['marketStructure']['detail'] ?? 'Classifies market structure without claiming hidden order flow.'),
    ];
    $systems[] = [
        'name' => 'Signal object layer',
        'status' => 'multi-dimensional',
        'value' => (string) ($evolution['summary']['signalObjects'] ?? 0),
        'detail' => 'Builds layered signal objects for confidence, volatility, provider agreement, liquidity, calibration support, timing, and execution feasibility.',
    ];
    $systems[] = [
        'name' => 'Adaptive intelligence network',
        'status' => (string) ($adaptive['summary']['networkStatus'] ?? 'collecting_memory'),
        'value' => (string) ($adaptive['summary']['compositeReadiness'] ?? 0) . '/100',
        'detail' => 'Coordinates independent intelligence layers while exposing uncertainty and allowing layer-specific failure.',
    ];
    $systems[] = [
        'name' => 'Self-evaluation monitors',
        'status' => (string) ($adaptive['summary']['selfEvaluationStatus'] ?? 'self_evaluating'),
        'value' => (string) ($adaptive['selfEvaluation']['problemCount'] ?? 0) . ' watch items',
        'detail' => 'Continuously asks where Lineforge is becoming unreliable before promoting confidence or execution posture.',
    ];
    $systems[] = [
        'name' => 'Resilience orchestration',
        'status' => (string) ($adaptive['summary']['resilienceStatus'] ?? 'collecting_memory'),
        'value' => (string) count((array) ($adaptive['resilience']['systems'] ?? [])) . ' systems',
        'detail' => 'Keeps public intelligence, storage fallback, stale-data isolation, and provider fallback visible as operational states.',
    ];
    $systems[] = [
        'name' => 'Explanation layer',
        'status' => 'explainable_watch',
        'value' => (string) ($adaptive['summary']['explanations'] ?? 0),
        'detail' => 'Turns layer uncertainty, model gates, market regime, and provider posture into operator-readable reasoning.',
    ];
    $systems[] = [
        'name' => 'Generalized intelligence core',
        'status' => (string) ($generalized['summary']['status'] ?? 'domain_agnostic_foundation'),
        'value' => (string) ($generalized['summary']['coreReadiness'] ?? 0) . '/100',
        'detail' => 'Abstracts Lineforge into reusable event, signal, state, workflow, memory, orchestration, and governance primitives.',
    ];
    $systems[] = [
        'name' => 'Universal primitive model',
        'status' => 'active',
        'value' => (string) (($generalized['summary']['eventPrimitives'] ?? 0) + ($generalized['summary']['signalPrimitives'] ?? 0)) . ' primitives',
        'detail' => 'Defines domain-agnostic events, signals, states, and workflows so sports becomes one adapter instead of the whole platform.',
    ];
    $systems[] = [
        'name' => 'Decision governance layer',
        'status' => (string) ($generalized['governance']['status'] ?? 'governance_first'),
        'value' => (string) ($generalized['summary']['governanceSystems'] ?? 0) . ' controls',
        'detail' => 'Keeps explainability, confidence transparency, audit, risk, stale-data enforcement, and safety degradation visible across domains.',
    ];
    $activePublic = count(array_filter($modules, static fn(array $module): bool => in_array($module['mode'], ['public/free', 'partial'], true) && !str_contains(strtolower((string) $module['status']), 'disabled')));
    $premiumConfigured = count(array_filter($modules, static fn(array $module): bool => $module['mode'] === 'premium' && !str_contains(strtolower((string) $module['status']), 'optional')));
    $mode = $premiumConfigured > 0 ? 'hybrid intelligence' : 'public intelligence';

    return [
        'mode' => $mode,
        'priorityOrder' => ['Reliability', 'Freshness', 'Normalization', 'Historical storage', 'Inference systems', 'Premium APIs later'],
        'summary' => [
            'mode' => ucwords($mode),
            'activePublicModules' => $activePublic,
            'premiumModules' => $premiumConfigured,
            'gameRowsStored' => $snapshot['gameRowsStored'],
            'oddsRowsStored' => $snapshot['oddsRowsStored'],
            'inferenceSignals' => count($marketInference['signals'] ?? []),
            'refreshCadence' => max(30, $refreshSeconds) . 's UI / cached source fetches',
            'warehouseDriver' => $warehouseDriver,
            'warehouseStatus' => $warehouseStatus,
            'warehouseRuns' => (int) ($warehouseCounts['ingestionRuns'] ?? 0),
            'warehouseRows' => array_sum(array_map(static fn($value): int => is_numeric($value) ? (int) $value : 0, $warehouseCounts)),
            'calibrationClosedSamples' => (int) ($calibration['closedSamples'] ?? 0),
            'calibrationStatus' => (string) ($calibration['status'] ?? 'collecting_baseline'),
            'brierScore' => $calibration['brierScore'] ?? null,
            'calibrationError' => $calibration['calibrationError'] ?? null,
            'replayStatus' => (string) ($warehouse['replay']['status'] ?? 'waiting_for_snapshots'),
            'workerStatus' => (string) ($warehouse['operational']['worker']['status'] ?? 'not_started'),
            'featureRows' => (int) ($evolution['summary']['featureRows'] ?? 0),
            'trainableRows' => (int) ($evolution['summary']['trainableRows'] ?? 0),
            'modelReadiness' => (string) ($evolution['summary']['modelReadiness'] ?? 'collecting_labels'),
            'activeMarketRegime' => (string) ($evolution['summary']['activeRegime'] ?? 'unknown'),
            'signalObjects' => (int) ($evolution['summary']['signalObjects'] ?? 0),
            'observabilityStatus' => (string) ($evolution['summary']['observabilityStatus'] ?? 'unknown'),
            'adaptiveNetworkStatus' => (string) ($adaptive['summary']['networkStatus'] ?? 'collecting_memory'),
            'adaptiveCompositeReadiness' => (float) ($adaptive['summary']['compositeReadiness'] ?? 0),
            'adaptiveLayers' => (int) ($adaptive['summary']['layers'] ?? 0),
            'adaptiveHighUncertaintyLayers' => (int) ($adaptive['summary']['highUncertaintyLayers'] ?? 0),
            'generalizedCoreStatus' => (string) ($generalized['summary']['status'] ?? 'domain_agnostic_foundation'),
            'generalizedCoreReadiness' => (float) ($generalized['summary']['coreReadiness'] ?? 0),
            'generalizedDomainAdapters' => (int) ($generalized['summary']['domainAdapters'] ?? 0),
            'generalizedMemoryStores' => (int) ($generalized['summary']['memoryStores'] ?? 0),
        ],
        'modules' => $modules,
        'systems' => $systems,
        'fatigue' => $fatigue,
        'marketInference' => $marketInference,
        'storage' => $snapshot,
        'warehouse' => $warehouse,
        'calibration' => $calibration,
        'intelligenceEvolution' => $evolution,
        'adaptiveIntelligence' => $adaptive,
        'generalizedIntelligence' => $generalized,
        'operatorWorkflows' => [
            ['name' => 'Ingest', 'status' => $warehouseStatus, 'detail' => 'Collect scoreboard, odds, prediction, provider-health, volatility, and line-movement timelines.'],
            ['name' => 'Compare', 'status' => (($marketInference['marketDisagreement']['flaggedOutcomes'] ?? 0) > 0) ? 'Flags active' : 'Monitoring', 'detail' => 'Normalize markets and compare provider disagreement without claiming verified sharp money.'],
            ['name' => 'Verify', 'status' => 'Manual-first', 'detail' => 'Require final line, eligibility, injuries, and provider health checks before action.'],
            ['name' => 'Calibrate', 'status' => (string) ($calibration['status'] ?? 'collecting_baseline'), 'detail' => (string) ($calibration['message'] ?? 'Collecting baseline samples.')],
            ['name' => 'Replay', 'status' => (string) ($warehouse['replay']['status'] ?? 'waiting_for_snapshots'), 'detail' => (string) ($warehouse['replay']['message'] ?? 'Warehouse snapshots feed replay mode.')],
            ['name' => 'Audit', 'status' => 'Append-only posture', 'detail' => 'Execution and intelligence events are retained for review instead of overwritten by UI state.'],
        ],
        'productPhilosophy' => [
            'is' => [
                'Operator-focused',
                'Audit-aware',
                'Provider-transparent',
                'Calibration-driven',
                'Execution-aware',
                'Paper-first',
                'Institutional',
                'Uncertainty-aware',
                'Replayable',
                'Resilient',
                'Memory-centered',
                'Trust-preserving',
            ],
            'isNot' => [
                'Guaranteed-profit AI',
                'Blind prediction engine',
                'Casino software',
                'Hype-driven betting app',
                'Fake certainty platform',
                'More-AI theater',
                'Unsupported execution automation',
                'Feature bloat',
                'Black-box opacity',
            ],
            'statement' => 'Lineforge is disciplined probabilistic decision infrastructure: it helps operators ingest, normalize, evaluate, calibrate, orchestrate, execute only where compliant, replay context, and audit decisions.',
            'doctrine' => (string) ($generalized['identity']['doctrine'] ?? 'Improve signal quality, calibration, resilience, orchestration, transparency, operator trust, and workflow clarity before adding more model surface area.'),
            'highestGoal' => (string) ($generalized['identity']['highestGoal'] ?? 'Help humans make better decisions under uncertainty without pretending uncertainty disappears.'),
            'continuousRefinement' => (string) ($generalized['identity']['continuousRefinement'] ?? 'Mature systems evolve through continuous refinement.'),
            'signalStandard' => (string) ($generalized['identity']['signalStandard'] ?? 'Optimize for signal over stimulation.'),
            'maturityDoctrine' => (string) ($generalized['identity']['maturityDoctrine'] ?? 'The most valuable asset is the accumulated intelligence ecosystem around the platform.'),
            'maturityAxiom' => (string) ($generalized['identity']['maturityAxiom'] ?? 'Refinement without corruption: become more truthful, resilient, adaptive, and trustworthy over time.'),
            'qualityTargets' => (array) ($generalized['identity']['qualityTargets'] ?? []),
            'accumulatedEcosystem' => (array) ($generalized['identity']['accumulatedEcosystem'] ?? []),
            'refinementGuardrails' => (array) ($generalized['identity']['refinementGuardrails'] ?? []),
            'failureModes' => (array) ($generalized['identity']['failureModes'] ?? []),
            'driftRisks' => (array) ($generalized['identity']['driftRisks'] ?? []),
            'enduringQualities' => (array) ($generalized['identity']['enduringQualities'] ?? []),
            'operatorThinkingSupport' => (array) ($generalized['identity']['operatorThinkingSupport'] ?? []),
            'operatorContract' => (array) ($generalized['identity']['operatorContract'] ?? []),
        ],
        'compliance' => [
            'No sportsbook scraping or unofficial wagering automation.',
            'No verified sharp-money claims unless a source explicitly proves order flow or account class.',
            'Confidence is displayed as research readiness and calibration estimate, not a guaranteed prediction.',
            'Premium providers enhance depth, but public intelligence mode remains functional without them.',
        ],
    ];
}
