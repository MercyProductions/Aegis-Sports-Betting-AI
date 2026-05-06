<?php

require __DIR__ . '/../config/sports_product.php';

aegis_sports_product_bootstrap();
aegis_sports_product_enforce_rate_limit('api_provider_settings', aegis_sports_product_client_ip(), 120, 60);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$account = aegis_sports_product_account();
if (!$account['signedIn']) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Sign in to manage provider settings.',
        'loginUrl' => '/login?next=%2Fapp',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'ok' => true,
        'csrfToken' => aegis_sports_product_csrf_token(),
        'settings' => aegis_sports_product_provider_settings_public(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Unsupported provider settings method.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

aegis_sports_product_enforce_rate_limit('api_provider_settings_write', aegis_sports_product_client_ip(), 30, 60);

$raw = file_get_contents('php://input');
$input = json_decode((string) $raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrf = (string) ($_SERVER['HTTP_X_AEGIS_CSRF'] ?? ($input['csrf'] ?? ''));
if (!aegis_sports_product_verify_csrf($csrf)) {
    aegis_sports_product_record_security_event('csrf_failed', 'Provider settings CSRF check failed.', 'warning');
    http_response_code(419);
    echo json_encode([
        'ok' => false,
        'message' => 'Session expired. Refresh and try again.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $current = aegis_sports_product_read_provider_settings();
    $next = [
        'odds_api_key' => !empty($input['clear_odds_api_key'])
            ? ''
            : (trim((string) ($input['odds_api_key'] ?? '')) !== ''
                ? (string) $input['odds_api_key']
                : (string) ($current['odds_api_key'] ?? '')),
        'injury_feed_url' => (string) ($input['injury_feed_url'] ?? ''),
        'lineup_feed_url' => (string) ($input['lineup_feed_url'] ?? ''),
        'news_feed_url' => (string) ($input['news_feed_url'] ?? ''),
        'props_feed_url' => (string) ($input['props_feed_url'] ?? ''),
        'preferred_region' => (string) ($input['preferred_region'] ?? 'us'),
        'bankroll_unit' => (string) ($input['bankroll_unit'] ?? '1.00'),
        'max_stake_units' => (string) ($input['max_stake_units'] ?? '0.85'),
    ];

    aegis_sports_product_write_provider_settings($next);

    echo json_encode([
        'ok' => true,
        'message' => 'Provider settings saved.',
        'csrfToken' => aegis_sports_product_csrf_token(),
        'settings' => aegis_sports_product_provider_settings_public(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => $error->getMessage(),
        'settings' => aegis_sports_product_provider_settings_public(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
