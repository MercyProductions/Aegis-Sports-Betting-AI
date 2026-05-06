<?php

require_once __DIR__ . '/env.php';

function aegis_sports_product_bootstrap(): void
{
    aegis_env_bootstrap();
    aegis_sports_product_security_headers();
    aegis_sports_product_enforce_request_safety();
    aegis_sports_product_enforce_rate_limit('http', aegis_sports_product_client_ip(), aegis_sports_product_env_int('LINEFORGE_HTTP_RATE_LIMIT', 600), 60);
    aegis_sports_product_apply_provider_settings();
    if (getenv('AEGIS_SPORTS_MAX_LEAGUES_PER_REFRESH') === false) {
        putenv('AEGIS_SPORTS_MAX_LEAGUES_PER_REFRESH=16');
        $_ENV['AEGIS_SPORTS_MAX_LEAGUES_PER_REFRESH'] = '16';
        $_SERVER['AEGIS_SPORTS_MAX_LEAGUES_PER_REFRESH'] = '16';
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_name('aegis_sports_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => aegis_sports_product_is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    aegis_sports_product_harden_session();
}

function aegis_sports_product_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function aegis_sports_product_display_name(): string
{
    return trim(aegis_env('AEGIS_SPORTS_DISPLAY_NAME', 'Lineforge Operator')) ?: 'Lineforge Operator';
}

function aegis_sports_product_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function aegis_sports_product_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    $nonce = aegis_sports_product_csp_nonce();
    $scriptSrc = "'self' 'nonce-" . $nonce . "'";
    $styleSrc = "'self' 'unsafe-inline'";
    $upgradeInsecure = aegis_is_production() ? '; upgrade-insecure-requests' : '';

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=(), browsing-topics=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Origin-Agent-Cluster: ?1');
    header("Content-Security-Policy: default-src 'self'; script-src {$scriptSrc}; script-src-attr 'none'; style-src {$styleSrc}; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'{$upgradeInsecure}");

    if (aegis_sports_product_is_https()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

function aegis_sports_product_csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    return $nonce;
}

function aegis_sports_product_env_int(string $name, int $fallback): int
{
    $value = trim(aegis_env($name, (string) $fallback));
    if ($value === '' || preg_match('/^-?\d+$/', $value) !== 1) {
        return $fallback;
    }

    return (int) $value;
}

function aegis_sports_product_client_ip(): string
{
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
    if ($remote === '') {
        $remote = '0.0.0.0';
    }

    return $remote;
}

function aegis_sports_product_reject_request(int $status, string $message, array $extra = []): never
{
    http_response_code($status);
    if (!headers_sent()) {
        header('Cache-Control: no-store');
    }

    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $json = str_starts_with($path, '/api/') || str_contains($accept, 'application/json');

    if ($json) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(array_merge([
            'ok' => false,
            'message' => $message,
        ], $extra), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><meta charset="utf-8"><title>Request blocked | Lineforge</title><h1>Request blocked</h1><p>'
        . aegis_sports_product_e($message)
        . '</p>';
    exit;
}

function aegis_sports_product_same_origin(string $origin): bool
{
    $origin = trim($origin);
    if ($origin === '') {
        return true;
    }

    $requestOrigin = aegis_public_origin();
    $originParts = parse_url($origin);
    $requestParts = parse_url($requestOrigin);
    if (!is_array($originParts) || !is_array($requestParts)) {
        return false;
    }

    $originScheme = strtolower((string) ($originParts['scheme'] ?? ''));
    $requestScheme = strtolower((string) ($requestParts['scheme'] ?? ''));
    $originHost = strtolower((string) ($originParts['host'] ?? ''));
    $requestHost = strtolower((string) ($requestParts['host'] ?? ''));
    $originPort = (int) ($originParts['port'] ?? ($originScheme === 'https' ? 443 : 80));
    $requestPort = (int) ($requestParts['port'] ?? ($requestScheme === 'https' ? 443 : 80));

    return $originScheme === $requestScheme
        && $originHost === $requestHost
        && $originPort === $requestPort;
}

function aegis_sports_product_enforce_request_safety(): void
{
    $maxBytes = max(65536, aegis_sports_product_env_int('LINEFORGE_MAX_REQUEST_BYTES', 524288));
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $maxBytes) {
        aegis_sports_product_record_security_event('request_rejected', 'Request body exceeded configured limit.', 'warning', [
            'contentLength' => $contentLength,
            'maxBytes' => $maxBytes,
        ]);
        aegis_sports_product_reject_request(413, 'Request payload is too large.');
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return;
    }

    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '' && !aegis_sports_product_same_origin($origin)) {
        aegis_sports_product_record_security_event('request_rejected', 'Cross-origin unsafe request blocked.', 'warning', [
            'origin' => $origin,
            'method' => $method,
        ]);
        aegis_sports_product_reject_request(403, 'Cross-origin requests are not allowed.');
    }

    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    if ($origin === '' && $referer !== '' && !aegis_sports_product_same_origin($referer)) {
        aegis_sports_product_record_security_event('request_rejected', 'Cross-site referer request blocked.', 'warning', [
            'refererHost' => (string) (parse_url($referer, PHP_URL_HOST) ?: ''),
            'method' => $method,
        ]);
        aegis_sports_product_reject_request(403, 'Cross-origin requests are not allowed.');
    }
}

function aegis_sports_product_site_url(): string
{
    $configured = trim(aegis_env('AEGIS_SITE_URL', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $scheme = aegis_sports_product_is_https() ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8088'));
    return $scheme . '://' . ($host !== '' ? $host : '127.0.0.1:8088');
}

function aegis_sports_product_absolute_url(string $path = '/'): string
{
    return aegis_sports_product_site_url() . aegis_sports_product_url($path);
}

function aegis_sports_product_url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    return $path === '//' ? '/' : $path;
}

function aegis_sports_product_redirect(string $path): never
{
    header('Location: ' . aegis_sports_product_url($path));
    exit;
}

function aegis_sports_product_storage_dir(): string
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

function aegis_sports_product_users_path(): string
{
    return aegis_sports_product_storage_dir() . DIRECTORY_SEPARATOR . 'users.json';
}

function aegis_sports_product_provider_settings_path(): string
{
    return aegis_sports_product_storage_dir() . DIRECTORY_SEPARATOR . 'provider-settings.json';
}

function aegis_sports_product_rate_limit_path(): string
{
    return aegis_sports_product_storage_dir() . DIRECTORY_SEPARATOR . 'security-rate-limits.json';
}

function aegis_sports_product_security_events_path(): string
{
    return aegis_sports_product_storage_dir() . DIRECTORY_SEPARATOR . 'security-events.jsonl';
}

function aegis_sports_product_rate_limit_check(string $scope, string $identity, int $limit, int $windowSeconds): array
{
    $limit = max(1, $limit);
    $windowSeconds = max(1, $windowSeconds);
    $now = time();
    $path = aegis_sports_product_rate_limit_path();
    $handle = @fopen($path, 'c+b');
    if (!$handle) {
        return ['allowed' => true, 'retryAfter' => 0, 'remaining' => $limit];
    }

    flock($handle, LOCK_EX);
    $payload = stream_get_contents($handle);
    $data = json_decode((string) $payload, true);
    if (!is_array($data)) {
        $data = [];
    }

    foreach ($data as $key => $bucket) {
        if (!is_array($bucket) || (int) ($bucket['expiresAt'] ?? 0) < $now) {
            unset($data[$key]);
        }
    }

    $key = hash('sha256', $scope . '|' . $identity);
    $bucket = is_array($data[$key] ?? null) ? $data[$key] : [];
    $attempts = array_values(array_filter((array) ($bucket['attempts'] ?? []), static function ($timestamp) use ($now, $windowSeconds): bool {
        return is_numeric($timestamp) && (int) $timestamp > ($now - $windowSeconds);
    }));
    $attempts[] = $now;
    $count = count($attempts);
    $allowed = $count <= $limit;
    $oldest = (int) ($attempts[0] ?? $now);
    $retryAfter = $allowed ? 0 : max(1, ($oldest + $windowSeconds) - $now);

    $data[$key] = [
        'scope' => $scope,
        'expiresAt' => $now + $windowSeconds,
        'attempts' => $attempts,
    ];

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return [
        'allowed' => $allowed,
        'retryAfter' => $retryAfter,
        'remaining' => max(0, $limit - $count),
    ];
}

function aegis_sports_product_enforce_rate_limit(string $scope, string $identity, int $limit, int $windowSeconds): void
{
    $result = aegis_sports_product_rate_limit_check($scope, $identity, $limit, $windowSeconds);
    if (!empty($result['allowed'])) {
        return;
    }

    $retryAfter = max(1, (int) ($result['retryAfter'] ?? $windowSeconds));
    if (!headers_sent()) {
        header('Retry-After: ' . $retryAfter);
    }
    aegis_sports_product_record_security_event('rate_limit_exceeded', 'Request rate limit exceeded.', 'warning', [
        'scope' => $scope,
        'retryAfter' => $retryAfter,
    ]);
    aegis_sports_product_reject_request(429, 'Too many requests. Try again shortly.', [
        'retryAfter' => $retryAfter,
    ]);
}

function aegis_sports_product_record_security_event(string $type, string $message, string $severity = 'info', array $context = []): void
{
    $path = aegis_sports_product_security_events_path();
    $event = [
        'createdAt' => gmdate('c'),
        'type' => preg_replace('/[^a-z0-9_\-]/i', '', $type) ?: 'security_event',
        'severity' => in_array($severity, ['info', 'warning', 'error', 'success'], true) ? $severity : 'info',
        'message' => $message,
        'ipHash' => hash('sha256', aegis_sports_product_client_ip()),
        'path' => (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH),
        'context' => $context,
    ];

    $handle = @fopen($path, 'ab');
    if (!$handle) {
        return;
    }

    flock($handle, LOCK_EX);
    fwrite($handle, json_encode($event, JSON_UNESCAPED_SLASHES) . PHP_EOL);
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function aegis_sports_product_recent_security_events(int $limit = 8): array
{
    $path = aegis_sports_product_security_events_path();
    if (!is_file($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return [];
    }

    $events = [];
    foreach (array_reverse($lines) as $line) {
        $decoded = json_decode((string) $line, true);
        if (is_array($decoded)) {
            $events[] = $decoded;
        }
        if (count($events) >= $limit) {
            break;
        }
    }

    return $events;
}

function aegis_sports_product_default_provider_settings(): array
{
    return [
        'odds_api_key' => '',
        'odds_api_key_encrypted' => null,
        'injury_feed_url' => '',
        'lineup_feed_url' => '',
        'news_feed_url' => '',
        'props_feed_url' => '',
        'preferred_region' => 'us',
        'bankroll_unit' => '1.00',
        'max_stake_units' => '0.85',
        'manual_verification_required' => true,
        'updated_at' => '',
    ];
}

function aegis_sports_product_read_provider_settings(): array
{
    $path = aegis_sports_product_provider_settings_path();
    if (!is_file($path)) {
        return aegis_sports_product_default_provider_settings();
    }

    $handle = @fopen($path, 'rb');
    if (!$handle) {
        return aegis_sports_product_default_provider_settings();
    }

    flock($handle, LOCK_SH);
    $payload = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    $decoded = json_decode((string) $payload, true);
    $settings = array_merge(
        aegis_sports_product_default_provider_settings(),
        is_array($decoded) ? $decoded : []
    );

    if (is_array($settings['odds_api_key_encrypted'] ?? null)) {
        try {
            $settings['odds_api_key'] = aegis_sports_product_decrypt_secret((array) $settings['odds_api_key_encrypted']);
            $settings['_odds_key_encrypted_readable'] = true;
        } catch (Throwable $error) {
            $settings['odds_api_key'] = '';
            $settings['_odds_key_error'] = $error->getMessage();
            $settings['_odds_key_encrypted_readable'] = false;
        }
    }

    return $settings;
}

function aegis_sports_product_write_provider_settings(array $settings): array
{
    $current = aegis_sports_product_read_provider_settings();
    $oddsApiKey = trim((string) ($settings['odds_api_key'] ?? $current['odds_api_key'] ?? ''));
    $clean = array_merge($current, [
        'odds_api_key' => $oddsApiKey,
        'injury_feed_url' => aegis_sports_product_clean_optional_url((string) ($settings['injury_feed_url'] ?? $current['injury_feed_url'] ?? '')),
        'lineup_feed_url' => aegis_sports_product_clean_optional_url((string) ($settings['lineup_feed_url'] ?? $current['lineup_feed_url'] ?? '')),
        'news_feed_url' => aegis_sports_product_clean_optional_url((string) ($settings['news_feed_url'] ?? $current['news_feed_url'] ?? '')),
        'props_feed_url' => aegis_sports_product_clean_optional_url((string) ($settings['props_feed_url'] ?? $current['props_feed_url'] ?? '')),
        'preferred_region' => strtolower(trim((string) ($settings['preferred_region'] ?? $current['preferred_region'] ?? 'us'))) ?: 'us',
        'bankroll_unit' => number_format(max(0.01, (float) ($settings['bankroll_unit'] ?? $current['bankroll_unit'] ?? 1)), 2, '.', ''),
        'max_stake_units' => number_format(max(0.05, min(5.0, (float) ($settings['max_stake_units'] ?? $current['max_stake_units'] ?? 0.85))), 2, '.', ''),
        'manual_verification_required' => true,
        'updated_at' => gmdate('c'),
    ]);
    unset($clean['_odds_key_error'], $clean['_odds_key_encrypted_readable']);

    if ($oddsApiKey === '') {
        $clean['odds_api_key'] = '';
        $clean['odds_api_key_encrypted'] = null;
    } elseif (aegis_sports_product_secret_crypto_available()) {
        $clean['odds_api_key_encrypted'] = aegis_sports_product_encrypt_secret($oddsApiKey);
        $clean['odds_api_key'] = '';
    } elseif (aegis_is_production()) {
        throw new RuntimeException('Configure LINEFORGE_CREDENTIAL_KEY before storing provider API keys in production.');
    } else {
        $clean['odds_api_key_encrypted'] = null;
    }

    $path = aegis_sports_product_provider_settings_path();
    $handle = @fopen($path, 'c+b');
    if (!$handle) {
        throw new RuntimeException('Unable to open provider settings for writing.');
    }

    flock($handle, LOCK_EX);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    aegis_sports_product_apply_provider_settings($clean);
    return $clean;
}

function aegis_sports_product_clean_optional_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Provider feed URLs must be valid absolute URLs.');
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['https', 'http'], true)) {
        throw new InvalidArgumentException('Provider feed URLs must use HTTP or HTTPS.');
    }

    if ($scheme === 'http' && aegis_is_production()) {
        throw new InvalidArgumentException('Provider feed URLs must use HTTPS in production.');
    }

    return $url;
}

function aegis_sports_product_mask_secret(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (strlen($value) <= 8) {
        return str_repeat('*', strlen($value));
    }

    return substr($value, 0, 4) . str_repeat('*', max(4, strlen($value) - 8)) . substr($value, -4);
}

function aegis_sports_product_secret_crypto_available(): bool
{
    return extension_loaded('openssl')
        && function_exists('openssl_encrypt')
        && function_exists('openssl_decrypt')
        && trim(aegis_env('LINEFORGE_CREDENTIAL_KEY', aegis_env('AEGIS_CREDENTIAL_KEY', ''))) !== '';
}

function aegis_sports_product_secret_key_material(): string
{
    $secret = trim(aegis_env('LINEFORGE_CREDENTIAL_KEY', aegis_env('AEGIS_CREDENTIAL_KEY', '')));
    if ($secret === '') {
        throw new RuntimeException('LINEFORGE_CREDENTIAL_KEY is required for provider secret encryption.');
    }

    return hash('sha256', $secret, true);
}

function aegis_sports_product_encrypt_secret(string $secret): array
{
    if (!aegis_sports_product_secret_crypto_available()) {
        throw new RuntimeException('Credential encryption is unavailable. Configure PHP OpenSSL and LINEFORGE_CREDENTIAL_KEY before storing provider keys.');
    }

    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', aegis_sports_product_secret_key_material(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Unable to encrypt provider credentials.');
    }

    return [
        'cipher' => 'aes-256-gcm',
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'value' => base64_encode($ciphertext),
        'createdAt' => gmdate('c'),
    ];
}

function aegis_sports_product_decrypt_secret(array $payload): string
{
    if (!aegis_sports_product_secret_crypto_available()) {
        throw new RuntimeException('Credential decryption is unavailable in this runtime.');
    }

    $plaintext = openssl_decrypt(
        base64_decode((string) ($payload['value'] ?? ''), true) ?: '',
        'aes-256-gcm',
        aegis_sports_product_secret_key_material(),
        OPENSSL_RAW_DATA,
        base64_decode((string) ($payload['iv'] ?? ''), true) ?: '',
        base64_decode((string) ($payload['tag'] ?? ''), true) ?: ''
    );

    if ($plaintext === false) {
        throw new RuntimeException('Unable to decrypt provider credentials.');
    }

    return $plaintext;
}

function aegis_sports_product_putenv_if_empty(string $name, string $value): void
{
    $value = trim($value);
    if ($value === '' || getenv($name) !== false) {
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

function aegis_sports_product_runtime_source_name(string $name): string
{
    return 'LINEFORGE_' . strtoupper($name) . '_SOURCE';
}

function aegis_sports_product_apply_runtime_setting(string $name, string $value): void
{
    $value = trim($value);
    $sourceName = aegis_sports_product_runtime_source_name($name);
    $currentSource = (string) (getenv($sourceName) ?: '');
    $runtimeValue = trim((string) (getenv($name) ?: ''));
    $hasRuntimeValue = $runtimeValue !== '';

    if ($value !== '') {
        if (!$hasRuntimeValue || $currentSource === 'provider_settings' || ($currentSource === '' && $runtimeValue === $value)) {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv($sourceName . '=provider_settings');
            $_ENV[$sourceName] = 'provider_settings';
            $_SERVER[$sourceName] = 'provider_settings';
            return;
        }

        if ($currentSource === '') {
            putenv($sourceName . '=environment');
            $_ENV[$sourceName] = 'environment';
            $_SERVER[$sourceName] = 'environment';
        }
        return;
    }

    if ($currentSource === 'provider_settings') {
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($sourceName);
        unset($_ENV[$sourceName], $_SERVER[$sourceName]);
        return;
    }

    if ($hasRuntimeValue && $currentSource === '') {
        putenv($sourceName . '=environment');
        $_ENV[$sourceName] = 'environment';
        $_SERVER[$sourceName] = 'environment';
    }
}

function aegis_sports_product_apply_provider_settings(?array $settings = null): void
{
    static $applying = false;
    if ($applying) {
        return;
    }

    $applying = true;
    $settings = $settings ?? aegis_sports_product_read_provider_settings();

    aegis_sports_product_apply_runtime_setting('AEGIS_ODDS_API_KEY', (string) ($settings['odds_api_key'] ?? ''));
    aegis_sports_product_apply_runtime_setting('AEGIS_INJURY_FEED_URL', (string) ($settings['injury_feed_url'] ?? ''));
    aegis_sports_product_apply_runtime_setting('AEGIS_LINEUP_FEED_URL', (string) ($settings['lineup_feed_url'] ?? ''));
    aegis_sports_product_apply_runtime_setting('AEGIS_NEWS_FEED_URL', (string) ($settings['news_feed_url'] ?? ''));
    aegis_sports_product_apply_runtime_setting('AEGIS_PLAYER_PROPS_FEED_URL', (string) ($settings['props_feed_url'] ?? ''));

    $applying = false;
}

function aegis_sports_product_provider_settings_public(): array
{
    $settings = aegis_sports_product_read_provider_settings();
    $envOdds = trim((string) (getenv('AEGIS_ODDS_API_KEY') ?: ''));
    $envOddsSource = (string) (getenv(aegis_sports_product_runtime_source_name('AEGIS_ODDS_API_KEY')) ?: '');
    $externalEnvOdds = $envOdds !== '' && $envOddsSource !== 'provider_settings';
    $storedOdds = trim((string) ($settings['odds_api_key'] ?? ''));
    $oddsKey = $externalEnvOdds ? $envOdds : $storedOdds;
    $storedEncrypted = is_array($settings['odds_api_key_encrypted'] ?? null);
    $encryptedReadable = !empty($settings['_odds_key_encrypted_readable']);
    $cryptoAvailable = aegis_sports_product_secret_crypto_available();
    $storageMode = $externalEnvOdds
        ? 'environment'
        : ($storedEncrypted ? 'encrypted' : ($storedOdds !== '' ? 'plain_local' : 'empty'));
    $oddsSource = match ($storageMode) {
        'environment' => 'Environment',
        'encrypted' => $encryptedReadable ? 'Encrypted local vault' : 'Encrypted vault unavailable',
        'plain_local' => 'Local unencrypted settings',
        default => 'Not connected',
    };
    $secretMessage = match ($storageMode) {
        'environment' => 'Odds API key is loaded from server environment variables.',
        'encrypted' => $encryptedReadable
            ? 'Odds API key is encrypted at rest with LINEFORGE_CREDENTIAL_KEY.'
            : 'Encrypted odds key cannot be read until LINEFORGE_CREDENTIAL_KEY is configured.',
        'plain_local' => $cryptoAvailable
            ? 'Save provider settings again to migrate the Odds API key into encrypted storage.'
            : 'Local development can use plain storage; configure LINEFORGE_CREDENTIAL_KEY before hosting.',
        default => $cryptoAvailable
            ? 'Provider secret encryption is ready.'
            : 'Configure LINEFORGE_CREDENTIAL_KEY to encrypt provider keys before hosting.',
    };
    $specialtyFeeds = [
        'injury_feed_url' => 'Injuries',
        'lineup_feed_url' => 'Lineups',
        'news_feed_url' => 'News',
        'props_feed_url' => 'Player Props',
    ];
    $connectedSpecialty = 0;
    foreach ($specialtyFeeds as $key => $_label) {
        if (trim((string) ($settings[$key] ?? '')) !== '') {
            $connectedSpecialty++;
        }
    }

    return [
        'oddsConnected' => $oddsKey !== '',
        'oddsSource' => $oddsSource,
        'oddsKeyMasked' => aegis_sports_product_mask_secret($oddsKey),
        'secretStorage' => [
            'available' => $cryptoAvailable,
            'mode' => $storageMode,
            'encryptedAtRest' => in_array($storageMode, ['environment', 'encrypted'], true) && ($storageMode !== 'encrypted' || $encryptedReadable),
            'message' => $secretMessage,
        ],
        'injury_feed_url' => (string) ($settings['injury_feed_url'] ?? ''),
        'lineup_feed_url' => (string) ($settings['lineup_feed_url'] ?? ''),
        'news_feed_url' => (string) ($settings['news_feed_url'] ?? ''),
        'props_feed_url' => (string) ($settings['props_feed_url'] ?? ''),
        'preferred_region' => (string) ($settings['preferred_region'] ?? 'us'),
        'bankroll_unit' => (string) ($settings['bankroll_unit'] ?? '1.00'),
        'max_stake_units' => (string) ($settings['max_stake_units'] ?? '0.85'),
        'specialtyFeedsConnected' => $connectedSpecialty,
        'updated_at' => (string) ($settings['updated_at'] ?? ''),
        'readiness' => [
            'scoreboard' => 'Connected',
            'odds' => $oddsKey !== '' ? 'Connected' : 'Needs API key',
            'injuries' => trim((string) ($settings['injury_feed_url'] ?? '')) !== '' ? 'Configured' : 'Manual check',
            'lineups' => trim((string) ($settings['lineup_feed_url'] ?? '')) !== '' ? 'Configured' : 'Manual check',
            'manualMode' => 'Required',
        ],
    ];
}

function aegis_sports_product_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function aegis_sports_product_session_user_agent_hash(): string
{
    return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
}

function aegis_sports_product_harden_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $now = time();
    $idleTimeout = max(300, aegis_sports_product_env_int('LINEFORGE_SESSION_IDLE_SECONDS', 28800));
    $absoluteTimeout = max($idleTimeout, aegis_sports_product_env_int('LINEFORGE_SESSION_ABSOLUTE_SECONDS', 86400));
    $rotationSeconds = max(60, aegis_sports_product_env_int('LINEFORGE_SESSION_ROTATE_SECONDS', 900));

    $_SESSION['aegis_sports_created_at'] = (int) ($_SESSION['aegis_sports_created_at'] ?? $now);
    $_SESSION['aegis_sports_last_activity_at'] = (int) ($_SESSION['aegis_sports_last_activity_at'] ?? $now);
    $_SESSION['aegis_sports_last_regenerated_at'] = (int) ($_SESSION['aegis_sports_last_regenerated_at'] ?? $now);
    $_SESSION['aegis_sports_user_agent_hash'] = (string) ($_SESSION['aegis_sports_user_agent_hash'] ?? aegis_sports_product_session_user_agent_hash());

    $signedIn = !empty($_SESSION['aegis_sports_user_id']);
    $expired = $signedIn && (
        ($_SESSION['aegis_sports_last_activity_at'] + $idleTimeout) < $now
        || ($_SESSION['aegis_sports_created_at'] + $absoluteTimeout) < $now
    );
    $agentChanged = $signedIn && $_SESSION['aegis_sports_user_agent_hash'] !== aegis_sports_product_session_user_agent_hash();

    if ($expired || $agentChanged) {
        aegis_sports_product_record_security_event(
            $expired ? 'session_expired' : 'session_user_agent_changed',
            $expired ? 'Authenticated session expired.' : 'Authenticated session user agent changed.',
            'warning'
        );
        unset($_SESSION['aegis_sports_user_id']);
        $_SESSION['aegis_sports_csrf'] = bin2hex(random_bytes(32));
        $_SESSION['aegis_sports_created_at'] = $now;
        $_SESSION['aegis_sports_last_regenerated_at'] = $now;
        $_SESSION['aegis_sports_user_agent_hash'] = aegis_sports_product_session_user_agent_hash();
        session_regenerate_id(true);
    } elseif (($_SESSION['aegis_sports_last_regenerated_at'] + $rotationSeconds) < $now) {
        session_regenerate_id(true);
        $_SESSION['aegis_sports_last_regenerated_at'] = $now;
    }

    $_SESSION['aegis_sports_last_activity_at'] = $now;
}

function aegis_sports_product_read_users(): array
{
    $path = aegis_sports_product_users_path();
    if (!is_file($path)) {
        return [];
    }

    $handle = @fopen($path, 'rb');
    if (!$handle) {
        return [];
    }

    flock($handle, LOCK_SH);
    $payload = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    $decoded = json_decode((string) $payload, true);
    return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
}

function aegis_sports_product_write_users(array $users): void
{
    $path = aegis_sports_product_users_path();
    $handle = @fopen($path, 'c+b');
    if (!$handle) {
        throw new RuntimeException('Unable to open user store for writing.');
    }

    flock($handle, LOCK_EX);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function aegis_sports_product_find_user_by_email(string $email): ?array
{
    $email = aegis_sports_product_normalize_email($email);
    foreach (aegis_sports_product_read_users() as $user) {
        if (aegis_sports_product_normalize_email((string) ($user['email'] ?? '')) === $email) {
            return $user;
        }
    }

    return null;
}

function aegis_sports_product_find_user_by_id(string $id): ?array
{
    foreach (aegis_sports_product_read_users() as $user) {
        if ((string) ($user['id'] ?? '') === $id) {
            return $user;
        }
    }

    return null;
}

function aegis_sports_product_create_user(string $email, string $password, string $displayName): array
{
    $email = aegis_sports_product_normalize_email($email);
    $displayName = trim(preg_replace('/\s+/', ' ', $displayName) ?? $displayName);
    $displayName = $displayName !== '' ? substr($displayName, 0, 80) : strtok($email, '@');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }

    if (strlen($email) > 254) {
        throw new InvalidArgumentException('Email address is too long.');
    }

    if (strlen($password) < 10) {
        throw new InvalidArgumentException('Use at least 10 characters for the password.');
    }

    if (strlen($password) > 4096) {
        throw new InvalidArgumentException('Password is too long.');
    }

    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d|[^A-Za-z]/', $password)) {
        throw new InvalidArgumentException('Use a stronger password with letters and at least one number or symbol.');
    }

    $users = aegis_sports_product_read_users();
    foreach ($users as $user) {
        if (aegis_sports_product_normalize_email((string) ($user['email'] ?? '')) === $email) {
            throw new InvalidArgumentException('An account already exists for that email.');
        }
    }

    $user = [
        'id' => bin2hex(random_bytes(16)),
        'email' => $email,
        'display_name' => $displayName,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'tier' => strtolower(trim(aegis_env('AEGIS_SPORTS_NEW_USER_TIER', 'pro'))) ?: 'pro',
        'created_at' => gmdate('c'),
        'last_login_at' => null,
    ];
    $users[] = $user;
    aegis_sports_product_write_users($users);

    return $user;
}

function aegis_sports_product_verify_user(string $email, string $password): ?array
{
    $user = aegis_sports_product_find_user_by_email($email);
    if (!$user) {
        password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
        return null;
    }

    $hash = (string) ($user['password_hash'] ?? '');
    if (!password_verify($password, $hash)) {
        return null;
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $users = aegis_sports_product_read_users();
        foreach ($users as &$record) {
            if ((string) ($record['id'] ?? '') === (string) ($user['id'] ?? '')) {
                $record['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                break;
            }
        }
        unset($record);
        aegis_sports_product_write_users($users);
    }

    return $user;
}

function aegis_sports_product_login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['aegis_sports_user_id'] = (string) ($user['id'] ?? '');
    $_SESSION['aegis_sports_authenticated_at'] = time();
    $_SESSION['aegis_sports_created_at'] = time();
    $_SESSION['aegis_sports_last_activity_at'] = time();
    $_SESSION['aegis_sports_last_regenerated_at'] = time();
    $_SESSION['aegis_sports_user_agent_hash'] = aegis_sports_product_session_user_agent_hash();
    $_SESSION['aegis_sports_csrf'] = bin2hex(random_bytes(32));

    $users = aegis_sports_product_read_users();
    foreach ($users as &$record) {
        if ((string) ($record['id'] ?? '') === (string) ($user['id'] ?? '')) {
            $record['last_login_at'] = gmdate('c');
            break;
        }
    }
    unset($record);
    aegis_sports_product_write_users($users);
    aegis_sports_product_record_security_event('login_success', 'Operator signed in.', 'success', [
        'userIdHash' => hash('sha256', (string) ($user['id'] ?? '')),
    ]);
}

function aegis_sports_product_logout_user(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $userId = (string) ($_SESSION['aegis_sports_user_id'] ?? '');
    if ($userId !== '') {
        aegis_sports_product_record_security_event('logout', 'Operator signed out.', 'info', [
            'userIdHash' => hash('sha256', $userId),
        ]);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => true,
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
    session_start();
    session_regenerate_id(true);
    $_SESSION['aegis_sports_created_at'] = time();
    $_SESSION['aegis_sports_last_activity_at'] = time();
    $_SESSION['aegis_sports_last_regenerated_at'] = time();
    $_SESSION['aegis_sports_user_agent_hash'] = aegis_sports_product_session_user_agent_hash();
    $_SESSION['aegis_sports_csrf'] = bin2hex(random_bytes(32));
}

function aegis_sports_product_current_user(): ?array
{
    $id = (string) ($_SESSION['aegis_sports_user_id'] ?? '');
    if ($id === '') {
        return null;
    }

    return aegis_sports_product_find_user_by_id($id);
}

function aegis_sports_product_account(): array
{
    $user = aegis_sports_product_current_user();
    if ($user) {
        return [
            'signedIn' => true,
            'id' => (string) ($user['id'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'username' => (string) ($user['display_name'] ?? $user['email'] ?? 'Lineforge Operator'),
            'tier' => (string) ($user['tier'] ?? 'pro'),
            'created_at' => (string) ($user['created_at'] ?? ''),
            'last_login_at' => (string) ($user['last_login_at'] ?? ''),
        ];
    }

    return [
        'signedIn' => false,
        'username' => '',
        'tier' => 'free',
    ];
}

function aegis_sports_product_require_auth(string $next = '/app'): array
{
    aegis_sports_product_bootstrap();
    $account = aegis_sports_product_account();
    if (!$account['signedIn']) {
        aegis_sports_product_redirect('/login?next=' . rawurlencode($next));
    }

    return $account;
}

function aegis_sports_product_tier(?array $account = null): array
{
    $tier = strtolower(trim((string) ($account['tier'] ?? aegis_env('AEGIS_SPORTS_TIER', 'pro'))));
    $profiles = [
        'free' => [
            'tracked_games' => 12,
            'models' => 2,
            'refresh_seconds' => 60,
        ],
        'pro' => [
            'tracked_games' => 36,
            'models' => 6,
            'refresh_seconds' => 20,
        ],
        'elite' => [
            'tracked_games' => 120,
            'models' => 10,
            'refresh_seconds' => 10,
        ],
    ];

    if (!isset($profiles[$tier])) {
        $tier = 'pro';
    }

    $limits = $profiles[$tier];
    $limits['tracked_games'] = max(1, (int) aegis_env('AEGIS_SPORTS_TRACKED_GAMES', (string) $limits['tracked_games']));
    $limits['models'] = max(1, (int) aegis_env('AEGIS_SPORTS_MODELS', (string) $limits['models']));
    $limits['refresh_seconds'] = max(5, (int) aegis_env('AEGIS_SPORTS_REFRESH_SECONDS', (string) $limits['refresh_seconds']));

    return [
        'tier' => $tier,
        'limits' => $limits,
    ];
}

function aegis_sports_product_preferences(): array
{
    return [
        'theme' => aegis_env('AEGIS_SPORTS_THEME', 'aegis-blue'),
        'density' => aegis_env('AEGIS_SPORTS_DENSITY', 'comfortable'),
        'command_palette' => false,
    ];
}

function aegis_sports_product_flash(string $type, string $message): void
{
    $_SESSION['aegis_sports_flash'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function aegis_sports_product_take_flash(): array
{
    $items = $_SESSION['aegis_sports_flash'] ?? [];
    unset($_SESSION['aegis_sports_flash']);
    return is_array($items) ? $items : [];
}

function aegis_sports_product_csrf_token(): string
{
    if (empty($_SESSION['aegis_sports_csrf'])) {
        $_SESSION['aegis_sports_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['aegis_sports_csrf'];
}

function aegis_sports_product_verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['aegis_sports_csrf'])
        && hash_equals((string) $_SESSION['aegis_sports_csrf'], $token);
}

function aegis_sports_product_safe_next(string $next): string
{
    $next = trim($next);
    if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
        return '/app';
    }

    return $next;
}

function aegis_sports_product_body_attributes(array $preferences): string
{
    $theme = htmlspecialchars((string) ($preferences['theme'] ?? 'aegis-blue'), ENT_QUOTES, 'UTF-8');
    $density = htmlspecialchars((string) ($preferences['density'] ?? 'comfortable'), ENT_QUOTES, 'UTF-8');

    return ' data-aegis-theme="' . $theme . '" data-aegis-density="' . $density . '" data-aegis-command-palette="0"';
}

function aegis_sports_product_compact_text(string $value, int $limit = 180): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    if (strlen($value) <= $limit) {
        return $value;
    }

    return rtrim(substr($value, 0, max(1, $limit - 3))) . '...';
}

function aegis_sports_product_public_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || $url === '#') {
        return '#';
    }

    if (str_starts_with($url, '/')) {
        return $url;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (in_array($scheme, ['https', 'http'], true) && filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }

    return '#';
}

function aegis_sports_product_pick_percent(array $prediction): int
{
    $raw = $prediction['confidenceValue'] ?? $prediction['confidence'] ?? 58;
    if (is_numeric($raw)) {
        return max(0, min(100, (int) round((float) $raw)));
    }

    if (preg_match('/\d+(?:\.\d+)?/', (string) $raw, $match)) {
        return max(0, min(100, (int) round((float) $match[0])));
    }

    return 58;
}

function aegis_sports_product_pick_best_link(array $links): array
{
    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        if (
            !empty($link['available'])
            && strcasecmp((string) ($link['kind'] ?? ''), 'Sportsbook') === 0
            && (string) ($link['price'] ?? '--') !== '--'
        ) {
            return $link;
        }
    }

    foreach ($links as $link) {
        if (is_array($link) && !empty($link['available'])) {
            return $link;
        }
    }

    return is_array($links[0] ?? null) ? $links[0] : [];
}

function aegis_sports_product_prediction_readiness(array $prediction): array
{
    static $providerSettings = null;
    if ($providerSettings === null) {
        $providerSettings = aegis_sports_product_provider_settings_public();
    }

    $confidence = aegis_sports_product_pick_percent($prediction);
    $statusKey = strtolower((string) ($prediction['statusKey'] ?? 'scheduled'));
    $marketLinks = array_values(array_filter((array) ($prediction['marketLinks'] ?? []), 'is_array'));
    $sportsbookLines = array_values(array_filter($marketLinks, static function (array $link): bool {
        return !empty($link['available'])
            && strcasecmp((string) ($link['kind'] ?? ''), 'Sportsbook') === 0
            && (string) ($link['price'] ?? '--') !== '--';
    }));
    $availableLinks = array_values(array_filter($marketLinks, static fn(array $link): bool => !empty($link['available'])));
    $bestLink = aegis_sports_product_pick_best_link($marketLinks);
    $comparison = is_array($prediction['teamComparison'] ?? null) ? $prediction['teamComparison'] : [];
    $signals = is_array($comparison['signals'] ?? null) ? $comparison['signals'] : [];
    $breakdown = is_array($prediction['breakdown'] ?? null) ? $prediction['breakdown'] : [];
    $missingInputs = array_values(array_filter((array) ($breakdown['missingInputs'] ?? []), 'is_array'));
    $playerCount = (int) ($signals['playerCount'] ?? 0);
    $injuryCount = (int) ($signals['injuryCount'] ?? 0);
    $hasSummary = !empty($signals['summaryAvailable']);
    $hasHistory = !empty($signals['historyAvailable']);
    $hasBoxscore = !empty($signals['boxscoreAvailable']);
    $oddsConnected = !empty($providerSettings['oddsConnected']);
    $specialtyConnected = (int) ($providerSettings['specialtyFeedsConnected'] ?? 0);
    $injuryConfigured = trim((string) ($providerSettings['injury_feed_url'] ?? '')) !== '';
    $lineupConfigured = trim((string) ($providerSettings['lineup_feed_url'] ?? '')) !== '';
    $propsConfigured = trim((string) ($providerSettings['props_feed_url'] ?? '')) !== '';
    $hasAvailabilityFeed = $injuryConfigured || $lineupConfigured || $hasSummary;
    $canBet = !array_key_exists('canBet', $prediction) || (bool) $prediction['canBet'];
    $sportsbookCount = count($sportsbookLines);
    $availableCount = count($availableLinks);

    $score = (int) round($confidence * 0.48);
    $score += $confidence >= 70 ? 8 : ($confidence >= 64 ? 4 : 0);
    $score += $sportsbookCount > 0 ? 22 : ($availableCount > 0 ? 8 : 0);
    $score += $oddsConnected ? 8 : 0;
    $score += min(12, $specialtyConnected * 4);
    $score += $playerCount > 0 ? 6 : 0;
    $score += $hasSummary ? 5 : 0;
    $score += $hasHistory ? 4 : 0;
    $score += $hasBoxscore ? 4 : 0;
    $score -= min(18, count($missingInputs) * 3);
    if ($statusKey === 'live') {
        $score -= 4;
    }
    if ($statusKey === 'final') {
        $score = min($score, 28);
    }
    if (!$canBet) {
        $score = min($score, 38);
    }
    $score = max(0, min(100, $score));

    $noBetReasons = [];
    if ($statusKey === 'final') {
        $noBetReasons[] = [
            'label' => 'Closed market',
            'value' => 'No action',
            'detail' => 'The game is final, so this pick is audit-only and should not be treated as a new opportunity.',
            'tone' => 'bad',
            'status' => 'Needs setup',
        ];
    }
    if (!$canBet) {
        $noBetReasons[] = [
            'label' => 'Execution disabled',
            'value' => 'Informational only',
            'detail' => 'This market is displayed for monitoring and explanation, not for bet placement.',
            'tone' => 'bad',
            'status' => 'Needs setup',
        ];
    }
    if ($confidence < 68) {
        $noBetReasons[] = [
            'label' => 'Confidence threshold',
            'value' => $confidence . '%',
            'detail' => 'Lineforge keeps picks below 68% out of ready status until more evidence or a better price appears.',
            'tone' => $confidence >= 60 ? 'warn' : 'bad',
            'status' => $confidence >= 60 ? 'Partial' : 'Needs setup',
        ];
    }
    if ($sportsbookCount === 0) {
        $noBetReasons[] = [
            'label' => 'Live sportsbook line',
            'value' => 'Not confirmed',
            'detail' => 'No matched bookmaker price is attached. Compare the final line manually or connect an odds feed.',
            'tone' => 'warn',
            'status' => 'Needs setup',
        ];
    }
    if (!$oddsConnected) {
        $noBetReasons[] = [
            'label' => 'Odds provider',
            'value' => 'Needs key',
            'detail' => 'The Odds API key is not connected, so sportsbook prices may only be quick links.',
            'tone' => 'warn',
            'status' => 'Needs setup',
        ];
    }
    if (!$hasAvailabilityFeed || (!$injuryConfigured && !$lineupConfigured && $playerCount === 0)) {
        $noBetReasons[] = [
            'label' => 'Availability check',
            'value' => 'Manual required',
            'detail' => 'Confirm injuries, scratches, starting lineups, minutes restrictions, and late news before acting.',
            'tone' => 'warn',
            'status' => 'Partial',
        ];
    }
    if ($statusKey === 'live') {
        $noBetReasons[] = [
            'label' => 'Live volatility',
            'value' => 'Fast market',
            'detail' => 'Live-game prices can move between refreshes. Re-check the exact number before trusting the edge.',
            'tone' => 'warn',
            'status' => 'Partial',
        ];
    }

    $blockerCount = count($noBetReasons);
    if ($blockerCount === 0) {
        $noBetReasons[] = [
            'label' => 'No major blocker detected',
            'value' => 'Proceed to verification',
            'detail' => 'Still confirm legal eligibility, final price, bankroll limits, and responsible-use rules before placing anything.',
            'tone' => 'ok',
            'status' => 'Active',
        ];
    }

    if ($statusKey === 'final' || !$canBet) {
        $label = 'No action';
        $tone = 'bad';
    } elseif ($score >= 78 && $confidence >= 68 && $sportsbookCount > 0 && $blockerCount <= 1) {
        $label = 'Ready';
        $tone = 'ok';
    } elseif ($score >= 60 && $sportsbookCount > 0) {
        $label = 'Watch';
        $tone = 'warn';
    } elseif ($score >= 42) {
        $label = 'Needs verification';
        $tone = 'warn';
    } else {
        $label = 'No action';
        $tone = 'bad';
    }

    $bestBook = (string) ($bestLink['title'] ?? ($prediction['bestBook'] ?? 'Provider links'));
    $bestLine = (string) ($bestLink['line'] ?? ($prediction['bookLine'] ?? ($prediction['pick'] ?? 'Market')));
    $bestPrice = (string) ($bestLink['price'] ?? ($prediction['odds'] ?? '--'));
    $bookProbability = (string) ($bestLink['bookProbability'] ?? '--');
    $modelEdge = (string) ($bestLink['modelEdge'] ?? ($prediction['edge'] ?? '+0.0%'));
    $lineShoppingDetail = $sportsbookCount > 0
        ? $sportsbookCount . ' live sportsbook line' . ($sportsbookCount === 1 ? '' : 's') . ' attached. Confirm the exact line and price in the book before taking action.'
        : 'Provider links are available for discovery, but no live bookmaker price is attached to this pick yet.';

    return [
        'score' => $score,
        'label' => $label,
        'tone' => $tone,
        'detail' => $label === 'Ready'
            ? 'The model, market line, and verification data are strong enough for final manual review.'
            : ($label === 'Watch'
                ? 'The pick has enough signal to monitor closely, but at least one verification step still matters.'
                : ($label === 'Needs verification'
                    ? 'The model likes the spot, but missing price or availability evidence keeps it out of ready status.'
                    : 'The current evidence does not support a new bet decision.')),
        'blockerCount' => $blockerCount,
        'availableSportsbookLines' => $sportsbookCount,
        'providerLinks' => count($marketLinks),
        'checks' => [
            [
                'label' => 'Model confidence',
                'value' => $confidence . '%',
                'detail' => 'Fair odds ' . (string) ($prediction['fairOdds'] ?? '--') . ' before comparing market price.',
                'tone' => $confidence >= 68 ? 'ok' : ($confidence >= 60 ? 'warn' : 'bad'),
                'status' => $confidence >= 68 ? 'Active' : 'Partial',
            ],
            [
                'label' => 'Line shopping',
                'value' => $sportsbookCount > 0 ? $bestBook : 'Needs live line',
                'detail' => $sportsbookCount > 0 ? $bestLine . ' at ' . $bestPrice : 'Connect odds or manually verify sportsbook prices.',
                'tone' => $sportsbookCount > 0 ? 'ok' : 'warn',
                'status' => $sportsbookCount > 0 ? 'Active' : 'Needs setup',
            ],
            [
                'label' => 'Odds provider',
                'value' => $oddsConnected ? 'Connected' : 'Needs API key',
                'detail' => (string) ($providerSettings['oddsSource'] ?? 'Not connected'),
                'tone' => $oddsConnected ? 'ok' : 'warn',
                'status' => $oddsConnected ? 'Connected' : 'Needs setup',
            ],
            [
                'label' => 'Injuries and lineups',
                'value' => $hasAvailabilityFeed ? ($injuryCount . ' listed') : 'Manual check',
                'detail' => $hasAvailabilityFeed ? 'Public summary or configured availability feed is attached.' : 'Confirm official injuries, scratches, lineups, and late news.',
                'tone' => $hasAvailabilityFeed ? 'ok' : 'warn',
                'status' => $hasAvailabilityFeed ? 'Active' : 'Partial',
            ],
            [
                'label' => 'Player signal depth',
                'value' => $playerCount > 0 ? $playerCount . ' signals' : 'Pending',
                'detail' => $propsConfigured ? 'Player props feed is configured.' : 'Player leaders or props feed improve matchup confidence.',
                'tone' => $playerCount > 0 || $propsConfigured ? 'ok' : 'warn',
                'status' => $playerCount > 0 || $propsConfigured ? 'Active' : 'Partial',
            ],
            [
                'label' => 'Execution mode',
                'value' => 'Manual only',
                'detail' => 'Lineforge explains probability and edge; it does not place wagers or bypass legal checks.',
                'tone' => 'ok',
                'status' => 'Active',
            ],
        ],
        'lineShopping' => [
            'summary' => $lineShoppingDetail,
            'liveLines' => $sportsbookCount,
            'bestBook' => $bestBook,
            'bestLine' => $bestLine,
            'bestPrice' => $bestPrice,
            'fairOdds' => (string) ($prediction['fairOdds'] ?? '--'),
            'bookProbability' => $bookProbability,
            'modelEdge' => $modelEdge,
            'cards' => [
                [
                    'label' => 'Best book',
                    'value' => $sportsbookCount > 0 ? $bestBook : 'No live line',
                    'detail' => $sportsbookCount > 0 ? $bestLine . ' at ' . $bestPrice : 'Use the provider links below to verify manually.',
                    'tone' => $sportsbookCount > 0 ? 'ok' : 'warn',
                    'status' => $sportsbookCount > 0 ? 'Active' : 'Needs setup',
                ],
                [
                    'label' => 'Model edge',
                    'value' => $modelEdge,
                    'detail' => 'Model gap versus the best attached market price.',
                    'tone' => $sportsbookCount > 0 ? 'ok' : 'warn',
                    'status' => $sportsbookCount > 0 ? 'Active' : 'Partial',
                ],
                [
                    'label' => 'Fair odds',
                    'value' => (string) ($prediction['fairOdds'] ?? '--'),
                    'detail' => 'Lineforge fair price derived from model probability.',
                    'tone' => 'ok',
                    'status' => 'Active',
                ],
                [
                    'label' => 'Book probability',
                    'value' => $bookProbability,
                    'detail' => 'Implied probability from the attached sportsbook price when available.',
                    'tone' => $bookProbability !== '--' ? 'ok' : 'warn',
                    'status' => $bookProbability !== '--' ? 'Active' : 'Partial',
                ],
            ],
        ],
        'noBetReasons' => $noBetReasons,
        'manualVerification' => [
            ['label' => 'Legal location', 'value' => 'Required', 'detail' => 'Only use legal, regulated books available in your location.', 'tone' => 'warn', 'status' => 'Partial'],
            ['label' => 'Final price', 'value' => 'Re-check', 'detail' => 'Confirm the exact line, odds, limits, and market rules before acting.', 'tone' => 'warn', 'status' => 'Partial'],
            ['label' => 'Availability news', 'value' => 'Confirm', 'detail' => 'Recheck official injury reports, lineups, scratches, and beat news.', 'tone' => 'warn', 'status' => 'Partial'],
            ['label' => 'Bankroll rule', 'value' => (string) ($prediction['stake'] ?? '0.00u'), 'detail' => 'Keep exposure within the configured paper stake and responsible-use limits.', 'tone' => 'ok', 'status' => 'Active'],
        ],
    ];
}

function aegis_sports_product_public_link(array $link): array
{
    foreach (['market', 'line', 'note'] as $key) {
        if (isset($link[$key])) {
            $link[$key] = aegis_sports_product_compact_text((string) $link[$key], $key === 'note' ? 150 : 120);
        }
    }

    if (isset($link['url'])) {
        $link['url'] = aegis_sports_product_public_url((string) $link['url']);
    }

    return $link;
}

function aegis_sports_product_public_prediction(array $prediction): array
{
    if (trim((string) ($prediction['predictedWinner'] ?? '')) === '' && is_array($prediction['teamComparison'] ?? null)) {
        $comparison = (array) $prediction['teamComparison'];
        $side = (string) ($comparison['pickSide'] ?? '');
        if ($side !== 'away' && $side !== 'home') {
            $awayRating = is_numeric($comparison['away']['rating'] ?? null) ? (float) $comparison['away']['rating'] : null;
            $homeRating = is_numeric($comparison['home']['rating'] ?? null) ? (float) $comparison['home']['rating'] : null;
            if ($awayRating !== null && $homeRating !== null && $awayRating !== $homeRating) {
                $side = $awayRating > $homeRating ? 'away' : 'home';
            }
        }
        if ($side === 'away' || $side === 'home') {
            $prediction['predictedWinner'] = (string) ($comparison[$side]['name'] ?? $comparison[$side]['abbr'] ?? 'Predicted winner');
            $prediction['predictedWinnerSide'] = $side;
            $prediction['predictedWinnerBasis'] = (string) ($prediction['predictedWinnerBasis'] ?? 'Model comparison');
        }
    }

    if (isset($prediction['marketLinks']) && is_array($prediction['marketLinks'])) {
        $prediction['marketLinks'] = array_map(
            'aegis_sports_product_public_link',
            array_slice(array_values($prediction['marketLinks']), 0, 8)
        );
    }

    if (isset($prediction['breakdown']['factors']) && is_array($prediction['breakdown']['factors'])) {
        $prediction['breakdown']['factors'] = array_slice(array_values($prediction['breakdown']['factors']), 0, 15);
    }

    if (isset($prediction['teamComparison']['rows']) && is_array($prediction['teamComparison']['rows'])) {
        $prediction['teamComparison']['rows'] = array_slice(array_values($prediction['teamComparison']['rows']), 0, 10);
    }

    $prediction['readiness'] = aegis_sports_product_prediction_readiness($prediction);

    unset($prediction['autoContext']);

    return $prediction;
}

function aegis_sports_product_number_from_string($value): float
{
    if (is_numeric($value)) {
        return (float) $value;
    }

    if (preg_match('/[+-]?\d+(?:\.\d+)?/', (string) $value, $match)) {
        return (float) $match[0];
    }

    return 0.0;
}

function aegis_sports_product_decision_action(array $readiness, array $prediction): array
{
    $label = (string) ($readiness['label'] ?? 'Watch');
    $score = (int) ($readiness['score'] ?? 0);
    $sportsbookLines = (int) ($readiness['availableSportsbookLines'] ?? 0);
    $blockers = (int) ($readiness['blockerCount'] ?? 0);
    $confidence = aegis_sports_product_pick_percent($prediction);

    if ($label === 'Ready') {
        return [
            'label' => 'Verify and compare',
            'tone' => 'ok',
            'detail' => 'Strongest current board candidate. Re-check final line, legal eligibility, and stake cap.',
        ];
    }

    if ($label === 'Watch' || ($score >= 60 && $sportsbookLines > 0)) {
        return [
            'label' => 'Watch price',
            'tone' => 'warn',
            'detail' => 'Good signal, but wait for a cleaner line or complete the remaining verification item.',
        ];
    }

    if ($confidence >= 68 && $sportsbookLines === 0) {
        return [
            'label' => 'Find line',
            'tone' => 'warn',
            'detail' => 'Model confidence is useful, but no live sportsbook price is attached yet.',
        ];
    }

    if ($blockers > 0) {
        return [
            'label' => 'Skip for now',
            'tone' => 'bad',
            'detail' => 'Blockers outweigh the current edge until data or price quality improves.',
        ];
    }

    return [
        'label' => 'Monitor',
        'tone' => 'warn',
        'detail' => 'Keep it on the board, but do not treat it as an actionable bet yet.',
    ];
}

function aegis_sports_product_decision_board(array $predictions, array $games = []): array
{
    $gamesById = [];
    foreach ($games as $game) {
        if (is_array($game)) {
            $gameId = (string) ($game['id'] ?? '');
            if ($gameId !== '') {
                $gamesById[$gameId] = $game;
            }
        }
    }

    $rows = [];
    foreach (array_values($predictions) as $index => $prediction) {
        if (!is_array($prediction)) {
            continue;
        }

        $readiness = is_array($prediction['readiness'] ?? null)
            ? $prediction['readiness']
            : aegis_sports_product_prediction_readiness($prediction);
        $lineShopping = is_array($readiness['lineShopping'] ?? null) ? $readiness['lineShopping'] : [];
        $game = $gamesById[(string) ($prediction['gameId'] ?? '')] ?? [];
        $confidence = aegis_sports_product_pick_percent($prediction);
        $readinessScore = (int) ($readiness['score'] ?? 0);
        $edgeValue = aegis_sports_product_number_from_string($prediction['edge'] ?? 0);
        $evValue = aegis_sports_product_number_from_string($prediction['expectedValue'] ?? 0);
        $sportsbookLines = (int) ($readiness['availableSportsbookLines'] ?? 0);
        $blockers = (int) ($readiness['blockerCount'] ?? 0);
        $decisionScore = (int) round(
            ($readinessScore * 0.68)
            + ($confidence * 0.18)
            + (max(0, $edgeValue) * 0.8)
            + min(10, $evValue / 12)
            + min(8, $sportsbookLines * 3)
            - min(16, $blockers * 4)
        );
        $decisionScore = max(0, min(100, $decisionScore));
        $action = aegis_sports_product_decision_action($readiness, $prediction);
        $noBetReasons = array_values(array_filter((array) ($readiness['noBetReasons'] ?? []), 'is_array'));
        $firstBlocker = $noBetReasons[0] ?? [];
        $quickChecks = array_slice(array_values(array_filter((array) ($readiness['checks'] ?? []), 'is_array')), 0, 3);

        $rows[] = [
            'rank' => 0,
            'predictionIndex' => $index,
            'gameId' => (string) ($prediction['gameId'] ?? ''),
            'pick' => (string) ($prediction['pick'] ?? 'Pick'),
            'matchup' => (string) ($prediction['matchup'] ?? ($game['matchup'] ?? 'Matchup')),
            'league' => (string) ($prediction['league'] ?? ($game['league'] ?? 'Sports')),
            'market' => (string) ($prediction['market'] ?? 'Market'),
            'statusKey' => (string) ($prediction['statusKey'] ?? ($game['statusKey'] ?? 'scheduled')),
            'statusLabel' => (string) ($prediction['statusLabel'] ?? ($game['statusLabel'] ?? 'Watch')),
            'confidence' => (string) ($prediction['confidence'] ?? ($confidence . '%')),
            'confidenceValue' => $confidence,
            'readinessScore' => $readinessScore,
            'readinessLabel' => (string) ($readiness['label'] ?? 'Watch'),
            'readinessTone' => (string) ($readiness['tone'] ?? 'warn'),
            'decisionScore' => $decisionScore,
            'edge' => (string) ($prediction['edge'] ?? '+0.0%'),
            'expectedValue' => (string) ($prediction['expectedValue'] ?? '$0.00'),
            'risk' => (string) ($prediction['risk'] ?? 'Model risk'),
            'stake' => (string) ($prediction['stake'] ?? '0.00u'),
            'bestBook' => (string) ($lineShopping['bestBook'] ?? ($prediction['bestBook'] ?? 'Provider links')),
            'bestLine' => (string) ($lineShopping['bestLine'] ?? ($prediction['bookLine'] ?? ($prediction['pick'] ?? 'Market'))),
            'bestPrice' => (string) ($lineShopping['bestPrice'] ?? ($prediction['odds'] ?? '--')),
            'sportsbookLines' => $sportsbookLines,
            'blockers' => $blockers,
            'blockerSummary' => (string) ($firstBlocker['label'] ?? ($readiness['detail'] ?? 'Manual verification required')),
            'reason' => aegis_sports_product_compact_text((string) ($prediction['reason'] ?? ($readiness['detail'] ?? 'Lineforge is monitoring this pick.')), 140),
            'action' => $action,
            'quickChecks' => array_map(static function (array $check): array {
                return [
                    'label' => (string) ($check['label'] ?? 'Check'),
                    'value' => (string) ($check['value'] ?? ''),
                    'tone' => (string) ($check['tone'] ?? 'warn'),
                ];
            }, $quickChecks),
        ];
    }

    usort($rows, static function (array $a, array $b): int {
        foreach (['decisionScore', 'readinessScore', 'confidenceValue', 'sportsbookLines'] as $key) {
            $left = (float) ($a[$key] ?? 0);
            $right = (float) ($b[$key] ?? 0);
            if ($left !== $right) {
                return $right <=> $left;
            }
        }

        return 0;
    });

    foreach ($rows as $index => &$row) {
        $row['rank'] = $index + 1;
    }
    unset($row);

    return array_slice($rows, 0, 12);
}

function aegis_sports_product_public_game(array $game): array
{
    if (isset($game['betLinks']) && is_array($game['betLinks'])) {
        $game['betLinks'] = array_map(
            'aegis_sports_product_public_link',
            array_slice(array_values($game['betLinks']), 0, 8)
        );
    }

    if (isset($game['bestLine']) && is_array($game['bestLine'])) {
        $game['bestLine'] = aegis_sports_product_public_link($game['bestLine']);
    }

    unset($game['prediction']);

    return $game;
}

function aegis_sports_product_public_state(array $state): array
{
    if (isset($state['games']) && is_array($state['games'])) {
        $state['games'] = array_map('aegis_sports_product_public_game', array_values($state['games']));
    }

    if (isset($state['predictions']) && is_array($state['predictions'])) {
        $state['predictions'] = array_map('aegis_sports_product_public_prediction', array_values($state['predictions']));
    }

    if (isset($state['topPick']) && is_array($state['topPick'])) {
        $state['topPick'] = aegis_sports_product_public_prediction($state['topPick']);
    }

    $state['decisionBoard'] = aegis_sports_product_decision_board(
        is_array($state['predictions'] ?? null) ? $state['predictions'] : [],
        is_array($state['games'] ?? null) ? $state['games'] : []
    );

    return $state;
}
