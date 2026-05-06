<?php

require_once __DIR__ . '/config/product_layout.php';

sports_page_header('Pricing', '/pricing', [
    'description' => 'Compare Lineforge research tiers for live sports intelligence dashboards, AI picks, market intelligence, provider health, alerts, and export workflows.',
    'canonical' => '/pricing',
]);
?>
<main>
<section class="sports-product-section">
    <span class="sports-product-kicker">Research infrastructure</span>
    <h1>Pricing built for disciplined market operators.</h1>
    <p>Lineforge tiers are structured around coverage, refresh depth, auditability, and provider intelligence. Billing is still a product placeholder locally, but the packaging now reflects the platform the site is becoming.</p>
    <div class="sports-infra-map" aria-label="Platform infrastructure visualization">
        <article><span>Public fabric</span><strong>Free sources first</strong><i style="--level: 72%"></i></article>
        <article><span>Provider layer</span><strong>Official feeds only</strong><i style="--level: 58%"></i></article>
        <article><span>Audit systems</span><strong>Rules, skips, errors</strong><i style="--level: 86%"></i></article>
        <article><span>Execution guard</span><strong>Paper before live</strong><i style="--level: 64%"></i></article>
    </div>
    <div class="sports-pricing-grid">
        <article class="sports-product-card">
            <span>Explorer</span>
            <strong>Free</strong>
            <b>$0 / month</b>
            <ul>
                <li>Public scoreboard intelligence</li>
                <li>Limited tracked board</li>
                <li>Basic model reads and source warnings</li>
                <li>Manual verification checklist</li>
            </ul>
        </article>
        <article class="sports-product-card is-featured">
            <span>Operator</span>
            <strong>Pro</strong>
            <b>$29 / month target</b>
            <ul>
                <li>Broader live board and faster refresh</li>
                <li>Data-quality confidence caps</li>
                <li>Paper slips, audit history, and rule dry-runs</li>
                <li>Provider settings and odds matching</li>
                <li>Arbitrage rejection logs and quality grades</li>
            </ul>
        </article>
        <article class="sports-product-card">
            <span>Institutional</span>
            <strong>Elite</strong>
            <b>$79 / month target</b>
            <ul>
                <li>Larger scan universe and premium data adapters</li>
                <li>Advanced provider health and staleness controls</li>
                <li>Execution Center governance and risk limits</li>
                <li>Calibration, alerts, exports, and operator reports</li>
            </ul>
        </article>
    </div>
</section>

<section class="sports-product-section is-wide">
    <span class="sports-product-kicker">Plan comparison</span>
    <h2>Infrastructure, not hype.</h2>
    <p>The pricing surface emphasizes coverage quality and controls instead of promising outcomes. More expensive tiers should unlock better data, faster review loops, and stronger auditability.</p>
    <div class="sports-comparison-table" role="table" aria-label="Lineforge pricing comparison">
        <div class="sports-comparison-row is-head" role="row">
            <span role="columnheader">Capability</span>
            <span role="columnheader">Explorer</span>
            <span role="columnheader">Pro</span>
            <span role="columnheader">Elite</span>
        </div>
        <div class="sports-comparison-row" role="row">
            <strong>Public data fabric</strong><span>Core</span><span>Expanded</span><b>Expanded plus priority refresh</b>
        </div>
        <div class="sports-comparison-row" role="row">
            <strong>Confidence calibration</strong><span>Basic</span><b>Data-quality caps</b><span>Custom thresholds</span>
        </div>
        <div class="sports-comparison-row" role="row">
            <strong>Sportsbook comparison</strong><span>Manual links</span><b>Odds feed matching</b><span>Premium adapters when configured</span>
        </div>
        <div class="sports-comparison-row" role="row">
            <strong>Execution governance</strong><span>Unavailable</span><span>Paper mode</span><b>Provider health, limits, audit logs</b>
        </div>
        <div class="sports-comparison-row" role="row">
            <strong>Exports and operator workflow</strong><span>Limited</span><span>Reports</span><b>Institutional review pack</b>
        </div>
    </div>
</section>
</main>
<?php sports_page_footer(); ?>
