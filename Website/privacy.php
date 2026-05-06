<?php

require_once __DIR__ . '/config/product_layout.php';

sports_page_header('Privacy', '/privacy', [
    'description' => 'Privacy overview for Lineforge, including local development account storage, provider configuration, and responsible analytics behavior.',
    'canonical' => '/privacy',
]);
?>
<main class="sports-product-section">
    <h2>Privacy</h2>
    <p>This standalone local build stores account records in `Website/storage/users.json` and sports feed caches in `Website/storage/cache`. Provider keys should stay in `.env` and are ignored by git.</p>
    <div class="sports-product-card">
        <ul>
            <li>Passwords are stored as PHP password hashes, not plaintext.</li>
            <li>Local feed cache files may contain public sports event data.</li>
            <li>Do not commit `.env`, local user records, or runtime cache files.</li>
            <li>A production deployment should move auth data to a managed database and add email verification, password reset, billing, and audit logs.</li>
        </ul>
    </div>
</main>
<?php sports_page_footer(); ?>
