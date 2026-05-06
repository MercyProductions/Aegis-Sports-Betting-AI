<?php

require __DIR__ . '/../config/sports_product.php';
require __DIR__ . '/../config/aegis_sports.php';

aegis_sports_product_bootstrap();
aegis_sports_product_enforce_rate_limit('api_sports_live', aegis_sports_product_client_ip(), 180, 60);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$account = aegis_sports_product_account();
if (!$account['signedIn']) {
    http_response_code(401);
    echo json_encode([
        'name' => 'Lineforge Sports Live',
        'ok' => false,
        'message' => 'Sign in to access live sports data.',
        'loginUrl' => '/login?next=%2Fapp',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$tier = aegis_sports_product_tier($account);
$state = aegis_sports_product_public_state(aegis_sports_state($tier['limits'] ?? []));

echo json_encode([
    'name' => 'Lineforge Sports Live',
    'ok' => true,
    'tier' => $tier['tier'] ?? 'free',
    'limits' => $tier['limits'] ?? [],
    'providerSettings' => aegis_sports_product_provider_settings_public(),
    'state' => $state,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
