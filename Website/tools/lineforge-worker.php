<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/aegis_sports.php';

function lineforge_worker_arg(string $name, string $fallback): string
{
    global $argv;
    foreach ((array) $argv as $arg) {
        if (str_starts_with((string) $arg, '--' . $name . '=')) {
            return substr((string) $arg, strlen($name) + 3);
        }
    }

    return $fallback;
}

function lineforge_worker_int(string $argName, string $envName, int $fallback, int $min, int $max): int
{
    $value = (int) lineforge_worker_arg($argName, aegis_env($envName, (string) $fallback));
    return max($min, min($max, $value));
}

$cycles = lineforge_worker_int('cycles', 'LINEFORGE_WORKER_CYCLES', 1, 1, 288);
$sleepSeconds = lineforge_worker_int('sleep', 'LINEFORGE_WORKER_SLEEP_SECONDS', 0, 0, 3600);
$limits = [
    'tracked_games' => lineforge_worker_int('tracked-games', 'LINEFORGE_WORKER_TRACKED_GAMES', 50, 1, 250),
    'models' => lineforge_worker_int('models', 'LINEFORGE_WORKER_MODELS', 8, 1, 25),
    'refresh_seconds' => lineforge_worker_int('refresh-seconds', 'LINEFORGE_WORKER_REFRESH_SECONDS', 60, 5, 900),
];

$storageDir = dirname(__DIR__) . '/storage';
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0775, true);
}

$lockPath = $storageDir . '/lineforge-worker.lock';
$lock = fopen($lockPath, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Lineforge worker is already running.\n");
    exit(2);
}

$startedAt = gmdate('c');
$results = [];
for ($cycle = 1; $cycle <= $cycles; $cycle += 1) {
    $cycleStarted = microtime(true);
    try {
        $state = aegis_sports_state($limits);
        $architecture = is_array($state['dataArchitecture'] ?? null) ? $state['dataArchitecture'] : [];
        $warehouse = is_array($architecture['warehouse'] ?? null) ? $architecture['warehouse'] : [];
        $summary = is_array($architecture['summary'] ?? null) ? $architecture['summary'] : [];
        $result = [
            'cycle' => $cycle,
            'ok' => true,
            'completedAt' => gmdate('c'),
            'durationMs' => (int) round((microtime(true) - $cycleStarted) * 1000),
            'games' => count((array) ($state['games'] ?? [])),
            'predictions' => count((array) ($state['predictions'] ?? [])),
            'warehouseStatus' => (string) ($summary['warehouseStatus'] ?? $warehouse['status'] ?? 'unknown'),
            'warehouseDriver' => (string) ($summary['warehouseDriver'] ?? $warehouse['driver'] ?? 'unknown'),
            'warehouseRuns' => (int) ($summary['warehouseRuns'] ?? $warehouse['counts']['ingestionRuns'] ?? 0),
            'calibrationClosedSamples' => (int) ($summary['calibrationClosedSamples'] ?? $warehouse['calibration']['closedSamples'] ?? 0),
            'replayStatus' => (string) ($summary['replayStatus'] ?? $warehouse['replay']['status'] ?? 'waiting_for_snapshots'),
        ];
        $results[] = $result;
        echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } catch (Throwable $error) {
        $result = [
            'cycle' => $cycle,
            'ok' => false,
            'completedAt' => gmdate('c'),
            'durationMs' => (int) round((microtime(true) - $cycleStarted) * 1000),
            'message' => $error->getMessage(),
        ];
        $results[] = $result;
        echo json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }

    if ($cycle < $cycles && $sleepSeconds > 0) {
        sleep($sleepSeconds);
    }
}

$runSummary = [
    'type' => 'lineforge_worker_run',
    'startedAt' => $startedAt,
    'completedAt' => gmdate('c'),
    'cycles' => $cycles,
    'limits' => $limits,
    'results' => $results,
];
@file_put_contents(
    lineforge_warehouse_jsonl_path('warehouse-worker-runs'),
    json_encode($runSummary, JSON_UNESCAPED_SLASHES) . PHP_EOL,
    FILE_APPEND | LOCK_EX
);

flock($lock, LOCK_UN);
fclose($lock);

exit(count(array_filter($results, static fn(array $result): bool => empty($result['ok']))) > 0 ? 1 : 0);
