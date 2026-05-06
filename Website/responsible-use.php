<?php

require_once __DIR__ . '/config/product_layout.php';

sports_page_header('Responsible Use', '/responsible-use', [
    'description' => 'Responsible-use rules for Lineforge, including manual verification, paper tracking, no automated wager execution, and risk limits.',
    'canonical' => '/responsible-use',
]);
?>
<main>
<section class="sports-product-section">
    <span class="sports-product-kicker">Compliance architecture</span>
    <h1>Responsible use is part of the product, not a footer.</h1>
    <p>Lineforge is an informational sports intelligence platform. It does not guarantee outcomes, bypass provider rules, automate sportsbook wagering, or remove the user's obligation to verify legality, location eligibility, price, and risk.</p>
    <div class="sports-compliance-grid">
        <div class="sports-risk-timeline">
            <article class="sports-risk-step"><i>1</i><div><strong>Source check</strong><span>Confirm provider health, data freshness, market status, and matched line quality.</span></div><b>Required</b></article>
            <article class="sports-risk-step"><i>2</i><div><strong>Eligibility check</strong><span>Verify age, location, account status, market rules, and provider restrictions yourself.</span></div><b>Manual</b></article>
            <article class="sports-risk-step"><i>3</i><div><strong>Risk check</strong><span>Review stake size, max loss, daily loss limits, cooldowns, and self-exclusion controls.</span></div><b>Guarded</b></article>
            <article class="sports-risk-step"><i>4</i><div><strong>Audit check</strong><span>Every paper or provider-aware action should leave an evaluation, action, skip, or error trail.</span></div><b>Logged</b></article>
        </div>
        <aside class="sports-product-terminal">
            <div class="sports-product-terminal-head">
                <strong>Execution governance</strong>
                <span class="sports-product-status">Manual first</span>
            </div>
            <div class="sports-market-row"><div><strong>Paper mode</strong><span>Simulation and dry-run before any live-money workflow.</span></div><b>Default</b></div>
            <div class="sports-market-row"><div><strong>Live opt-in</strong><span>Explicit confirmation, risk limits, provider health, and auditability required.</span></div><b>Locked</b></div>
            <div class="sports-market-row"><div><strong>Emergency stop</strong><span>Pending rules can be cancelled immediately.</span></div><b>Available</b></div>
            <div class="sports-governance-visual" aria-hidden="true">
                <span>risk</span>
                <span>limit</span>
                <span>audit</span>
                <span>stop</span>
            </div>
        </aside>
    </div>
</section>

<section class="sports-product-section is-wide">
    <span class="sports-product-kicker">Risk systems</span>
    <h2>Financial-platform discipline for sports intelligence.</h2>
    <div class="sports-product-grid">
        <article class="sports-product-card"><strong>Manual Verification</strong><p>Always verify final provider price, market rules, injury news, lineup state, and legal eligibility before acting.</p></article>
        <article class="sports-product-card"><strong>Paper First</strong><p>Use dry-runs, paper tracking, reports, and calibration before risking money. Confidence is an estimate, not a promise.</p></article>
        <article class="sports-product-card"><strong>Responsible Limits</strong><p>Use max stake, daily loss, cooldown, self-exclusion, and emergency disable controls. Stop if the product creates financial or emotional pressure.</p></article>
    </div>
</section>
</main>
<?php sports_page_footer(); ?>
