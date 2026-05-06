<?php

require_once __DIR__ . '/config/product_layout.php';

sports_page_header('Methodology', '/methodology', [
    'description' => 'How Lineforge builds a signal from live game state, team strength, market prices, injury and lineup uncertainty, fair odds, expected value, and responsible risk checks.',
    'canonical' => '/methodology',
]);
?>
<main>
<section class="sports-product-section">
    <span class="sports-product-kicker">Quant research notes</span>
    <h1>How Lineforge builds and limits a signal.</h1>
    <p>Lineforge is designed to slow the decision down. It starts at a neutral matchup, adds only the data that is actually available, subtracts uncertainty for missing feeds, and keeps the final recommendation manual.</p>
    <div class="sports-method-grid">
        <div class="sports-research-note">
            <article class="sports-research-step">
                <b>Input 01</b>
                <div><strong>Game state</strong><p>League, status, score, venue, records, live clock, and public scoreboard freshness define the first context layer.</p></div>
            </article>
            <article class="sports-research-step">
                <b>Input 02</b>
                <div><strong>Market context</strong><p>Spread, total, moneyline, fair odds, provider source health, and market disagreement shape the edge estimate.</p></div>
            </article>
            <article class="sports-research-step">
                <b>Input 03</b>
                <div><strong>Availability risk</strong><p>Injuries, lineup certainty, minutes limits, late scratches, and player availability gaps reduce confidence until verified.</p></div>
            </article>
            <article class="sports-research-step">
                <b>Output</b>
                <div><strong>Calibrated probability</strong><p>The model converts confidence into fair probability and fair American odds, then caps it when data quality is partial.</p></div>
            </article>
        </div>
        <aside class="sports-product-terminal" aria-label="Methodology visualization">
            <div class="sports-product-terminal-head">
                <strong>Signal calibration example</strong>
                <span class="sports-product-status">Research</span>
            </div>
            <div class="sports-line-chart" aria-hidden="true">
                <span style="height: 36%"></span>
                <span style="height: 42%"></span>
                <span style="height: 61%"></span>
                <span style="height: 58%"></span>
                <span style="height: 76%"></span>
                <span style="height: 65%"></span>
                <span style="height: 54%"></span>
            </div>
            <div class="sports-market-row">
                <div><strong>Raw model confidence</strong><span>Model read before source gating.</span></div>
                <b>72%</b>
            </div>
            <div class="sports-market-row">
                <div><strong>Data quality cap</strong><span>No matched sportsbook line; public summary only.</span></div>
                <b>68%</b>
            </div>
            <div class="sports-market-row">
                <div><strong>Final action state</strong><span>Watch only until a provider price is verified.</span></div>
                <b>Gated</b>
            </div>
            <div class="sports-calibration-visual" aria-hidden="true">
                <span style="--hot: 42%"></span>
                <span style="--hot: 58%"></span>
                <span style="--hot: 76%"></span>
                <span style="--hot: 64%"></span>
                <span style="--hot: 48%"></span>
                <span style="--hot: 82%"></span>
                <span style="--hot: 56%"></span>
                <span style="--hot: 68%"></span>
                <span style="--hot: 52%"></span>
            </div>
        </aside>
    </div>
</section>

<section class="sports-product-section is-wide">
    <span class="sports-product-kicker">Market logic</span>
    <h2>Line movement is evidence, not a slogan.</h2>
    <p>Lineforge avoids claiming sharp money unless it is directly verified. The system instead describes observable market behavior: velocity, disagreement, stale data, and source sequencing.</p>
    <div class="sports-flow-diagram">
        <article class="sports-flow-node"><strong>Snapshot</strong><span>Collect odds, timestamps, lines, periods, outcomes, and provider health.</span></article>
        <article class="sports-flow-node"><strong>Normalize</strong><span>Map teams, markets, line values, odds formats, and event start times.</span></article>
        <article class="sports-flow-node"><strong>Compare</strong><span>Reject mismatches, remove vig, build consensus, and flag stale prices.</span></article>
        <article class="sports-flow-node"><strong>Explain</strong><span>Show rapid movement, high disagreement, volatility, or missing evidence.</span></article>
    </div>
</section>

<section class="sports-product-section">
    <span class="sports-product-kicker">Confidence controls</span>
    <h2>The model has brakes.</h2>
    <div class="sports-product-grid">
        <article class="sports-product-card"><strong>Missing Line Penalty</strong><p>If a live sportsbook price is absent, Lineforge can still brief the market but does not treat the signal as execution-ready.</p></article>
        <article class="sports-product-card"><strong>Stale Data Penalty</strong><p>Old odds snapshots and delayed providers reduce grade and can reject arbitrage entirely.</p></article>
        <article class="sports-product-card"><strong>Market Matching Gate</strong><p>Full-game totals are not compared to first-half totals, and mismatched spread lines stay out of pure arbitrage.</p></article>
    </div>
</section>
</main>
<?php sports_page_footer(); ?>
