<?php

require_once __DIR__ . '/sports_product.php';

function sports_page_header(string $title, string $active = '', array $options = []): void
{
    aegis_sports_product_bootstrap();
    $account = aegis_sports_product_account();
    $flashes = aegis_sports_product_take_flash();
    $description = (string) ($options['description'] ?? 'Lineforge is a premium sports intelligence platform for live boards, AI confidence, market context, and responsible manual signal verification.');
    $canonicalPath = (string) ($options['canonical'] ?? ($_SERVER['REQUEST_URI'] ?? '/'));
    $canonicalPath = parse_url($canonicalPath, PHP_URL_PATH) ?: '/';
    $canonical = aegis_sports_product_absolute_url($canonicalPath);
    $robots = (string) ($options['robots'] ?? 'index,follow');
    $image = aegis_sports_product_absolute_url('/assets/images/lineforge-logo.png');
    $schema = $options['schema'] ?? null;
    $items = [
        '/' => 'Home',
        '/pricing' => 'Pricing',
        '/methodology' => 'Methodology',
        '/responsible-use' => 'Responsible Use',
    ];
    $pageKey = match ($active) {
        '/' => 'home',
        '/pricing' => 'pricing',
        '/methodology' => 'methodology',
        '/responsible-use' => 'responsible',
        '/login' => 'auth',
        '/register' => 'auth',
        '/account' => 'account',
        default => 'default',
    };

    ?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= aegis_sports_product_e($title); ?> | Lineforge</title>
    <meta name="description" content="<?= aegis_sports_product_e($description); ?>">
    <meta name="robots" content="<?= aegis_sports_product_e($robots); ?>">
    <link rel="canonical" href="<?= aegis_sports_product_e($canonical); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Lineforge">
    <meta property="og:title" content="<?= aegis_sports_product_e($title); ?> | Lineforge">
    <meta property="og:description" content="<?= aegis_sports_product_e($description); ?>">
    <meta property="og:url" content="<?= aegis_sports_product_e($canonical); ?>">
    <meta property="og:image" content="<?= aegis_sports_product_e($image); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= aegis_sports_product_e($title); ?> | Lineforge">
    <meta name="twitter:description" content="<?= aegis_sports_product_e($description); ?>">
    <meta name="theme-color" content="#071624">
    <link rel="icon" type="image/png" href="/assets/images/lineforge-logo.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="stylesheet" href="/assets/css/premium.css?v=20260505-scrollbar-blue-1">
    <link rel="stylesheet" href="/assets/css/aegis.css?v=20260425-aegis-sports-coverage-2">
    <link rel="stylesheet" href="/assets/css/product.css?v=20260505-scrollbar-blue-1">
    <?php if (is_array($schema)): ?>
        <script type="application/ld+json" nonce="<?= aegis_sports_product_e(aegis_sports_product_csp_nonce()); ?>"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
    <?php endif; ?>
</head>
<body<?= aegis_sports_product_body_attributes(aegis_sports_product_preferences()); ?>>
    <div class="sports-product-page sports-product-page-<?= aegis_sports_product_e($pageKey); ?>">
        <div class="sports-atmosphere" aria-hidden="true">
            <i></i>
            <i></i>
            <i></i>
        </div>
        <header class="sports-product-nav">
            <a class="sports-product-brand" href="/" aria-label="Lineforge home">
                <img class="lineforge-brand-logo lineforge-brand-logo-public" src="/assets/images/lineforge-logo.png" alt="" aria-hidden="true">
            </a>
            <nav aria-label="Public navigation">
                <?php foreach ($items as $href => $label): ?>
                    <a class="<?= $active === $href ? 'is-active' : ''; ?>" href="<?= aegis_sports_product_e($href); ?>"><?= aegis_sports_product_e($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <span class="sports-nav-status"><i></i> Intelligence layer online</span>
            <div class="sports-product-actions">
                <?php if ($account['signedIn']): ?>
                    <a href="/account"><?= aegis_sports_product_e($account['username']); ?></a>
                    <a class="is-primary" href="/app">Open App</a>
                <?php else: ?>
                    <a href="/login">Log In</a>
                    <a class="is-primary" href="/register">Create Account</a>
                <?php endif; ?>
            </div>
        </header>
        <?php foreach ($flashes as $flash): ?>
            <div class="sports-product-flash <?= aegis_sports_product_e((string) ($flash['type'] ?? 'info')); ?>">
                <?= aegis_sports_product_e((string) ($flash['message'] ?? '')); ?>
            </div>
        <?php endforeach; ?>
<?php
}

function sports_page_footer(): void
{
    ?>
        <footer class="sports-product-footer">
            <div>
                <strong>Lineforge</strong>
                <span>Institutional sports intelligence, manual verification, and paper decision support.</span>
            </div>
            <nav aria-label="Legal navigation">
                <a href="/terms">Terms</a>
                <a href="/privacy">Privacy</a>
                <a href="/responsible-use">Responsible Use</a>
            </nav>
        </footer>
    </div>
</body>
</html>
<?php
}
