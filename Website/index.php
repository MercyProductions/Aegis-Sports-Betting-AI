<?php

require_once __DIR__ . '/config/product_layout.php';

$homeSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => 'Lineforge',
    'applicationCategory' => 'SportsApplication',
    'operatingSystem' => 'Web',
    'description' => 'Premium sports intelligence software for live board monitoring, AI confidence, market context, and responsible manual signal verification.',
    'url' => aegis_sports_product_absolute_url('/'),
    'offers' => [
        '@type' => 'Offer',
        'price' => '0',
        'priceCurrency' => 'USD',
        'availability' => 'https://schema.org/InStock',
    ],
];

sports_page_header('Home', '/', [
    'description' => 'Lineforge is a premium sports intelligence platform for live scores, AI confidence, market context, line shopping, and responsible manual signal verification.',
    'canonical' => '/',
    'schema' => $homeSchema,
]);
?>
<main>
    <section class="sports-product-hero">
        <div class="sports-product-copy">
            <span class="sports-product-kicker">Institutional sports intelligence</span>
            <h1>Lineforge</h1>
            <p>Lineforge turns public data, live scoreboards, odds context, confidence calibration, and missing-data warnings into one command surface for disciplined sports market decisions.</p>
            <div class="sports-product-cta">
                <a class="sports-product-button is-primary" href="/register">Create Account</a>
                <a class="sports-product-button" href="/login">Log In</a>
                <a class="sports-product-button" href="/responsible-use">Responsible Use</a>
            </div>
            <div class="sports-product-stats">
                <article class="sports-product-stat"><span>Board state</span><strong>Live</strong><small>Scores, schedules, status, and freshness labels.</small></article>
                <article class="sports-product-stat"><span>Signal mode</span><strong>Manual</strong><small>Confidence is capped until price and source quality support it.</small></article>
                <article class="sports-product-stat"><span>Provider layer</span><strong>Public+</strong><small>Free data first, premium feeds when configured.</small></article>
            </div>
        </div>
        <aside class="sports-hero-visual" aria-label="Lineforge intelligence preview">
            <div class="sports-product-terminal">
                <div class="sports-product-terminal-head">
                    <img class="sports-product-terminal-logo" src="/assets/images/lineforge-logo.png" alt="Lineforge">
                    <strong>Live Intelligence Brief</strong>
                    <span class="sports-product-status">Monitoring</span>
                </div>
                <div class="sports-intel-grid">
                    <div class="sports-line-chart" aria-hidden="true">
                        <span style="height: 44%"></span>
                        <span style="height: 58%"></span>
                        <span style="height: 52%"></span>
                        <span style="height: 72%"></span>
                        <span style="height: 64%"></span>
                        <span style="height: 86%"></span>
                        <span style="height: 78%"></span>
                    </div>
                    <div class="sports-visual-panel">
                        <small>Confidence cap</small>
                        <div class="sports-probability-ring"><strong>68%</strong></div>
                        <small>Public partial / no live book line attached</small>
                    </div>
                </div>
                <div class="sports-market-tape">
                    <div class="sports-market-row">
                        <div><strong>Market pressure</strong><span>Rapid movement inferred from available snapshots.</span></div>
                        <b>Watch</b>
                    </div>
                    <div class="sports-market-row">
                        <div><strong>Line quality</strong><span>Needs matched provider price before action scoring.</span></div>
                        <b>Gated</b>
                    </div>
                    <div class="sports-market-row">
                        <div><strong>Execution status</strong><span>Research, paper tracking, and manual verification.</span></div>
                        <b>Manual</b>
                    </div>
                </div>
            </div>
        </aside>
    </section>

    <section class="sports-product-section sports-operational-strip" aria-label="Live system telemetry">
        <div class="sports-system-ticker">
            <span>ESPN scoreboard sync</span>
            <span>market normalization</span>
            <span>confidence cap review</span>
            <span>provider health check</span>
            <span>arbitrage reject audit</span>
            <span>paper execution guard</span>
        </div>
        <div class="sports-signal-system" aria-hidden="true">
            <i style="--x: 12%; --y: 58%"></i>
            <i style="--x: 32%; --y: 34%"></i>
            <i style="--x: 52%; --y: 70%"></i>
            <i style="--x: 73%; --y: 42%"></i>
            <i style="--x: 88%; --y: 62%"></i>
        </div>
    </section>

    <section class="sports-product-section is-wide">
        <span class="sports-product-kicker">Decision architecture</span>
        <h2>Built to separate signal from atmosphere.</h2>
        <p>The platform should earn confidence before it displays confidence. Lineforge makes source depth, freshness, odds availability, risk controls, and manual verification visible at every decision point.</p>
        <div class="sports-flow-diagram">
            <article class="sports-flow-node"><strong>Public data fabric</strong><span>Scoreboards, schedules, standings, weather, injuries, and historical snapshots.</span></article>
            <article class="sports-flow-node"><strong>Market normalization</strong><span>Odds, implied probability, no-vig estimates, source health, and stale-data flags.</span></article>
            <article class="sports-flow-node"><strong>AI calibration</strong><span>Confidence caps, edge context, volatility, inferred pressure, and missing-data penalties.</span></article>
            <article class="sports-flow-node"><strong>Manual action layer</strong><span>Paper slips, audit trails, responsible limits, and final user verification.</span></article>
        </div>
    </section>

    <section class="sports-product-section">
        <span class="sports-product-kicker">Product surface</span>
        <h2>Every panel has a job.</h2>
        <p>Lineforge is built for operators who need dense, readable context rather than promotional picks. Primary intelligence gets weight; secondary context recedes; unresolved inputs stay visible.</p>
        <div class="sports-product-grid">
            <article class="sports-product-card">
                <strong>Live Board</strong>
                <p>Track live, upcoming, and final events across major leagues with explicit source-state labeling.</p>
            </article>
            <article class="sports-product-card">
                <strong>Model Reads</strong>
                <p>Compare confidence, fair odds, market context, and missing-data penalties before trusting a read.</p>
            </article>
            <article class="sports-product-card">
                <strong>Arbitrage Review</strong>
                <p>Normalize provider odds, reject stale or mismatched markets, and separate true arbitrage from middles and positive-EV candidates.</p>
            </article>
            <article class="sports-product-card">
                <strong>Execution Center</strong>
                <p>Paper mode first, official providers only, explicit live opt-in, risk limits, audit logs, and emergency stop controls.</p>
            </article>
            <article class="sports-product-card">
                <strong>Data Architecture</strong>
                <p>Graceful fallback across disabled, public, partial, and premium modules so the product degrades honestly instead of failing silently.</p>
            </article>
            <article class="sports-product-card">
                <strong>Provider Setup</strong>
                <p>Connect optional odds, injuries, lineup, news, and props feeds with clear storage and source-state labeling.</p>
            </article>
        </div>
    </section>
</main>
<?php sports_page_footer(); ?>
