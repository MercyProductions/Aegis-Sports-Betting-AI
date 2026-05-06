<?php

require_once __DIR__ . '/config/product_layout.php';

sports_page_header('Terms', '/terms', [
    'description' => 'Terms for Lineforge, an informational sports research product that does not place bets, execute exchange orders, or guarantee outcomes.',
    'canonical' => '/terms',
]);
?>
<main class="sports-product-section">
    <h2>Terms</h2>
    <p>This local product build is for development and research. Lineforge provides informational analytics only and does not provide financial, legal, gambling, or investment advice.</p>
    <div class="sports-product-card">
        <ul>
            <li>You are responsible for complying with laws and provider terms in your location.</li>
            <li>All provider prices, links, and event-contract details must be verified manually.</li>
            <li>No part of the current product places bets or submits exchange orders for you.</li>
            <li>Model confidence, edge, and expected-value estimates can be wrong.</li>
        </ul>
    </div>
</main>
<?php sports_page_footer(); ?>
