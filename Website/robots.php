<?php

require_once __DIR__ . '/config/sports_product.php';

aegis_env_bootstrap();
aegis_sports_product_security_headers();
header('Content-Type: text/plain; charset=utf-8');

$sitemap = aegis_sports_product_absolute_url('/sitemap.xml');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /app\n";
echo "Disallow: /account\n";
echo "Disallow: /login\n";
echo "Disallow: /register\n";
echo "Disallow: /logout\n";
echo "Disallow: /api/\n";
echo "Disallow: /storage/\n";
echo "Disallow: /config/\n";
echo "\nSitemap: {$sitemap}\n";
