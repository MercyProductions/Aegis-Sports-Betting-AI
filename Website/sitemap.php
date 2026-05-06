<?php

require_once __DIR__ . '/config/sports_product.php';

aegis_env_bootstrap();
aegis_sports_product_security_headers();
header('Content-Type: application/xml; charset=utf-8');

$pages = [
    ['path' => '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
    ['path' => '/pricing', 'priority' => '0.8', 'changefreq' => 'monthly'],
    ['path' => '/methodology', 'priority' => '0.9', 'changefreq' => 'monthly'],
    ['path' => '/responsible-use', 'priority' => '0.7', 'changefreq' => 'monthly'],
    ['path' => '/terms', 'priority' => '0.4', 'changefreq' => 'yearly'],
    ['path' => '/privacy', 'priority' => '0.4', 'changefreq' => 'yearly'],
];

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
foreach ($pages as $page) {
    echo "  <url>\n";
    echo '    <loc>' . aegis_sports_product_e(aegis_sports_product_absolute_url($page['path'])) . "</loc>\n";
    echo '    <lastmod>' . gmdate('Y-m-d') . "</lastmod>\n";
    echo '    <changefreq>' . aegis_sports_product_e($page['changefreq']) . "</changefreq>\n";
    echo '    <priority>' . aegis_sports_product_e($page['priority']) . "</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
