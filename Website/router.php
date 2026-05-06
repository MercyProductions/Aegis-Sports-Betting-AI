<?php

function lineforge_router_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Origin-Agent-Cluster: ?1');
}

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$decodedPath = rawurldecode($path);
$file = __DIR__ . $path;

$blockedPrefixes = ['/config/', '/storage/', '/.aegis-runtime/'];
$blockedFiles = ['/.env', '/.env.local'];
foreach ($blockedPrefixes as $prefix) {
    if (str_starts_with($path, $prefix) || str_starts_with($decodedPath, $prefix)) {
        lineforge_router_security_headers();
        http_response_code(404);
        echo 'Not found';
        return true;
    }
}
if (in_array($path, $blockedFiles, true) || in_array($decodedPath, $blockedFiles, true)) {
    lineforge_router_security_headers();
    http_response_code(404);
    echo 'Not found';
    return true;
}

if ($path !== '/' && is_file($file)) {
    return false;
}

if (str_starts_with($path, '/api/')) {
    lineforge_router_security_headers();
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'API route not found.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return true;
}

$routes = [
    '/' => 'index.php',
    '/pricing' => 'pricing.php',
    '/methodology' => 'methodology.php',
    '/login' => 'login.php',
    '/register' => 'register.php',
    '/logout' => 'logout.php',
    '/account' => 'account.php',
    '/app' => 'app.php',
    '/dashboard' => 'app.php',
    '/sports-betting' => 'app.php',
    '/responsible-use' => 'responsible-use.php',
    '/terms' => 'terms.php',
    '/privacy' => 'privacy.php',
    '/robots.txt' => 'robots.php',
    '/sitemap.xml' => 'sitemap.php',
];

if (isset($routes[$path])) {
    require __DIR__ . '/' . $routes[$path];
    return true;
}

lineforge_router_security_headers();
http_response_code(404);
require __DIR__ . '/index.php';
