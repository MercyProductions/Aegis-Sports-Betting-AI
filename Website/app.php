<?php

require_once __DIR__ . '/config/sports_product.php';
require_once __DIR__ . '/config/aegis_sports.php';
require_once __DIR__ . '/config/lineforge_icons.php';
require_once __DIR__ . '/config/execution.php';

aegis_sports_product_bootstrap();

function sports_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function sports_team_mark(array $team): string
{
    $source = (string) ($team['abbr'] ?? $team['short'] ?? $team['name'] ?? 'TM');
    $letters = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $source));
    return substr($letters !== '' ? $letters : 'TM', 0, 2);
}

function sports_team_avatar(array $team): string
{
    $label = (string) ($team['abbr'] ?? $team['name'] ?? 'Team');
    $logo = (string) ($team['logo'] ?? '');

    if ($logo !== '') {
        return '<img src="' . sports_e($logo) . '" alt="' . sports_e($label) . '">';
    }

    return '<span>' . sports_e(sports_team_mark($team)) . '</span>';
}

function sports_prediction_winner(array $prediction, array $gamesById): string
{
    $direct = trim((string) ($prediction['predictedWinner'] ?? ''));
    if ($direct !== '' && !preg_match('/^watch\s+/i', $direct)) {
        return $direct;
    }

    $game = $gamesById[(string) ($prediction['gameId'] ?? '')] ?? null;
    if (is_array($game)) {
        if ((string) ($game['statusKey'] ?? '') === 'final') {
            $winnerSide = !empty($game['home']['winner']) ? 'home' : 'away';
            return (string) ($game[$winnerSide]['name'] ?? $game[$winnerSide]['abbr'] ?? 'Winner');
        }

        $side = function_exists('aegis_sports_pick_side')
            ? aegis_sports_pick_side($game, $prediction)
            : null;
        if ($side === 'away' || $side === 'home') {
            return (string) ($game[$side]['name'] ?? $game[$side]['abbr'] ?? 'Predicted winner');
        }

        if (function_exists('aegis_sports_prediction_winner_projection')) {
            $projection = aegis_sports_prediction_winner_projection($game, $prediction);
            $label = trim((string) ($projection['label'] ?? ''));
            if ($label !== '' && $label !== 'No clear side') {
                return $label;
            }
        }
    }

    $pick = trim((string) ($prediction['pick'] ?? 'Predicted winner'));
    $pick = (string) preg_replace('/^postgame review:\s*/i', '', $pick);
    $pick = (string) preg_replace('/^watch\s+/i', '', $pick);
    $pick = (string) preg_replace('/\s+[+-]\d+.*$/', '', $pick);
    $matchup = trim((string) ($prediction['matchup'] ?? ''));
    return trim($pick) !== '' && trim($pick) !== $matchup ? trim($pick) : 'Predicted winner';
}

function sports_icon(string $name): string
{
    return lineforge_icon($name);
}

function sports_signal_icon_for(string $label): string
{
    $text = strtolower($label);
    if (str_contains($text, 'injury') || str_contains($text, 'lineup') || str_contains($text, 'blocker')) {
        return 'injury-risk';
    }
    if (str_contains($text, 'line') || str_contains($text, 'odds') || str_contains($text, 'book')) {
        return 'line-movement';
    }
    if (str_contains($text, 'market') || str_contains($text, 'coverage')) {
        return 'market-analysis';
    }
    if (str_contains($text, 'stake') || str_contains($text, 'bankroll')) {
        return 'bankroll-tracking';
    }
    if (str_contains($text, 'manual') || str_contains($text, 'execution') || str_contains($text, 'location')) {
        return 'status-indicator';
    }
    if (str_contains($text, 'edge')) {
        return 'edge-rating';
    }
    if (str_contains($text, 'risk')) {
        return 'risk-tier';
    }
    if (str_contains($text, 'probability') || str_contains($text, 'confidence') || str_contains($text, 'readiness')) {
        return 'confidence-score';
    }
    return 'ai-signals';
}

function sports_to_win(string $odds, float $stake): string
{
    $price = (int) preg_replace('/[^0-9+\-]/', '', $odds);
    if ($price === 0) {
        return '$0.00';
    }

    $win = $price > 0
        ? $stake * ($price / 100)
        : $stake * (100 / abs($price));

    return '$' . number_format($win, 2);
}

$account = aegis_sports_product_require_auth('/app');
$preferences = aegis_sports_product_preferences();
$displayName = (string) ($account['username'] ?? 'Lineforge Operator');
$tier = aegis_sports_product_tier($account);
$limits = $tier['limits'];
$sportsState = aegis_sports_product_public_state(aegis_sports_state($limits));
$providerSettings = aegis_sports_product_provider_settings_public();
$executionState = lineforge_execution_public_state($account);
$games = array_values((array) ($sportsState['games'] ?? []));
$predictionCards = array_values((array) ($sportsState['predictions'] ?? []));
$decisionBoard = array_values((array) ($sportsState['decisionBoard'] ?? []));
$displayGames = array_slice($games, 0, 12);
$dashboardLiveGames = array_slice(array_values(array_filter($games, static function ($game): bool {
    return (string) ($game['statusKey'] ?? '') === 'live';
})), 0, 5);
$displayPredictions = array_slice($predictionCards, 0, 8);
$displayDecisionBoard = array_slice($decisionBoard, 0, 6);
$topPrediction = $sportsState['topPick'] ?? ($displayPredictions[0] ?? []);
$sportsCoverage = $sportsState['coverage'] ?? ['configuredLeagues' => 0, 'activeLeagues' => 0, 'groups' => []];
$marketAccess = $sportsState['marketAccess'] ?? [
    'oddsProvider' => 'The Odds API',
    'oddsProviderConfigured' => false,
    'exchangeProvider' => 'Kalshi',
    'bookmakers' => 0,
    'availableLines' => 0,
    'matchedEvents' => 0,
    'kalshiMarketsCached' => 0,
    'refreshCadence' => '90s odds cache',
    'note' => 'Provider links are available for discovery. Connect an odds feed for live sportsbook prices.',
];
$primaryHistory = $displayGames[0]['history'] ?? [50, 54, 57, 59, 63, 67, 71, 78];
$factors = (array) ($sportsState['factors'] ?? []);
$edgeStack = (array) ($sportsState['edgeStack'] ?? []);
$bankroll = (array) ($sportsState['bankroll'] ?? []);
$bankrollControls = (array) ($sportsState['riskControls'] ?? []);
$sportsAlerts = (array) ($sportsState['alerts'] ?? []);
$sportsRules = (array) ($sportsState['rules'] ?? []);
$sportsOpportunities = (array) ($sportsState['opportunities'] ?? []);
$arbitrageState = (array) ($sportsState['arbitrage'] ?? []);
$dataArchitecture = (array) ($sportsState['dataArchitecture'] ?? []);
$sportsPerformance = (array) ($sportsState['performance'] ?? []);
$sportsTape = (array) ($sportsState['tape'] ?? []);
$sportsMetrics = (array) ($sportsState['metrics'] ?? []);
$modelSources = (array) ($sportsState['modelSources'] ?? []);
$sportsInsight = (array) ($sportsState['insight'] ?? []);
$primaryMarket = (string) ($sportsState['primaryMarket'] ?? 'Primary market');
$selectedMarket = (string) ($sportsState['selectedMarket'] ?? ($topPrediction['pick'] ?? 'Market'));
$marketHistory = (array) ($sportsState['marketHistory'] ?? $primaryHistory);
$bookSummary = (array) ($sportsState['bookSummary'] ?? []);
$gameSections = (array) ($sportsState['gameSections'] ?? []);
$sportsConfig = [
    'endpoint' => 'api/sports-live.php',
    'providerEndpoint' => 'api/provider-settings.php',
    'executionEndpoint' => 'api/execution-center.php',
    'providerSettings' => $providerSettings,
    'executionState' => $executionState,
    'csrfToken' => aegis_sports_product_csrf_token(),
    'refreshSeconds' => (int) ($limits['refresh_seconds'] ?? 60),
];
$sourceLabelShort = (string) ($sportsState['sourceBadge'] ?? 'Feed');
$sourceLabel = (string) ($sportsState['sourceLabel'] ?? 'Sports feed');
$tierLabel = ucfirst((string) ($tier['tier'] ?? 'free'));
$bankrollBalance = aegis_sports_money((float) ($bankroll['balance'] ?? 0));
$bankrollProfitDisplay = ((float) ($bankroll['profit'] ?? 0) >= 0 ? '+' : '-') . aegis_sports_money(abs((float) ($bankroll['profit'] ?? 0)));
$bankrollProfitPercent = number_format((float) ($bankroll['profitPercent'] ?? 0), 1) . '%';
$predictionIndexesByGame = [];
$gamesById = [];
foreach ($games as $game) {
    $gameId = (string) ($game['id'] ?? '');
    if ($gameId !== '') {
        $gamesById[$gameId] = (array) $game;
    }
}
foreach ($displayPredictions as $predictionIndex => $prediction) {
    $predictionGameId = (string) ($prediction['gameId'] ?? '');
    if ($predictionGameId !== '') {
        $predictionIndexesByGame[$predictionGameId] = $predictionIndex;
    }
}
$sportsTabs = [
    ['label' => 'All Sports', 'filter' => 'all', 'icon' => 'grid'],
    ['label' => 'NBA', 'filter' => 'league:nba', 'icon' => 'basketball'],
    ['label' => 'NFL', 'filter' => 'league:nfl', 'icon' => 'football'],
    ['label' => 'MLB', 'filter' => 'league:mlb', 'icon' => 'baseball'],
    ['label' => 'NHL', 'filter' => 'league:nhl', 'icon' => 'hockey'],
    ['label' => 'Soccer', 'filter' => 'group:soccer', 'icon' => 'soccer'],
    ['label' => 'UFC', 'filter' => 'league:ufc', 'icon' => 'combat'],
    ['label' => 'Tennis', 'filter' => 'group:tennis', 'icon' => 'tennis'],
    ['label' => 'Esports', 'filter' => 'group:esports', 'icon' => 'monitor'],
];
$sportsFilterOptions = [
    ['label' => 'All Sports', 'filter' => 'all'],
    ['label' => 'Live Now', 'filter' => 'live'],
    ['label' => 'Upcoming', 'filter' => 'scheduled'],
    ['label' => 'NBA', 'filter' => 'league:nba'],
    ['label' => 'WNBA', 'filter' => 'league:wnba'],
    ['label' => 'NCAAB', 'filter' => 'league:ncaab'],
    ['label' => 'NCAAW', 'filter' => 'league:ncaaw'],
    ['label' => 'NFL', 'filter' => 'league:nfl'],
    ['label' => 'College Football', 'filter' => 'league:ncaaf'],
    ['label' => 'UFL', 'filter' => 'league:ufl'],
    ['label' => 'MLB', 'filter' => 'league:mlb'],
    ['label' => 'NCAA Baseball', 'filter' => 'league:college-baseball'],
    ['label' => 'NCAA Softball', 'filter' => 'league:college-softball'],
    ['label' => 'NHL', 'filter' => 'league:nhl'],
    ['label' => 'NCAA Hockey', 'filter' => 'league:ncaa-hockey'],
    ['label' => 'Soccer', 'filter' => 'group:soccer'],
    ['label' => 'MLS', 'filter' => 'league:mls'],
    ['label' => 'Combat Sports', 'filter' => 'group:combat'],
    ['label' => 'Tennis', 'filter' => 'group:tennis'],
    ['label' => 'Golf', 'filter' => 'group:golf'],
    ['label' => 'Racing', 'filter' => 'group:racing'],
    ['label' => 'Cricket', 'filter' => 'group:cricket'],
    ['label' => 'Rugby', 'filter' => 'group:rugby'],
    ['label' => 'Lacrosse', 'filter' => 'group:lacrosse'],
    ['label' => 'Volleyball', 'filter' => 'group:volleyball'],
    ['label' => 'Esports', 'filter' => 'group:esports'],
];
$topNav = [
    ['label' => 'Dashboard', 'view' => 'dashboard', 'icon' => 'dashboard'],
    ['label' => 'Live Games', 'view' => 'live', 'icon' => 'live-games'],
    ['label' => 'Research Signals', 'view' => 'picks', 'icon' => 'ai-signals'],
    ['label' => 'Analytics', 'view' => 'analytics', 'icon' => 'analytics'],
    ['label' => 'Markets', 'view' => 'arbitrage', 'icon' => 'market-analysis'],
    ['label' => 'Execution', 'view' => 'execution', 'icon' => 'status-indicator'],
    ['label' => 'Settings', 'view' => 'settings', 'icon' => 'settings'],
];
$sidebarNav = [
    ['label' => 'Overview', 'view' => 'dashboard', 'meta' => '', 'icon' => 'dashboard'],
    ['label' => 'Live Games', 'view' => 'live', 'meta' => (string) count($gameSections['live'] ?? []), 'icon' => 'live-games'],
    ['label' => 'Watchlists', 'view' => 'live', 'meta' => (string) count($gameSections['scheduled'] ?? []), 'icon' => 'watchlists'],
    ['label' => 'Alerts', 'view' => 'analytics', 'meta' => '', 'icon' => 'alerts'],
    ['label' => 'Market Analysis', 'view' => 'arbitrage', 'meta' => '', 'icon' => 'market-analysis'],
    ['label' => 'Research Signals', 'view' => 'picks', 'meta' => '', 'icon' => 'ai-signals'],
    ['label' => 'Line Movement', 'view' => 'arbitrage', 'meta' => '', 'icon' => 'line-movement'],
    ['label' => 'Edge Rating', 'view' => 'arbitrage', 'meta' => '', 'icon' => 'edge-rating'],
    ['label' => 'Execution Center', 'view' => 'execution', 'meta' => strtoupper((string) ($executionState['mode'] ?? 'paper')), 'icon' => 'status-indicator'],
    ['label' => 'Historical Trends', 'view' => 'analytics', 'meta' => '', 'icon' => 'historical-trends'],
    ['label' => 'Settings', 'view' => 'settings', 'meta' => '', 'icon' => 'settings'],
];
$marketRows = [];
foreach ($displayGames as $game) {
    foreach (array_slice((array) ($game['betLinks'] ?? []), 0, 8) as $link) {
        $marketRows[] = [
            'providerKey' => strtolower((string) ($link['providerKey'] ?? $link['title'] ?? 'provider')),
            'provider' => (string) ($link['title'] ?? 'Provider'),
            'kind' => (string) ($link['kind'] ?? 'Sportsbook'),
            'matchup' => (string) ($game['matchup'] ?? 'Matchup'),
            'league' => (string) ($game['league'] ?? ''),
            'statusKey' => (string) ($game['statusKey'] ?? 'scheduled'),
            'statusLabel' => (string) ($game['statusLabel'] ?? 'Watch'),
            'market' => (string) ($link['market'] ?? 'Market'),
            'line' => (string) ($link['line'] ?? 'Line'),
            'price' => (string) ($link['price'] ?? '--'),
            'available' => !empty($link['available']),
            'url' => (string) ($link['url'] ?? '#'),
            'note' => (string) ($link['note'] ?? 'Verify eligibility and final price before taking action.'),
        ];
    }
}
$providerFilters = [
    ['label' => 'All apps', 'filter' => 'all'],
    ['label' => 'Sportsbooks', 'filter' => 'sportsbook'],
    ['label' => 'Kalshi', 'filter' => 'kalshi'],
    ['label' => 'Live only', 'filter' => 'live'],
];
$betSlipItems = [];
foreach ($displayPredictions as $predictionIndex => $prediction) {
    if (count($betSlipItems) >= 2) {
        break;
    }
    if (isset($prediction['canBet']) && !$prediction['canBet']) {
        continue;
    }

    $stake = 100.0;
    $oddsDisplay = (string) ($prediction['odds'] ?? '');
    if (!preg_match('/[+-]?\d+/', $oddsDisplay)) {
        $oddsDisplay = (string) ($prediction['fairOdds'] ?? '-110');
    }

    $betSlipItems[] = [
        'predictionIndex' => $predictionIndex,
        'pick' => $prediction['pick'] ?? 'Pick',
        'market' => $prediction['market'] ?? 'Market',
        'matchup' => $prediction['matchup'] ?? 'Matchup',
        'odds' => $oddsDisplay,
        'stake' => number_format($stake, 2, '.', ''),
        'win' => sports_to_win($oddsDisplay, $stake),
    ];
}
$topConfidence = (int) ($topPrediction['confidenceValue'] ?? preg_replace('/[^0-9]/', '', (string) ($topPrediction['confidence'] ?? '58')));
$topConfidence = $topConfidence > 0 ? $topConfidence : 58;
$topBreakdown = (array) ($topPrediction['breakdown'] ?? []);
$topScore = (array) ($topBreakdown['score'] ?? []);
$topComparison = (array) ($topPrediction['teamComparison'] ?? []);
$topSignals = (array) ($topComparison['signals'] ?? []);
$topMarketLinks = array_values((array) ($topPrediction['marketLinks'] ?? []));
$availableTopLines = count(array_filter($topMarketLinks, static function ($link): bool {
    return !empty($link['available']) && strtolower((string) ($link['kind'] ?? '')) === 'sportsbook';
}));
$topBestBook = (string) ($topPrediction['bestBook'] ?? 'Provider links');
$topBookLine = (string) ($topPrediction['bookLine'] ?? ($topPrediction['odds'] ?? ($topPrediction['fairOdds'] ?? '--')));
$topInjuryCount = (int) ($topSignals['injuryCount'] ?? 0);
$topPlayerCount = (int) ($topSignals['playerCount'] ?? 0);
$topReadiness = (array) ($topPrediction['readiness'] ?? (function_exists('aegis_sports_product_prediction_readiness') ? aegis_sports_product_prediction_readiness((array) $topPrediction) : []));
$topReadinessScore = (int) ($topReadiness['score'] ?? $topConfidence);
$topReadinessLabel = (string) ($topReadiness['label'] ?? 'Watch');
$topReadinessDetail = (string) ($topReadiness['detail'] ?? 'Verify the model, market price, availability news, and legal eligibility before making a decision.');
$topReadinessBlockers = (int) ($topReadiness['blockerCount'] ?? 0);
$topLineShopping = (array) ($topReadiness['lineShopping'] ?? []);
$topDecisionChecks = array_values(array_filter((array) ($topReadiness['checks'] ?? []), 'is_array'));
if (!$topDecisionChecks) {
    $topDecisionChecks = [
        [
            'label' => 'Model probability',
            'value' => (string) ($topPrediction['fairProbability'] ?? ($topConfidence . '.0%')),
            'detail' => 'Fair odds ' . (string) ($topPrediction['fairOdds'] ?? '--') . ' before book comparison.',
            'tone' => $topConfidence >= 68 ? 'ok' : 'warn',
        ],
        [
            'label' => 'Best available line',
            'value' => $availableTopLines > 0 ? $topBestBook : 'Needs live line',
            'detail' => $availableTopLines > 0 ? $topBookLine : 'Add an odds API key or verify the price manually in the book.',
            'tone' => $availableTopLines > 0 ? 'ok' : 'warn',
        ],
        [
            'label' => 'Injury and lineup check',
            'value' => $topInjuryCount > 0 ? ($topInjuryCount . ' listed') : 'Manual check',
            'detail' => $topPlayerCount > 0 ? ($topPlayerCount . ' public player signals attached.') : 'Confirm official injuries, scratches, lineups, and minutes restrictions.',
            'tone' => $topPlayerCount > 0 ? 'ok' : 'warn',
        ],
    ];
}
$topDecisionContext = [
    ['label' => 'Readiness', 'icon' => 'confidence-ring', 'value' => $topReadinessScore . '/100', 'detail' => $topReadinessLabel],
    ['label' => 'Edge', 'icon' => 'edge-rating', 'value' => (string) ($topPrediction['edge'] ?? '+0.0%'), 'detail' => 'Estimated model gap versus market context.'],
    ['label' => 'Line shop', 'icon' => 'line-movement', 'value' => (string) ($topLineShopping['bestBook'] ?? $topBestBook), 'detail' => (string) ($topLineShopping['summary'] ?? ($availableTopLines . ' live lines attached.'))],
    ['label' => 'Blockers', 'icon' => 'risk-tier', 'value' => $topReadinessBlockers > 0 ? (string) $topReadinessBlockers : 'None flagged', 'detail' => $topReadinessBlockers > 0 ? 'Open the drawer before acting.' : 'Still complete manual verification.'],
];
$lineforgeMicroGlyphs = [
    ['label' => 'Probability', 'icon' => 'probability-bars'],
    ['label' => 'Live feed', 'icon' => 'live-feed'],
    ['label' => 'Trend', 'icon' => 'trend-up'],
    ['label' => 'Calibration', 'icon' => 'confidence-ring'],
    ['label' => 'Status', 'icon' => 'status-indicator'],
    ['label' => 'Pulse', 'icon' => 'signal-pulse'],
    ['label' => 'Risk tier', 'icon' => 'risk-tier'],
];
$avatarInitials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $displayName) ?: 'GN', 0, 2));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lineforge Intelligence</title>
    <meta name="description" content="Protected Lineforge workspace for sports intelligence, signal readiness, live market movement, calibration review, and manual verification.">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#071624">
    <link rel="icon" type="image/png" href="assets/images/lineforge-logo.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="stylesheet" href="assets/css/aegis.css?v=20260425-aegis-sports-coverage-2">
    <link rel="stylesheet" href="assets/css/premium.css?v=20260505-phase5-generalized-1">
</head>
<body<?= aegis_sports_product_body_attributes($preferences); ?>>
    <div class="sports-reference-page betedge-page">
        <main>
            <?php if (!$account['signedIn']): ?>
                <section class="product-login sports-login">
                    <div>
                        <p class="kicker"><span>SPORTS.</span> Standalone Mode</p>
                        <h1>The sports prediction lab is ready.</h1>
                        <p>This standalone build uses local product settings from the website environment file. Configure tier limits, refresh cadence, and provider keys in <code>.env</code>.</p>
                        <div class="hero-actions">
                            <a class="button primary" href="/app">Open dashboard</a>
                            <a class="button ghost" href="/README.md">Setup notes</a>
                        </div>
                    </div>
                </section>
            <?php else: ?>
                <section class="betedge-shell" aria-label="Lineforge intelligence terminal">
                    <header class="betedge-topbar">
                        <a class="betedge-brand" href="/app" aria-label="Lineforge dashboard">
                            <img class="lineforge-brand-logo lineforge-brand-logo-app" src="assets/images/lineforge-logo.png" alt="" aria-hidden="true">
                        </a>

                        <nav class="betedge-topnav" aria-label="Sports intelligence views">
                            <?php foreach ($topNav as $index => $item): ?>
                                <a class="<?= $index === 0 ? 'is-active' : ''; ?>" href="#<?= sports_e($item['view']); ?>" data-sports-view="<?= sports_e($item['view']); ?>">
                                    <span class="betedge-nav-icon"><?= sports_icon($item['icon'] ?? 'dashboard'); ?></span>
                                    <?= sports_e($item['label']); ?>
                                </a>
                            <?php endforeach; ?>
                        </nav>

                        <div class="betedge-userbar">
                            <span class="betedge-pro-chip"><i></i><?= sports_e($tierLabel); ?></span>
                            <button class="betedge-icon-button" type="button" title="Alerts" data-sports-view="analytics">
                                <span><?= sports_icon('alerts'); ?></span>
                                <b><?= sports_e((string) count($sportsAlerts)); ?></b>
                            </button>
                            <div class="betedge-user">
                                <span class="betedge-user-avatar"><?= sports_icon('user-profile'); ?><b><?= sports_e($avatarInitials); ?></b></span>
                                <div>
                                    <strong><?= sports_e($displayName); ?></strong>
                                    <small><?= sports_e($tierLabel); ?> access</small>
                                </div>
                            </div>
                        </div>
                    </header>

                    <div class="betedge-layout">
                        <aside class="betedge-sidebar">
                            <nav class="betedge-side-nav" aria-label="Lineforge intelligence sections">
                                <?php foreach ($sidebarNav as $index => $item): ?>
                                    <button type="button" class="<?= $index === 0 ? 'is-active' : ''; ?>" data-sports-view="<?= sports_e($item['view']); ?>">
                                        <span class="betedge-icon"><?= sports_icon($item['icon'] ?? 'grid'); ?></span>
                                        <strong><?= sports_e($item['label']); ?></strong>
                                        <?php if ($item['meta'] !== ''): ?>
                                            <em><?= sports_e($item['meta']); ?></em>
                                        <?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </nav>

                        </aside>

                        <section class="betedge-main">
                            <div class="betedge-filter-bar" aria-label="Sports board filters">
                                <label class="betedge-filter-combo">
                                    <span>Sport</span>
                                    <select id="sportsLeagueSelect" data-sports-filter-select>
                                        <?php foreach ($sportsFilterOptions as $option): ?>
                                            <option value="<?= sports_e($option['filter']); ?>"><?= sports_e($option['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="betedge-game-search">
                                    <span>Search</span>
                                    <input id="sportsGameSearch" type="search" placeholder="Search team, league, or game" autocomplete="off" data-sports-game-search>
                                </label>
                            </div>
                            <div class="betedge-sport-tabs" id="sportsFilterTabs">
                                <?php foreach ($sportsTabs as $index => $tab): ?>
                                    <button type="button" class="<?= $index === 0 ? 'is-active' : ''; ?>" data-sports-filter="<?= sports_e($tab['filter']); ?>">
                                        <span class="betedge-icon"><?= sports_icon($tab['icon'] ?? 'grid'); ?></span>
                                        <span class="betedge-tab-label"><?= sports_e($tab['label']); ?></span>
                                    </button>
                                <?php endforeach; ?>
                                <button type="button" data-sports-view="live">
                                    <span class="betedge-icon"><?= sports_icon('plus'); ?></span>
                                    <span class="betedge-tab-label">More</span>
                                </button>
                            </div>

                            <div class="lineforge-workspace-bar" aria-label="Workspace orchestration controls">
                                <div class="lineforge-workspace-group" aria-label="Workspace modes">
                                    <span>Workspace</span>
                                    <button type="button" class="is-active" data-workspace-mode="command">Command</button>
                                    <button type="button" data-workspace-mode="analyst">Analyst</button>
                                    <button type="button" data-workspace-mode="monitoring">Monitoring</button>
                                    <button type="button" data-workspace-mode="signals">Signals</button>
                                    <button type="button" data-workspace-mode="markets">Markets</button>
                                    <button type="button" data-workspace-mode="execution">Execution</button>
                                </div>
                                <div class="lineforge-workspace-group" aria-label="Density controls">
                                    <span>Density</span>
                                    <button type="button" data-workspace-density="compact">Compact</button>
                                    <button type="button" class="is-active" data-workspace-density="balanced">Balanced</button>
                                    <button type="button" data-workspace-density="expanded">Expanded</button>
                                </div>
                                <div class="lineforge-workspace-group lineforge-workspace-actions" aria-label="Panel controls">
                                    <span>Panels</span>
                                    <button type="button" data-workspace-collapse-secondary>Collapse secondary</button>
                                    <button type="button" data-workspace-expand-all>Expand all</button>
                                </div>
                            </div>
                            <div class="lineforge-workspace-dock" id="lineforgeWorkspaceDock" aria-label="Minimized workspace panels"></div>

                            <section class="betedge-card betedge-decision-card lineforge-hero" data-sports-dashboard-hero aria-label="Lineforge sports intelligence command center">
                                <div class="betedge-decision-main">
                                    <span class="betedge-decision-eyebrow">Lineforge Intelligence OS</span>
                                    <h1>Sports intelligence, forged in real time.</h1>
                                    <p id="sportsDecisionReason"><?= sports_e($topPrediction['reason'] ?? ($sportsInsight['copy'] ?? 'Lineforge ranks the board by model probability, live market context, missing-data risk, and manual verification readiness.')); ?></p>
                                    <div class="lineforge-trust-strip" aria-label="Platform trust indicators">
                                        <span>Manual-first research</span>
                                        <span>Live market context</span>
                                        <span>Missing-data penalties</span>
                                        <span>No auto-execution</span>
                                    </div>
                                    <div class="lineforge-micro-glyphs" aria-label="Lineforge signal language">
                                        <?php foreach ($lineforgeMicroGlyphs as $glyph): ?>
                                            <span><?= sports_icon($glyph['icon']); ?><b><?= sports_e($glyph['label']); ?></b></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="lineforge-hero-visual" aria-label="Live intelligence preview">
                                        <article class="lineforge-float-panel lineforge-primary-signal">
                                            <div class="lineforge-panel-label"><?= sports_icon('ai-signals'); ?><span>Primary signal</span></div>
                                            <strong id="sportsDecisionPick"><?= sports_e(sports_prediction_winner((array) $topPrediction, $gamesById)); ?></strong>
                                            <small><?= sports_e($topPrediction['matchup'] ?? 'Best board matchup'); ?> / <?= sports_e($topPrediction['market'] ?? 'Market'); ?></small>
                                            <div class="lineforge-mini-bars" aria-hidden="true">
                                                <i style="--h: 41%"></i><i style="--h: 58%"></i><i style="--h: 48%"></i><i style="--h: 72%"></i><i style="--h: 66%"></i><i style="--h: 84%"></i>
                                            </div>
                                        </article>
                                        <article class="lineforge-float-panel lineforge-odds-card">
                                            <div class="lineforge-panel-label"><?= sports_icon('line-movement'); ?><span>Odds movement</span></div>
                                            <strong><?= sports_e($topBookLine); ?></strong>
                                            <small><?= sports_e($topBestBook); ?> / Fair <?= sports_e($topPrediction['fairOdds'] ?? '--'); ?></small>
                                            <div class="lineforge-tape-line" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>
                                        </article>
                                        <article class="lineforge-float-panel lineforge-confidence-card">
                                            <div class="lineforge-panel-label"><?= sports_icon('confidence-score'); ?><span>Calibration estimate</span></div>
                                            <strong><?= sports_e($topPrediction['confidence'] ?? ($topConfidence . '%')); ?></strong>
                                            <small><?= sports_e($topReadinessLabel); ?> readiness</small>
                                            <div class="lineforge-ring" style="--p: <?= sports_e((string) max(0, min(100, $topReadinessScore))); ?>%"></div>
                                        </article>
                                        <article class="lineforge-float-panel lineforge-feed-card">
                                            <div class="lineforge-panel-label"><?= sports_icon('live-feed'); ?><span>Live feed</span></div>
                                            <strong><?= sports_e((string) count($games)); ?> events scanned</strong>
                                            <small><?= sports_e($sourceLabelShort); ?> / <?= sports_e((string) ($marketAccess['availableLines'] ?? 0)); ?> live lines</small>
                                        </article>
                                    </div>
                                    <div class="betedge-decision-metrics" id="sportsDecisionMetrics">
                                        <div><span><?= sports_icon('matchup-analysis'); ?>Match</span><strong><?= sports_e($topPrediction['matchup'] ?? 'Best board matchup'); ?></strong><small><?= sports_e($topPrediction['league'] ?? 'Sports'); ?></small></div>
                                        <div><span><?= sports_icon('market-analysis'); ?>Market</span><strong><?= sports_e($topPrediction['market'] ?? 'Monitor'); ?></strong><small><?= sports_e($topBookLine); ?></small></div>
                                        <div><span><?= sports_icon('confidence-score'); ?>Research confidence</span><strong><?= sports_e($topPrediction['confidence'] ?? ($topConfidence . '%')); ?></strong><small>Fair <?= sports_e($topPrediction['fairOdds'] ?? '--'); ?></small></div>
                                        <div><span><?= sports_icon('edge-rating'); ?>Edge</span><strong><?= sports_e($topPrediction['edge'] ?? '+0.0%'); ?></strong><small><?= sports_e($topPrediction['expectedValue'] ?? '$0.00'); ?> EV</small></div>
                                        <div><span><?= sports_icon('risk-tier'); ?>Risk</span><strong><?= sports_e($topPrediction['risk'] ?? 'Model risk'); ?></strong><small><?= sports_e($topPrediction['stake'] ?? '0.00u'); ?> paper stake</small></div>
                                        <div><span><?= sports_icon('confidence-ring'); ?>Readiness</span><strong><?= sports_e($topReadinessScore . '/100'); ?></strong><small><?= sports_e($topReadinessLabel); ?></small></div>
                                    </div>
                                </div>
                                <aside class="betedge-decision-rail">
                                    <div class="betedge-decision-grade" id="sportsDecisionGrade">
                                        <span>Signal readiness</span>
                                        <strong><?= sports_e((string) $topReadinessScore); ?></strong>
                                        <small><?= sports_e($topReadinessLabel); ?></small>
                                    </div>
                                    <div class="betedge-decision-checks" id="sportsDecisionChecks">
                                        <?php foreach ($topDecisionChecks as $check): ?>
                                            <div class="betedge-check-<?= sports_e($check['tone']); ?>">
                                                <span><?= sports_icon(sports_signal_icon_for((string) ($check['label'] ?? ''))); ?><?= sports_e($check['label']); ?></span>
                                                <strong><?= sports_e($check['value']); ?></strong>
                                                <small><?= sports_e($check['detail']); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="betedge-decision-context" id="sportsDecisionContext">
                                        <?php foreach ($topDecisionContext as $item): ?>
                                            <div>
                                                <span><?= sports_icon($item['icon'] ?? sports_signal_icon_for((string) ($item['label'] ?? ''))); ?><?= sports_e($item['label']); ?></span>
                                                <strong><?= sports_e($item['value']); ?></strong>
                                                <small><?= sports_e($item['detail']); ?></small>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button class="sports-reference-place-bet" type="button" data-sports-open-pick="0">Open full pick breakdown</button>
                                </aside>
                            </section>

                            <section class="betedge-panel is-active" data-sports-panel="dashboard" id="dashboard">
                                <section class="betedge-card betedge-decision-board-card">
                                    <div class="betedge-card-head">
                                        <div>
                                            <strong>Actionable Pick Board</strong>
                                            <small id="sportsDecisionBoardCount"><?= sports_e((string) count($displayDecisionBoard)); ?> ranked picks</small>
                                        </div>
                                        <button type="button" data-sports-view="picks">Open Research Signals</button>
                                    </div>
                                    <div class="betedge-decision-board" id="sportsDecisionBoard">
                                        <?php if (empty($displayDecisionBoard)): ?>
                                            <div class="betedge-decision-board-empty">
                                                <strong>No ranked decisions yet</strong>
                                                <span>Keep the board open while Lineforge refreshes model picks, line status, and verification checks.</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php foreach ($displayDecisionBoard as $decision): ?>
                                            <?php
                                            $action = (array) ($decision['action'] ?? []);
                                            $tone = (string) ($action['tone'] ?? ($decision['readinessTone'] ?? 'warn'));
                                            ?>
                                            <article class="betedge-decision-row is-<?= sports_e($tone); ?>">
                                                <div class="betedge-rank-badge">
                                                    <span>#<?= sports_e((string) ($decision['rank'] ?? 0)); ?></span>
                                                    <strong><?= sports_e((string) ($decision['decisionScore'] ?? 0)); ?></strong>
                                                </div>
                                                <div class="betedge-decision-pick">
                                                    <strong><?= sports_e($decision['pick'] ?? 'Pick'); ?></strong>
                                                    <small><?= sports_e($decision['matchup'] ?? 'Matchup'); ?> / <?= sports_e($decision['league'] ?? 'Sports'); ?></small>
                                                </div>
                                                <div>
                                                    <span>Readiness</span>
                                                    <strong><?= sports_e((string) ($decision['readinessScore'] ?? 0)); ?>/100</strong>
                                                    <small><?= sports_e($decision['readinessLabel'] ?? 'Watch'); ?></small>
                                                </div>
                                                <div>
                                                    <span>Market</span>
                                                    <strong><?= sports_e($decision['market'] ?? 'Market'); ?></strong>
                                                    <small><?= sports_e($decision['bestBook'] ?? 'Provider links'); ?> / <?= sports_e($decision['bestPrice'] ?? '--'); ?></small>
                                                </div>
                                                <div>
                                                    <span>Edge</span>
                                                    <strong><?= sports_e($decision['edge'] ?? '+0.0%'); ?></strong>
                                                    <small><?= sports_e($decision['expectedValue'] ?? '$0.00'); ?> EV</small>
                                                </div>
                                                <div>
                                                    <span>Blockers</span>
                                                    <strong><?= sports_e((string) ($decision['blockers'] ?? 0)); ?></strong>
                                                    <small><?= sports_e($decision['blockerSummary'] ?? 'Manual verification'); ?></small>
                                                </div>
                                                <button type="button" class="betedge-decision-action" data-sports-open-pick="<?= sports_e((string) ($decision['predictionIndex'] ?? 0)); ?>">
                                                    <span><?= sports_e($action['label'] ?? 'Open'); ?></span>
                                                    <small><?= sports_e($action['detail'] ?? 'Inspect the full pick before acting.'); ?></small>
                                                </button>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </section>

                                <section class="betedge-card betedge-live-card">
                                    <div class="betedge-card-head">
                                        <div>
                                            <strong class="lineforge-section-title"><?= sports_icon('live-games'); ?>Top Live Events</strong>
                                            <small><i></i><span id="sportsSourceLabel"><?= sports_e($sourceLabelShort); ?></span></small>
                                        </div>
                                        <button type="button" data-sports-view="live">View All</button>
                                    </div>

                                    <div class="betedge-events-row" id="sportsLiveBoard">
                                        <?php if (empty($dashboardLiveGames)): ?>
                                            <div class="betedge-empty-state">
                                                <strong>No live games right now</strong>
                                                <span>Upcoming and final games are still available in View All.</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php foreach ($dashboardLiveGames as $gameIndex => $game): ?>
                                            <?php $cardPredictionIndex = $predictionIndexesByGame[(string) ($game['id'] ?? '')] ?? null; ?>
                                            <article class="betedge-event-card <?= $gameIndex === 0 ? 'is-selected' : ''; ?>" data-sports-select-game="<?= sports_e((string) ($game['id'] ?? '')); ?>" data-game-id="<?= sports_e($game['id'] ?? ''); ?>" data-status-key="<?= sports_e($game['statusKey'] ?? 'scheduled'); ?>" data-sport-group="<?= sports_e($game['sportGroup'] ?? 'Sports'); ?>" data-league="<?= sports_e($game['league'] ?? ''); ?>" data-league-key="<?= sports_e($game['leagueKey'] ?? ''); ?>">
                                                <div class="betedge-event-head">
                                                    <span><?= sports_e($game['league'] ?? 'League'); ?></span>
                                                    <em class="tone-<?= sports_e($game['statusTone'] ?? 'scheduled'); ?>"><?= sports_e($game['clock'] ?? ($game['statusLabel'] ?? 'Watch')); ?></em>
                                                </div>
                                                <div class="betedge-team-row">
                                                    <div><?= sports_team_avatar((array) ($game['away'] ?? [])); ?><strong><?= sports_e($game['away']['name'] ?? $game['away']['abbr'] ?? 'Away'); ?></strong></div>
                                                    <b><?= sports_e((string) ($game['away']['score'] ?? 0)); ?></b>
                                                </div>
                                                <div class="betedge-team-row">
                                                    <div><?= sports_team_avatar((array) ($game['home'] ?? [])); ?><strong><?= sports_e($game['home']['name'] ?? $game['home']['abbr'] ?? 'Home'); ?></strong></div>
                                                    <b><?= sports_e((string) ($game['home']['score'] ?? 0)); ?></b>
                                                </div>
                                                <div class="betedge-market-lines">
                                                    <span>Spread <b><?= sports_e($game['spread']['favoriteLine'] ?? '--'); ?></b><b><?= sports_e($game['spread']['otherLine'] ?? '--'); ?></b></span>
                                                    <span>Total <b><?= sports_e($game['total']['over'] ?? '--'); ?></b><b><?= sports_e($game['total']['under'] ?? '--'); ?></b></span>
                                                </div>
                                                <button
                                                    class="betedge-card-hit"
                                                    type="button"
                                                    <?= $cardPredictionIndex !== null ? 'data-sports-open-pick="' . sports_e((string) $cardPredictionIndex) . '"' : 'data-sports-open-game="' . sports_e((string) ($game['id'] ?? '')) . '"'; ?>
                                                    title="Open research breakdown"
                                                ></button>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </section>

                                <section class="betedge-card betedge-picks-card" id="ai-picks">
                                    <div class="betedge-card-head">
                                        <strong class="lineforge-section-title"><?= sports_icon('ai-signals'); ?>Research Signals</strong>
                                        <button type="button" data-sports-view="picks">View All Signals</button>
                                    </div>
                                    <div class="betedge-picks-table" id="sportsPredictionGrid">
                                        <div class="betedge-picks-head">
                                            <span>Predicted Winner</span>
                                            <span>Match</span>
                                            <span>Market</span>
                                            <span>Research confidence</span>
                                            <span>Odds</span>
                                            <span>Edge</span>
                                            <span>Expected Value</span>
                                            <span></span>
                                        </div>
                                        <?php foreach (array_slice($displayPredictions, 0, 5) as $predictionIndex => $prediction): ?>
                                            <?php
                                            $confidenceValue = (int) ($prediction['confidenceValue'] ?? preg_replace('/[^0-9]/', '', (string) ($prediction['confidence'] ?? '50')));
                                            $confidenceValue = $confidenceValue > 0 ? $confidenceValue : 50;
                                            ?>
                                            <article class="betedge-pick-row" data-prediction-index="<?= sports_e((string) $predictionIndex); ?>" data-can-bet="<?= !empty($prediction['canBet']) ? 'true' : 'false'; ?>">
                                                <div class="betedge-pick-main"><i></i><strong><?= sports_e(sports_prediction_winner((array) $prediction, $gamesById)); ?></strong></div>
                                                <div><?= sports_e($prediction['matchup'] ?? 'Matchup'); ?></div>
                                                <div><?= sports_e($prediction['market'] ?? 'Market'); ?></div>
                                                <div class="betedge-confidence"><span><i style="width: <?= sports_e((string) $confidenceValue); ?>%"></i></span><b><?= sports_e($prediction['confidence'] ?? '0%'); ?></b></div>
                                                <div><strong><?= sports_e($prediction['odds'] ?? ($prediction['fairOdds'] ?? '--')); ?></strong></div>
                                                <div class="up"><?= sports_e($prediction['edge'] ?? '+0.0%'); ?></div>
                                                <div class="up"><?= sports_e($prediction['expectedValue'] ?? '$0.00'); ?></div>
                                                <div class="betedge-pick-actions">
                                                    <button type="button" data-sports-open-pick="<?= sports_e((string) $predictionIndex); ?>" title="Open details"></button>
                                                </div>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                </section>

                                <div class="betedge-lower-grid">
                                    <section class="betedge-card">
                                        <div class="betedge-card-head"><strong class="lineforge-section-title"><?= sports_icon('signal-pulse'); ?>Signal Insight</strong></div>
                                        <p class="betedge-insight-copy" id="sportsInsightCopy"><?= sports_e($sportsInsight['copy'] ?? ($topPrediction['reason'] ?? 'Lineforge is monitoring live status, market snapshots, and risk context.')); ?></p>
                                        <div class="betedge-signal-graph betedge-confidence-chart is-confidence" id="sportsConfidenceTimeline">
                                            <?php foreach ($primaryHistory as $point): ?>
                                                <i style="--p: <?= sports_e((string) $point); ?>%"><span><?= sports_e((string) $point); ?>%</span></i>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>

                                    <section class="betedge-card">
                                        <div class="betedge-card-head"><strong class="lineforge-section-title"><?= sports_icon('line-movement'); ?>Market Movement</strong><button type="button" data-sports-view="analytics"><?= sports_e($selectedMarket); ?></button></div>
                                        <strong class="betedge-market-title" id="sportsMarketTitle"><?= sports_e($primaryMarket); ?></strong>
                                        <div class="betedge-signal-graph betedge-line-chart is-market" id="sportsMarketLine">
                                            <?php foreach ($marketHistory as $point): ?>
                                                <i style="--h: <?= sports_e((string) $point); ?>%"></i>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="betedge-chart-legend">
                                            <span>Opened <?= sports_e($bookSummary['opened'] ?? ''); ?></span>
                                            <span>Current <?= sports_e($bookSummary['current'] ?? ''); ?></span>
                                        </div>
                                    </section>

                                    <section class="betedge-card">
                                        <div class="betedge-card-head"><strong class="lineforge-section-title"><?= sports_icon('alerts'); ?>Alerts</strong><button type="button" data-sports-view="analytics">View All</button></div>
                                        <div class="betedge-alert-list" id="sportsAlertsBoard">
                                            <?php foreach (array_slice($sportsAlerts, 0, 3) as $alert): ?>
                                                <div>
                                                    <span></span>
                                                    <div><strong><?= sports_e($alert['name'] ?? 'Alert'); ?></strong><small><?= sports_e($alert['detail'] ?? ''); ?></small></div>
                                                    <em><?= sports_e($alert['time'] ?? 'Now'); ?></em>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                </div>
                            </section>

                            <section class="betedge-panel" data-sports-panel="live" id="live" hidden>
                                <section class="betedge-card">
                                    <div class="betedge-card-head"><strong class="lineforge-section-title"><?= sports_icon('live-feed'); ?>Live, Upcoming & Final Board</strong><small id="sportsBoardCount"><?= sports_e((string) count($displayGames)); ?> games loaded</small></div>
                                    <div class="betedge-expanded-events" id="sportsLiveExpanded"></div>
                                </section>
                            </section>

                            <section class="betedge-panel" data-sports-panel="picks" id="picks" hidden>
                                <section class="betedge-card">
                                    <div class="betedge-card-head"><strong class="lineforge-section-title"><?= sports_icon('ai-signals'); ?>Lineforge Research Signals</strong><small>Calibration estimate, fair odds, edge, and EV</small></div>
                                    <div class="betedge-picks-table" id="sportsPredictionExpanded"></div>
                                </section>
                            </section>

                            <section class="betedge-panel" data-sports-panel="analytics" id="analytics" hidden>
                                <div class="betedge-analytics-grid">
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('analytics'); ?>Public Data Fabric</strong>
                                            <small>Free sources first, premium APIs later</small>
                                        </div>
                                        <div class="lineforge-data-summary" id="lineforgeDataSummary">
                                            <div><span>Mode</span><strong><?= sports_e($dataArchitecture['summary']['mode'] ?? 'Public Intelligence'); ?></strong><small>Graceful fallback without premium feeds</small></div>
                                            <div><span>Public modules</span><strong><?= sports_e((string) ($dataArchitecture['summary']['activePublicModules'] ?? 0)); ?></strong><small>Disabled / public-free / partial / premium states</small></div>
                                            <div><span>Game archive</span><strong><?= sports_e((string) ($dataArchitecture['summary']['gameRowsStored'] ?? 0)); ?></strong><small>Stored public scoreboard rows</small></div>
                                            <div><span>Odds history</span><strong><?= sports_e((string) ($dataArchitecture['summary']['oddsRowsStored'] ?? 0)); ?></strong><small>Stored normalized odds snapshots</small></div>
                                            <div><span>Inference signals</span><strong><?= sports_e((string) ($dataArchitecture['summary']['inferenceSignals'] ?? 0)); ?></strong><small>No unverified sharp-money claims</small></div>
                                            <div><span>Warehouse</span><strong><?= sports_e($dataArchitecture['summary']['warehouseStatus'] ?? 'JSONL fallback'); ?></strong><small><?= sports_e($dataArchitecture['summary']['warehouseDriver'] ?? 'unavailable'); ?> / <?= sports_e((string) ($dataArchitecture['summary']['warehouseRows'] ?? 0)); ?> rows</small></div>
                                            <div><span>Calibration</span><strong><?= sports_e((string) ($dataArchitecture['summary']['calibrationClosedSamples'] ?? 0)); ?> closed</strong><small><?= sports_e(str_replace('_', ' ', (string) ($dataArchitecture['summary']['calibrationStatus'] ?? 'collecting baseline'))); ?></small></div>
                                            <div><span>Brier score</span><strong><?= sports_e(($dataArchitecture['summary']['brierScore'] ?? null) === null ? 'Pending' : (string) $dataArchitecture['summary']['brierScore']); ?></strong><small>Lower is better / closed outcomes only</small></div>
                                            <div><span>Replay</span><strong><?= sports_e(str_replace('_', ' ', (string) ($dataArchitecture['summary']['replayStatus'] ?? 'waiting'))); ?></strong><small><?= sports_e(str_replace('_', ' ', (string) ($dataArchitecture['summary']['workerStatus'] ?? 'not started'))); ?> worker</small></div>
                                        </div>
                                        <div class="lineforge-data-module-grid" id="lineforgeDataModules">
                                            <?php foreach (array_slice((array) ($dataArchitecture['modules'] ?? []), 0, 10) as $module): ?>
                                                <div class="is-<?= sports_e(str_replace('/', '-', (string) ($module['mode'] ?? 'partial'))); ?>">
                                                    <span><?= sports_e($module['name'] ?? 'Source'); ?></span>
                                                    <strong><?= sports_e($module['status'] ?? 'Partial'); ?></strong>
                                                    <code><?= sports_e($module['mode'] ?? 'partial'); ?></code>
                                                    <small><?= sports_e($module['detail'] ?? 'Fallback-ready source module.'); ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>

                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('market-analysis'); ?>Internal Intelligence Systems</strong>
                                            <small>Historical storage and market-pressure inference</small>
                                        </div>
                                        <div class="lineforge-inference-grid">
                                            <div class="lineforge-inference-board" id="lineforgeInferenceBoard"></div>
                                            <div class="lineforge-system-grid" id="lineforgeInternalSystems"></div>
                                        </div>
                                    </section>

                                    <section class="betedge-card">
                                        <div class="betedge-card-head"><strong class="lineforge-section-title"><?= sports_icon('analytics'); ?>Model Blend</strong></div>
                                        <div class="betedge-chip-grid" id="sportsFactorGrid">
                                            <?php foreach ($factors as $factor): ?>
                                                <div><span><?= sports_e($factor['name'] ?? 'Factor'); ?></span><strong><?= sports_e($factor['weight'] ?? ''); ?></strong><small><?= sports_e($factor['detail'] ?? ''); ?></small></div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('ai-signals'); ?>Intelligence Evolution</strong>
                                            <small>Transparent features, model gates, market regimes, signal objects</small>
                                        </div>
                                        <div class="lineforge-data-summary" id="lineforgeEvolutionSummary">
                                            <div><span>Layer</span><strong><?= sports_e(str_replace('_', ' ', (string) ($dataArchitecture['intelligenceEvolution']['summary']['status'] ?? 'transparent learning'))); ?></strong><small>No black-box accuracy claims</small></div>
                                            <div><span>Feature rows</span><strong><?= sports_e((string) ($dataArchitecture['intelligenceEvolution']['summary']['featureRows'] ?? 0)); ?></strong><small><?= sports_e((string) ($dataArchitecture['intelligenceEvolution']['summary']['featureCount'] ?? 0)); ?> transparent features</small></div>
                                            <div><span>Trainable labels</span><strong><?= sports_e((string) ($dataArchitecture['intelligenceEvolution']['summary']['trainableRows'] ?? 0)); ?></strong><small><?= sports_e(str_replace('_', ' ', (string) ($dataArchitecture['intelligenceEvolution']['summary']['modelReadiness'] ?? 'collecting labels'))); ?></small></div>
                                            <div><span>Market regime</span><strong><?= sports_e(str_replace('_', ' ', (string) ($dataArchitecture['intelligenceEvolution']['summary']['activeRegime'] ?? 'unknown'))); ?></strong><small>Structure before prediction hype</small></div>
                                            <div><span>Signal objects</span><strong><?= sports_e((string) ($dataArchitecture['intelligenceEvolution']['summary']['signalObjects'] ?? 0)); ?></strong><small>Multi-dimensional review layer</small></div>
                                            <div><span>Observability</span><strong><?= sports_e(str_replace('_', ' ', (string) ($dataArchitecture['intelligenceEvolution']['summary']['observabilityStatus'] ?? 'unknown'))); ?></strong><small><?= sports_e((string) ($dataArchitecture['intelligenceEvolution']['summary']['triggerCount'] ?? 0)); ?> active triggers</small></div>
                                        </div>
                                        <div class="lineforge-data-module-grid" id="lineforgeModelCandidates">
                                            <?php foreach ((array) ($dataArchitecture['intelligenceEvolution']['modelReadiness']['candidates'] ?? []) as $candidate): ?>
                                                <div class="is-<?= sports_e(str_replace('_', '-', (string) ($candidate['status'] ?? 'data_gated'))); ?>">
                                                    <span><?= sports_e($candidate['family'] ?? 'Model'); ?></span>
                                                    <strong><?= sports_e($candidate['name'] ?? 'Model candidate'); ?></strong>
                                                    <code><?= sports_e($candidate['status'] ?? 'data_gated'); ?></code>
                                                    <small><?= sports_e($candidate['message'] ?? 'Training remains evidence-gated.'); ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('market-analysis'); ?>Market Structure & Signal Objects</strong>
                                            <small>Regime detection and layered intelligence signals</small>
                                        </div>
                                        <div class="lineforge-inference-grid">
                                            <div class="lineforge-system-grid" id="lineforgeMarketRegimeBoard">
                                                <?php foreach ((array) ($dataArchitecture['intelligenceEvolution']['marketStructure']['classifications'] ?? []) as $classification): ?>
                                                    <div>
                                                        <span><?= sports_e($classification['name'] ?? 'Classification'); ?></span>
                                                        <strong><?= sports_e($classification['value'] ?? 'Watch'); ?></strong>
                                                        <em><?= sports_e($classification['status'] ?? 'monitoring'); ?></em>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="lineforge-system-grid" id="lineforgeSignalObjects">
                                                <?php foreach (array_slice((array) ($dataArchitecture['intelligenceEvolution']['signals'] ?? []), 0, 4) as $signal): ?>
                                                    <div>
                                                        <span><?= sports_e($signal['market'] ?? 'Signal object'); ?></span>
                                                        <strong><?= sports_e($signal['pick'] ?? ($signal['name'] ?? 'Review')); ?></strong>
                                                        <em><?= sports_e((string) ($signal['readinessScore'] ?? 0)); ?>/100</em>
                                                        <small><?= sports_e($signal['explanation'] ?? 'Layered signal object.'); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </section>
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('analytics'); ?>Adaptive Intelligence Network</strong>
                                            <small>Independent layers, uncertainty, and coordinated failure isolation</small>
                                        </div>
                                        <div class="lineforge-data-summary" id="lineforgeAdaptiveSummary">
                                            <div><span>Network</span><strong><?= sports_e(str_replace('_', ' ', (string) ($dataArchitecture['adaptiveIntelligence']['summary']['networkStatus'] ?? 'collecting memory'))); ?></strong><small>Layer coordination without monolithic certainty</small></div>
                                            <div><span>Composite</span><strong><?= sports_e((string) ($dataArchitecture['adaptiveIntelligence']['summary']['compositeReadiness'] ?? 0)); ?>/100</strong><small>Readiness after uncertainty penalty</small></div>
                                            <div><span>Layers</span><strong><?= sports_e((string) ($dataArchitecture['adaptiveIntelligence']['summary']['layers'] ?? 0)); ?></strong><small>Independent intelligence systems</small></div>
                                            <div><span>High uncertainty</span><strong><?= sports_e((string) ($dataArchitecture['adaptiveIntelligence']['summary']['highUncertaintyLayers'] ?? 0)); ?></strong><small>Layers that must stay conservative</small></div>
                                            <div><span>Degraded layers</span><strong><?= sports_e((string) ($dataArchitecture['adaptiveIntelligence']['summary']['degradedLayers'] ?? 0)); ?></strong><small>Fail independently before orchestration</small></div>
                                            <div><span>Explanations</span><strong><?= sports_e((string) ($dataArchitecture['adaptiveIntelligence']['summary']['explanations'] ?? 0)); ?></strong><small>Operator-readable reasoning</small></div>
                                        </div>
                                        <div class="lineforge-data-module-grid" id="lineforgeAdaptiveLayers">
                                            <?php foreach (array_slice((array) ($dataArchitecture['adaptiveIntelligence']['layers'] ?? []), 0, 10) as $layer): ?>
                                                <div class="is-<?= sports_e(str_replace('_', '-', (string) ($layer['status'] ?? 'collecting_memory'))); ?>">
                                                    <span><?= sports_e($layer['name'] ?? 'Adaptive layer'); ?></span>
                                                    <strong><?= sports_e((string) ($layer['score'] ?? 0)); ?>/100</strong>
                                                    <code><?= sports_e((string) ($layer['measurement'] ?? ($layer['status'] ?? 'watch'))); ?></code>
                                                    <small><?= sports_e($layer['detail'] ?? 'Independent adaptive intelligence layer.'); ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('line-movement'); ?>Self-Evaluation & Orchestration</strong>
                                            <small>Coordination rules and reliability monitors</small>
                                        </div>
                                        <div class="lineforge-inference-grid">
                                            <div class="lineforge-system-grid" id="lineforgeAdaptiveOrchestration">
                                                <?php foreach ((array) ($dataArchitecture['adaptiveIntelligence']['composition']['rules'] ?? []) as $rule): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($rule['status'] ?? 'armed'))); ?>">
                                                        <span><?= sports_e($rule['name'] ?? 'Orchestration rule'); ?></span>
                                                        <strong><?= sports_e(str_replace('_', ' ', (string) ($rule['status'] ?? 'armed'))); ?></strong>
                                                        <small><?= sports_e($rule['effect'] ?? 'Adaptive coordination effect.'); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="lineforge-system-grid" id="lineforgeSelfEvaluation">
                                                <?php foreach (array_slice((array) ($dataArchitecture['adaptiveIntelligence']['selfEvaluation']['monitors'] ?? []), 0, 7) as $monitor): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($monitor['status'] ?? 'watch'))); ?>">
                                                        <span><?= sports_e($monitor['name'] ?? 'Self-evaluation'); ?></span>
                                                        <strong><?= sports_e($monitor['value'] ?? 'Monitoring'); ?></strong>
                                                        <em><?= sports_e(str_replace('_', ' ', (string) ($monitor['status'] ?? 'watch'))); ?></em>
                                                        <small><?= sports_e($monitor['detail'] ?? 'Reliability monitor.'); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </section>
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('historical-trends'); ?>Historical Pattern & Operator Memory</strong>
                                            <small>Similarity scaffolding and institutional knowledge capture</small>
                                        </div>
                                        <div class="lineforge-inference-grid">
                                            <div class="lineforge-system-grid" id="lineforgeHistoricalPatterns">
                                                <?php foreach ((array) ($dataArchitecture['adaptiveIntelligence']['historicalPatterns']['patterns'] ?? []) as $pattern): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($pattern['status'] ?? 'collecting'))); ?>">
                                                        <span><?= sports_e($pattern['name'] ?? 'Pattern'); ?></span>
                                                        <strong><?= sports_e($pattern['value'] ?? 'Collecting'); ?></strong>
                                                        <em><?= sports_e(str_replace('_', ' ', (string) ($pattern['status'] ?? 'collecting'))); ?></em>
                                                        <small><?= sports_e($pattern['detail'] ?? 'Historical pattern system.'); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="lineforge-system-grid" id="lineforgeOperatorMemory">
                                                <?php foreach ((array) ($dataArchitecture['adaptiveIntelligence']['operatorMemory']['artifacts'] ?? []) as $artifact): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($artifact['status'] ?? 'ready_empty'))); ?>">
                                                        <span><?= sports_e($artifact['name'] ?? 'Operator memory'); ?></span>
                                                        <strong><?= sports_e($artifact['value'] ?? '0 records'); ?></strong>
                                                        <em><?= sports_e(str_replace('_', ' ', (string) ($artifact['status'] ?? 'ready empty'))); ?></em>
                                                        <small><?= sports_e($artifact['detail'] ?? 'Operator knowledge artifact.'); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </section>
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('status-indicator'); ?>Resilience & Explanations</strong>
                                            <small>Degraded-mode operation and transparent reasoning</small>
                                        </div>
                                        <div class="lineforge-inference-grid">
                                            <div class="lineforge-system-grid" id="lineforgeResilienceSystems">
                                                <?php foreach ((array) ($dataArchitecture['adaptiveIntelligence']['resilience']['systems'] ?? []) as $system): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($system['status'] ?? 'armed'))); ?>">
                                                        <span><?= sports_e($system['name'] ?? 'Resilience system'); ?></span>
                                                        <strong><?= sports_e($system['value'] ?? 'Armed'); ?></strong>
                                                        <em><?= sports_e(str_replace('_', ' ', (string) ($system['status'] ?? 'armed'))); ?></em>
                                                        <small><?= sports_e($system['detail'] ?? 'Failure-aware platform behavior.'); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="lineforge-system-grid" id="lineforgeAdaptiveExplanations">
                                                <?php foreach (array_slice((array) ($dataArchitecture['adaptiveIntelligence']['explanations'] ?? []), 0, 6) as $explanation): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($explanation['status'] ?? 'watch'))); ?>">
                                                        <span><?= sports_e($explanation['title'] ?? 'Explanation'); ?></span>
                                                        <strong><?= sports_e(str_replace('_', ' ', (string) ($explanation['status'] ?? 'watch'))); ?></strong>
                                                        <small><?= sports_e(($explanation['reason'] ?? '') . ' ' . ($explanation['impact'] ?? '')); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </section>
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('analytics'); ?>Generalized Intelligence Core</strong>
                                            <small>Sports as the first domain adapter for decision intelligence infrastructure</small>
                                        </div>
                                        <div class="lineforge-data-summary" id="lineforgeGeneralizedSummary">
                                            <div><span>Core</span><strong><?= sports_e(str_replace('_', ' ', (string) ($dataArchitecture['generalizedIntelligence']['summary']['status'] ?? 'domain agnostic foundation'))); ?></strong><small>Reusable intelligence primitives</small></div>
                                            <div><span>Readiness</span><strong><?= sports_e((string) ($dataArchitecture['generalizedIntelligence']['summary']['coreReadiness'] ?? 0)); ?>/100</strong><small>Built on adaptive infrastructure</small></div>
                                            <div><span>Domains</span><strong><?= sports_e((string) ($dataArchitecture['generalizedIntelligence']['summary']['domainAdapters'] ?? 0)); ?></strong><small><?= sports_e((string) ($dataArchitecture['generalizedIntelligence']['summary']['activeDomains'] ?? 0)); ?> active adapter</small></div>
                                            <div><span>Events</span><strong><?= sports_e((string) ($dataArchitecture['generalizedIntelligence']['summary']['eventPrimitives'] ?? 0)); ?></strong><small>Universal event model</small></div>
                                            <div><span>Signals</span><strong><?= sports_e((string) ($dataArchitecture['generalizedIntelligence']['summary']['signalPrimitives'] ?? 0)); ?></strong><small>Universal signal model</small></div>
                                            <div><span>Governance</span><strong><?= sports_e((string) ($dataArchitecture['generalizedIntelligence']['summary']['governanceSystems'] ?? 0)); ?></strong><small>Controls remain first-class</small></div>
                                        </div>
                                        <div class="lineforge-data-module-grid" id="lineforgeDomainAdapters">
                                            <?php foreach ((array) ($dataArchitecture['generalizedIntelligence']['domains'] ?? []) as $domain): ?>
                                                <div class="is-<?= sports_e(str_replace('_', '-', (string) ($domain['status'] ?? 'planned_domain'))); ?>">
                                                    <span><?= sports_e($domain['name'] ?? 'Domain adapter'); ?></span>
                                                    <strong><?= sports_e((string) ($domain['readiness'] ?? 0)); ?>/100</strong>
                                                    <code><?= sports_e($domain['status'] ?? 'planned_domain'); ?></code>
                                                    <small><?= sports_e($domain['detail'] ?? 'Domain adapter blueprint.'); ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('market-analysis'); ?>Universal Primitives & Orchestration</strong>
                                            <small>Event, signal, state, workflow, dependency, and uncertainty graph</small>
                                        </div>
                                        <div class="lineforge-inference-grid">
                                            <div class="lineforge-system-grid" id="lineforgeUniversalPrimitives">
                                                <?php foreach (array_slice((array) ($dataArchitecture['generalizedIntelligence']['primitives']['events'] ?? []), 0, 3) as $primitive): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($primitive['status'] ?? 'defined'))); ?>">
                                                        <span><?= sports_e($primitive['name'] ?? 'Event'); ?></span>
                                                        <strong><?= sports_e($primitive['value'] ?? 'Defined'); ?></strong>
                                                        <small><?= sports_e($primitive['detail'] ?? 'Universal event primitive.'); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                                <?php foreach (array_slice((array) ($dataArchitecture['generalizedIntelligence']['primitives']['signals'] ?? []), 0, 3) as $primitive): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($primitive['status'] ?? 'defined'))); ?>">
                                                        <span><?= sports_e($primitive['name'] ?? 'Signal'); ?></span>
                                                        <strong><?= sports_e($primitive['value'] ?? 'Defined'); ?></strong>
                                                        <small><?= sports_e($primitive['detail'] ?? 'Universal signal primitive.'); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="lineforge-system-grid" id="lineforgeGeneralizedOrchestration">
                                                <?php foreach ((array) ($dataArchitecture['generalizedIntelligence']['orchestration']['pipelines'] ?? []) as $pipeline): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($pipeline['status'] ?? 'armed'))); ?>">
                                                        <span><?= sports_e($pipeline['name'] ?? 'Pipeline'); ?></span>
                                                        <strong><?= sports_e($pipeline['value'] ?? 'Active'); ?></strong>
                                                        <em><?= sports_e(str_replace('_', ' ', (string) ($pipeline['status'] ?? 'armed'))); ?></em>
                                                        <small><?= sports_e($pipeline['detail'] ?? 'Orchestration pipeline.'); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </section>
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('historical-trends'); ?>Contextual Memory & Self-Optimization</strong>
                                            <small>Historical context, adaptive workspace triggers, and operating intelligence</small>
                                        </div>
                                        <div class="lineforge-inference-grid">
                                            <div class="lineforge-system-grid" id="lineforgeContextualMemory">
                                                <?php foreach (array_slice((array) ($dataArchitecture['generalizedIntelligence']['memory']['stores'] ?? []), 0, 8) as $store): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($store['status'] ?? 'memory_active'))); ?>">
                                                        <span><?= sports_e($store['name'] ?? 'Memory'); ?></span>
                                                        <strong><?= sports_e($store['value'] ?? '0'); ?></strong>
                                                        <em><?= sports_e(str_replace('_', ' ', (string) ($store['status'] ?? 'memory active'))); ?></em>
                                                        <small><?= sports_e($store['detail'] ?? 'Contextual memory store.'); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="lineforge-system-grid" id="lineforgeSelfOptimization">
                                                <?php foreach ((array) ($dataArchitecture['generalizedIntelligence']['selfOptimization']['systems'] ?? []) as $system): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($system['status'] ?? 'planned'))); ?>">
                                                        <span><?= sports_e($system['name'] ?? 'Optimization'); ?></span>
                                                        <strong><?= sports_e($system['value'] ?? 'Watch'); ?></strong>
                                                        <em><?= sports_e(str_replace('_', ' ', (string) ($system['status'] ?? 'planned'))); ?></em>
                                                        <small><?= sports_e($system['detail'] ?? 'Self-optimization system.'); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </section>
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('status-indicator'); ?>Institutional Governance Layer</strong>
                                            <small>Organizations, review boards, audit, transparency, and safety degradation</small>
                                        </div>
                                        <div class="lineforge-inference-grid">
                                            <div class="lineforge-system-grid" id="lineforgeAdaptiveWorkspaces">
                                                <?php foreach ((array) ($dataArchitecture['generalizedIntelligence']['adaptiveWorkspaces']['workspaces'] ?? []) as $workspace): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($workspace['status'] ?? 'armed'))); ?>">
                                                        <span><?= sports_e($workspace['name'] ?? 'Workspace'); ?></span>
                                                        <strong><?= sports_e(str_replace('_', ' ', (string) ($workspace['status'] ?? 'armed'))); ?></strong>
                                                        <small><?= sports_e(($workspace['trigger'] ?? '') . ' ' . ($workspace['detail'] ?? '')); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <div class="lineforge-system-grid" id="lineforgeGeneralizedGovernance">
                                                <?php foreach (array_slice((array) ($dataArchitecture['generalizedIntelligence']['governance']['systems'] ?? []), 0, 8) as $system): ?>
                                                    <div class="is-<?= sports_e(str_replace('_', '-', (string) ($system['status'] ?? 'active'))); ?>">
                                                        <span><?= sports_e($system['name'] ?? 'Governance'); ?></span>
                                                        <strong><?= sports_e(str_replace('_', ' ', (string) ($system['status'] ?? 'active'))); ?></strong>
                                                        <small><?= sports_e($system['detail'] ?? 'Governance control.'); ?></small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </section>
                                    <section class="betedge-card">
                                        <div class="betedge-card-head"><strong class="lineforge-section-title"><?= sports_icon('confidence-ring'); ?>Calibration & Replay</strong><small>Auditable prediction history</small></div>
                                        <div class="betedge-chip-grid">
                                            <div><span>Warehouse mode</span><strong><?= sports_e($dataArchitecture['summary']['warehouseStatus'] ?? 'JSONL fallback'); ?></strong><small><?= sports_e($dataArchitecture['warehouse']['message'] ?? 'Historical snapshots collect for replay when storage is available.'); ?></small></div>
                                            <div><span>Closed samples</span><strong><?= sports_e((string) ($dataArchitecture['calibration']['closedSamples'] ?? 0)); ?>/<?= sports_e((string) ($dataArchitecture['calibration']['minimumClosedSamples'] ?? 100)); ?></strong><small><?= sports_e($dataArchitecture['calibration']['message'] ?? 'Collecting closed samples before calibration claims.'); ?></small></div>
                                            <div><span>Brier score</span><strong><?= sports_e(($dataArchitecture['calibration']['brierScore'] ?? null) === null ? 'Pending' : (string) $dataArchitecture['calibration']['brierScore']); ?></strong><small>Probability grading waits for final outcomes and mapped winners.</small></div>
                                            <div><span>Calibration error</span><strong><?= sports_e(($dataArchitecture['calibration']['calibrationError'] ?? null) === null ? 'Pending' : ((string) $dataArchitecture['calibration']['calibrationError'] . '%')); ?></strong><small>Average gap between estimated probability buckets and hit rate.</small></div>
                                            <div><span>Replay posture</span><strong><?= sports_e(str_replace('_', ' ', (string) ($dataArchitecture['warehouse']['replay']['status'] ?? 'collecting'))); ?></strong><small><?= sports_e($dataArchitecture['warehouse']['replay']['message'] ?? 'Snapshots preserve what Lineforge knew at prediction time for future review.'); ?></small></div>
                                            <div><span>Worker posture</span><strong><?= sports_e(str_replace('_', ' ', (string) ($dataArchitecture['warehouse']['operational']['worker']['status'] ?? 'not started'))); ?></strong><small><?= sports_e($dataArchitecture['warehouse']['operational']['worker']['message'] ?? 'Run the Lineforge worker from a scheduler for background collection.'); ?></small></div>
                                        </div>
                                    </section>
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head"><strong class="lineforge-section-title"><?= sports_icon('status-indicator'); ?>Operator Workflow</strong><small>Ingest, compare, verify, calibrate, replay, audit</small></div>
                                        <div class="lineforge-system-grid" id="lineforgeOperatorWorkflows">
                                            <?php foreach ((array) ($dataArchitecture['operatorWorkflows'] ?? []) as $workflow): ?>
                                                <div>
                                                    <span><?= sports_e($workflow['name'] ?? 'Workflow'); ?></span>
                                                    <strong><?= sports_e($workflow['status'] ?? 'Monitoring'); ?></strong>
                                                    <small><?= sports_e($workflow['detail'] ?? 'Operational workflow state.'); ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                    <section class="betedge-card">
                                        <div class="betedge-card-head"><strong class="lineforge-section-title"><?= sports_icon('status-indicator'); ?>Decision Doctrine</strong><small>Infrastructure philosophy for disciplined probabilistic environments</small></div>
                                        <div class="betedge-chip-grid">
                                            <div><span>Lineforge is</span><strong>Operator-first</strong><small><?= sports_e(implode(' / ', array_slice((array) ($dataArchitecture['productPhilosophy']['is'] ?? []), 0, 5))); ?></small></div>
                                            <div><span>Lineforge is not</span><strong>No fake certainty</strong><small><?= sports_e(implode(' / ', array_slice((array) ($dataArchitecture['productPhilosophy']['isNot'] ?? []), 0, 5))); ?></small></div>
                                            <div><span>Doctrine</span><strong>Better decisions</strong><small><?= sports_e($dataArchitecture['productPhilosophy']['highestGoal'] ?? 'Help humans make better decisions under uncertainty without pretending uncertainty disappears.'); ?></small></div>
                                            <div><span>Quality loop</span><strong>Continuous refinement</strong><small><?= sports_e($dataArchitecture['productPhilosophy']['continuousRefinement'] ?? 'Mature systems evolve through continuous refinement.'); ?></small></div>
                                            <div><span>Operator contract</span><strong>Expose uncertainty</strong><small><?= sports_e(implode(' / ', array_slice((array) ($dataArchitecture['productPhilosophy']['operatorContract'] ?? []), 0, 6))); ?></small></div>
                                            <div><span>Accumulated ecosystem</span><strong>Memory is the moat</strong><small><?= sports_e(implode(' / ', array_slice((array) ($dataArchitecture['productPhilosophy']['accumulatedEcosystem'] ?? []), 0, 5))); ?></small></div>
                                            <div><span>Refinement guardrails</span><strong>No corruption</strong><small><?= sports_e(implode(' / ', array_slice((array) ($dataArchitecture['productPhilosophy']['refinementGuardrails'] ?? []), 0, 5))); ?></small></div>
                                            <div><span>Operating standard</span><strong>Signal over stimulation</strong><small><?= sports_e($dataArchitecture['productPhilosophy']['signalStandard'] ?? 'Optimize for signal over stimulation.'); ?></small></div>
                                            <div><span>Workflow</span><strong>Ingest -> Replay -> Audit</strong><small><?= sports_e($dataArchitecture['productPhilosophy']['statement'] ?? 'Compare, verify, calibrate, evaluate, review, and audit before action.'); ?></small></div>
                                        </div>
                                    </section>
                                    <section class="betedge-card">
                                        <div class="betedge-card-head"><strong>Coverage Universe</strong></div>
                                        <div class="betedge-chip-grid" id="sportsCoverageGrid">
                                            <?php foreach (array_slice((array) ($sportsCoverage['groups'] ?? []), 0, 12) as $group): ?>
                                                <div><span><?= sports_e($group['label'] ?? 'Sports'); ?></span><strong><?= sports_e((string) ($group['games'] ?? 0)); ?> games</strong><small><?= sports_e((string) ($group['live'] ?? 0)); ?> live / <?= sports_e((string) ($group['scheduled'] ?? 0)); ?> upcoming</small></div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                    <section class="betedge-card">
                                        <div class="betedge-card-head"><strong class="lineforge-section-title"><?= sports_icon('sportsbooks'); ?>Market Health</strong></div>
                                        <div class="betedge-chip-grid" id="sportsProviderGrid">
                                            <div><span>Odds feed</span><strong><?= !empty($marketAccess['oddsProviderConfigured']) ? 'Connected' : 'Needs API key'; ?></strong><small><?= sports_e($marketAccess['oddsProvider'] ?? 'The Odds API'); ?></small></div>
                                            <div><span>Bookmakers</span><strong><?= sports_e((string) ($marketAccess['bookmakers'] ?? 0)); ?></strong><small>Outbound app links</small></div>
                                            <div><span>Matched lines</span><strong><?= sports_e((string) ($marketAccess['availableLines'] ?? 0)); ?></strong><small><?= sports_e((string) ($marketAccess['matchedEvents'] ?? 0)); ?> matched events</small></div>
                                            <div><span>Exchange scan</span><strong><?= sports_e($marketAccess['exchangeProvider'] ?? 'Kalshi'); ?></strong><small><?= sports_e((string) ($marketAccess['kalshiMarketsCached'] ?? 0)); ?> cached markets</small></div>
                                        </div>
                                    </section>
                                    <section class="betedge-card">
                                        <div class="betedge-card-head"><strong>Market Intelligence</strong></div>
                                        <div class="betedge-book-grid" id="sportsBookGrid"></div>
                                    </section>
                                </div>
                            </section>

                            <section class="betedge-panel" data-sports-panel="arbitrage" id="arbitrage" hidden>
                                <section class="betedge-card lineforge-arb-command">
                                    <div>
                                        <span class="lineforge-execution-kicker"><?= sports_icon('market-analysis'); ?>Arbitrage Engine</span>
                                        <strong>Multi-source odds comparison</strong>
                                        <small>Official/licensed feeds only. Pure arbitrage is separated from middles, scalps, and positive-EV signals, with stale-data and provider-health checks before any manual action.</small>
                                    </div>
                                    <div class="lineforge-arb-status">
                                        <span>Refresh policy</span>
                                        <strong id="lineforgeArbRefresh">Provider-budget cache</strong>
                                        <small>Re-check prices before action</small>
                                    </div>
                                </section>

                                <div class="lineforge-arb-layout">
                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('confidence-score'); ?>Arbitrage Summary</strong>
                                            <small id="lineforgeArbHealthLabel">Provider health pending</small>
                                        </div>
                                        <div class="lineforge-arb-summary" id="lineforgeArbSummary"></div>
                                    </section>

                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('sportsbooks'); ?>Provider Health</strong>
                                            <small>Official APIs, licensed odds feeds, and data-only access</small>
                                        </div>
                                        <div class="lineforge-arb-provider-grid" id="lineforgeArbProviderHealth"></div>
                                    </section>

                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('sharp-money'); ?>Pure Arbitrage</strong>
                                            <small>Best available price for every matched outcome</small>
                                        </div>
                                        <div class="lineforge-arb-table" id="lineforgeArbTable"></div>
                                    </section>

                                    <section class="betedge-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('line-movement'); ?>Middles & Scalps</strong>
                                            <small>Separate from guaranteed arbitrage</small>
                                        </div>
                                        <div class="lineforge-arb-signal-board" id="lineforgeMiddleBoard"></div>
                                    </section>

                                    <section class="betedge-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('edge-rating'); ?>Positive EV</strong>
                                            <small>Consensus edge, not guaranteed profit</small>
                                        </div>
                                        <div class="lineforge-arb-signal-board" id="lineforgePositiveEvBoard"></div>
                                    </section>

                                    <section class="betedge-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('risk-tier'); ?>Rejected / Debug Log</strong>
                                            <small>Rejected market matches, stale data, and calculation blockers</small>
                                        </div>
                                        <div class="lineforge-arb-rejected" id="lineforgeRejectedBoard"></div>
                                    </section>
                                </div>

                                <aside class="lineforge-arb-drawer" id="lineforgeArbDrawer" hidden>
                                    <div class="lineforge-arb-drawer-panel">
                                        <div class="lineforge-arb-drawer-head">
                                            <div>
                                                <span>Opportunity Detail</span>
                                                <strong id="lineforgeArbDrawerTitle">Market comparison</strong>
                                                <small id="lineforgeArbDrawerMeta">No opportunity selected</small>
                                            </div>
                                            <button type="button" data-arb-close aria-label="Close arbitrage detail">Close</button>
                                        </div>
                                        <div class="lineforge-arb-drawer-grid">
                                            <section>
                                                <h3>Sportsbook Comparison</h3>
                                                <div id="lineforgeArbDrawerOdds"></div>
                                            </section>
                                            <section>
                                                <h3>Stake Plan</h3>
                                                <div id="lineforgeArbDrawerStake"></div>
                                            </section>
                                            <section>
                                                <h3>No-Vig Consensus</h3>
                                                <div id="lineforgeArbDrawerConsensus"></div>
                                            </section>
                                            <section>
                                                <h3>Risk Warnings</h3>
                                                <div id="lineforgeArbDrawerWarnings"></div>
                                            </section>
                                            <section>
                                                <h3>Manual Checklist</h3>
                                                <div id="lineforgeArbDrawerChecklist"></div>
                                            </section>
                                            <section>
                                                <h3>Slip Notes</h3>
                                                <pre id="lineforgeArbDrawerExport"></pre>
                                            </section>
                                        </div>
                                    </div>
                                </aside>
                            </section>

                            <section class="betedge-panel" data-sports-panel="execution" id="execution" hidden>
                                <section class="betedge-card lineforge-execution-command">
                                    <div>
                                        <span class="lineforge-execution-kicker"><?= sports_icon('status-indicator'); ?>Execution Layer</span>
                                        <strong>Paper-first execution center</strong>
                                        <small id="lineforgeExecutionSummary">Rules are simulated until a supported provider, risk limits, and explicit live authorization are active.</small>
                                    </div>
                                    <div class="lineforge-execution-mode" id="lineforgeExecutionMode">
                                        <button type="button" data-execution-mode="paper" class="is-active">Paper</button>
                                        <button type="button" data-execution-mode="demo">Kalshi Demo</button>
                                        <button type="button" data-execution-mode="live">Live</button>
                                    </div>
                                    <div class="lineforge-execution-actions">
                                        <button type="button" data-execution-action="healthCheck"><?= sports_icon('signal-pulse'); ?>Health</button>
                                        <button type="button" data-execution-action="dryRun"><?= sports_icon('analytics'); ?>Dry Run</button>
                                        <button type="button" data-execution-action="evaluateRules"><?= sports_icon('ai-signals'); ?>Evaluate</button>
                                        <button type="button" class="is-danger" data-execution-action="emergencyStop"><?= sports_icon('risk-tier'); ?>Emergency Stop</button>
                                    </div>
                                </section>

                                <div class="lineforge-execution-grid">
                                    <section class="betedge-card lineforge-execution-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('sportsbooks'); ?>Connected Providers</strong>
                                            <small id="lineforgeExecutionProviderStatus">Official APIs only</small>
                                        </div>
                                        <div class="lineforge-provider-grid" id="lineforgeExecutionProviders"></div>
                                    </section>

                                    <section class="betedge-card lineforge-execution-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('bankroll-tracking'); ?>Balances & Exposure</strong>
                                            <small id="lineforgeExecutionBalanceStatus">Paper balance ready</small>
                                        </div>
                                        <div class="lineforge-balance-grid" id="lineforgeExecutionBalances"></div>
                                    </section>

                                    <section class="betedge-card lineforge-execution-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('settings'); ?>Kalshi Official API</strong>
                                            <small id="lineforgeKalshiStatus">Demo environment first</small>
                                        </div>
                                        <form class="lineforge-execution-form" id="lineforgeKalshiForm" autocomplete="off">
                                            <input type="hidden" name="csrf" value="<?= sports_e(aegis_sports_product_csrf_token()); ?>">
                                            <label>
                                                <span>Environment</span>
                                                <select name="environment">
                                                    <option value="demo">Demo</option>
                                                    <option value="live">Live</option>
                                                </select>
                                            </label>
                                            <label>
                                                <span>Kalshi API key ID</span>
                                                <input type="password" name="keyId" placeholder="Key ID is kept server-side" autocomplete="off">
                                            </label>
                                            <label class="lineforge-form-wide">
                                                <span>RSA private key</span>
                                                <textarea name="privateKey" rows="4" placeholder="Paste only on a backend with credential encryption configured"></textarea>
                                            </label>
                                            <label class="lineforge-check">
                                                <input type="checkbox" name="clearCredentials" value="1">
                                                <span>Clear saved Kalshi credentials</span>
                                            </label>
                                            <div class="lineforge-form-actions">
                                                <button type="submit" class="sports-reference-place-bet">Save Kalshi Profile</button>
                                                <small>Lineforge never sends API keys to the browser after saving. Signed calls stay backend-only.</small>
                                            </div>
                                        </form>
                                    </section>

                                    <section class="betedge-card lineforge-execution-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('risk-tier'); ?>Risk Limits</strong>
                                            <small id="lineforgeRiskStatus">Responsible-use controls</small>
                                        </div>
                                        <form class="lineforge-execution-form" id="lineforgeRiskForm">
                                            <label>
                                                <span>Max stake per order</span>
                                                <input type="number" name="maxStakePerOrder" min="1" step="1" value="<?= sports_e((string) ($executionState['riskLimits']['maxStakePerOrder'] ?? 25)); ?>">
                                            </label>
                                            <label>
                                                <span>Max daily loss</span>
                                                <input type="number" name="maxDailyLoss" min="1" step="1" value="<?= sports_e((string) ($executionState['riskLimits']['maxDailyLoss'] ?? 100)); ?>">
                                            </label>
                                            <label>
                                                <span>Cooldown minutes</span>
                                                <input type="number" name="cooldownMinutes" min="0" max="1440" step="1" value="<?= sports_e((string) ($executionState['riskLimits']['cooldownMinutes'] ?? 5)); ?>">
                                            </label>
                                            <label>
                                                <span>Stale data block</span>
                                                <input type="number" name="blockStaleMarketDataSeconds" min="15" step="15" value="<?= sports_e((string) ($executionState['riskLimits']['blockStaleMarketDataSeconds'] ?? 120)); ?>">
                                            </label>
                                            <label class="lineforge-check">
                                                <input type="checkbox" name="selfExcluded" value="1" <?= !empty($executionState['riskLimits']['selfExcluded']) ? 'checked' : ''; ?>>
                                                <span>Self-exclusion</span>
                                            </label>
                                            <label class="lineforge-check">
                                                <input type="checkbox" name="emergencyDisabled" value="1" <?= !empty($executionState['riskLimits']['emergencyDisabled']) ? 'checked' : ''; ?>>
                                                <span>Emergency disable</span>
                                            </label>
                                            <label class="lineforge-check">
                                                <input type="checkbox" name="requireManualConfirmation" value="1" <?= !empty($executionState['riskLimits']['requireManualConfirmation']) ? 'checked' : ''; ?>>
                                                <span>Require manual confirmation</span>
                                            </label>
                                            <label class="lineforge-check">
                                                <input type="checkbox" name="allowLiveAuto" value="1" <?= !empty($executionState['riskLimits']['allowLiveAuto']) ? 'checked' : ''; ?>>
                                                <span>Allow live-auto only after provider approval</span>
                                            </label>
                                            <div class="lineforge-form-actions">
                                                <button type="submit" class="sports-reference-place-bet">Save Risk Limits</button>
                                            </div>
                                        </form>
                                    </section>

                                    <section class="betedge-card lineforge-execution-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('ai-signals'); ?>Rule Builder</strong>
                                            <small>Sentence-style execution rules</small>
                                        </div>
                                        <form class="lineforge-rule-builder" id="lineforgeRuleForm">
                                            <fieldset>
                                                <legend>WHEN</legend>
                                                <label><span>Provider</span><select name="provider"><option value="paper">Paper</option><option value="kalshi">Kalshi</option><option value="fanduel">FanDuel data only</option></select></label>
                                                <label><span>Market ticker</span><input name="marketTicker" value="KXEXAMPLE-26MAY05-LF" autocomplete="off"></label>
                                                <label><span>Market probability is</span><select name="probabilityOperator"><option value="&lt;=">&lt;=</option><option value="&gt;=">&gt;=</option><option value="&lt;">&lt;</option><option value="&gt;">&gt;</option></select></label>
                                                <label><span>Probability %</span><input type="number" name="entryProbability" min="1" max="99" value="45"></label>
                                                <label><span>Research confidence >=</span><input type="number" name="confidenceMin" min="0" max="100" value="70"></label>
                                                <label><span>Edge score >=</span><input type="number" name="edgeMin" min="0" max="100" value="5"></label>
                                                <label><span>Liquidity >=</span><input type="number" name="liquidityMin" min="0" step="25" value="500"></label>
                                                <label><span>Current probability</span><input type="number" name="currentProbability" min="1" max="99" value="44"></label>
                                            </fieldset>
                                            <fieldset>
                                                <legend>THEN</legend>
                                                <label><span>Buy side</span><select name="side"><option value="YES">YES</option><option value="NO">NO</option></select></label>
                                                <label><span>Stake type</span><select name="stakeType"><option value="fixed">Fixed dollars</option><option value="contracts">Max contracts</option><option value="percent_balance">Percent balance</option></select></label>
                                                <label><span>Stake $</span><input type="number" name="stakeAmount" min="1" step="1" value="25"></label>
                                                <label><span>Max contracts</span><input type="number" name="maxContracts" min="1" step="1" value="10"></label>
                                                <label><span>Balance %</span><input type="number" name="percentBalance" min="0.1" step="0.1" value="1"></label>
                                                <label><span>Max price</span><input type="number" name="maxPrice" min="0.01" max="0.99" step="0.01" value="0.45"></label>
                                            </fieldset>
                                            <fieldset>
                                                <legend>EXIT</legend>
                                                <label><span>Sell if falls to</span><input type="number" name="exitProbabilityLow" min="1" max="99" value="35"></label>
                                                <label><span>Take profit at</span><input type="number" name="takeProfitProbability" min="1" max="99" value="65"></label>
                                                <label><span>Stop loss at</span><input type="number" name="stopLossProbability" min="1" max="99" value="30"></label>
                                                <label><span>Cancel after</span><input type="datetime-local" name="cancelAfter"></label>
                                                <label><span>Confirmation</span><select name="confirmationMode"><option value="manual">Manual confirmation</option><option value="semi_auto">Semi-auto approval</option><option value="paper_auto">Paper auto only</option><option value="live_auto">Live auto guarded</option></select></label>
                                                <label class="lineforge-check"><input type="checkbox" name="allowDuplicates" value="1"><span>Allow duplicate orders</span></label>
                                            </fieldset>
                                            <div class="lineforge-form-actions">
                                                <button type="submit" class="sports-reference-place-bet">Create Paused Rule</button>
                                                <small>Rules always start paused. Enable one only after reviewing dry-run output.</small>
                                            </div>
                                        </form>
                                    </section>

                                    <section class="betedge-card lineforge-execution-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('watchlists'); ?>Rule Status</strong>
                                            <small id="lineforgeRuleStatus">No live-money actions without authorization</small>
                                        </div>
                                        <div class="lineforge-rule-list" id="lineforgeExecutionRules"></div>
                                    </section>

                                    <section class="betedge-card lineforge-execution-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('edge-rating'); ?>Open Positions</strong>
                                            <small id="lineforgePositionsStatus">Paper and supported providers</small>
                                        </div>
                                        <div class="lineforge-ledger" id="lineforgeExecutionPositions"></div>
                                    </section>

                                    <section class="betedge-card lineforge-execution-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('odds'); ?>Open Orders</strong>
                                            <small id="lineforgeOrdersStatus">Idempotent client order IDs</small>
                                        </div>
                                        <div class="lineforge-ledger" id="lineforgeExecutionOrders"></div>
                                    </section>

                                    <section class="betedge-card lineforge-execution-card lineforge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('historical-trends'); ?>Audit Log</strong>
                                            <small id="lineforgeAuditStatus">Every evaluation, skip, error, and response</small>
                                        </div>
                                        <div class="lineforge-audit-log" id="lineforgeExecutionAudit"></div>
                                    </section>
                                </div>
                                <p class="lineforge-execution-result" id="lineforgeExecutionResult">Execution Center is loading provider state.</p>
                            </section>

                            <section class="betedge-panel" data-sports-panel="settings" id="settings" hidden>
                                <div class="betedge-analytics-grid">
                                    <section class="betedge-card betedge-wide-card betedge-provider-setup-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('settings'); ?>Provider Setup</strong>
                                            <small id="sportsProviderStatus"><?= !empty($providerSettings['oddsConnected']) ? 'Odds feed connected' : 'Odds feed needs a key'; ?></small>
                                        </div>
                                        <div class="betedge-provider-readiness" id="sportsProviderReadiness">
                                            <div class="is-connected"><span><?= sports_icon('live-feed'); ?>Scoreboard</span><strong><?= sports_e($providerSettings['readiness']['scoreboard'] ?? 'Connected'); ?></strong><small>Public live state and schedule scan.</small></div>
                                            <div class="<?= !empty($providerSettings['oddsConnected']) ? 'is-connected' : 'needs-setup'; ?>"><span><?= sports_icon('odds'); ?>Sportsbook Odds</span><strong><?= sports_e($providerSettings['readiness']['odds'] ?? 'Needs API key'); ?></strong><small><?= sports_e($providerSettings['oddsSource'] ?? 'Not connected'); ?></small></div>
                                            <div class="<?= ($providerSettings['readiness']['injuries'] ?? '') === 'Configured' ? 'is-connected' : 'is-partial'; ?>"><span><?= sports_icon('injury-risk'); ?>Injuries</span><strong><?= sports_e($providerSettings['readiness']['injuries'] ?? 'Manual check'); ?></strong><small>Late availability and lineup risk.</small></div>
                                            <div class="<?= ($providerSettings['readiness']['lineups'] ?? '') === 'Configured' ? 'is-connected' : 'is-partial'; ?>"><span><?= sports_icon('matchup-analysis'); ?>Lineups</span><strong><?= sports_e($providerSettings['readiness']['lineups'] ?? 'Manual check'); ?></strong><small>Starting lineups, scratches, and minutes limits.</small></div>
                                            <div class="is-connected"><span><?= sports_icon('status-indicator'); ?>Execution</span><strong>Manual only</strong><small>No automated wagers or exchange orders.</small></div>
                                        </div>
                                        <form class="betedge-provider-form" id="sportsProviderSetupForm" data-sports-provider-form>
                                            <input type="hidden" name="csrf" value="<?= sports_e(aegis_sports_product_csrf_token()); ?>">
                                            <label>
                                                <span>The Odds API key</span>
                                                <input type="password" name="odds_api_key" placeholder="<?= !empty($providerSettings['oddsKeyMasked']) ? sports_e($providerSettings['oddsKeyMasked']) : 'Paste key to enable sportsbook line matching'; ?>" autocomplete="off">
                                            </label>
                                            <label>
                                                <span>Preferred region</span>
                                                <select name="preferred_region">
                                                    <option value="us" <?= ($providerSettings['preferred_region'] ?? 'us') === 'us' ? 'selected' : ''; ?>>United States</option>
                                                    <option value="us2" <?= ($providerSettings['preferred_region'] ?? 'us') === 'us2' ? 'selected' : ''; ?>>United States 2</option>
                                                    <option value="uk" <?= ($providerSettings['preferred_region'] ?? 'us') === 'uk' ? 'selected' : ''; ?>>United Kingdom</option>
                                                    <option value="eu" <?= ($providerSettings['preferred_region'] ?? 'us') === 'eu' ? 'selected' : ''; ?>>Europe</option>
                                                    <option value="au" <?= ($providerSettings['preferred_region'] ?? 'us') === 'au' ? 'selected' : ''; ?>>Australia</option>
                                                </select>
                                            </label>
                                            <label>
                                                <span>Injury feed URL</span>
                                                <input type="url" name="injury_feed_url" value="<?= sports_e($providerSettings['injury_feed_url'] ?? ''); ?>" placeholder="https://provider.example/injuries.json" autocomplete="off">
                                            </label>
                                            <label>
                                                <span>Lineup feed URL</span>
                                                <input type="url" name="lineup_feed_url" value="<?= sports_e($providerSettings['lineup_feed_url'] ?? ''); ?>" placeholder="https://provider.example/lineups.json" autocomplete="off">
                                            </label>
                                            <label>
                                                <span>News feed URL</span>
                                                <input type="url" name="news_feed_url" value="<?= sports_e($providerSettings['news_feed_url'] ?? ''); ?>" placeholder="https://provider.example/news.json" autocomplete="off">
                                            </label>
                                            <label>
                                                <span>Player props feed URL</span>
                                                <input type="url" name="props_feed_url" value="<?= sports_e($providerSettings['props_feed_url'] ?? ''); ?>" placeholder="https://provider.example/props.json" autocomplete="off">
                                            </label>
                                            <label>
                                                <span>Bankroll unit</span>
                                                <input type="number" name="bankroll_unit" min="0.01" step="0.01" value="<?= sports_e($providerSettings['bankroll_unit'] ?? '1.00'); ?>">
                                            </label>
                                            <label>
                                                <span>Max stake units</span>
                                                <input type="number" name="max_stake_units" min="0.05" max="5" step="0.05" value="<?= sports_e($providerSettings['max_stake_units'] ?? '0.85'); ?>">
                                            </label>
                                            <label class="betedge-provider-toggle">
                                                <input type="checkbox" name="clear_odds_api_key" value="1">
                                                <span>Clear saved Odds API key</span>
                                            </label>
                                            <div class="betedge-provider-actions">
                                                <button class="sports-reference-place-bet" type="submit">Save Provider Setup</button>
                                                <small id="sportsProviderSaveResult"><?= sports_e($providerSettings['secretStorage']['message'] ?? 'Configure LINEFORGE_CREDENTIAL_KEY to encrypt provider keys before hosting.'); ?></small>
                                            </div>
                                        </form>
                                    </section>
                                    <section class="betedge-card betedge-wide-card">
                                        <div class="betedge-card-head">
                                            <strong class="lineforge-section-title"><?= sports_icon('market-analysis'); ?>Model Data Settings</strong>
                                            <small>Automatic and configurable inputs</small>
                                        </div>
                                        <div class="betedge-source-grid" id="sportsModelSourceGrid">
                                            <?php foreach ($modelSources as $source): ?>
                                                <?php $sourceStatus = strtolower((string) ($source['status'] ?? '')); ?>
                                                <div class="<?= (str_contains($sourceStatus, 'connected') || str_contains($sourceStatus, 'active')) ? 'is-connected' : ((str_contains($sourceStatus, 'designed') || str_contains($sourceStatus, 'partial') || str_contains($sourceStatus, 'public')) ? 'is-partial' : 'needs-setup'); ?>">
                                                    <span><?= sports_e($source['name'] ?? 'Data source'); ?></span>
                                                    <strong><?= sports_e($source['status'] ?? 'Needs setup'); ?></strong>
                                                    <code><?= sports_e($source['env'] ?? 'Configuration'); ?></code>
                                                        <small><?= sports_e($source['detail'] ?? 'Connect this source to improve calibration and research scoring.'); ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </section>
                                    <section class="betedge-card">
                                        <div class="betedge-card-head"><strong class="lineforge-section-title"><?= sports_icon('confidence-score'); ?>Calibration Inputs</strong></div>
                                        <div class="betedge-feed-grid">
                                            <div><span><?= sports_icon('status-indicator'); ?>Active now</span><strong>Scoreboard, status, records, public history</strong><small>These are used automatically when the public feed supplies them.</small></div>
                                            <div><span><?= sports_icon('injury-risk'); ?>Partial now</span><strong>Player leaders, injuries, boxscore stats, weather</strong><small>Public summaries and no-key weather feeds fill these when available; late lineup and tracking feeds can still improve them.</small></div>
                                            <div><span><?= sports_icon('sharp-money'); ?>Needs setup</span><strong>Sharp money, referee tendencies, deep tracking</strong><small>Ref crews may be listed publicly, but tendency history remains conservative until a verified vendor feed is configured.</small></div>
                                            <div><span><?= sports_icon('ai-signals'); ?>Future model</span><strong>XGBoost, LightGBM, simulation</strong><small>The feature layout is ready for trained ML and Monte Carlo simulation inputs.</small></div>
                                        </div>
                                    </section>
                                    <section class="betedge-card">
                                        <div class="betedge-card-head"><strong class="lineforge-section-title"><?= sports_icon('risk-tier'); ?>Responsible Mode</strong></div>
                                        <div class="betedge-feed-grid">
                                            <div><span><?= sports_icon('status-indicator'); ?>No auto execution</span><strong>Informational only</strong><small>Lineforge explains probabilities and uncertainty without placing wagers.</small></div>
                                            <div><span><?= sports_icon('risk-tier'); ?>Missing data penalty</span><strong>Always applied</strong><small>Research confidence is clipped when injuries, fatigue, advanced metrics, or lineup feeds are unavailable.</small></div>
                                        </div>
                                    </section>
                                </div>
                            </section>
                        </section>

                    </div>

                    <footer class="betedge-statusbar" id="sportsTape">
                        <?php foreach ($sportsTape as $ticker): ?>
                            <span><strong><?= sports_e($ticker['label'] ?? 'Feed'); ?></strong> <?= sports_e($ticker['value'] ?? ''); ?> <em><?= sports_e($ticker['state'] ?? ''); ?></em></span>
                        <?php endforeach; ?>
                        <span class="system"><i></i> All Systems Operational</span>
                    </footer>
                </section>

                <div class="sports-reference-drawer betedge-drawer" id="sportsPickDrawer" hidden aria-hidden="true">
                    <div class="sports-reference-drawer-backdrop" data-sports-close-pick></div>
                    <section class="sports-reference-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="sportsPickDrawerTitle">
                        <button type="button" class="sports-reference-drawer-close" data-sports-close-pick aria-label="Close prediction details">&times;</button>
                        <span id="sportsPickDrawerEyebrow">AI prediction details</span>
                        <h2 id="sportsPickDrawerTitle">Prediction breakdown</h2>
                        <p id="sportsPickDrawerReason">Select a pick to inspect how Lineforge weighted matchup strength, form, context, market data, and missing inputs.</p>
                        <div class="betedge-drawer-score" id="sportsPickDrawerScore"></div>
                        <div class="sports-reference-drawer-section">
                            <h3>Team comparison</h3>
                            <div class="betedge-team-comparison" id="sportsPickDrawerComparison"></div>
                        </div>
                        <div class="sports-reference-drawer-section">
                            <h3>How the rating is built</h3>
                            <div class="sports-reference-drawer-grid betedge-rating-steps" id="sportsPickDrawerMath"></div>
                        </div>
                        <div class="sports-reference-drawer-section">
                            <h3>Signal readiness checklist</h3>
                            <div class="sports-reference-drawer-grid betedge-readiness-grid" id="sportsPickDrawerReadiness"></div>
                        </div>
                        <div class="sports-reference-drawer-section">
                            <h3>Factor checklist</h3>
                            <div class="betedge-factor-matrix" id="sportsPickDrawerFactors"></div>
                        </div>
                        <div class="sports-reference-drawer-section">
                            <h3>Line shopping</h3>
                            <div class="sports-reference-drawer-providers betedge-provider-links" id="sportsPickDrawerProviders"></div>
                        </div>
                        <div class="sports-reference-drawer-section">
                            <h3>No-bet reasons</h3>
                            <div class="sports-reference-drawer-list" id="sportsPickDrawerNoBet"></div>
                        </div>
                        <div class="sports-reference-drawer-section">
                            <h3>Manual verification</h3>
                            <div class="sports-reference-drawer-list" id="sportsPickDrawerManual"></div>
                        </div>
                        <div class="sports-reference-drawer-section">
                            <h3>Data gaps to configure</h3>
                            <div class="sports-reference-drawer-list" id="sportsPickDrawerInjuries"></div>
                        </div>
                    </section>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <?php if ($account['signedIn']): ?>
        <script nonce="<?= sports_e(aegis_sports_product_csp_nonce()); ?>">
            window.AEGIS_SPORTS = <?= json_encode($sportsConfig, JSON_UNESCAPED_SLASHES); ?>;
            window.AEGIS_SPORTS_STATE = <?= json_encode($sportsState, JSON_UNESCAPED_SLASHES); ?>;
        </script>
    <?php endif; ?>
    <script nonce="<?= sports_e(aegis_sports_product_csp_nonce()); ?>" src="assets/js/aegis.js?v=20260505-security-1"></script>
</body>
</html>
