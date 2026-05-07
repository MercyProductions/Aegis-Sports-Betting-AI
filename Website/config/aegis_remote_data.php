<?php

require_once __DIR__ . '/env.php';

function aegis_remote_data_allow_insecure_ssl(): bool
{
    $flag = getenv('AEGIS_REMOTE_ALLOW_INSECURE_SSL');
    return in_array(strtolower((string) $flag), ['1', 'true', 'yes', 'on'], true);
}

function aegis_remote_data_external_curl_path(): string
{
    static $resolved = null;

    if ($resolved !== null) {
        return $resolved;
    }

    $resolved = '';
    if (!function_exists('shell_exec')) {
        return $resolved;
    }

    $command = PHP_OS_FAMILY === 'Windows'
        ? 'where curl.exe 2>NUL'
        : 'command -v curl 2>/dev/null';
    $output = @shell_exec($command);
    if (!is_string($output) || trim($output) === '') {
        return $resolved;
    }

    $candidate = trim(strtok($output, "\r\n") ?: '');
    if ($candidate !== '' && (PHP_OS_FAMILY === 'Windows' || is_executable($candidate))) {
        $resolved = $candidate;
    }

    return $resolved;
}

function aegis_remote_data_transport_state(): array
{
    $curl = function_exists('curl_init');
    $openssl = extension_loaded('openssl');
    $allowUrlFopen = filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    $allowUrlFopen = $allowUrlFopen ?? (string) ini_get('allow_url_fopen') === '1';
    $externalCurl = aegis_remote_data_external_curl_path() !== '';

    if ($curl) {
        return [
            'https_capable' => true,
            'mode' => 'curl',
            'curl' => true,
            'openssl' => $openssl,
            'allow_url_fopen' => $allowUrlFopen,
            'external_curl' => $externalCurl,
            'message' => 'HTTPS feed transport ready via cURL.',
        ];
    }

    if ($openssl && $allowUrlFopen) {
        return [
            'https_capable' => true,
            'mode' => 'streams',
            'curl' => false,
            'openssl' => true,
            'allow_url_fopen' => true,
            'external_curl' => $externalCurl,
            'message' => 'HTTPS feed transport ready via PHP streams.',
        ];
    }

    if ($externalCurl) {
        return [
            'https_capable' => true,
            'mode' => 'external-curl',
            'curl' => false,
            'openssl' => $openssl,
            'allow_url_fopen' => $allowUrlFopen,
            'external_curl' => true,
            'message' => 'HTTPS feed transport ready via system curl.',
        ];
    }

    return [
        'https_capable' => false,
        'mode' => 'none',
        'curl' => $curl,
        'openssl' => $openssl,
        'allow_url_fopen' => $allowUrlFopen,
        'external_curl' => false,
        'message' => 'HTTPS feed transport unavailable. Enable cURL or OpenSSL to refresh live external feeds.',
    ];
}

function aegis_remote_data_age_seconds(?string $iso): ?int
{
    if (!$iso) {
        return null;
    }

    $timestamp = strtotime($iso);
    if (!$timestamp) {
        return null;
    }

    return max(0, time() - $timestamp);
}

function aegis_remote_data_storage_dir(string $namespace): string
{
    $safeNamespace = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(trim($namespace))) ?: 'shared';
    $dir = dirname(__DIR__) . '/storage/cache/' . $safeNamespace;

    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return is_dir($dir) ? $dir : dirname(__DIR__) . '/storage/cache';
}

function aegis_remote_data_cache_path(string $namespace, string $key): string
{
    return aegis_remote_data_storage_dir($namespace) . '/' . md5($key) . '.json';
}

function aegis_remote_data_cache_read(string $namespace, string $key): ?array
{
    $path = aegis_remote_data_cache_path($namespace, $key);
    if (!is_file($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function aegis_remote_data_cache_write(string $namespace, string $key, array $data): void
{
    $path = aegis_remote_data_cache_path($namespace, $key);
    $payload = [
        'cached_at' => time(),
        'data' => $data,
    ];

    @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function aegis_remote_data_cached(string $namespace, string $key, int $ttlSeconds, callable $resolver): ?array
{
    $ttlSeconds = max(5, $ttlSeconds);
    $cached = aegis_remote_data_cache_read($namespace, $key);
    $cachedData = is_array($cached['data'] ?? null) ? $cached['data'] : null;
    $cachedAt = (int) ($cached['cached_at'] ?? 0);

    if ($cachedData !== null && (time() - $cachedAt) <= $ttlSeconds) {
        return $cachedData;
    }

    $fresh = call_user_func($resolver);
    if (is_array($fresh)) {
        aegis_remote_data_cache_write($namespace, $key, $fresh);
        return $fresh;
    }

    return $cachedData;
}

function aegis_remote_data_http_json(string $url, float $timeout = 10.0, array $headers = []): ?array
{
    if (!preg_match('#^https?://#i', $url)) {
        return null;
    }

    $headerLines = array_merge(
        [
            'Accept: application/json',
            'User-Agent: AegisPlatform/1.0 (+https://localhost)',
        ],
        $headers
    );

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            $allowInsecure = aegis_remote_data_allow_insecure_ssl();
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => max(2, min(30, (int) ceil($timeout))),
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_SSL_VERIFYPEER => !$allowInsecure,
                CURLOPT_SSL_VERIFYHOST => $allowInsecure ? 0 : 2,
            ]);

            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($status >= 200 && $status < 300 && is_string($response) && $response !== '') {
                $decoded = json_decode($response, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headerLines) . "\r\n",
            'timeout' => max(2.0, min(30.0, $timeout)),
            'ignore_errors' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    $status = 0;
    $responseHeaders = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];

    if (!empty($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', (string) $responseHeaders[0], $matches)) {
        $status = (int) $matches[1];
    }

    if ($status >= 200 && $status < 300 && is_string($response) && $response !== '') {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $externalDecoded = aegis_remote_data_external_curl_json($url, $timeout, $headerLines);
    if (is_array($externalDecoded)) {
        return $externalDecoded;
    }

    return null;
}

function aegis_remote_data_external_curl_json(string $url, float $timeout, array $headerLines): ?array
{
    $curlPath = aegis_remote_data_external_curl_path();
    if ($curlPath === '') {
        return null;
    }

    $arguments = [
        $curlPath,
        '--silent',
        '--fail',
        '--location',
        '--max-time',
        (string) max(2, min(30, (int) ceil($timeout))),
    ];
    foreach ($headerLines as $headerLine) {
        $arguments[] = '-H';
        $arguments[] = $headerLine;
    }
    $arguments[] = $url;

    if (function_exists('proc_open')) {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = @proc_open($arguments, $descriptors, $pipes);
        if (is_resource($process)) {
            $response = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            if ($exitCode === 0 && is_string($response) && $response !== '') {
                $decoded = json_decode($response, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }
    }

    if (!function_exists('shell_exec')) {
        return null;
    }

    $parts = array_map('aegis_remote_data_shell_arg', $arguments);
    $externalResponse = @shell_exec(implode(' ', $parts));
    if (is_string($externalResponse) && $externalResponse !== '') {
        $externalDecoded = json_decode($externalResponse, true);
        if (is_array($externalDecoded)) {
            return $externalDecoded;
        }
    }

    return null;
}

function aegis_remote_data_shell_arg(string $value): string
{
    if (PHP_OS_FAMILY === 'Windows') {
        return '"' . str_replace('"', '\"', $value) . '"';
    }

    return escapeshellarg($value);
}

function aegis_remote_data_relative_time(?string $iso): string
{
    if (!$iso) {
        return 'Now';
    }

    $timestamp = strtotime($iso);
    if (!$timestamp) {
        return 'Now';
    }

    $delta = time() - $timestamp;
    if ($delta < 60) {
        return max(1, $delta) . 's ago';
    }

    if ($delta < 3600) {
        return floor($delta / 60) . 'm ago';
    }

    if ($delta < 86400) {
        return floor($delta / 3600) . 'h ago';
    }

    return floor($delta / 86400) . 'd ago';
}

function aegis_remote_data_format_short_date(?string $iso): string
{
    if (!$iso) {
        return 'TBD';
    }

    try {
        $date = new DateTimeImmutable($iso);
    } catch (Exception $exception) {
        return 'TBD';
    }

    $timezone = getenv('AEGIS_DISPLAY_TIMEZONE')
        ?: getenv('APP_TIMEZONE')
        ?: getenv('TZ')
        ?: 'America/Chicago';

    try {
        $date = $date->setTimezone(new DateTimeZone($timezone));
    } catch (Exception $exception) {
        $date = $date->setTimezone(new DateTimeZone('America/Chicago'));
    }

    return $date->format('M j g:i A');
}
