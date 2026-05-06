<?php

function aegis_env_file_candidates(): array
{
    $root = dirname(__DIR__);
    return [
        $root . DIRECTORY_SEPARATOR . '.env',
        $root . DIRECTORY_SEPARATOR . '.env.local',
    ];
}

function aegis_env_parse_value(string $value): string
{
    $value = trim($value);

    if (
        (str_starts_with($value, '"') && str_ends_with($value, '"'))
        || (str_starts_with($value, "'") && str_ends_with($value, "'"))
    ) {
        $value = substr($value, 1, -1);
    }

    return str_replace(['\n', '\r'], ["\n", "\r"], $value);
}

function aegis_env_load(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    $loaded = true;

    foreach (aegis_env_file_candidates() as $path) {
        if (!is_file($path)) {
            continue;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (!$lines) {
            continue;
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $position = strpos($line, '=');
            if ($position === false) {
                continue;
            }

            $name = trim(substr($line, 0, $position));
            $value = aegis_env_parse_value(substr($line, $position + 1));

            if ($name === '' || preg_match('/^[A-Z0-9_]+$/i', $name) !== 1) {
                continue;
            }

            if (getenv($name) === false) {
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

function aegis_env(string $name, string $fallback = ''): string
{
    aegis_env_load();

    $value = getenv($name);
    return $value === false || $value === '' ? $fallback : (string) $value;
}

function aegis_env_bool(string $name, bool $fallback = false): bool
{
    $value = aegis_env($name, $fallback ? '1' : '0');
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

function aegis_env_is_local_request(): bool
{
    $host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $serverAddr = strtolower((string) ($_SERVER['SERVER_ADDR'] ?? ''));
    $remoteAddr = strtolower((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

    return in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
        || in_array($serverAddr, ['127.0.0.1', '::1'], true)
        || in_array($remoteAddr, ['127.0.0.1', '::1'], true);
}

function aegis_app_environment(): string
{
    $configured = strtolower(trim(aegis_env('AEGIS_APP_ENV', aegis_env('APP_ENV', ''))));
    if ($configured !== '') {
        return $configured;
    }

    return aegis_env_is_local_request() ? 'local' : 'production';
}

function aegis_is_production(): bool
{
    return in_array(aegis_app_environment(), ['prod', 'production', 'live'], true);
}

function aegis_public_origin(): string
{
    $configured = rtrim(aegis_env('AEGIS_PUBLIC_ORIGIN', ''), '/');
    if ($configured !== '') {
        return $configured;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');

    return ($https ? 'https://' : 'http://') . $host;
}

function aegis_env_bootstrap(): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }

    $bootstrapped = true;
    aegis_env_load();

    $devErrors = aegis_env_bool('AEGIS_DEV_ERRORS', false);
    error_reporting($devErrors ? E_ALL : 0);
    @ini_set('display_errors', $devErrors ? '1' : '0');
    @ini_set('log_errors', '1');

    @ini_set('session.use_strict_mode', '1');
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($isHttps) {
        @ini_set('session.cookie_secure', '1');
    }
}

aegis_env_bootstrap();
