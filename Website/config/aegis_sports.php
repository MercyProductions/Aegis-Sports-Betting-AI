<?php

require_once __DIR__ . '/aegis_remote_data.php';
require_once __DIR__ . '/arbitrage.php';
require_once __DIR__ . '/public_intelligence.php';

function aegis_sports_limit_int(array $limits, string $key, int $fallback): int
{
    $value = (int) ($limits[$key] ?? 0);
    return $value > 0 ? $value : $fallback;
}

function aegis_sports_tier_from_limits(array $limits): string
{
    $trackedGames = aegis_sports_limit_int($limits, 'tracked_games', 3);
    $models = aegis_sports_limit_int($limits, 'models', 2);
    $refresh = aegis_sports_limit_int($limits, 'refresh_seconds', 60);

    if ($trackedGames >= 100 || $models >= 10 || $refresh <= 5) {
        return 'elite';
    }

    if ($trackedGames >= 25 || $models >= 5 || $refresh <= 20) {
        return 'pro';
    }

    return 'free';
}

function aegis_sports_clamp(float $value, float $min, float $max): float
{
    return max($min, min($max, $value));
}

function aegis_sports_signed(float $value, string $suffix = '%', int $precision = 1): string
{
    $rounded = round($value, $precision);
    $prefix = $rounded > 0 ? '+' : '';
    return $prefix . number_format($rounded, $precision) . $suffix;
}

function aegis_sports_money(float $value): string
{
    $prefix = $value < 0 ? '-$' : '$';
    return $prefix . number_format(abs($value), 2);
}

function aegis_sports_edge_value(string $edge): ?float
{
    $clean = trim(str_replace('%', '', $edge));
    if ($clean === '' || !is_numeric($clean)) {
        return null;
    }

    return (float) $clean;
}

function aegis_sports_expected_value_from_edge(string $edge, float $stake = 100.0): string
{
    $edgeValue = aegis_sports_edge_value($edge);
    if ($edgeValue === null) {
        return aegis_sports_money(0);
    }

    return aegis_sports_money(($edgeValue / 100) * $stake);
}

function aegis_sports_probability_to_american(float $probability): string
{
    $probability = aegis_sports_clamp($probability, 0.01, 0.99);
    if ($probability >= 0.5) {
        return '-' . number_format(($probability / (1 - $probability)) * 100, 0, '.', '');
    }

    return '+' . number_format(((1 - $probability) / $probability) * 100, 0, '.', '');
}

function aegis_sports_american_to_probability(string $odds): ?float
{
    $price = (int) preg_replace('/[^0-9+\-]/', '', $odds);
    if ($price === 0) {
        return null;
    }

    return $price > 0
        ? 100 / ($price + 100)
        : abs($price) / (abs($price) + 100);
}

function aegis_sports_format_american_price($price): string
{
    if (!is_numeric($price)) {
        return '--';
    }

    $price = (int) round((float) $price);
    return $price > 0 ? '+' . $price : (string) $price;
}

function aegis_sports_normalize_name(string $value): string
{
    $value = strtolower($value);
    $value = preg_replace('/\b(fc|sc|cf|the|team|club|university|college)\b/', ' ', $value) ?? $value;
    return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
}

function aegis_sports_bookmaker_catalog(): array
{
    return [
        'fanduel' => [
            'title' => 'FanDuel',
            'kind' => 'Sportsbook',
            'oddsKey' => 'fanduel',
            'url' => 'https://sportsbook.fanduel.com/',
            'note' => 'Availability depends on user location and FanDuel account eligibility.',
        ],
        'draftkings' => [
            'title' => 'DraftKings',
            'kind' => 'Sportsbook',
            'oddsKey' => 'draftkings',
            'url' => 'https://sportsbook.draftkings.com/',
            'note' => 'Availability depends on user location and DraftKings account eligibility.',
        ],
        'betmgm' => [
            'title' => 'BetMGM',
            'kind' => 'Sportsbook',
            'oddsKey' => 'betmgm',
            'url' => 'https://sports.betmgm.com/',
            'note' => 'Availability depends on user location and BetMGM account eligibility.',
        ],
        'caesars' => [
            'title' => 'Caesars',
            'kind' => 'Sportsbook',
            'oddsKey' => 'williamhill_us',
            'url' => 'https://www.caesars.com/sportsbook-and-casino',
            'note' => 'Availability depends on user location and Caesars account eligibility.',
        ],
        'espnbet' => [
            'title' => 'ESPN BET',
            'kind' => 'Sportsbook',
            'oddsKey' => 'espnbet',
            'url' => 'https://espnbet.com/',
            'note' => 'Availability depends on user location and ESPN BET account eligibility.',
        ],
        'fanatics' => [
            'title' => 'Fanatics',
            'kind' => 'Sportsbook',
            'oddsKey' => 'fanatics',
            'url' => 'https://sportsbook.fanatics.com/',
            'note' => 'Availability depends on user location and Fanatics account eligibility.',
        ],
        'betrivers' => [
            'title' => 'BetRivers',
            'kind' => 'Sportsbook',
            'oddsKey' => 'betrivers',
            'url' => 'https://www.betrivers.com/',
            'note' => 'Availability depends on user location and BetRivers account eligibility.',
        ],
        'kalshi' => [
            'title' => 'Kalshi',
            'kind' => 'Prediction Exchange',
            'oddsKey' => '',
            'url' => 'https://kalshi.com/markets',
            'note' => 'Kalshi markets are event contracts, not sportsbook bets.',
        ],
    ];
}

function aegis_sports_odds_api_sport_key(array $game): ?string
{
    $leagueKey = strtolower((string) ($game['leagueKey'] ?? ''));
    $league = strtolower((string) ($game['league'] ?? ''));
    $map = [
        'nba' => 'basketball_nba',
        'wnba' => 'basketball_wnba',
        'ncaab' => 'basketball_ncaab',
        'ncaaw' => 'basketball_ncaab',
        'nfl' => 'americanfootball_nfl',
        'ncaaf' => 'americanfootball_ncaaf',
        'mlb' => 'baseball_mlb',
        'nhl' => 'icehockey_nhl',
        'epl' => 'soccer_epl',
        'laliga' => 'soccer_spain_la_liga',
        'serie-a' => 'soccer_italy_serie_a',
        'bundesliga' => 'soccer_germany_bundesliga',
        'ligue-1' => 'soccer_france_ligue_one',
        'mls' => 'soccer_usa_mls',
        'liga-mx' => 'soccer_mexico_ligamx',
        'ucl' => 'soccer_uefa_champs_league',
        'uel' => 'soccer_uefa_europa_league',
        'ufc' => 'mma_mixed_martial_arts',
        'atp' => 'tennis_atp',
        'wta' => 'tennis_wta',
        'pga' => 'golf_pga_championship_winner',
    ];

    if (isset($map[$leagueKey])) {
        return $map[$leagueKey];
    }

    foreach ($map as $needle => $sportKey) {
        if ($needle !== '' && str_contains($league, $needle)) {
            return $sportKey;
        }
    }

    return null;
}

function aegis_sports_odds_api_bookmakers(): string
{
    $keys = [];
    foreach (aegis_sports_bookmaker_catalog() as $book) {
        $key = (string) ($book['oddsKey'] ?? '');
        if ($key !== '') {
            $keys[] = $key;
        }
    }

    return implode(',', array_values(array_unique($keys)));
}

function aegis_sports_odds_api_key(): string
{
    foreach (['AEGIS_ODDS_API_KEY', 'ODDS_API_KEY', 'THE_ODDS_API_KEY'] as $name) {
        $value = getenv($name);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return '';
}

function aegis_sports_odds_api_region(): string
{
    $region = strtolower(trim((string) (getenv('AEGIS_ODDS_API_REGION') ?: 'us')));
    return in_array($region, ['us', 'us2', 'uk', 'eu', 'au'], true) ? $region : 'us';
}

function aegis_sports_team_aliases(array $team): array
{
    $aliases = [];
    foreach (['name', 'abbr', 'short', 'displayName'] as $key) {
        $value = (string) ($team[$key] ?? '');
        if ($value !== '') {
            $aliases[] = aegis_sports_normalize_name($value);
        }
    }

    $name = (string) ($team['name'] ?? '');
    foreach (preg_split('/\s+/', $name) ?: [] as $part) {
        $normalized = aegis_sports_normalize_name((string) $part);
        if (strlen($normalized) >= 3) {
            $aliases[] = $normalized;
        }
    }

    return array_values(array_unique(array_filter($aliases, static fn(string $value): bool => strlen($value) >= 2)));
}

function aegis_sports_alias_matches(string $value, array $aliases): bool
{
    $value = aegis_sports_normalize_name($value);
    if ($value === '') {
        return false;
    }

    foreach ($aliases as $alias) {
        if ($alias === '' || strlen((string) $alias) < 2) {
            continue;
        }

        if ($value === $alias || str_contains($value, (string) $alias) || str_contains((string) $alias, $value)) {
            return true;
        }
    }

    return false;
}

function aegis_sports_pick_side(array $game, array $prediction): ?string
{
    $pick = (string) ($prediction['pick'] ?? '');
    $lead = trim((string) preg_replace('/\s+.*$/', '', $pick));
    $awayAliases = aegis_sports_team_aliases((array) ($game['away'] ?? []));
    $homeAliases = aegis_sports_team_aliases((array) ($game['home'] ?? []));

    if ($lead !== '') {
        if (aegis_sports_alias_matches($lead, $awayAliases)) {
            return 'away';
        }

        if (aegis_sports_alias_matches($lead, $homeAliases)) {
            return 'home';
        }
    }

    $awayMatch = aegis_sports_alias_matches($pick, $awayAliases);
    $homeMatch = aegis_sports_alias_matches($pick, $homeAliases);
    if ($awayMatch && !$homeMatch) {
        return 'away';
    }

    if ($homeMatch && !$awayMatch) {
        return 'home';
    }

    return null;
}

function aegis_sports_prediction_winner_projection(array $game, array $prediction, array $context = []): array
{
    $away = (array) ($game['away'] ?? []);
    $home = (array) ($game['home'] ?? []);
    $statusKey = (string) ($game['statusKey'] ?? $prediction['statusKey'] ?? 'scheduled');
    $side = null;
    $basis = 'Model rating lean';
    $strength = 'Lean';

    if ($statusKey === 'final') {
        if (!empty($home['winner'])) {
            $side = 'home';
        } elseif (!empty($away['winner'])) {
            $side = 'away';
        } else {
            $awayScore = (int) ($away['score'] ?? 0);
            $homeScore = (int) ($home['score'] ?? 0);
            if ($awayScore !== $homeScore) {
                $side = $awayScore > $homeScore ? 'away' : 'home';
            }
        }
        $basis = 'Final result';
        $strength = 'Observed';
    }

    if ($side === null) {
        $pickSide = aegis_sports_pick_side($game, $prediction);
        if ($pickSide === 'away' || $pickSide === 'home') {
            $side = $pickSide;
            $basis = 'Market pick side';
            $strength = 'Signal side';
        }
    }

    $awayRating = null;
    $homeRating = null;
    if ($side === null && is_array($prediction['teamComparison'] ?? null)) {
        $comparison = (array) $prediction['teamComparison'];
        $comparisonSide = (string) ($comparison['pickSide'] ?? '');
        if ($comparisonSide === 'away' || $comparisonSide === 'home') {
            $side = $comparisonSide;
            $basis = 'Model comparison';
        }
        $awayRating = is_numeric($comparison['away']['rating'] ?? null) ? (float) $comparison['away']['rating'] : null;
        $homeRating = is_numeric($comparison['home']['rating'] ?? null) ? (float) $comparison['home']['rating'] : null;
    }

    if ($side === null) {
        $awayRating ??= (float) aegis_sports_team_rating_for_side('away', $game, $context);
        $homeRating ??= (float) aegis_sports_team_rating_for_side('home', $game, $context);
        if ($awayRating !== $homeRating) {
            $side = $awayRating > $homeRating ? 'away' : 'home';
            $delta = abs($awayRating - $homeRating);
            $strength = $delta >= 7 ? 'Clear lean' : ($delta >= 3 ? 'Lean' : 'Thin lean');
        }
    }

    if ($side !== 'away' && $side !== 'home') {
        return [
            'side' => 'market',
            'label' => 'No clear side',
            'abbr' => 'N/A',
            'basis' => 'Market watch',
            'strength' => 'Uncertain',
        ];
    }

    $team = $side === 'home' ? $home : $away;

    return [
        'side' => $side,
        'label' => (string) ($team['name'] ?? $team['abbr'] ?? 'Predicted winner'),
        'abbr' => (string) ($team['abbr'] ?? $team['name'] ?? 'WIN'),
        'basis' => $basis,
        'strength' => $strength,
    ];
}

function aegis_sports_fetch_odds_board(string $sportKey, int $ttlSeconds): ?array
{
    $apiKey = aegis_sports_odds_api_key();
    if ($apiKey === '' || $sportKey === '') {
        return null;
    }

    $bookmakers = aegis_sports_odds_api_bookmakers();
    $query = http_build_query([
        'apiKey' => $apiKey,
        'regions' => aegis_sports_odds_api_region(),
        'markets' => 'h2h,spreads,totals',
        'oddsFormat' => 'american',
        'dateFormat' => 'iso',
        'bookmakers' => $bookmakers,
    ]);
    $url = 'https://api.the-odds-api.com/v4/sports/' . rawurlencode($sportKey) . '/odds/?' . $query;

    return aegis_remote_data_cached(
        'sports-odds',
        'v2:' . aegis_sports_odds_api_region() . ':' . $sportKey . ':' . $bookmakers,
        max(90, min(900, $ttlSeconds)),
        static function () use ($url): array {
            $data = aegis_remote_data_http_json($url, 4.0);
            return is_array($data) ? $data : [];
        }
    );
}

function aegis_sports_find_matching_odds_event(array $game, array $events): ?array
{
    $homeAliases = aegis_sports_team_aliases((array) ($game['home'] ?? []));
    $awayAliases = aegis_sports_team_aliases((array) ($game['away'] ?? []));

    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }

        $eventHome = (string) ($event['home_team'] ?? '');
        $eventAway = (string) ($event['away_team'] ?? '');
        $directMatch = aegis_sports_alias_matches($eventHome, $homeAliases) && aegis_sports_alias_matches($eventAway, $awayAliases);
        $swappedMatch = aegis_sports_alias_matches($eventHome, $awayAliases) && aegis_sports_alias_matches($eventAway, $homeAliases);

        if ($directMatch || $swappedMatch) {
            return $event;
        }
    }

    return null;
}

function aegis_sports_select_outcome(array $outcomes, string $marketKey, array $game, array $prediction): ?array
{
    $outcomes = array_values(array_filter($outcomes, 'is_array'));
    if (!$outcomes) {
        return null;
    }

    $pick = strtolower((string) ($prediction['pick'] ?? ''));
    if ($marketKey === 'totals') {
        $preferUnder = preg_match('/(^|\s)(u|under)\b/', $pick) === 1;
        $preferOver = preg_match('/(^|\s)(o|over)\b/', $pick) === 1 || !$preferUnder;
        foreach ($outcomes as $outcome) {
            $name = strtolower((string) ($outcome['name'] ?? ''));
            if (($preferOver && str_contains($name, 'over')) || ($preferUnder && str_contains($name, 'under'))) {
                return $outcome;
            }
        }
    }

    $side = aegis_sports_pick_side($game, $prediction);
    if ($side !== null) {
        $aliases = aegis_sports_team_aliases((array) ($game[$side] ?? []));
        foreach ($outcomes as $outcome) {
            if (aegis_sports_alias_matches((string) ($outcome['name'] ?? ''), $aliases)) {
                return $outcome;
            }
        }
    }

    return $outcomes[0];
}

function aegis_sports_extract_book_market(array $bookmaker, array $game, array $prediction): array
{
    $marketLabel = strtolower((string) ($prediction['market'] ?? ''));
    $preferredMarkets = str_contains($marketLabel, 'total')
        ? ['totals', 'spreads', 'h2h']
        : (str_contains($marketLabel, 'spread') ? ['spreads', 'h2h', 'totals'] : ['h2h', 'spreads', 'totals']);
    $marketTitles = [
        'h2h' => 'Moneyline',
        'spreads' => 'Spread',
        'totals' => 'Total',
    ];

    foreach ($preferredMarkets as $preferredMarket) {
        foreach ((array) ($bookmaker['markets'] ?? []) as $market) {
            if (!is_array($market) || (string) ($market['key'] ?? '') !== $preferredMarket) {
                continue;
            }

            $outcome = aegis_sports_select_outcome((array) ($market['outcomes'] ?? []), $preferredMarket, $game, $prediction);
            if (!$outcome) {
                continue;
            }

            $point = is_numeric($outcome['point'] ?? null) ? (float) $outcome['point'] : null;
            $price = aegis_sports_format_american_price($outcome['price'] ?? null);
            $name = (string) ($outcome['name'] ?? '');
            $line = $name;
            if ($point !== null) {
                $line .= ' ' . ($preferredMarket === 'spreads' ? aegis_sports_format_line($point) : number_format($point, abs($point - round($point)) > 0.01 ? 1 : 0));
            }

            $modelProbability = aegis_sports_clamp(((float) ($prediction['confidenceValue'] ?? 58)) / 100, 0.01, 0.99);
            $bookProbability = aegis_sports_american_to_probability($price);
            $edge = $bookProbability !== null
                ? aegis_sports_signed(($modelProbability - $bookProbability) * 100)
                : (string) ($prediction['edge'] ?? '+0.0%');

            return [
                'market' => $marketTitles[$preferredMarket] ?? strtoupper($preferredMarket),
                'line' => trim($line) !== '' ? trim($line) : (string) ($prediction['pick'] ?? 'Market'),
                'price' => $price,
                'bookProbability' => $bookProbability !== null ? number_format($bookProbability * 100, 1) . '%' : '--',
                'modelEdge' => $edge,
                'lastUpdate' => (string) ($market['last_update'] ?? $bookmaker['last_update'] ?? ''),
            ];
        }
    }

    return [];
}

function aegis_sports_build_odds_index(array $games, int $refreshSeconds): array
{
    if (aegis_sports_odds_api_key() === '') {
        return [];
    }

    $ttl = max(90, min(900, $refreshSeconds * 6));
    $eventsBySport = [];
    $index = [];

    foreach ($games as $game) {
        $sportKey = aegis_sports_odds_api_sport_key($game);
        if ($sportKey === null) {
            continue;
        }

        if (!array_key_exists($sportKey, $eventsBySport)) {
            $eventsBySport[$sportKey] = aegis_sports_fetch_odds_board($sportKey, $ttl) ?? [];
        }

        $event = aegis_sports_find_matching_odds_event($game, $eventsBySport[$sportKey]);
        if ($event) {
            $index[(string) ($game['id'] ?? '')] = [
                'sportKey' => $sportKey,
                'event' => $event,
            ];
        }
    }

    return $index;
}

function aegis_sports_fetch_kalshi_markets(int $ttlSeconds): array
{
    $enabled = strtolower((string) (getenv('AEGIS_KALSHI_PUBLIC_MARKETS') ?: '1'));
    if (in_array($enabled, ['0', 'false', 'off', 'no'], true)) {
        return [];
    }

    $url = 'https://api.elections.kalshi.com/trade-api/v2/markets?' . http_build_query([
        'status' => 'open',
        'limit' => 100,
    ]);

    $data = aegis_remote_data_cached(
        'sports-kalshi',
        'open-markets',
        max(300, min(1800, $ttlSeconds)),
        static function () use ($url): array {
            $payload = aegis_remote_data_http_json($url, 4.0);
            return is_array($payload) ? $payload : [];
        }
    ) ?? [];

    return is_array($data['markets'] ?? null) ? $data['markets'] : (is_array($data) ? $data : []);
}

function aegis_sports_kalshi_market_text(array $market): string
{
    return implode(
        ' ',
        array_filter([
            (string) ($market['ticker'] ?? ''),
            (string) ($market['event_ticker'] ?? ''),
            (string) ($market['title'] ?? ''),
            (string) ($market['subtitle'] ?? ''),
            (string) ($market['yes_sub_title'] ?? ''),
        ])
    );
}

function aegis_sports_matching_kalshi_links(array $game, array $markets): array
{
    $query = trim((string) ($game['matchup'] ?? '') . ' ' . (string) ($game['league'] ?? ''));
    $fallbackUrl = 'https://kalshi.com/markets?search=' . rawurlencode($query !== '' ? $query : 'sports');
    $aliases = array_merge(
        aegis_sports_team_aliases((array) ($game['away'] ?? [])),
        aegis_sports_team_aliases((array) ($game['home'] ?? []))
    );
    $matches = [];

    foreach ($markets as $market) {
        if (!is_array($market)) {
            continue;
        }

        $text = aegis_sports_kalshi_market_text($market);
        $hits = 0;
        foreach ($aliases as $alias) {
            if (aegis_sports_alias_matches($text, [$alias])) {
                $hits += 1;
            }
        }

        if ($hits < 1) {
            continue;
        }

        $title = (string) ($market['title'] ?? $market['ticker'] ?? 'Kalshi event contract');
        $price = '';
        foreach (['yes_ask', 'last_price', 'yes_bid'] as $priceKey) {
            if (is_numeric($market[$priceKey] ?? null)) {
                $price = number_format(((float) $market[$priceKey]) / 100, 2);
                break;
            }
        }

        $matches[] = [
            'providerKey' => 'kalshi',
            'title' => 'Kalshi',
            'kind' => 'Prediction Exchange',
            'url' => 'https://kalshi.com/markets?search=' . rawurlencode((string) ($market['ticker'] ?? $title)),
            'available' => true,
            'market' => $title,
            'line' => (string) ($market['subtitle'] ?? 'Event contract'),
            'price' => $price !== '' ? $price : '--',
            'fairOdds' => '--',
            'modelEdge' => 'Verify',
            'source' => 'Kalshi public markets',
            'note' => 'Event-contract pricing differs from sportsbook odds. Confirm contract rules before trading.',
        ];

        if (count($matches) >= 2) {
            break;
        }
    }

    if ($matches) {
        return $matches;
    }

    return [[
        'providerKey' => 'kalshi',
        'title' => 'Kalshi',
        'kind' => 'Prediction Exchange',
        'url' => $fallbackUrl,
        'available' => false,
        'market' => 'Search event contracts',
        'line' => (string) ($game['matchup'] ?? 'Matchup'),
        'price' => '--',
        'fairOdds' => '--',
        'modelEdge' => 'Search',
        'source' => 'Kalshi search',
        'note' => 'No matching public contract was cached for this matchup yet.',
    ]];
}

function aegis_sports_market_links_for_game(array $game, array $prediction, array $oddsIndex, array $kalshiMarkets): array
{
    $catalog = aegis_sports_bookmaker_catalog();
    $eventRecord = $oddsIndex[(string) ($game['id'] ?? '')] ?? null;
    $event = is_array($eventRecord['event'] ?? null) ? $eventRecord['event'] : [];
    $bookmakers = [];
    foreach ((array) ($event['bookmakers'] ?? []) as $bookmaker) {
        if (is_array($bookmaker) && !empty($bookmaker['key'])) {
            $bookmakers[(string) $bookmaker['key']] = $bookmaker;
        }
    }

    $modelProbability = aegis_sports_clamp(((float) ($prediction['confidenceValue'] ?? 58)) / 100, 0.01, 0.99);
    $fairOdds = aegis_sports_probability_to_american($modelProbability);
    $links = [];

    foreach ($catalog as $providerKey => $provider) {
        if (($provider['kind'] ?? '') === 'Prediction Exchange') {
            continue;
        }

        $oddsKey = (string) ($provider['oddsKey'] ?? '');
        $market = $oddsKey !== '' && isset($bookmakers[$oddsKey])
            ? aegis_sports_extract_book_market($bookmakers[$oddsKey], $game, $prediction)
            : [];

        $links[] = [
            'providerKey' => (string) $providerKey,
            'title' => (string) ($provider['title'] ?? ucfirst((string) $providerKey)),
            'kind' => (string) ($provider['kind'] ?? 'Sportsbook'),
            'url' => (string) ($provider['url'] ?? '#'),
            'available' => $market !== [],
            'market' => (string) ($market['market'] ?? ($prediction['market'] ?? 'Market')),
            'line' => (string) ($market['line'] ?? ($game['matchup'] ?? 'Open app')),
            'price' => (string) ($market['price'] ?? '--'),
            'fairOdds' => $fairOdds,
            'bookProbability' => (string) ($market['bookProbability'] ?? '--'),
            'modelEdge' => (string) ($market['modelEdge'] ?? ($prediction['edge'] ?? '+0.0%')),
            'lastUpdate' => (string) ($market['lastUpdate'] ?? ''),
            'source' => $market !== [] ? 'The Odds API' : (aegis_sports_odds_api_key() !== '' ? 'No line returned' : 'Link only'),
            'note' => (string) ($provider['note'] ?? 'Open the provider and confirm the market before wagering.'),
        ];
    }

    return array_merge($links, aegis_sports_matching_kalshi_links($game, $kalshiMarkets));
}

function aegis_sports_best_market_link(array $links): ?array
{
    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        if (!empty($link['available']) && (string) ($link['price'] ?? '--') !== '--' && (string) ($link['kind'] ?? '') === 'Sportsbook') {
            return $link;
        }
    }

    foreach ($links as $link) {
        if (is_array($link) && !empty($link['available'])) {
            return $link;
        }
    }

    return is_array($links[0] ?? null) ? $links[0] : null;
}

function aegis_sports_prediction_breakdown(array $prediction, ?array $game = null): array
{
    $confidence = (int) ($prediction['confidenceValue'] ?? 58);
    $statusKey = (string) ($prediction['statusKey'] ?? ($game['statusKey'] ?? 'scheduled'));
    $market = (string) ($prediction['market'] ?? 'Monitor');
    $autoContext = is_array($prediction['autoContext'] ?? null) ? $prediction['autoContext'] : [];
    $comparison = is_array($prediction['teamComparison'] ?? null)
        ? $prediction['teamComparison']
        : ($game ? aegis_sports_team_comparison($prediction, $game, $autoContext) : []);
    $comparisonSignals = is_array($comparison['signals'] ?? null) ? $comparison['signals'] : [];
    $h2hCount = (int) ($comparisonSignals['h2hCount'] ?? 0);
    $recentCount = (int) ($comparisonSignals['recentCount'] ?? 0);
    $playerCount = (int) ($comparisonSignals['playerCount'] ?? 0);
    $injuryCount = (int) ($comparisonSignals['injuryCount'] ?? 0);
    $boxscoreAvailable = !empty($comparisonSignals['boxscoreAvailable']) || !empty($autoContext['boxscoreAvailable']);
    $summaryAvailable = !empty($comparisonSignals['summaryAvailable']) || !empty($autoContext['summaryAvailable']);
    $historyAvailable = !empty($comparisonSignals['historyAvailable']) || !empty($autoContext['historyAvailable']);
    $weather = is_array($autoContext['weather'] ?? null) ? $autoContext['weather'] : [];
    $officials = is_array($autoContext['officials'] ?? null) ? $autoContext['officials'] : [];
    $weatherAvailable = !empty($weather['available']);
    $officialCount = (int) ($officials['count'] ?? 0);
    $history = array_values(array_filter((array) ($game['history'] ?? []), 'is_numeric'));
    $firstHistory = (float) ($history[0] ?? 50);
    $lastHistory = (float) ($history[count($history) - 1] ?? $firstHistory);
    $trendDelta = (int) round($lastHistory - $firstHistory);
    $awayScore = (int) ($game['away']['score'] ?? 0);
    $homeScore = (int) ($game['home']['score'] ?? 0);
    $margin = abs($awayScore - $homeScore);
    $hasSpread = ($game['spread']['favoriteLine'] ?? '--') !== '--';
    $hasTotal = ($game['total']['over'] ?? '--') !== '--';
    $marketLinks = array_values(array_filter((array) ($prediction['marketLinks'] ?? []), 'is_array'));
    $dataQuality = is_array($prediction['dataQuality'] ?? null) ? $prediction['dataQuality'] : [];
    $hasSportsbookLine = (bool) count(array_filter($marketLinks, static function (array $link): bool {
        return !empty($link['available']) && (string) ($link['kind'] ?? '') === 'Sportsbook' && (string) ($link['price'] ?? '--') !== '--';
    }));
    $side = $game ? aegis_sports_pick_side($game, $prediction) : null;
    $baseline = 50;
    $statusSignal = match ($statusKey) {
        'live' => 8,
        'scheduled' => 5,
        'final' => -12,
        default => 2,
    };
    $marketSignal = $statusKey === 'final'
        ? -8
        : ($hasSportsbookLine ? 9 : ($hasSpread ? 6 : ($hasTotal ? 4 : 1)));
    $riskAdjustment = match ($statusKey) {
        'live' => -6,
        'final' => -12,
        default => -5,
    };
    $dataSignal = $confidence - $baseline - $statusSignal - $marketSignal - $riskAdjustment;
    $formulaTotal = $baseline + $statusSignal + $marketSignal + $dataSignal + $riskAdjustment;
    $trendSignal = (int) aegis_sports_clamp(round($trendDelta / 7), -4, 4);
    $teamStrengthSignal = (int) aegis_sports_clamp($margin * 2 + ($hasSpread ? 2 : 0), 0, 8);
    $locationSignal = $side === 'home' ? 2 : ($side === 'away' ? -1 : 0);
    $marketStatus = $hasSportsbookLine ? 'Active' : (($hasSpread || $hasTotal) ? 'Partial' : 'Needs setup');
    $lineDetail = $hasSportsbookLine
        ? 'A matched sportsbook price is available, so market disagreement can compare model probability against a real line.'
        : (($hasSpread || $hasTotal)
            ? 'The scoreboard has a spread or total snapshot, but a live bookmaker price still needs the odds API.'
            : 'No line snapshot is attached yet, so this pick stays in watch mode.');

    $factorRows = [
        [
            'label' => 'Core team strength',
            'value' => aegis_sports_signed($teamStrengthSignal, ' pts', 0),
            'status' => $game && ((string) ($game['away']['record'] ?? '') !== '' || (string) ($game['home']['record'] ?? '') !== '') ? 'Active public feed' : 'Partial',
            'impact' => $teamStrengthSignal,
            'detail' => 'Uses scoreboard records, current score margin, league context, and available line snapshots. Deeper offensive rating, defensive rating, net rating, pace, and strength-of-schedule feeds remain optional upgrades.',
        ],
        [
            'label' => 'Head-to-head matchup',
            'value' => $h2hCount > 0 ? $h2hCount . ' found' : 'No recent H2H',
            'status' => $historyAvailable ? ($h2hCount > 0 ? 'Active public feed' : 'Partial public feed') : 'Needs setup',
            'impact' => 0,
            'detail' => $historyAvailable ? 'Scans recent completed games from the public league scoreboard date range and isolates direct meetings when found.' : 'Needs the public history scan or a historical matchup provider for last 5-10 meetings.',
        ],
        [
            'label' => 'Location and environment',
            'value' => $side === 'home' ? '+2 home edge' : ($side === 'away' ? '-1 road tax' : 'Neutral'),
            'status' => 'Partial',
            'impact' => $locationSignal,
            'detail' => 'Home/away side is available. Travel distance, time zones, altitude, and crowd intensity need a schedule/location feed.',
        ],
        [
            'label' => 'Recent form',
            'value' => $recentCount > 0 ? (string) ($comparisonSignals['recentLabel'] ?? ($recentCount . ' games')) : aegis_sports_signed($trendSignal, ' pts', 0),
            'status' => $recentCount > 0 ? 'Active public feed' : ($history ? 'Active proxy' : 'Needs setup'),
            'impact' => $trendSignal,
            'detail' => $recentCount > 0 ? 'Uses the latest completed games found in the public league scoreboard history cache for each team.' : ($history ? 'Uses the confidence/history curve as a proxy until recent completed games are available.' : 'Recent game form needs public history or a team game-log provider.'),
        ],
        [
            'label' => 'Injuries and lineups',
            'value' => $summaryAvailable ? ($injuryCount . ' listed') : '-4 uncertainty',
            'status' => $summaryAvailable ? ($injuryCount > 0 ? 'Active public feed' : 'Partial public feed') : 'Needs setup',
            'impact' => -4,
            'detail' => $summaryAvailable ? 'Uses injuries included in the public event summary when present. Starting lineup confirmation, minutes limits, and on/off impact still need a verified lineup/player provider.' : 'Star availability, bench depth, starting lineup changes, minutes limits, returns from injury, and on/off impact require an injury/lineup API.',
        ],
        [
            'label' => 'Player matchups',
            'value' => $playerCount > 0 ? $playerCount . ' leaders' : 'Pending public summary',
            'status' => $playerCount > 0 ? 'Active public feed' : ($summaryAvailable ? 'Partial public feed' : 'Needs setup'),
            'impact' => 0,
            'detail' => $playerCount > 0 ? 'Uses public event leaders and boxscore athletes to show player-level signals. Defensive assignments, star-versus-star history, and on/off impact still need tracking data.' : 'Player leaders can auto-fill from the public summary when the event feed supplies them.',
        ],
        [
            'label' => 'Advanced metrics',
            'value' => $boxscoreAvailable ? 'Boxscore attached' : 'Advanced feed pending',
            'status' => $boxscoreAvailable ? 'Partial public feed' : 'Needs setup',
            'impact' => 0,
            'detail' => $boxscoreAvailable ? 'Basic boxscore/player stat inputs are attached. eFG%, TS%, turnover rate, rebound rate, assist ratio, usage rate, and possession models still need an advanced-stats provider.' : 'eFG%, TS%, turnover rate, rebound rate, assist ratio, and usage rate need a box-score/advanced-stats provider.',
        ],
        [
            'label' => 'Play style matchup',
            'value' => $hasTotal ? 'Total proxy' : 'Style pending',
            'status' => $hasTotal ? 'Partial' : 'Needs setup',
            'impact' => $hasTotal ? 2 : 0,
            'detail' => $hasTotal ? 'The total market gives a rough pace/scoring proxy. Scheme-level style data still needs a team profile feed.' : 'Fast/slow pace, 3-point versus paint profile, defensive scheme, and transition rate need team style data.',
        ],
        [
            'label' => 'Betting market data',
            'value' => aegis_sports_signed($marketSignal, ' pts', 0),
            'status' => $marketStatus,
            'impact' => $marketSignal,
            'detail' => $lineDetail,
        ],
        [
            'label' => 'Data quality and cap',
            'value' => (string) ($dataQuality['score'] ?? 0) . '/100',
            'status' => (string) ($dataQuality['label'] ?? 'Partial'),
            'impact' => min(0, (int) ($dataQuality['confidenceCap'] ?? $confidence) - (int) ($prediction['rawConfidenceValue'] ?? $confidence)),
            'detail' => 'Confidence is capped by source freshness, sportsbook price depth, public summary availability, recent-history samples, and missing data warnings.',
        ],
        [
            'label' => 'Game context',
            'value' => ucfirst($statusKey),
            'status' => 'Active',
            'impact' => $statusSignal,
            'detail' => 'Uses current status so live games, scheduled games, and finals are scored differently. Playoff, rivalry, trap-game, and must-win context need a schedule/context provider.',
        ],
        [
            'label' => 'Rest and fatigue',
            'value' => $recentCount > 0 ? 'Recent dates scanned' : 'Schedule depth needed',
            'status' => $recentCount > 0 ? 'Partial public feed' : 'Needs setup',
            'impact' => -1,
            'detail' => $recentCount > 0 ? 'Recent completed game dates are visible through the history scan. Exact travel distance, time zones, back-to-backs, and 3-in-4 stretches still need a schedule/travel model.' : 'Days of rest, back-to-backs, 3-in-4 stretches, and travel schedule need deeper schedule data.',
        ],
        [
            'label' => 'Coaching and strategy',
            'value' => 'Not live yet',
            'status' => 'Needs setup',
            'impact' => 0,
            'detail' => 'Coaching win rates, mid-game adjustments, timeout usage, and playoff history require historical team/coaching data.',
        ],
        [
            'label' => 'External factors',
            'value' => $weatherAvailable
                ? (string) ($weather['value'] ?? 'Weather attached') . ($officialCount > 0 ? ' / ' . $officialCount . ' officials' : '')
                : ($officialCount > 0 ? $officialCount . ' officials listed' : 'Weather/ref setup'),
            'status' => $weatherAvailable || $officialCount > 0 ? 'Partial public feed' : 'Needs setup',
            'impact' => (int) ($weather['impact'] ?? 0),
            'detail' => ($weatherAvailable
                ? (string) ($weather['detail'] ?? 'Weather is attached from the public forecast feed.')
                : 'Weather can attach automatically when venue city/state is available from the scoreboard feed.')
                . ' '
                . ($officialCount > 0
                    ? 'Officials are listed by the public event summary, but referee tendency/history still needs a configured dataset.'
                    : 'Referee/umpire names and tendency history are not attached for this event yet.'),
        ],
        [
            'label' => 'Live game data',
            'value' => $statusKey === 'live' ? 'Active live state' : ($statusKey === 'final' ? 'Final audit' : 'Pregame only'),
            'status' => $statusKey === 'live' ? 'Active public feed' : 'Partial public feed',
            'impact' => $statusKey === 'live' ? 3 : 0,
            'detail' => $statusKey === 'live' ? 'Score, clock, status, and momentum proxy are available. Foul trouble, timeouts, runs, and turnover streaks need richer live play-by-play.' : 'Live momentum factors activate after the event is marked live by the provider feed.',
        ],
        [
            'label' => 'Predictive model inputs',
            'value' => aegis_sports_signed($dataSignal, ' pts', 0),
            'status' => 'Active',
            'impact' => $dataSignal,
            'detail' => 'Combines weighted strength, market disagreement, context, trend proxy, and missing-data penalties into one confidence adjustment.',
        ],
    ];

    $missingInputs = array_values(array_filter($factorRows, static function (array $factor): bool {
        return str_contains(strtolower((string) ($factor['status'] ?? '')), 'needs');
    }));

    return [
        'summary' => 'Lineforge starts every matchup at 50/50, compares both teams side by side, adds public signals that are actually available, and subtracts risk for missing lineup, fatigue, advanced tracking, and market-depth data.',
        'formula' => '50 + status + market + model data - missing-data risk = confidence',
        'score' => [
            'confidence' => $confidence . '%',
            'fairProbability' => (string) ($prediction['fairProbability'] ?? ($confidence . '.0%')),
            'fairOdds' => (string) ($prediction['fairOdds'] ?? aegis_sports_probability_to_american($confidence / 100)),
            'edge' => (string) ($prediction['edge'] ?? '+0.0%'),
            'risk' => (string) ($prediction['risk'] ?? 'Model risk'),
            'computed' => $formulaTotal . '%',
            'detail' => 'This is a probability estimate for comparison and monitoring. It is not a promise that the pick wins.',
        ],
        'steps' => [
            [
                'label' => '1. Start neutral',
                'value' => $baseline . '%',
                'detail' => 'Every pick begins at 50% before matchup, market, and live-status evidence is added.',
            ],
            [
                'label' => '2. Add game context',
                'value' => aegis_sports_signed($statusSignal, ' pts', 0),
                'detail' => $statusKey === 'live' ? 'Live status adds urgency, but also raises volatility.' : ($statusKey === 'final' ? 'Final events are audit-only and are not treated as new opportunities.' : 'Pregame status keeps the model calmer than live mode.'),
            ],
            [
                'label' => '3. Add market evidence',
                'value' => aegis_sports_signed($marketSignal, ' pts', 0),
                'detail' => $lineDetail,
            ],
            [
                'label' => '4. Add model evidence',
                'value' => aegis_sports_signed($dataSignal, ' pts', 0),
                'detail' => 'This absorbs team-strength proxy, recent trend, location, live-state proxy, and model agreement.',
            ],
            [
                'label' => '5. Subtract uncertainty',
                'value' => aegis_sports_signed($riskAdjustment, ' pts', 0),
                'detail' => 'Missing injury, lineup, fatigue, advanced metric, and player matchup feeds keep confidence conservative.',
            ],
            [
                'label' => '6. Final confidence',
                'value' => $confidence . '%',
                'detail' => 'The final displayed estimate is clipped into a responsible informational range and capped by data quality.',
            ],
        ],
        'math' => [
            ['label' => 'Formula', 'value' => $formulaTotal . '%', 'detail' => 'The displayed formula is rounded to whole points for readability.'],
        ],
        'factors' => $factorRows,
        'comparison' => $comparison,
        'missingInputs' => array_map(static function (array $factor): array {
            return [
                'label' => (string) ($factor['label'] ?? 'Input'),
                'value' => (string) ($factor['value'] ?? 'Needs setup'),
                'detail' => (string) ($factor['detail'] ?? ''),
            ];
        }, $missingInputs),
        'injuries' => [
            [
                'label' => 'Injury feed',
                'value' => 'Not connected',
                'detail' => 'Connect an injury/lineup provider before treating player availability as live-confirmed.',
            ],
            [
                'label' => 'Manual check',
                'value' => 'Required',
                'detail' => 'Verify official injury reports, starting lineups, scratches, and late news before relying on the rating.',
            ],
            [
                'label' => 'Model handling',
                'value' => 'Conservative',
                'detail' => 'Lineforge treats missing injury data as uncertainty and avoids presenting the pick as guaranteed.',
            ],
        ],
        'computedScore' => $formulaTotal . '%',
    ];
}

function aegis_sports_model_sources(array $marketAccess, array $remoteTransport): array
{
    $hasEnv = static function (array $names): bool {
        foreach ($names as $name) {
            $value = getenv($name);
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    };

    $oddsConnected = !empty($marketAccess['oddsProviderConfigured']);
    $scoreboardConnected = !empty($remoteTransport['https_capable']);

    return [
        [
            'name' => 'Scoreboard and live state',
            'status' => $scoreboardConnected ? 'Connected' : 'Needs server HTTPS',
            'env' => 'Built-in public scoreboard feed',
            'detail' => $scoreboardConnected ? 'Game status, clock, score, league, team records, and board freshness can refresh live without an API key.' : 'Enable PHP cURL/OpenSSL so the server can refresh public scoreboard data.',
        ],
        [
            'name' => 'Sportsbook odds and line movement',
            'status' => $oddsConnected ? 'Connected' : 'Needs API key',
            'env' => 'AEGIS_ODDS_API_KEY',
            'detail' => $oddsConnected ? 'Matched sportsbook prices can feed market disagreement and line movement.' : 'Add a The Odds API key to compare opening/current prices, sportsbook depth, and model edge.',
        ],
        [
            'name' => 'Public betting and sharp movement',
            'status' => $hasEnv(['AEGIS_MARKET_SPLIT_API_KEY', 'AEGIS_ACTION_NETWORK_API_KEY']) ? 'Connected' : 'Needs API key',
            'env' => 'AEGIS_MARKET_SPLIT_API_KEY',
            'detail' => 'Needed for public percentage, sharp-money signals, and line movement against public action.',
        ],
        [
            'name' => 'Injuries and starting lineups',
            'status' => $hasEnv(['AEGIS_INJURY_API_KEY', 'AEGIS_INJURY_FEED_URL', 'AEGIS_LINEUP_FEED_URL', 'AEGIS_SPORTSDATA_API_KEY', 'AEGIS_SPORTRADAR_API_KEY']) ? 'Connected' : ($scoreboardConnected ? 'Partial public feed' : 'Needs server HTTPS'),
            'env' => 'ESPN summary / AEGIS_INJURY_FEED_URL',
            'detail' => $scoreboardConnected ? 'Public event summaries can auto-fill listed injuries when ESPN supplies them. Verified starting lineups, minutes restrictions, and late scratches still benefit from a dedicated provider.' : 'Needed for star availability, bench depth, minutes restrictions, returns from injury, and late scratches.',
        ],
        [
            'name' => 'Team advanced metrics',
            'status' => $hasEnv(['AEGIS_TEAM_STATS_API_KEY', 'AEGIS_SPORTSDATA_API_KEY', 'AEGIS_SPORTRADAR_API_KEY']) ? 'Connected' : ($scoreboardConnected ? 'Partial public feed' : 'Needs server HTTPS'),
            'env' => 'ESPN summary / AEGIS_TEAM_STATS_API_KEY',
            'detail' => $scoreboardConnected ? 'Public scoreboard and summary data can auto-fill records, score context, leaders, and boxscore stats. True possession metrics like offensive rating, defensive rating, net rating, pace, eFG%, TS%, and usage need a richer stats provider.' : 'Needed for offensive rating, defensive rating, net rating, pace, strength of schedule, eFG%, TS%, turnover rate, rebound rate, and assist ratio.',
        ],
        [
            'name' => 'Player matchup and on/off data',
            'status' => $hasEnv(['AEGIS_PLAYER_TRACKING_API_KEY', 'AEGIS_PLAYER_PROPS_FEED_URL', 'AEGIS_SPORTRADAR_API_KEY']) ? 'Connected' : ($scoreboardConnected ? 'Partial public feed' : 'Needs server HTTPS'),
            'env' => 'ESPN summary / AEGIS_PLAYER_PROPS_FEED_URL',
            'detail' => $scoreboardConnected ? 'Public summaries can auto-fill player leaders and boxscore athletes, and Lineforge derives an internal overall from those stats. Defensive assignments, position mismatches, star matchup history, foul-prone defenders, and on/off impact still need tracking data.' : 'Needed for defensive assignments, position mismatches, star matchup history, foul-prone defenders, usage rate, and team performance with or without key players.',
        ],
        [
            'name' => 'Historical head-to-head and recent form',
            'status' => $hasEnv(['AEGIS_HISTORY_API_KEY', 'AEGIS_SPORTSDATA_API_KEY']) ? 'Connected' : ($scoreboardConnected ? 'Active public feed' : 'Needs server HTTPS'),
            'env' => 'ESPN scoreboard date ranges / AEGIS_HISTORY_API_KEY',
            'detail' => $scoreboardConnected ? 'Lineforge now scans public scoreboard date ranges to auto-build latest five-team form and recent head-to-head samples. Deep shooting trends, coaching adjustments, and exploit patterns can still be upgraded with a history provider.' : 'Needed for last 5-10 matchup history, recent shooting/defensive trends, coaching adjustments, and exploit patterns.',
        ],
        [
            'name' => 'Schedule, travel, and fatigue',
            'status' => $hasEnv(['AEGIS_SCHEDULE_API_KEY', 'AEGIS_TRAVEL_API_KEY']) ? 'Connected' : ($scoreboardConnected ? 'Partial public feed' : 'Needs server HTTPS'),
            'env' => 'ESPN history dates / AEGIS_SCHEDULE_API_KEY',
            'detail' => $scoreboardConnected ? 'Recent game dates are visible through the public history scan. Rest days, back-to-backs, 3 games in 4 nights, time-zone changes, travel distance, and altitude effects still need schedule/travel modeling.' : 'Needed for rest days, back-to-backs, 3 games in 4 nights, time-zone changes, travel distance, and altitude effects.',
        ],
        [
            'name' => 'Venue weather',
            'status' => $scoreboardConnected ? 'Active public feed' : 'Needs server HTTPS',
            'env' => 'Open-Meteo public forecast',
            'detail' => $scoreboardConnected ? 'Outdoor venue weather is pulled automatically from Open-Meteo when the scoreboard supplies city/state. Indoor venues are treated as minimal weather impact.' : 'Needed for outdoor NFL, MLB, MLS, and other weather-sensitive events.',
        ],
        [
            'name' => 'Referee tendencies',
            'status' => $hasEnv(['AEGIS_REFEREE_API_KEY', 'AEGIS_OFFICIALS_API_KEY']) ? 'Connected' : ($scoreboardConnected ? 'Crew names only' : 'Needs setup'),
            'env' => 'ESPN summary / AEGIS_REFEREE_API_KEY',
            'detail' => $scoreboardConnected ? 'Public event summaries can list the officiating crew, but foul-heavy referee, strike-zone, card, or penalty tendency history still needs a verified history provider.' : 'Needed for foul-heavy referee crews, umpire strike-zone tendency, card tendency, and penalty-rate modeling.',
        ],
        [
            'name' => 'Simulation and ML engine',
            'status' => 'Designed',
            'env' => 'Local model pipeline',
            'detail' => 'The feature schema is ready for XGBoost, LightGBM, Monte Carlo simulation, and real-time confidence updates once training data is connected.',
        ],
    ];
}

function aegis_sports_enrich_market_access(array $games, array $predictions, int $refreshSeconds, int $bucket): array
{
    $oddsIndex = aegis_sports_build_odds_index($games, $refreshSeconds);
    $kalshiMarkets = aegis_sports_fetch_kalshi_markets(max(300, $refreshSeconds * 8));
    $predictionsByGame = [];
    foreach ($predictions as $prediction) {
        $gameId = (string) ($prediction['gameId'] ?? '');
        if ($gameId !== '') {
            $predictionsByGame[$gameId] = $prediction;
        }
    }

    $availableLines = 0;
    $enrichedGames = [];
    foreach ($games as $index => $game) {
        $gameId = (string) ($game['id'] ?? '');
        $prediction = $predictionsByGame[$gameId] ?? aegis_sports_prediction_for_game($game, $index, $bucket, false);
        $links = aegis_sports_market_links_for_game($game, $prediction, $oddsIndex, $kalshiMarkets);
        $prediction = aegis_sports_apply_confidence_calibration($prediction, $game, $links, []);
        $links = aegis_sports_reprice_market_links($links, $prediction);
        $availableLines += count(array_filter($links, static fn(array $link): bool => !empty($link['available']) && (string) ($link['kind'] ?? '') === 'Sportsbook'));
        $best = aegis_sports_best_market_link($links);
        $fairProbability = aegis_sports_clamp(((float) ($prediction['confidenceValue'] ?? 58)) / 100, 0.01, 0.99);
        $prediction['fairProbability'] = number_format($fairProbability * 100, 1) . '%';
        $prediction['fairOdds'] = aegis_sports_probability_to_american($fairProbability);
        $prediction['marketLinks'] = $links;
        $prediction['bestBook'] = (string) ($best['title'] ?? 'Provider links');
        $prediction['bookLine'] = (string) ($best['line'] ?? ($prediction['pick'] ?? 'Market'));
        if ($best && (string) ($best['price'] ?? '--') !== '--') {
            $prediction['odds'] = (string) $best['price'];
            $prediction['edge'] = (string) ($best['modelEdge'] ?? $prediction['edge'] ?? '+0.0%');
            $prediction['expectedValue'] = aegis_sports_expected_value_from_edge((string) $prediction['edge']);
        }
        $prediction['teamComparison'] = is_array($prediction['teamComparison'] ?? null)
            ? $prediction['teamComparison']
            : aegis_sports_team_comparison($prediction, $game, []);
        $winnerProjection = aegis_sports_prediction_winner_projection($game, $prediction);
        $prediction['predictedWinner'] = (string) ($winnerProjection['label'] ?? 'Predicted winner');
        $prediction['predictedWinnerSide'] = (string) ($winnerProjection['side'] ?? 'market');
        $prediction['predictedWinnerBasis'] = (string) ($winnerProjection['basis'] ?? 'Model lean');
        $prediction['predictedWinnerStrength'] = (string) ($winnerProjection['strength'] ?? 'Lean');
        $prediction['breakdown'] = aegis_sports_prediction_breakdown($prediction, $game);
        $game['betLinks'] = $links;
        $game['bestLine'] = $best;
        $game['prediction'] = $prediction;
        $enrichedGames[] = $game;
    }

    $gamesById = [];
    foreach ($enrichedGames as $game) {
        $gamesById[(string) ($game['id'] ?? '')] = $game;
    }

    $enrichedPredictions = [];
    foreach ($predictions as $prediction) {
        $gameId = (string) ($prediction['gameId'] ?? '');
        $game = $gamesById[$gameId] ?? null;
        $links = is_array($game['betLinks'] ?? null) ? $game['betLinks'] : [];
        $best = aegis_sports_best_market_link($links);
        $fairProbability = aegis_sports_clamp(((float) ($prediction['confidenceValue'] ?? 58)) / 100, 0.01, 0.99);
        $prediction['fairProbability'] = number_format($fairProbability * 100, 1) . '%';
        $prediction['fairOdds'] = aegis_sports_probability_to_american($fairProbability);
        $prediction['marketLinks'] = $links;
        $prediction['bestBook'] = (string) ($best['title'] ?? 'Provider links');
        $prediction['bookLine'] = (string) ($best['line'] ?? ($prediction['pick'] ?? 'Market'));
        if ($best && (string) ($best['price'] ?? '--') !== '--') {
            $prediction['odds'] = (string) $best['price'];
            $prediction['edge'] = (string) ($best['modelEdge'] ?? $prediction['edge'] ?? '+0.0%');
            $prediction['expectedValue'] = aegis_sports_expected_value_from_edge((string) $prediction['edge']);
        }
        $autoContext = $game ? aegis_sports_auto_context_for_game($game, $refreshSeconds) : [];
        $prediction['autoContext'] = $autoContext;
        $prediction = aegis_sports_apply_confidence_calibration($prediction, $game ?: [], $links, $autoContext);
        $links = aegis_sports_reprice_market_links($links, $prediction);
        $best = aegis_sports_best_market_link($links);
        $prediction['marketLinks'] = $links;
        $prediction['bestBook'] = (string) ($best['title'] ?? 'Provider links');
        $prediction['bookLine'] = (string) ($best['line'] ?? ($prediction['pick'] ?? 'Market'));
        if ($best && (string) ($best['price'] ?? '--') !== '--') {
            $prediction['odds'] = (string) $best['price'];
            $prediction['edge'] = (string) ($best['modelEdge'] ?? $prediction['edge'] ?? '+0.0%');
            $prediction['expectedValue'] = aegis_sports_expected_value_from_edge((string) $prediction['edge']);
        }
        $prediction['teamComparison'] = aegis_sports_team_comparison($prediction, $game, $autoContext);
        $winnerProjection = $game ? aegis_sports_prediction_winner_projection($game, $prediction, $autoContext) : [];
        $prediction['predictedWinner'] = (string) ($winnerProjection['label'] ?? $prediction['predictedWinner'] ?? 'Predicted winner');
        $prediction['predictedWinnerSide'] = (string) ($winnerProjection['side'] ?? $prediction['predictedWinnerSide'] ?? 'market');
        $prediction['predictedWinnerBasis'] = (string) ($winnerProjection['basis'] ?? $prediction['predictedWinnerBasis'] ?? 'Model lean');
        $prediction['predictedWinnerStrength'] = (string) ($winnerProjection['strength'] ?? $prediction['predictedWinnerStrength'] ?? 'Lean');
        $prediction['breakdown'] = aegis_sports_prediction_breakdown($prediction, $game);

        $enrichedPredictions[] = $prediction;
    }

    $marketAccess = [
        'oddsProvider' => 'The Odds API',
        'oddsProviderConfigured' => aegis_sports_odds_api_key() !== '',
        'exchangeProvider' => 'Kalshi',
        'bookmakers' => count(array_filter(aegis_sports_bookmaker_catalog(), static fn(array $book): bool => ($book['kind'] ?? '') !== 'Prediction Exchange')),
        'availableLines' => $availableLines,
        'matchedEvents' => count($oddsIndex),
        'kalshiMarketsCached' => count($kalshiMarkets),
        'refreshCadence' => max(90, min(900, $refreshSeconds * 6)) . 's odds cache',
        'note' => 'Lineforge opens provider links for fast access and requires users to verify eligibility, location, and final prices before wagering.',
    ];

    return [
        'games' => $enrichedGames,
        'predictions' => $enrichedPredictions,
        'marketAccess' => $marketAccess,
        'arbitrage' => lineforge_arbitrage_build_state($enrichedGames, $oddsIndex, $marketAccess, $refreshSeconds),
    ];
}

function aegis_sports_bucket(int $refreshSeconds): int
{
    $refreshSeconds = max(5, $refreshSeconds);
    return (int) floor(time() / max(30, min(180, $refreshSeconds)));
}

function aegis_sports_format_line(float $value): string
{
    $precision = abs($value - round($value)) > 0.01 ? 1 : 0;
    $prefix = $value > 0 ? '+' : '';
    return $prefix . number_format($value, $precision);
}

function aegis_sports_resample(array $values, int $count, int $min = 18, int $max = 96): array
{
    $series = array_values(
        array_map(
            static fn($value): float => (float) $value,
            array_filter($values, static fn($value): bool => is_numeric($value))
        )
    );

    if ($count <= 0) {
        return [];
    }

    if (!$series) {
        return array_fill(0, $count, (int) round(($min + $max) / 2));
    }

    if (count($series) === 1) {
        return array_fill(0, $count, (int) round(($min + $max) / 2));
    }

    $sourceMin = min($series);
    $sourceMax = max($series);
    $range = max(0.0001, $sourceMax - $sourceMin);
    $resampled = [];

    for ($index = 0; $index < $count; $index += 1) {
        $position = ($count === 1) ? 0.0 : (($index / ($count - 1)) * (count($series) - 1));
        $left = (int) floor($position);
        $right = (int) ceil($position);
        $weight = $position - $left;
        $leftValue = $series[$left];
        $rightValue = $series[$right];
        $interpolated = $leftValue + (($rightValue - $leftValue) * $weight);
        $normalized = (($interpolated - $sourceMin) / $range);
        $resampled[] = (int) round($min + ($normalized * ($max - $min)));
    }

    return $resampled;
}

function aegis_sports_provider_leagues(): array
{
    return [
        ['key' => 'nba', 'sport' => 'basketball', 'league' => 'nba', 'label' => 'NBA', 'group' => 'Basketball', 'priority' => 100],
        ['key' => 'wnba', 'sport' => 'basketball', 'league' => 'wnba', 'label' => 'WNBA', 'group' => 'Basketball', 'priority' => 91],
        ['key' => 'ncaab', 'sport' => 'basketball', 'league' => 'mens-college-basketball', 'label' => 'NCAAB', 'group' => 'Basketball', 'priority' => 94],
        ['key' => 'ncaaw', 'sport' => 'basketball', 'league' => 'womens-college-basketball', 'label' => 'NCAAW', 'group' => 'Basketball', 'priority' => 82],
        ['key' => 'nfl', 'sport' => 'football', 'league' => 'nfl', 'label' => 'NFL', 'group' => 'Football', 'priority' => 100],
        ['key' => 'ncaaf', 'sport' => 'football', 'league' => 'college-football', 'label' => 'NCAAF', 'group' => 'Football', 'priority' => 95],
        ['key' => 'ufl', 'sport' => 'football', 'league' => 'ufl', 'label' => 'UFL', 'group' => 'Football', 'priority' => 72],
        ['key' => 'mlb', 'sport' => 'baseball', 'league' => 'mlb', 'label' => 'MLB', 'group' => 'Baseball', 'priority' => 100],
        ['key' => 'college-baseball', 'sport' => 'baseball', 'league' => 'college-baseball', 'label' => 'NCAA Baseball', 'group' => 'Baseball', 'priority' => 70],
        ['key' => 'college-softball', 'sport' => 'softball', 'league' => 'college-softball', 'label' => 'NCAA Softball', 'group' => 'Softball', 'priority' => 62],
        ['key' => 'nhl', 'sport' => 'hockey', 'league' => 'nhl', 'label' => 'NHL', 'group' => 'Hockey', 'priority' => 100],
        ['key' => 'ncaa-hockey', 'sport' => 'hockey', 'league' => 'mens-college-hockey', 'label' => 'NCAA Hockey', 'group' => 'Hockey', 'priority' => 62],
        ['key' => 'epl', 'sport' => 'soccer', 'league' => 'eng.1', 'label' => 'Premier League', 'group' => 'Soccer', 'priority' => 98],
        ['key' => 'laliga', 'sport' => 'soccer', 'league' => 'esp.1', 'label' => 'LaLiga', 'group' => 'Soccer', 'priority' => 90],
        ['key' => 'serie-a', 'sport' => 'soccer', 'league' => 'ita.1', 'label' => 'Serie A', 'group' => 'Soccer', 'priority' => 88],
        ['key' => 'bundesliga', 'sport' => 'soccer', 'league' => 'ger.1', 'label' => 'Bundesliga', 'group' => 'Soccer', 'priority' => 88],
        ['key' => 'ligue-1', 'sport' => 'soccer', 'league' => 'fra.1', 'label' => 'Ligue 1', 'group' => 'Soccer', 'priority' => 84],
        ['key' => 'mls', 'sport' => 'soccer', 'league' => 'usa.1', 'label' => 'MLS', 'group' => 'Soccer', 'priority' => 86],
        ['key' => 'nwsl', 'sport' => 'soccer', 'league' => 'usa.nwsl', 'label' => 'NWSL', 'group' => 'Soccer', 'priority' => 68],
        ['key' => 'liga-mx', 'sport' => 'soccer', 'league' => 'mex.1', 'label' => 'Liga MX', 'group' => 'Soccer', 'priority' => 80],
        ['key' => 'ucl', 'sport' => 'soccer', 'league' => 'uefa.champions', 'label' => 'Champions League', 'group' => 'Soccer', 'priority' => 92],
        ['key' => 'uel', 'sport' => 'soccer', 'league' => 'uefa.europa', 'label' => 'Europa League', 'group' => 'Soccer', 'priority' => 78],
        ['key' => 'ufc', 'sport' => 'mma', 'league' => 'ufc', 'label' => 'UFC', 'group' => 'Combat', 'priority' => 92],
        ['key' => 'boxing', 'sport' => 'boxing', 'league' => 'boxing', 'label' => 'Boxing', 'group' => 'Combat', 'priority' => 66],
        ['key' => 'atp', 'sport' => 'tennis', 'league' => 'atp', 'label' => 'ATP Tennis', 'group' => 'Tennis', 'priority' => 82],
        ['key' => 'wta', 'sport' => 'tennis', 'league' => 'wta', 'label' => 'WTA Tennis', 'group' => 'Tennis', 'priority' => 82],
        ['key' => 'pga', 'sport' => 'golf', 'league' => 'pga', 'label' => 'PGA Tour', 'group' => 'Golf', 'priority' => 72],
        ['key' => 'lpga', 'sport' => 'golf', 'league' => 'lpga', 'label' => 'LPGA', 'group' => 'Golf', 'priority' => 58],
        ['key' => 'f1', 'sport' => 'racing', 'league' => 'f1', 'label' => 'Formula 1', 'group' => 'Racing', 'priority' => 72],
        ['key' => 'nascar', 'sport' => 'racing', 'league' => 'nascar', 'label' => 'NASCAR', 'group' => 'Racing', 'priority' => 70],
        ['key' => 'indycar', 'sport' => 'racing', 'league' => 'indycar', 'label' => 'IndyCar', 'group' => 'Racing', 'priority' => 60],
        ['key' => 'ipl', 'sport' => 'cricket', 'league' => 'ipl', 'label' => 'IPL Cricket', 'group' => 'Cricket', 'priority' => 65],
        ['key' => 'icc', 'sport' => 'cricket', 'league' => 'icc', 'label' => 'ICC Cricket', 'group' => 'Cricket', 'priority' => 60],
        ['key' => 'rugby', 'sport' => 'rugby', 'league' => 'rugby-union', 'label' => 'Rugby Union', 'group' => 'Rugby', 'priority' => 54],
        ['key' => 'college-lacrosse', 'sport' => 'lacrosse', 'league' => 'college-lacrosse', 'label' => 'NCAA Lacrosse', 'group' => 'Lacrosse', 'priority' => 52],
        ['key' => 'ncaavb', 'sport' => 'volleyball', 'league' => 'womens-college-volleyball', 'label' => 'NCAA Volleyball', 'group' => 'Volleyball', 'priority' => 50],
        ['key' => 'lol', 'sport' => 'esports', 'league' => 'league-of-legends', 'label' => 'League of Legends', 'group' => 'Esports', 'priority' => 42],
        ['key' => 'valorant', 'sport' => 'esports', 'league' => 'valorant', 'label' => 'VALORANT', 'group' => 'Esports', 'priority' => 40],
    ];
}

function aegis_sports_active_provider_leagues(int $trackedGames, int $refreshSeconds): array
{
    $all = aegis_sports_provider_leagues();
    usort(
        $all,
        static fn(array $left, array $right): int => ((int) ($right['priority'] ?? 0)) <=> ((int) ($left['priority'] ?? 0))
    );

    $configured = (int) (getenv('AEGIS_SPORTS_MAX_LEAGUES_PER_REFRESH') ?: 0);
    $maxLeagues = $configured > 0
        ? $configured
        : ($trackedGames >= 80 ? 38 : ($trackedGames >= 35 ? 30 : 20));
    $maxLeagues = max(12, min(60, $maxLeagues));

    $core = array_values(array_filter($all, static fn(array $league): bool => (int) ($league['priority'] ?? 0) >= 82));
    $rotation = array_values(array_filter($all, static fn(array $league): bool => (int) ($league['priority'] ?? 0) < 82));
    $selected = array_slice($core, 0, min(count($core), $maxLeagues));
    $remainingSlots = $maxLeagues - count($selected);

    if ($remainingSlots > 0 && $rotation) {
        $offset = aegis_sports_bucket($refreshSeconds) % count($rotation);
        for ($index = 0; $index < $remainingSlots; $index += 1) {
            $selected[] = $rotation[($offset + $index) % count($rotation)];
        }
    }

    $seen = [];
    return array_values(array_filter(
        $selected,
        static function (array $league) use (&$seen): bool {
            $key = (string) ($league['key'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                return false;
            }

            $seen[$key] = true;
            return true;
        }
    ));
}

function aegis_sports_scoreboard_dates_window(): string
{
    $backfillDays = (int) (getenv('AEGIS_SPORTS_SCOREBOARD_BACKFILL_DAYS') ?: 1);
    $lookaheadDays = (int) (getenv('AEGIS_SPORTS_SCOREBOARD_LOOKAHEAD_DAYS') ?: 4);
    $backfillDays = max(0, min(7, $backfillDays));
    $lookaheadDays = max(1, min(21, $lookaheadDays));

    $start = gmdate('Ymd', time() - ($backfillDays * 86400));
    $end = gmdate('Ymd', time() + ($lookaheadDays * 86400));

    return $start . '-' . $end;
}

function aegis_sports_provider_url(array $league, string $dates = ''): string
{
    $url = 'https://site.api.espn.com/apis/site/v2/sports/'
        . rawurlencode((string) $league['sport'])
        . '/'
        . rawurlencode((string) $league['league'])
        . '/scoreboard?limit=100';

    if ($dates !== '') {
        $url .= '&dates=' . rawurlencode($dates);
    }

    return $url;
}

function aegis_sports_fetch_provider_board(array $league, int $ttlSeconds): ?array
{
    $dates = aegis_sports_scoreboard_dates_window();

    return aegis_remote_data_cached(
        'sports-feed',
        (string) ($league['key'] ?? 'league') . ':' . $dates,
        $ttlSeconds,
        static function () use ($league, $dates): ?array {
            $payload = aegis_remote_data_http_json(aegis_sports_provider_url($league, $dates), 3.0);
            if (!is_array($payload)) {
                return [
                    'fetchedAt' => gmdate('c'),
                    'events' => [],
                    'unavailable' => true,
                    'dates' => $dates,
                ];
            }

            return [
                'fetchedAt' => gmdate('c'),
                'events' => is_array($payload['events'] ?? null) ? $payload['events'] : [],
                'unavailable' => false,
                'dates' => $dates,
            ];
        }
    );
}

function aegis_sports_parse_team(array $competitors, string $homeAway): array
{
    foreach ($competitors as $competitor) {
        if (($competitor['homeAway'] ?? '') !== $homeAway) {
            continue;
        }

        $team = is_array($competitor['team'] ?? null) ? $competitor['team'] : [];
        $record = '';
        foreach (($competitor['records'] ?? []) as $recordItem) {
            if (!empty($recordItem['summary'])) {
                $record = (string) $recordItem['summary'];
                break;
            }
        }

        return [
            'id' => (string) ($team['id'] ?? ''),
            'uid' => (string) ($team['uid'] ?? ''),
            'name' => (string) ($team['displayName'] ?? $team['shortDisplayName'] ?? $team['name'] ?? $team['abbreviation'] ?? ucfirst($homeAway)),
            'abbr' => (string) ($team['abbreviation'] ?? strtoupper(substr((string) ($team['name'] ?? $homeAway), 0, 3))),
            'short' => (string) ($team['shortDisplayName'] ?? $team['abbreviation'] ?? ''),
            'displayName' => (string) ($team['displayName'] ?? ''),
            'score' => (int) ($competitor['score'] ?? 0),
            'logo' => (string) ($team['logo'] ?? ''),
            'record' => $record,
            'winner' => !empty($competitor['winner']),
            'linescores' => is_array($competitor['linescores'] ?? null) ? $competitor['linescores'] : [],
        ];
    }

    $fallbackIndex = $homeAway === 'away' ? 0 : 1;
    $competitor = is_array($competitors[$fallbackIndex] ?? null) ? $competitors[$fallbackIndex] : [];
    if ($competitor) {
        $team = is_array($competitor['team'] ?? null) ? $competitor['team'] : [];
        $athlete = is_array($competitor['athlete'] ?? null) ? $competitor['athlete'] : [];
        $name = (string) (
            $team['displayName']
            ?? $team['shortDisplayName']
            ?? $team['name']
            ?? $athlete['displayName']
            ?? $athlete['shortName']
            ?? $competitor['displayName']
            ?? ucfirst($homeAway)
        );
        $abbr = (string) ($team['abbreviation'] ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: $homeAway, 0, 3)));

        return [
            'id' => (string) ($team['id'] ?? $athlete['id'] ?? ''),
            'uid' => (string) ($team['uid'] ?? $athlete['uid'] ?? ''),
            'name' => $name,
            'abbr' => $abbr,
            'short' => (string) ($team['shortDisplayName'] ?? $abbr),
            'displayName' => (string) ($team['displayName'] ?? $name),
            'score' => (int) ($competitor['score'] ?? 0),
            'logo' => (string) ($team['logo'] ?? ''),
            'record' => '',
            'winner' => !empty($competitor['winner']),
            'linescores' => is_array($competitor['linescores'] ?? null) ? $competitor['linescores'] : [],
        ];
    }

    return [
        'id' => '',
        'uid' => '',
        'name' => ucfirst($homeAway),
        'abbr' => strtoupper(substr($homeAway, 0, 3)),
        'short' => strtoupper(substr($homeAway, 0, 3)),
        'displayName' => ucfirst($homeAway),
        'score' => 0,
        'logo' => '',
        'record' => '',
        'winner' => false,
        'linescores' => [],
    ];
}

function aegis_sports_status_meta(array $status, string $startTime): array
{
    $type = is_array($status['type'] ?? null) ? $status['type'] : [];
    $name = strtolower((string) ($type['name'] ?? ''));
    $state = strtolower((string) ($type['state'] ?? ''));
    $detail = trim((string) ($type['detail'] ?? ''));
    $shortDetail = trim((string) ($type['shortDetail'] ?? $detail));
    $description = trim((string) ($type['description'] ?? ''));

    if (
        str_contains($name, 'postponed')
        || str_contains($name, 'delayed')
        || str_contains(strtolower($detail), 'postponed')
        || str_contains(strtolower($detail), 'delayed')
        || str_contains(strtolower($detail), 'suspended')
    ) {
        return [
            'statusKey' => 'alert',
            'statusLabel' => $description !== '' ? $description : 'Alert',
            'statusTone' => 'alert',
            'clock' => $shortDetail !== '' ? $shortDetail : 'Status update',
            'detail' => $detail !== '' ? $detail : 'Provider flagged this event for follow-up.',
        ];
    }

    if (!empty($type['completed']) || $state === 'post') {
        return [
            'statusKey' => 'final',
            'statusLabel' => 'Final',
            'statusTone' => 'final',
            'clock' => $shortDetail !== '' ? $shortDetail : 'Final',
            'detail' => $detail !== '' ? $detail : 'Game complete.',
        ];
    }

    if ($state === 'in' || str_contains($name, 'in_progress') || str_contains($name, 'live')) {
        return [
            'statusKey' => 'live',
            'statusLabel' => 'Live',
            'statusTone' => 'live',
            'clock' => $shortDetail !== '' ? $shortDetail : 'Live',
            'detail' => $detail !== '' ? $detail : 'In progress.',
        ];
    }

    return [
        'statusKey' => 'scheduled',
        'statusLabel' => 'Upcoming',
        'statusTone' => 'scheduled',
        'clock' => aegis_remote_data_format_short_date($startTime),
        'detail' => $detail !== '' ? $detail : 'Scheduled to start soon.',
    ];
}

function aegis_sports_parse_spread(string $details): array
{
    $text = trim($details);
    if ($text === '') {
        return ['favoriteLine' => '--', 'otherLine' => '--', 'details' => ''];
    }

    if (preg_match('/([+-]\d+(?:\.\d+)?)/', $text, $matches)) {
        $line = (float) $matches[1];
        $opposite = -1 * $line;
        return [
            'favoriteLine' => $text,
            'otherLine' => aegis_sports_format_line($opposite),
            'details' => $text,
        ];
    }

    return ['favoriteLine' => $text, 'otherLine' => '--', 'details' => $text];
}

function aegis_sports_parse_total(array $odds): array
{
    $overUnder = $odds['overUnder'] ?? null;
    if (is_numeric($overUnder)) {
        $formatted = number_format((float) $overUnder, abs(((float) $overUnder) - round((float) $overUnder)) > 0.01 ? 1 : 0);
        return ['over' => 'O ' . $formatted, 'under' => 'U ' . $formatted];
    }

    return ['over' => '--', 'under' => '--'];
}

function aegis_sports_history_from_game(array $away, array $home): array
{
    $awayValues = [];
    foreach (($away['linescores'] ?? []) as $item) {
        $awayValues[] = (float) ($item['value'] ?? $item['displayValue'] ?? 0);
    }

    $homeValues = [];
    foreach (($home['linescores'] ?? []) as $item) {
        $homeValues[] = (float) ($item['value'] ?? $item['displayValue'] ?? 0);
    }

    $length = max(count($awayValues), count($homeValues));
    if ($length === 0) {
        $total = max(1, (int) (($away['score'] ?? 0) + ($home['score'] ?? 0)));
        return aegis_sports_resample([$total * 0.25, $total * 0.45, $total * 0.7, $total], 8, 40, 82);
    }

    $combinedTotals = [];
    $awayRunning = 0.0;
    $homeRunning = 0.0;
    for ($index = 0; $index < $length; $index += 1) {
        $awayRunning += $awayValues[$index] ?? 0;
        $homeRunning += $homeValues[$index] ?? 0;
        $combinedTotals[] = $awayRunning + $homeRunning;
    }

    return aegis_sports_resample($combinedTotals, 8, 32, 94);
}

function aegis_sports_record_parts(string $record): ?array
{
    if (preg_match('/(\d+)\s*[-–]\s*(\d+)/', $record, $matches) !== 1) {
        return null;
    }

    return [
        'wins' => (int) $matches[1],
        'losses' => (int) $matches[2],
    ];
}

function aegis_sports_record_pct(array $team): ?float
{
    $parts = aegis_sports_record_parts((string) ($team['record'] ?? ''));
    if (!$parts) {
        return null;
    }

    $games = $parts['wins'] + $parts['losses'];
    return $games > 0 ? $parts['wins'] / $games : null;
}

function aegis_sports_provider_summary_url(array $game): string
{
    $sport = trim((string) ($game['providerSport'] ?? ''));
    $league = trim((string) ($game['providerLeague'] ?? ''));
    $eventId = trim((string) ($game['id'] ?? ''));
    if ($sport === '' || $league === '' || $eventId === '' || str_starts_with($eventId, 'fallback-')) {
        return '';
    }

    return 'https://site.api.espn.com/apis/site/v2/sports/'
        . rawurlencode($sport)
        . '/'
        . rawurlencode($league)
        . '/summary?event='
        . rawurlencode($eventId);
}

function aegis_sports_fetch_event_summary(array $game, int $refreshSeconds): array
{
    $url = aegis_sports_provider_summary_url($game);
    if ($url === '') {
        return [];
    }

    $ttl = match ((string) ($game['statusKey'] ?? 'scheduled')) {
        'live' => max(20, min(90, $refreshSeconds)),
        'final' => 3600,
        default => max(300, min(1200, $refreshSeconds * 10)),
    };

    return aegis_remote_data_cached(
        'sports-summary',
        (string) ($game['providerSport'] ?? '') . ':' . (string) ($game['providerLeague'] ?? '') . ':' . (string) ($game['id'] ?? ''),
        $ttl,
        static function () use ($url): array {
            $payload = aegis_remote_data_http_json($url, 4.0);
            return is_array($payload) ? $payload : [];
        }
    ) ?? [];
}

function aegis_sports_fetch_history_board(array $game, int $refreshSeconds, int $daysBack = 120): array
{
    $sport = trim((string) ($game['providerSport'] ?? ''));
    $league = trim((string) ($game['providerLeague'] ?? ''));
    if ($sport === '' || $league === '') {
        return [];
    }

    $eventTime = strtotime((string) ($game['startTime'] ?? '')) ?: time();
    $endTime = min(time(), $eventTime > 0 ? $eventTime : time());
    $startDate = gmdate('Ymd', $endTime - (max(21, min(240, $daysBack)) * 86400));
    $endDate = gmdate('Ymd', $endTime);
    $url = 'https://site.api.espn.com/apis/site/v2/sports/'
        . rawurlencode($sport)
        . '/'
        . rawurlencode($league)
        . '/scoreboard?'
        . http_build_query([
            'dates' => $startDate . '-' . $endDate,
            'limit' => 500,
        ]);

    $board = aegis_remote_data_cached(
        'sports-history',
        $sport . ':' . $league . ':' . $startDate . ':' . $endDate,
        max(900, min(7200, $refreshSeconds * 30)),
        static function () use ($url): array {
            $payload = aegis_remote_data_http_json($url, 4.5);
            return [
                'fetchedAt' => gmdate('c'),
                'events' => is_array($payload['events'] ?? null) ? $payload['events'] : [],
            ];
        }
    ) ?? [];

    return is_array($board['events'] ?? null) ? $board['events'] : [];
}

function aegis_sports_raw_event_competitors(array $event): array
{
    $competition = is_array(($event['competitions'] ?? [])[0] ?? null) ? $event['competitions'][0] : [];
    $items = [];
    foreach ((array) ($competition['competitors'] ?? []) as $competitor) {
        if (!is_array($competitor)) {
            continue;
        }

        $team = is_array($competitor['team'] ?? null) ? $competitor['team'] : [];
        $name = (string) ($team['displayName'] ?? $team['shortDisplayName'] ?? $team['name'] ?? $team['abbreviation'] ?? '');
        $abbr = (string) ($team['abbreviation'] ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'TM', 0, 3)));
        $items[] = [
            'id' => (string) ($team['id'] ?? ''),
            'name' => $name,
            'displayName' => (string) ($team['displayName'] ?? $name),
            'short' => (string) ($team['shortDisplayName'] ?? $abbr),
            'abbr' => $abbr,
            'score' => (int) ($competitor['score'] ?? 0),
            'homeAway' => (string) ($competitor['homeAway'] ?? ''),
            'winner' => !empty($competitor['winner']),
        ];
    }

    return $items;
}

function aegis_sports_event_is_completed(array $event): bool
{
    $status = is_array(($event['status'] ?? null)) ? $event['status'] : [];
    $type = is_array($status['type'] ?? null) ? $status['type'] : [];
    if (!empty($type['completed'])) {
        return true;
    }

    $competition = is_array(($event['competitions'] ?? [])[0] ?? null) ? $event['competitions'][0] : [];
    $competitionStatus = is_array($competition['status'] ?? null) ? $competition['status'] : [];
    $competitionType = is_array($competitionStatus['type'] ?? null) ? $competitionStatus['type'] : [];
    return !empty($competitionType['completed']) || strtolower((string) ($competitionType['state'] ?? '')) === 'post';
}

function aegis_sports_team_matches_identity(array $candidate, array $team): bool
{
    $candidateId = trim((string) ($candidate['id'] ?? ''));
    $teamId = trim((string) ($team['id'] ?? ''));
    if ($candidateId !== '' && $teamId !== '' && $candidateId === $teamId) {
        return true;
    }

    $candidateText = implode(' ', array_filter([
        (string) ($candidate['name'] ?? ''),
        (string) ($candidate['displayName'] ?? ''),
        (string) ($candidate['shortDisplayName'] ?? ''),
        (string) ($candidate['abbreviation'] ?? ''),
        (string) ($candidate['short'] ?? ''),
        (string) ($candidate['abbr'] ?? ''),
    ]));

    return aegis_sports_alias_matches($candidateText, aegis_sports_team_aliases($team));
}

function aegis_sports_find_raw_competitor(array $competitors, array $team): ?array
{
    foreach ($competitors as $competitor) {
        if (is_array($competitor) && aegis_sports_team_matches_identity($competitor, $team)) {
            return $competitor;
        }
    }

    return null;
}

function aegis_sports_recent_item_from_competitor(array $event, array $competitors, array $matched): array
{
    $opponent = [];
    foreach ($competitors as $competitor) {
        if (!is_array($competitor)) {
            continue;
        }

        $sameId = (string) ($competitor['id'] ?? '') !== '' && (string) ($competitor['id'] ?? '') === (string) ($matched['id'] ?? '');
        $sameName = aegis_sports_normalize_name((string) ($competitor['name'] ?? '')) === aegis_sports_normalize_name((string) ($matched['name'] ?? ''));
        if (!$sameId && !$sameName) {
            $opponent = $competitor;
            break;
        }
    }

    $score = (int) ($matched['score'] ?? 0);
    $opponentScore = (int) ($opponent['score'] ?? 0);
    $timestamp = strtotime((string) ($event['date'] ?? '')) ?: 0;
    $opponentAbbr = (string) ($opponent['abbr'] ?? 'OPP');

    return [
        'date' => $timestamp > 0 ? gmdate('M j', $timestamp) : 'Recent',
        'timestamp' => $timestamp,
        'result' => !empty($matched['winner']) ? 'W' : 'L',
        'score' => (string) ($matched['abbr'] ?? 'TM') . ' ' . $score . ', ' . $opponentAbbr . ' ' . $opponentScore,
        'opponent' => $opponentAbbr,
        'margin' => $score - $opponentScore,
        'homeAway' => (string) ($matched['homeAway'] ?? ''),
    ];
}

function aegis_sports_recent_summary(array $items): array
{
    $items = array_values(array_filter($items, 'is_array'));
    $count = count($items);
    if ($count === 0) {
        return [
            'label' => 'No recent sample',
            'detail' => 'The public history scan did not find completed games for this team in the current cache window.',
            'wins' => 0,
            'losses' => 0,
            'avgMargin' => 0.0,
            'score' => 50,
        ];
    }

    $wins = count(array_filter($items, static fn(array $item): bool => (string) ($item['result'] ?? '') === 'W'));
    $losses = $count - $wins;
    $avgMargin = array_sum(array_map(static fn(array $item): float => (float) ($item['margin'] ?? 0), $items)) / max(1, $count);
    $score = (int) aegis_sports_clamp(50 + (($wins / max(1, $count)) - 0.5) * 24 + aegis_sports_clamp($avgMargin, -14, 14), 28, 76);

    return [
        'label' => $wins . '-' . $losses . ' last ' . $count,
        'detail' => 'Average margin ' . aegis_sports_signed($avgMargin, ' pts', 1) . ' across the public scoreboard history window.',
        'wins' => $wins,
        'losses' => $losses,
        'avgMargin' => round($avgMargin, 1),
        'score' => $score,
    ];
}

function aegis_sports_extract_history_context(array $game, array $events): array
{
    $recent = ['away' => [], 'home' => []];
    $h2h = [];
    $currentId = (string) ($game['id'] ?? '');

    foreach ($events as $event) {
        if (!is_array($event) || (string) ($event['id'] ?? '') === $currentId || !aegis_sports_event_is_completed($event)) {
            continue;
        }

        $competitors = aegis_sports_raw_event_competitors($event);
        if (!$competitors) {
            continue;
        }

        $awayMatch = aegis_sports_find_raw_competitor($competitors, (array) ($game['away'] ?? []));
        $homeMatch = aegis_sports_find_raw_competitor($competitors, (array) ($game['home'] ?? []));
        if ($awayMatch) {
            $recent['away'][] = aegis_sports_recent_item_from_competitor($event, $competitors, $awayMatch);
        }

        if ($homeMatch) {
            $recent['home'][] = aegis_sports_recent_item_from_competitor($event, $competitors, $homeMatch);
        }

        if ($awayMatch && $homeMatch) {
            $timestamp = strtotime((string) ($event['date'] ?? '')) ?: 0;
            $awayScore = (int) ($awayMatch['score'] ?? 0);
            $homeScore = (int) ($homeMatch['score'] ?? 0);
            $winnerSide = $awayScore === $homeScore ? 'push' : ($awayScore > $homeScore ? 'away' : 'home');
            $h2h[] = [
                'date' => $timestamp > 0 ? gmdate('M j', $timestamp) : 'H2H',
                'timestamp' => $timestamp,
                'label' => (string) ($awayMatch['abbr'] ?? 'AWY') . ' ' . $awayScore . ' - ' . (string) ($homeMatch['abbr'] ?? 'HME') . ' ' . $homeScore,
                'winnerSide' => $winnerSide,
                'margin' => $winnerSide === 'away' ? $awayScore - $homeScore : $homeScore - $awayScore,
            ];
        }
    }

    foreach (['away', 'home'] as $side) {
        usort($recent[$side], static fn(array $left, array $right): int => ((int) ($right['timestamp'] ?? 0)) <=> ((int) ($left['timestamp'] ?? 0)));
        $recent[$side] = array_slice($recent[$side], 0, 5);
    }

    usort($h2h, static fn(array $left, array $right): int => ((int) ($right['timestamp'] ?? 0)) <=> ((int) ($left['timestamp'] ?? 0)));
    $h2h = array_slice($h2h, 0, 5);
    $awayH2hWins = count(array_filter($h2h, static fn(array $item): bool => (string) ($item['winnerSide'] ?? '') === 'away'));
    $homeH2hWins = count(array_filter($h2h, static fn(array $item): bool => (string) ($item['winnerSide'] ?? '') === 'home'));

    return [
        'recent' => $recent,
        'recentSummary' => [
            'away' => aegis_sports_recent_summary($recent['away']),
            'home' => aegis_sports_recent_summary($recent['home']),
        ],
        'h2h' => [
            'games' => $h2h,
            'count' => count($h2h),
            'awayWins' => $awayH2hWins,
            'homeWins' => $homeH2hWins,
            'label' => count($h2h) > 0 ? $awayH2hWins . '-' . $homeH2hWins . ' last ' . count($h2h) : 'No recent H2H',
        ],
    ];
}

function aegis_sports_context_side_for_team(array $game, array $team): ?string
{
    if (aegis_sports_team_matches_identity($team, (array) ($game['away'] ?? []))) {
        return 'away';
    }

    if (aegis_sports_team_matches_identity($team, (array) ($game['home'] ?? []))) {
        return 'home';
    }

    return null;
}

function aegis_sports_player_stat_weight(string $label): float
{
    $label = strtoupper(trim($label));
    return match ($label) {
        'PTS', 'POINTS' => 0.62,
        'REB', 'REBOUNDS' => 1.05,
        'AST', 'ASSISTS' => 1.15,
        'STL', 'STEALS', 'BLK', 'BLOCKS' => 1.85,
        'MIN', 'MINUTES' => 0.12,
        default => 0.55,
    };
}

function aegis_sports_store_player_signal(array &$players, string $side, array $athlete, string $label, string $displayValue, float $value, string $summary = ''): void
{
    if (!isset($players[$side]) || !in_array($side, ['away', 'home'], true)) {
        return;
    }

    $name = (string) ($athlete['displayName'] ?? $athlete['fullName'] ?? $athlete['shortName'] ?? '');
    if ($name === '') {
        return;
    }

    $key = (string) ($athlete['id'] ?? aegis_sports_normalize_name($name));
    if ($key === '') {
        $key = aegis_sports_normalize_name($name);
    }

    if (!isset($players[$side][$key])) {
        $position = is_array($athlete['position'] ?? null) ? (string) ($athlete['position']['abbreviation'] ?? $athlete['position']['displayName'] ?? '') : '';
        $players[$side][$key] = [
            'name' => $name,
            'position' => $position,
            'headshot' => (string) ($athlete['headshot']['href'] ?? ''),
            'signals' => [],
            'score' => 0.0,
        ];
    }

    $shortLabel = trim($label) !== '' ? trim($label) : 'STAT';
    $players[$side][$key]['signals'][] = [
        'label' => $shortLabel,
        'value' => $displayValue !== '' ? $displayValue : number_format($value, abs($value - round($value)) > 0.01 ? 1 : 0),
        'summary' => $summary,
    ];
    $players[$side][$key]['score'] += min(18.0, max(1.0, $value * aegis_sports_player_stat_weight($shortLabel)));
}

function aegis_sports_finalize_player_signals(array $players): array
{
    $final = ['away' => [], 'home' => []];
    foreach (['away', 'home'] as $side) {
        $items = [];
        foreach ((array) ($players[$side] ?? []) as $player) {
            if (!is_array($player)) {
                continue;
            }

            $seen = [];
            $stats = [];
            foreach ((array) ($player['signals'] ?? []) as $signal) {
                if (!is_array($signal)) {
                    continue;
                }

                $key = strtoupper((string) ($signal['label'] ?? ''));
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $stats[] = [
                    'label' => (string) ($signal['label'] ?? 'STAT'),
                    'value' => (string) ($signal['value'] ?? ''),
                ];
                if (count($stats) >= 4) {
                    break;
                }
            }

            $overall = (int) aegis_sports_clamp(round(52 + min(42, (float) ($player['score'] ?? 0))), 52, 96);
            $items[] = [
                'name' => (string) ($player['name'] ?? 'Player'),
                'position' => (string) ($player['position'] ?? ''),
                'headshot' => (string) ($player['headshot'] ?? ''),
                'overall' => $overall,
                'stats' => $stats,
            ];
        }

        usort($items, static fn(array $left, array $right): int => ((int) ($right['overall'] ?? 0)) <=> ((int) ($left['overall'] ?? 0)));
        $final[$side] = array_slice($items, 0, 3);
    }

    return $final;
}

function aegis_sports_parse_summary_players(array $summary, array $game): array
{
    $players = ['away' => [], 'home' => []];

    foreach ((array) ($summary['leaders'] ?? []) as $teamLeaders) {
        if (!is_array($teamLeaders)) {
            continue;
        }

        $team = is_array($teamLeaders['team'] ?? null) ? $teamLeaders['team'] : [];
        $side = aegis_sports_context_side_for_team($game, $team);
        if ($side === null) {
            continue;
        }

        foreach ((array) ($teamLeaders['leaders'] ?? []) as $category) {
            if (!is_array($category)) {
                continue;
            }

            foreach (array_slice((array) ($category['leaders'] ?? []), 0, 2) as $leader) {
                if (!is_array($leader)) {
                    continue;
                }

                $athlete = is_array($leader['athlete'] ?? null) ? $leader['athlete'] : [];
                $mainStat = is_array($leader['mainStat'] ?? null) ? $leader['mainStat'] : [];
                $label = (string) ($mainStat['label'] ?? $category['displayName'] ?? $category['name'] ?? 'Stat');
                $displayValue = (string) ($mainStat['value'] ?? $leader['displayValue'] ?? '');
                $value = is_numeric($leader['value'] ?? null) ? (float) $leader['value'] : (float) preg_replace('/[^0-9.\-]/', '', $displayValue);
                aegis_sports_store_player_signal($players, $side, $athlete, $label, $displayValue, $value, (string) ($leader['summary'] ?? ''));
            }
        }
    }

    foreach ((array) ($summary['boxscore']['players'] ?? []) as $teamBox) {
        if (!is_array($teamBox)) {
            continue;
        }

        $team = is_array($teamBox['team'] ?? null) ? $teamBox['team'] : [];
        $side = aegis_sports_context_side_for_team($game, $team);
        if ($side === null) {
            continue;
        }

        foreach ((array) ($teamBox['statistics'] ?? []) as $category) {
            if (!is_array($category)) {
                continue;
            }

            $labels = array_values(array_map('strval', (array) ($category['labels'] ?? [])));
            foreach ((array) ($category['athletes'] ?? []) as $athleteRow) {
                if (!is_array($athleteRow) || !empty($athleteRow['didNotPlay'])) {
                    continue;
                }

                $athlete = is_array($athleteRow['athlete'] ?? null) ? $athleteRow['athlete'] : [];
                $stats = array_values((array) ($athleteRow['stats'] ?? []));
                foreach ($labels as $index => $label) {
                    $short = strtoupper(trim($label));
                    if (!in_array($short, ['PTS', 'REB', 'AST', 'STL', 'BLK', 'MIN'], true)) {
                        continue;
                    }

                    $displayValue = (string) ($stats[$index] ?? '');
                    $numeric = (float) preg_replace('/[^0-9.\-]/', '', $displayValue);
                    if ($displayValue === '' || $numeric <= 0) {
                        continue;
                    }

                    aegis_sports_store_player_signal($players, $side, $athlete, $short, $displayValue, $numeric);
                }
            }
        }
    }

    return aegis_sports_finalize_player_signals($players);
}

function aegis_sports_parse_summary_injuries(array $summary, array $game): array
{
    $injuries = ['away' => [], 'home' => []];
    foreach ((array) ($summary['injuries'] ?? []) as $teamInjuries) {
        if (!is_array($teamInjuries)) {
            continue;
        }

        $team = is_array($teamInjuries['team'] ?? null) ? $teamInjuries['team'] : [];
        $side = aegis_sports_context_side_for_team($game, $team);
        if ($side === null) {
            continue;
        }

        foreach ((array) ($teamInjuries['injuries'] ?? []) as $injury) {
            if (!is_array($injury)) {
                continue;
            }

            $athlete = is_array($injury['athlete'] ?? null) ? $injury['athlete'] : [];
            $details = is_array($injury['details'] ?? null) ? $injury['details'] : [];
            $detailBits = array_filter([
                (string) ($details['type'] ?? ''),
                (string) ($details['detail'] ?? ''),
                (string) ($details['returnDate'] ?? ''),
            ]);
            $injuries[$side][] = [
                'name' => (string) ($athlete['displayName'] ?? $athlete['fullName'] ?? 'Player'),
                'status' => (string) ($injury['status'] ?? $injury['type']['description'] ?? 'Injury'),
                'detail' => implode(' / ', $detailBits),
            ];
            if (count($injuries[$side]) >= 4) {
                break;
            }
        }
    }

    return $injuries;
}

function aegis_sports_parse_summary_officials(array $summary): array
{
    $officials = [];
    foreach ((array) ($summary['gameInfo']['officials'] ?? []) as $official) {
        if (!is_array($official)) {
            continue;
        }

        $position = is_array($official['position'] ?? null) ? $official['position'] : [];
        $name = trim((string) ($official['displayName'] ?? $official['fullName'] ?? ''));
        if ($name === '') {
            continue;
        }

        $officials[] = [
            'name' => $name,
            'role' => (string) ($position['displayName'] ?? $position['name'] ?? 'Official'),
        ];

        if (count($officials) >= 8) {
            break;
        }
    }

    return [
        'available' => $officials !== [],
        'count' => count($officials),
        'names' => array_map(static fn(array $official): string => trim($official['name'] . ' ' . ($official['role'] !== '' ? '(' . $official['role'] . ')' : '')), $officials),
        'status' => $officials ? 'Officials listed' : 'Officials pending',
        'detail' => $officials
            ? 'Public event summary lists the crew, but Lineforge still needs a referee-tendency history source for foul rate, strike-zone, card, or penalty patterns.'
            : 'The public event summary did not list officials for this event yet.',
    ];
}

function aegis_sports_venue_query(array $game): string
{
    return trim(implode(' ', array_filter([
        (string) ($game['venueCity'] ?? ''),
        (string) ($game['venueState'] ?? ''),
        (string) ($game['venueCountry'] ?? ''),
    ])));
}

function aegis_sports_country_code_for_weather(array $game): string
{
    $country = strtolower(trim((string) ($game['venueCountry'] ?? '')));
    if (in_array($country, ['usa', 'us', 'united states', 'united states of america'], true)) {
        return 'US';
    }

    if (in_array($country, ['canada', 'ca'], true)) {
        return 'CA';
    }

    $state = trim((string) ($game['venueState'] ?? ''));
    if ($state !== '') {
        return 'US';
    }

    return '';
}

function aegis_sports_weather_city(array $game): string
{
    $city = trim((string) ($game['venueCity'] ?? ''));
    if ($city === '') {
        return '';
    }

    $parts = preg_split('/,/', $city) ?: [$city];
    return trim((string) ($parts[0] ?? $city));
}

function aegis_sports_weather_admin_hint(array $game): string
{
    $state = trim((string) ($game['venueState'] ?? ''));
    if ($state !== '') {
        return $state;
    }

    $city = trim((string) ($game['venueCity'] ?? ''));
    $parts = preg_split('/,/', $city) ?: [];
    return trim((string) ($parts[1] ?? ''));
}

function aegis_sports_weather_admin_aliases(string $hint): array
{
    $hint = trim($hint);
    if ($hint === '') {
        return [];
    }

    $states = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California',
        'CO' => 'Colorado', 'CT' => 'Connecticut', 'DC' => 'District of Columbia', 'DE' => 'Delaware',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'IA' => 'Iowa', 'ID' => 'Idaho',
        'IL' => 'Illinois', 'IN' => 'Indiana', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana',
        'MA' => 'Massachusetts', 'MD' => 'Maryland', 'ME' => 'Maine', 'MI' => 'Michigan', 'MN' => 'Minnesota',
        'MO' => 'Missouri', 'MS' => 'Mississippi', 'MT' => 'Montana', 'NC' => 'North Carolina',
        'ND' => 'North Dakota', 'NE' => 'Nebraska', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey',
        'NM' => 'New Mexico', 'NV' => 'Nevada', 'NY' => 'New York', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VA' => 'Virginia',
        'VT' => 'Vermont', 'WA' => 'Washington', 'WI' => 'Wisconsin', 'WV' => 'West Virginia', 'WY' => 'Wyoming',
    ];

    $upper = strtoupper($hint);
    $aliases = [$hint];
    if (isset($states[$upper])) {
        $aliases[] = $states[$upper];
    }

    return array_values(array_unique($aliases));
}

function aegis_sports_fetch_geocode(string $query, int $ttlSeconds, string $countryCode = '', string $adminHint = ''): ?array
{
    $query = trim($query);
    if ($query === '') {
        return null;
    }

    $params = [
        'name' => $query,
        'count' => 10,
        'language' => 'en',
        'format' => 'json',
    ];
    if ($countryCode !== '') {
        $params['countryCode'] = strtoupper($countryCode);
    }

    $url = 'https://geocoding-api.open-meteo.com/v1/search?' . http_build_query($params);

    $data = aegis_remote_data_cached(
        'sports-weather-geocode',
        $query . ':' . strtoupper($countryCode) . ':' . $adminHint,
        max(86400, $ttlSeconds),
        static function () use ($url): array {
            $payload = aegis_remote_data_http_json($url, 4.0);
            return is_array($payload) ? $payload : [];
        }
    ) ?? [];

    $results = array_values(array_filter((array) ($data['results'] ?? []), 'is_array'));
    $adminAliases = aegis_sports_weather_admin_aliases($adminHint);
    $result = null;
    foreach ($results as $candidate) {
        if (!$adminAliases) {
            $result = $candidate;
            break;
        }

        $admin = (string) ($candidate['admin1'] ?? '');
        foreach ($adminAliases as $alias) {
            if ($alias !== '' && aegis_sports_alias_matches($admin, [aegis_sports_normalize_name($alias)])) {
                $result = $candidate;
                break 2;
            }
        }
    }

    $result = $result ?? (is_array($results[0] ?? null) ? $results[0] : null);
    if (!$result || !is_numeric($result['latitude'] ?? null) || !is_numeric($result['longitude'] ?? null)) {
        return null;
    }

    return [
        'name' => (string) ($result['name'] ?? $query),
        'admin1' => (string) ($result['admin1'] ?? ''),
        'country' => (string) ($result['country'] ?? ''),
        'latitude' => (float) $result['latitude'],
        'longitude' => (float) $result['longitude'],
        'timezone' => (string) ($result['timezone'] ?? 'auto'),
    ];
}

function aegis_sports_weather_code_label($code): string
{
    $code = is_numeric($code) ? (int) $code : -1;
    return match (true) {
        $code === 0 => 'Clear',
        in_array($code, [1, 2, 3], true) => 'Clouds',
        in_array($code, [45, 48], true) => 'Fog',
        in_array($code, [51, 53, 55, 56, 57], true) => 'Drizzle',
        in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true) => 'Rain',
        in_array($code, [71, 73, 75, 77, 85, 86], true) => 'Snow',
        in_array($code, [95, 96, 99], true) => 'Storms',
        default => 'Weather',
    };
}

function aegis_sports_weather_pick_hour(array $hourly, string $startTime): int
{
    $times = array_values((array) ($hourly['time'] ?? []));
    if (!$times) {
        return -1;
    }

    $target = strtotime($startTime) ?: time();
    $bestIndex = 0;
    $bestDelta = PHP_INT_MAX;
    foreach ($times as $index => $time) {
        $timestamp = strtotime((string) $time);
        if (!$timestamp) {
            continue;
        }

        $delta = abs($timestamp - $target);
        if ($delta < $bestDelta) {
            $bestDelta = $delta;
            $bestIndex = (int) $index;
        }
    }

    return $bestIndex;
}

function aegis_sports_hourly_value(array $hourly, string $key, int $index): ?float
{
    $values = array_values((array) ($hourly[$key] ?? []));
    return is_numeric($values[$index] ?? null) ? (float) $values[$index] : null;
}

function aegis_sports_fetch_venue_weather(array $game, int $refreshSeconds): array
{
    $indoor = $game['venueIndoor'] ?? null;
    if ($indoor === true) {
        return [
            'available' => true,
            'status' => 'Indoor venue',
            'value' => 'Indoor',
            'impact' => 0,
            'source' => 'Venue metadata',
            'detail' => 'Venue is marked indoor by the scoreboard feed, so weather is treated as a minimal external factor.',
        ];
    }

    $query = aegis_sports_weather_city($game);
    if ($query === '') {
        return [
            'available' => false,
            'status' => 'Venue location pending',
            'value' => 'Weather pending',
            'impact' => 0,
            'source' => 'Open-Meteo',
            'detail' => 'The scoreboard feed did not include enough venue location data to request weather automatically.',
        ];
    }

    $geo = aegis_sports_fetch_geocode($query, 604800, aegis_sports_country_code_for_weather($game), aegis_sports_weather_admin_hint($game));
    if (!$geo) {
        return [
            'available' => false,
            'status' => 'Geocode pending',
            'value' => 'Weather pending',
            'impact' => 0,
            'source' => 'Open-Meteo geocoding',
            'detail' => 'Open-Meteo could not resolve the venue city/state from the public feed yet.',
        ];
    }

    $url = 'https://api.open-meteo.com/v1/forecast?'
        . 'latitude=' . rawurlencode((string) $geo['latitude'])
        . '&longitude=' . rawurlencode((string) $geo['longitude'])
        . '&current=temperature_2m,apparent_temperature,precipitation,rain,snowfall,weather_code,cloud_cover,wind_speed_10m,wind_gusts_10m'
        . '&hourly=temperature_2m,precipitation_probability,precipitation,weather_code,wind_speed_10m,wind_gusts_10m'
        . '&forecast_days=3'
        . '&temperature_unit=fahrenheit'
        . '&wind_speed_unit=mph'
        . '&precipitation_unit=inch'
        . '&timezone=auto';

    $weather = aegis_remote_data_cached(
        'sports-weather',
        'forecast-v2:' . number_format((float) $geo['latitude'], 3) . ':' . number_format((float) $geo['longitude'], 3) . ':' . gmdate('YmdH'),
        max(900, min(3600, $refreshSeconds * 20)),
        static function () use ($url): array {
            $payload = aegis_remote_data_http_json($url, 4.5);
            return is_array($payload) ? $payload : [];
        }
    ) ?? [];

    if (!$weather) {
        return [
            'available' => false,
            'status' => 'Weather unavailable',
            'value' => 'Weather pending',
            'impact' => 0,
            'source' => 'Open-Meteo',
            'detail' => 'The weather endpoint did not return a usable forecast for this venue.',
        ];
    }

    $hourIndex = aegis_sports_weather_pick_hour((array) ($weather['hourly'] ?? []), (string) ($game['startTime'] ?? ''));
    $hourly = (array) ($weather['hourly'] ?? []);
    $current = (array) ($weather['current'] ?? []);
    $temperature = $hourIndex >= 0 ? aegis_sports_hourly_value($hourly, 'temperature_2m', $hourIndex) : null;
    $precipProbability = $hourIndex >= 0 ? aegis_sports_hourly_value($hourly, 'precipitation_probability', $hourIndex) : null;
    $precip = $hourIndex >= 0 ? aegis_sports_hourly_value($hourly, 'precipitation', $hourIndex) : null;
    $wind = $hourIndex >= 0 ? aegis_sports_hourly_value($hourly, 'wind_speed_10m', $hourIndex) : null;
    $gust = $hourIndex >= 0 ? aegis_sports_hourly_value($hourly, 'wind_gusts_10m', $hourIndex) : null;
    $code = $hourIndex >= 0 ? aegis_sports_hourly_value($hourly, 'weather_code', $hourIndex) : null;

    $temperature = $temperature ?? (is_numeric($current['temperature_2m'] ?? null) ? (float) $current['temperature_2m'] : null);
    $wind = $wind ?? (is_numeric($current['wind_speed_10m'] ?? null) ? (float) $current['wind_speed_10m'] : null);
    $gust = $gust ?? (is_numeric($current['wind_gusts_10m'] ?? null) ? (float) $current['wind_gusts_10m'] : null);
    $precip = $precip ?? (is_numeric($current['precipitation'] ?? null) ? (float) $current['precipitation'] : null);
    $code = $code ?? (is_numeric($current['weather_code'] ?? null) ? (float) $current['weather_code'] : null);

    $impact = 0;
    if (($wind ?? 0) >= 18 || ($gust ?? 0) >= 28) {
        $impact -= 2;
    }
    if (($precipProbability ?? 0) >= 45 || ($precip ?? 0) >= 0.03) {
        $impact -= 2;
    }
    if ($temperature !== null && ($temperature <= 35 || $temperature >= 92)) {
        $impact -= 1;
    }

    $parts = [];
    if ($temperature !== null) {
        $parts[] = number_format($temperature, 0) . 'F';
    }
    if ($wind !== null) {
        $parts[] = number_format($wind, 0) . ' mph wind';
    }
    if ($precipProbability !== null) {
        $parts[] = number_format($precipProbability, 0) . '% precip';
    } elseif ($precip !== null && $precip > 0) {
        $parts[] = number_format($precip, 2) . ' in precip';
    }

    $location = trim((string) ($geo['name'] ?? '') . ((string) ($geo['admin1'] ?? '') !== '' ? ', ' . (string) $geo['admin1'] : ''));

    return [
        'available' => true,
        'status' => $impact < 0 ? 'Weather risk' : 'Weather attached',
        'value' => ($parts ? implode(' / ', $parts) : 'Forecast attached'),
        'impact' => $impact,
        'source' => 'Open-Meteo public forecast',
        'location' => $location,
        'condition' => aegis_sports_weather_code_label($code),
        'detail' => 'Outdoor venue weather is pulled automatically from Open-Meteo using the venue city/state supplied by the scoreboard feed.',
    ];
}

function aegis_sports_auto_context_for_game(array $game, int $refreshSeconds): array
{
    $summary = aegis_sports_fetch_event_summary($game, $refreshSeconds);
    $historyEvents = aegis_sports_fetch_history_board($game, $refreshSeconds);
    $history = aegis_sports_extract_history_context($game, $historyEvents);
    $players = $summary ? aegis_sports_parse_summary_players($summary, $game) : ['away' => [], 'home' => []];
    $injuries = $summary ? aegis_sports_parse_summary_injuries($summary, $game) : ['away' => [], 'home' => []];
    $officials = $summary ? aegis_sports_parse_summary_officials($summary) : aegis_sports_parse_summary_officials([]);
    $weather = aegis_sports_fetch_venue_weather($game, $refreshSeconds);

    return array_merge($history, [
        'source' => 'ESPN public summary and scoreboard history',
        'summaryUrl' => aegis_sports_provider_summary_url($game),
        'summaryAvailable' => $summary !== [],
        'historyAvailable' => $historyEvents !== [],
        'boxscoreAvailable' => is_array($summary['boxscore'] ?? null),
        'leadersAvailable' => is_array($summary['leaders'] ?? null),
        'injuryAvailable' => is_array($summary['injuries'] ?? null),
        'againstSpreadAvailable' => is_array($summary['againstTheSpread'] ?? null),
        'players' => $players,
        'injuries' => $injuries,
        'officials' => $officials,
        'weather' => $weather,
    ]);
}

function aegis_sports_top_player_label(array $players): string
{
    $top = is_array($players[0] ?? null) ? $players[0] : null;
    if (!$top) {
        return 'No public leader';
    }

    $stats = array_map(
        static fn(array $stat): string => trim((string) ($stat['label'] ?? '') . ' ' . (string) ($stat['value'] ?? '')),
        array_slice((array) ($top['stats'] ?? []), 0, 2)
    );
    $detail = implode(', ', array_filter($stats));
    return (string) ($top['name'] ?? 'Player') . ' ' . (int) ($top['overall'] ?? 0) . '/100' . ($detail !== '' ? ' (' . $detail . ')' : '');
}

function aegis_sports_injury_label(array $items): string
{
    $count = count($items);
    if ($count === 0) {
        return 'No listed injuries';
    }

    $first = is_array($items[0] ?? null) ? $items[0] : [];
    return $count . ' listed, ' . (string) ($first['name'] ?? 'player') . ' ' . (string) ($first['status'] ?? '');
}

function aegis_sports_team_rating_for_side(string $side, array $game, array $context): int
{
    $team = (array) ($game[$side] ?? []);
    $rating = 50.0;
    $recordPct = aegis_sports_record_pct($team);
    if ($recordPct !== null) {
        $rating += ($recordPct - 0.5) * 42;
    }

    $recent = is_array($context['recentSummary'][$side] ?? null) ? $context['recentSummary'][$side] : [];
    if ((int) ($recent['wins'] ?? 0) + (int) ($recent['losses'] ?? 0) > 0) {
        $games = max(1, (int) ($recent['wins'] ?? 0) + (int) ($recent['losses'] ?? 0));
        $rating += (((int) ($recent['wins'] ?? 0) / $games) - 0.5) * 20;
        $rating += aegis_sports_clamp((float) ($recent['avgMargin'] ?? 0), -10, 10) * 0.55;
    }

    $topPlayer = is_array(($context['players'][$side] ?? [])[0] ?? null) ? $context['players'][$side][0] : null;
    if ($topPlayer) {
        $rating += ((int) ($topPlayer['overall'] ?? 68) - 68) * 0.18;
    }

    $rating += $side === 'home' ? 2.0 : -1.0;
    if (in_array((string) ($game['statusKey'] ?? ''), ['live', 'final'], true)) {
        $scoreMargin = ((int) ($game[$side]['score'] ?? 0)) - ((int) ($game[$side === 'away' ? 'home' : 'away']['score'] ?? 0));
        $rating += aegis_sports_clamp($scoreMargin * 0.45, -8, 8);
    }

    $rating -= min(8, count((array) ($context['injuries'][$side] ?? [])) * 2);

    return (int) aegis_sports_clamp(round($rating), 35, 96);
}

function aegis_sports_edge_label(?float $awayValue, ?float $homeValue, array $away, array $home, float $threshold = 0.01): string
{
    if ($awayValue === null || $homeValue === null || abs($awayValue - $homeValue) <= $threshold) {
        return 'Even';
    }

    return ($awayValue > $homeValue ? (string) ($away['abbr'] ?? 'Away') : (string) ($home['abbr'] ?? 'Home')) . ' edge';
}

function aegis_sports_team_comparison(array $prediction, ?array $game, array $context): array
{
    if (!$game) {
        return [
            'away' => ['name' => 'Away', 'abbr' => 'AWY', 'rating' => 50, 'probability' => '50%'],
            'home' => ['name' => 'Home', 'abbr' => 'HME', 'rating' => 50, 'probability' => '50%'],
            'rows' => [],
            'signals' => ['h2hCount' => 0, 'recentCount' => 0, 'playerCount' => 0, 'injuryCount' => 0],
        ];
    }

    $away = (array) ($game['away'] ?? []);
    $home = (array) ($game['home'] ?? []);
    $confidence = (int) ($prediction['confidenceValue'] ?? 50);
    $pickSide = aegis_sports_pick_side($game, $prediction);
    $awayProbability = $pickSide === 'away' ? $confidence : ($pickSide === 'home' ? 100 - $confidence : 50);
    $homeProbability = $pickSide === 'home' ? $confidence : ($pickSide === 'away' ? 100 - $confidence : 50);
    $awayRating = aegis_sports_team_rating_for_side('away', $game, $context);
    $homeRating = aegis_sports_team_rating_for_side('home', $game, $context);
    $awayRecordPct = aegis_sports_record_pct($away);
    $homeRecordPct = aegis_sports_record_pct($home);
    $awayRecent = is_array($context['recentSummary']['away'] ?? null) ? $context['recentSummary']['away'] : aegis_sports_recent_summary([]);
    $homeRecent = is_array($context['recentSummary']['home'] ?? null) ? $context['recentSummary']['home'] : aegis_sports_recent_summary([]);
    $h2h = is_array($context['h2h'] ?? null) ? $context['h2h'] : ['count' => 0, 'label' => 'No recent H2H', 'awayWins' => 0, 'homeWins' => 0];
    $awayPlayers = (array) ($context['players']['away'] ?? []);
    $homePlayers = (array) ($context['players']['home'] ?? []);
    $awayInjuries = (array) ($context['injuries']['away'] ?? []);
    $homeInjuries = (array) ($context['injuries']['home'] ?? []);
    $weather = is_array($context['weather'] ?? null) ? $context['weather'] : [];
    $officials = is_array($context['officials'] ?? null) ? $context['officials'] : [];
    $spread = (string) ($game['spread']['favoriteLine'] ?? '--');
    $total = (string) ($game['total']['over'] ?? '--');

    $rows = [
        [
            'label' => 'Starting baseline',
            'away' => '50%',
            'home' => '50%',
            'edge' => 'Neutral',
            'detail' => 'Every matchup starts even before Lineforge adds team, form, player, market, and status evidence.',
        ],
        [
            'label' => 'Model lean',
            'away' => $awayProbability . '%',
            'home' => $homeProbability . '%',
            'edge' => $pickSide === 'away' ? (string) ($away['abbr'] ?? 'Away') . ' pick' : ($pickSide === 'home' ? (string) ($home['abbr'] ?? 'Home') . ' pick' : 'Market watch'),
            'detail' => 'This row shows how the final pick probability compares against the neutral 50% starting point.',
        ],
        [
            'label' => 'Lineforge team rating',
            'away' => $awayRating . '/100',
            'home' => $homeRating . '/100',
            'edge' => aegis_sports_edge_label($awayRating, $homeRating, $away, $home, 2),
            'detail' => 'Derived from record, recent results, available player leaders, location, current score, and listed injury drag.',
        ],
        [
            'label' => 'Record',
            'away' => (string) ($away['record'] ?? '') !== '' ? (string) $away['record'] : 'N/A',
            'home' => (string) ($home['record'] ?? '') !== '' ? (string) $home['record'] : 'N/A',
            'edge' => aegis_sports_edge_label($awayRecordPct, $homeRecordPct, $away, $home),
            'detail' => 'Pulled from the public scoreboard competitor records when supplied by the feed.',
        ],
        [
            'label' => 'Recent form',
            'away' => (string) ($awayRecent['label'] ?? 'No sample'),
            'home' => (string) ($homeRecent['label'] ?? 'No sample'),
            'edge' => aegis_sports_edge_label((float) ($awayRecent['score'] ?? 50), (float) ($homeRecent['score'] ?? 50), $away, $home, 2),
            'detail' => 'Scans completed games from the league scoreboard date range and summarizes the latest five for each team.',
        ],
        [
            'label' => 'Head-to-head',
            'away' => (int) ($h2h['awayWins'] ?? 0) . ' wins',
            'home' => (int) ($h2h['homeWins'] ?? 0) . ' wins',
            'edge' => (string) ($h2h['label'] ?? 'No recent H2H'),
            'detail' => 'Uses recent completed meetings found in the public league scoreboard history cache.',
        ],
        [
            'label' => 'Player leaders',
            'away' => aegis_sports_top_player_label($awayPlayers),
            'home' => aegis_sports_top_player_label($homePlayers),
            'edge' => aegis_sports_edge_label((float) (($awayPlayers[0]['overall'] ?? null) ?: 0), (float) (($homePlayers[0]['overall'] ?? null) ?: 0), $away, $home, 2),
            'detail' => 'Lineforge overall is derived from public leader and boxscore stats; it is not an official video-game rating.',
        ],
        [
            'label' => 'Injury drag',
            'away' => aegis_sports_injury_label($awayInjuries),
            'home' => aegis_sports_injury_label($homeInjuries),
            'edge' => count($awayInjuries) === count($homeInjuries) ? 'Even' : (count($awayInjuries) < count($homeInjuries) ? (string) ($away['abbr'] ?? 'Away') . ' cleaner' : (string) ($home['abbr'] ?? 'Home') . ' cleaner'),
            'detail' => 'Uses injuries included in the public event summary when available; late lineup confirmation still needs verification.',
        ],
        [
            'label' => 'Market snapshot',
            'away' => $spread !== '--' ? $spread : 'Spread pending',
            'home' => $total !== '--' ? $total : 'Total pending',
            'edge' => (string) ($prediction['market'] ?? 'Monitor'),
            'detail' => 'Scoreboard line snapshots are useful context; sportsbook consensus and sharp splits still need a market provider.',
        ],
        [
            'label' => 'Environment',
            'away' => (string) ($game['venue'] ?? '') !== '' ? (string) $game['venue'] : 'Venue pending',
            'home' => !empty($weather['available']) ? (string) ($weather['value'] ?? 'Weather attached') : 'Weather pending',
            'edge' => ((int) ($officials['count'] ?? 0) > 0 ? (int) $officials['count'] . ' officials listed' : 'Officials pending'),
            'detail' => !empty($weather['available'])
                ? (string) ($weather['detail'] ?? 'Weather is attached automatically for outdoor venues.')
                : 'Weather can auto-fill when the scoreboard supplies venue city/state. Referee tendencies still need a history provider even when official names are listed.',
        ],
    ];

    $recentCount = count((array) ($context['recent']['away'] ?? [])) + count((array) ($context['recent']['home'] ?? []));
    $playerCount = count($awayPlayers) + count($homePlayers);
    $injuryCount = count($awayInjuries) + count($homeInjuries);

    return [
        'away' => [
            'name' => (string) ($away['name'] ?? 'Away'),
            'abbr' => (string) ($away['abbr'] ?? 'AWY'),
            'logo' => (string) ($away['logo'] ?? ''),
            'rating' => $awayRating,
            'probability' => $awayProbability . '%',
            'record' => (string) ($away['record'] ?? ''),
        ],
        'home' => [
            'name' => (string) ($home['name'] ?? 'Home'),
            'abbr' => (string) ($home['abbr'] ?? 'HME'),
            'logo' => (string) ($home['logo'] ?? ''),
            'rating' => $homeRating,
            'probability' => $homeProbability . '%',
            'record' => (string) ($home['record'] ?? ''),
        ],
        'pickSide' => $pickSide ?? 'market',
        'rows' => $rows,
        'summary' => 'Lineforge starts at 50/50, then compares both sides across record, recent form, head-to-head, available player leaders, injuries, location, and market context.',
        'signals' => [
            'h2hCount' => (int) ($h2h['count'] ?? 0),
            'recentCount' => $recentCount,
            'playerCount' => $playerCount,
            'injuryCount' => $injuryCount,
            'officialCount' => (int) ($officials['count'] ?? 0),
            'weatherAvailable' => !empty($weather['available']),
            'boxscoreAvailable' => !empty($context['boxscoreAvailable']),
            'summaryAvailable' => !empty($context['summaryAvailable']),
            'historyAvailable' => !empty($context['historyAvailable']),
            'recentLabel' => (string) ($awayRecent['label'] ?? '') . ' / ' . (string) ($homeRecent['label'] ?? ''),
        ],
    ];
}

function aegis_sports_event_priority(array $game): int
{
    return match ((string) ($game['statusKey'] ?? 'scheduled')) {
        'live' => 0,
        'scheduled' => 1,
        'final' => 2,
        default => 3,
    };
}

function aegis_sports_compare_games(array $left, array $right): int
{
    $priority = aegis_sports_event_priority($left) <=> aegis_sports_event_priority($right);
    if ($priority !== 0) {
        return $priority;
    }

    $leftTime = strtotime((string) ($left['startTime'] ?? '')) ?: 0;
    $rightTime = strtotime((string) ($right['startTime'] ?? '')) ?: 0;

    if (($left['statusKey'] ?? '') === 'final' && ($right['statusKey'] ?? '') === 'final') {
        return $rightTime <=> $leftTime;
    }

    return $leftTime <=> $rightTime;
}

function aegis_sports_parse_provider_game(array $event, string $leagueLabel, string $fetchedAt, string $sportGroup = 'Sports', string $leagueKey = '', string $providerSport = '', string $providerLeague = ''): ?array
{
    $competition = is_array(($event['competitions'] ?? [])[0] ?? null) ? $event['competitions'][0] : null;
    if (!$competition) {
        return null;
    }

    $competitors = is_array($competition['competitors'] ?? null) ? $competition['competitors'] : [];
    if (!$competitors) {
        return null;
    }

    $away = aegis_sports_parse_team($competitors, 'away');
    $home = aegis_sports_parse_team($competitors, 'home');
    $status = aegis_sports_status_meta((array) ($competition['status'] ?? []), (string) ($competition['date'] ?? $event['date'] ?? ''));
    $odds = is_array(($competition['odds'] ?? [])[0] ?? null) ? $competition['odds'][0] : [];
    $spread = aegis_sports_parse_spread((string) ($odds['details'] ?? ''));
    $total = aegis_sports_parse_total($odds);
    $history = aegis_sports_history_from_game($away, $home);
    $venue = is_array($competition['venue'] ?? null) ? $competition['venue'] : [];
    $venueAddress = is_array($venue['address'] ?? null) ? $venue['address'] : [];
    $startTime = (string) ($competition['date'] ?? $event['date'] ?? '');

    return [
        'id' => (string) ($event['id'] ?? $competition['id'] ?? uniqid('sports-', true)),
        'league' => $leagueLabel,
        'leagueKey' => $leagueKey,
        'sportGroup' => $sportGroup,
        'providerSport' => $providerSport,
        'providerLeague' => $providerLeague,
        'matchup' => (string) ($away['abbr'] ?? 'AWY') . ' @ ' . (string) ($home['abbr'] ?? 'HME'),
        'clock' => $status['clock'],
        'detail' => $status['detail'],
        'statusKey' => $status['statusKey'],
        'statusLabel' => $status['statusLabel'],
        'statusTone' => $status['statusTone'],
        'startTime' => $startTime,
        'venue' => (string) ($venue['fullName'] ?? ''),
        'venueCity' => (string) ($venueAddress['city'] ?? ''),
        'venueState' => (string) ($venueAddress['state'] ?? ''),
        'venueCountry' => (string) ($venueAddress['country'] ?? ''),
        'venueIndoor' => is_bool($venue['indoor'] ?? null) ? (bool) $venue['indoor'] : null,
        'source' => 'ESPN scoreboard',
        'sourceFeed' => $leagueLabel . ' public feed',
        'fetchedAt' => $fetchedAt,
        'feedAgeLabel' => aegis_remote_data_relative_time($fetchedAt),
        'away' => $away,
        'home' => $home,
        'spread' => $spread,
        'total' => $total,
        'history' => $history,
    ];
}

function aegis_sports_build_fallback_games(int $bucket): array
{
    $templates = [
        [
            'id' => 'fallback-nba',
            'league' => 'NBA',
            'leagueKey' => 'fallback-nba',
            'sportGroup' => 'Basketball',
            'statusKey' => 'scheduled',
            'statusLabel' => 'Fallback',
            'statusTone' => 'scheduled',
            'clock' => 'Tonight 7:30 PM',
            'detail' => 'Fallback board: provider feed unavailable.',
            'away' => ['name' => 'Lakers', 'abbr' => 'LAL', 'score' => 0],
            'home' => ['name' => 'Warriors', 'abbr' => 'GSW', 'score' => 0],
            'spread' => ['favoriteLine' => 'LAL -4.5', 'otherLine' => '+4.5'],
            'total' => ['over' => 'O 224.5', 'under' => 'U 224.5'],
            'history' => [44, 46, 51, 55, 58, 62, 67, 71],
        ],
        [
            'id' => 'fallback-nfl',
            'league' => 'NFL',
            'leagueKey' => 'fallback-nfl',
            'sportGroup' => 'Football',
            'statusKey' => 'scheduled',
            'statusLabel' => 'Fallback',
            'statusTone' => 'scheduled',
            'clock' => 'Sunday 3:25 PM',
            'detail' => 'Fallback board: provider feed unavailable.',
            'away' => ['name' => 'Chiefs', 'abbr' => 'KC', 'score' => 0],
            'home' => ['name' => 'Bills', 'abbr' => 'BUF', 'score' => 0],
            'spread' => ['favoriteLine' => 'KC -3.5', 'otherLine' => '+3.5'],
            'total' => ['over' => 'O 48.5', 'under' => 'U 48.5'],
            'history' => [42, 45, 49, 53, 58, 60, 65, 69],
        ],
        [
            'id' => 'fallback-mlb',
            'league' => 'MLB',
            'leagueKey' => 'fallback-mlb',
            'sportGroup' => 'Baseball',
            'statusKey' => 'scheduled',
            'statusLabel' => 'Fallback',
            'statusTone' => 'scheduled',
            'clock' => 'Tonight 6:10 PM',
            'detail' => 'Fallback board: provider feed unavailable.',
            'away' => ['name' => 'Yankees', 'abbr' => 'NYY', 'score' => 0],
            'home' => ['name' => 'Red Sox', 'abbr' => 'BOS', 'score' => 0],
            'spread' => ['favoriteLine' => 'NYY -1.5', 'otherLine' => '+1.5'],
            'total' => ['over' => 'O 8.5', 'under' => 'U 8.5'],
            'history' => [40, 43, 48, 52, 56, 61, 64, 68],
        ],
    ];

    return array_map(
        static function (array $game, int $index) use ($bucket): array {
            $game['feedAgeLabel'] = (($bucket + $index) % 6 + 1) . 'm ago';
            $game['source'] = 'Lineforge fallback board';
            $game['sourceFeed'] = 'Fallback model';
            $game['matchup'] = ($game['away']['abbr'] ?? 'AWY') . ' @ ' . ($game['home']['abbr'] ?? 'HME');
            $game['startTime'] = gmdate('c', time() + (($index + 1) * 3600));
            return $game;
        },
        $templates,
        array_keys($templates)
    );
}

function aegis_sports_live_games(array $limits): array
{
    $trackedGames = aegis_sports_limit_int($limits, 'tracked_games', 3);
    $refreshSeconds = aegis_sports_limit_int($limits, 'refresh_seconds', 60);
    $ttl = max(15, min(90, $refreshSeconds));
    $games = [];
    $startedAt = microtime(true);
    $fetchBudget = (float) (getenv('AEGIS_SPORTS_FETCH_TIME_BUDGET_SECONDS') ?: 5.5);
    $fetchBudget = max(2.0, min(15.0, $fetchBudget));

    foreach (aegis_sports_active_provider_leagues($trackedGames, $refreshSeconds) as $league) {
        if ((microtime(true) - $startedAt) > $fetchBudget) {
            break;
        }

        $board = aegis_sports_fetch_provider_board($league, $ttl);
        if (!is_array($board) || !is_array($board['events'] ?? null)) {
            continue;
        }

        $fetchedAt = (string) ($board['fetchedAt'] ?? gmdate('c'));
        foreach ($board['events'] as $event) {
            $parsed = aegis_sports_parse_provider_game(
                (array) $event,
                (string) $league['label'],
                $fetchedAt,
                (string) ($league['group'] ?? 'Sports'),
                (string) ($league['key'] ?? ''),
                (string) ($league['sport'] ?? ''),
                (string) ($league['league'] ?? '')
            );
            if ($parsed) {
                $games[] = $parsed;
            }
        }
    }

    if (!$games) {
        return aegis_sports_build_fallback_games(aegis_sports_bucket($refreshSeconds));
    }

    usort($games, 'aegis_sports_compare_games');
    return array_slice($games, 0, max(60, min(160, $trackedGames + 60)));
}

function aegis_sports_prediction_for_game(array $game, int $index, int $bucket, bool $includeBreakdown = true): array
{
    $statusKey = (string) ($game['statusKey'] ?? 'scheduled');
    $matchup = (string) ($game['matchup'] ?? (($game['away']['abbr'] ?? 'AWY') . ' @ ' . ($game['home']['abbr'] ?? 'HME')));
    $hasSpread = ($game['spread']['favoriteLine'] ?? '--') !== '--';
    $hasTotal = ($game['total']['over'] ?? '--') !== '--';
    $margin = abs((int) (($game['away']['score'] ?? 0) - ($game['home']['score'] ?? 0)));
    $base = match ($statusKey) {
        'live' => 71,
        'scheduled' => 64,
        'final' => 55,
        default => 58,
    };
    $confidence = (int) aegis_sports_clamp($base + min(10, $margin * 2) + ($hasSpread || $hasTotal ? 6 : 0) + ((($bucket + $index) % 5) - 2), 52, 84);

    $pick = 'Watch ' . $matchup;
    $market = 'Monitor';
    $reason = 'Use this matchup as a watchlist entry until stronger provider market coverage is available.';

    if ($statusKey === 'final') {
        $winner = !empty($game['home']['winner']) ? ($game['home']['abbr'] ?? $game['home']['name'] ?? 'HOME') : ($game['away']['abbr'] ?? $game['away']['name'] ?? 'AWAY');
        $pick = 'Postgame review: ' . $winner;
        $market = 'Audit';
        $reason = 'This event is final on the live scoreboard. Keep it for grading and model review rather than new action.';
    } elseif ($hasSpread) {
        $pick = (string) ($game['spread']['favoriteLine'] ?? $pick);
        $market = 'Spread';
        $reason = $statusKey === 'live'
            ? 'Live scoreboard state and the current line snapshot are aligned, so this is the cleanest market to watch right now.'
            : 'Scheduled board shows a valid spread snapshot, making this the strongest pregame lane to monitor.';
    } elseif ($hasTotal) {
        $pick = (string) ($game['total']['over'] ?? $pick);
        $market = 'Total';
        $reason = $statusKey === 'live'
            ? 'The feed has a live total but limited book depth, so treat this as an informational edge watch.'
            : 'Pregame total is available on the feed, so this is the clearest market to stage for review.';
    }

    $edgeValue = $statusKey === 'final'
        ? '+0.0%'
        : aegis_sports_signed(aegis_sports_clamp(($confidence - 50) / 2.2, 3.8, 15.9));
    $expectedValue = $statusKey === 'final'
        ? aegis_sports_money(0)
        : aegis_sports_money(max(12.0, ($confidence - 46) * 2.15));
    $confidenceTier = $confidence >= 74 ? 'Strong pick' : ($confidence >= 64 ? 'Lean' : 'Watch only');
    $risk = match ($statusKey) {
        'live' => 'Live volatility',
        'scheduled' => 'Pregame risk',
        'final' => 'Closed market',
        default => 'Status risk',
    };
    $stakeUnits = $statusKey === 'final' ? '0.00u' : number_format(aegis_sports_clamp(($confidence - 52) / 28, 0.12, 0.85), 2) . 'u';
    $statusLabel = (string) ($game['statusLabel'] ?? ucfirst($statusKey));
    $why = $statusKey === 'final'
        ? ['Game is final', 'Use for grading only', 'No new action recommended']
        : [
            $hasSpread ? 'Provider has a spread snapshot' : ($hasTotal ? 'Provider has a total snapshot' : 'Provider has status coverage'),
            $statusKey === 'live' ? 'Live game state is active' : 'Scheduled board is staged',
            'Confidence is clipped while key data feeds are missing',
        ];

    $prediction = [
        'gameId' => (string) ($game['id'] ?? ''),
        'pick' => $pick,
        'matchup' => $matchup,
        'market' => $market,
        'league' => (string) ($game['league'] ?? ''),
        'sportGroup' => (string) ($game['sportGroup'] ?? 'Sports'),
        'statusKey' => $statusKey,
        'statusLabel' => $statusLabel,
        'actionLabel' => $statusKey === 'final' ? 'No Bet - Final' : 'AI Pick',
        'verdict' => $statusKey === 'final' ? 'Review only' : $confidenceTier,
        'risk' => $risk,
        'stake' => $stakeUnits,
        'canBet' => $statusKey !== 'final',
        'why' => $why,
        'confidenceValue' => $confidence,
        'confidence' => $confidence . '%',
        'fairProbability' => number_format($confidence, 1) . '%',
        'fairOdds' => aegis_sports_probability_to_american($confidence / 100),
        'odds' => ($hasSpread || $hasTotal) && $statusKey !== 'final' ? 'Public snapshot' : '--',
        'edge' => $edgeValue,
        'expectedValue' => $expectedValue,
        'reason' => $reason,
        'marketLinks' => [],
    ];
    $winnerProjection = aegis_sports_prediction_winner_projection($game, $prediction);
    $prediction['predictedWinner'] = (string) ($winnerProjection['label'] ?? 'Predicted winner');
    $prediction['predictedWinnerSide'] = (string) ($winnerProjection['side'] ?? 'market');
    $prediction['predictedWinnerBasis'] = (string) ($winnerProjection['basis'] ?? 'Model lean');
    $prediction['predictedWinnerStrength'] = (string) ($winnerProjection['strength'] ?? 'Lean');
    if ($includeBreakdown) {
        $prediction['breakdown'] = aegis_sports_prediction_breakdown($prediction, $game);
    }

    return $prediction;
}

function aegis_sports_prediction_data_quality(array $game, array $links = [], array $context = []): array
{
    $statusKey = (string) ($game['statusKey'] ?? 'scheduled');
    $source = strtolower((string) ($game['source'] ?? ''));
    $isFallback = str_contains($source, 'fallback');
    $hasSpread = ($game['spread']['favoriteLine'] ?? '--') !== '--';
    $hasTotal = ($game['total']['over'] ?? '--') !== '--';
    $sportsbookLines = count(array_filter($links, static fn(array $link): bool => !empty($link['available']) && (string) ($link['kind'] ?? '') === 'Sportsbook' && (string) ($link['price'] ?? '--') !== '--'));
    $summaryAvailable = !empty($context['summaryAvailable']);
    $historyAvailable = !empty($context['historyAvailable']);
    $boxscoreAvailable = !empty($context['boxscoreAvailable']);
    $weatherAvailable = !empty($context['weather']['available']);
    $playerCount = count((array) ($context['players']['away'] ?? [])) + count((array) ($context['players']['home'] ?? []));
    $injuryCount = count((array) ($context['injuries']['away'] ?? [])) + count((array) ($context['injuries']['home'] ?? []));
    $recentCount = count((array) ($context['recent']['away'] ?? [])) + count((array) ($context['recent']['home'] ?? []));

    $score = 0;
    $signals = [];
    $warnings = [];

    if ($isFallback) {
        $score += 4;
        $warnings[] = 'Using fallback slate instead of live provider data.';
    } else {
        $score += 16;
        $signals[] = 'Public scoreboard attached';
    }

    $fetchedAt = strtotime((string) ($game['fetchedAt'] ?? '')) ?: 0;
    if ($fetchedAt > 0 && (time() - $fetchedAt) <= 180) {
        $score += 8;
        $signals[] = 'Fresh scoreboard';
    } elseif ($fetchedAt > 0) {
        $score += 4;
        $warnings[] = 'Scoreboard cache is older than the live target.';
    }

    if (in_array($statusKey, ['live', 'scheduled', 'final'], true)) {
        $score += 8;
    }
    if ($hasSpread || $hasTotal) {
        $score += 10;
        $signals[] = $hasSpread ? 'Spread snapshot' : 'Total snapshot';
    } else {
        $warnings[] = 'No spread or total snapshot is attached.';
    }
    if ($sportsbookLines > 0) {
        $score += min(24, $sportsbookLines * 6);
        $signals[] = $sportsbookLines . ' sportsbook price' . ($sportsbookLines === 1 ? '' : 's');
    } else {
        $warnings[] = 'No live sportsbook price is attached; keep this in watch mode.';
    }
    if ($summaryAvailable) {
        $score += 10;
        $signals[] = 'Public event summary';
    } else {
        $warnings[] = 'Public summary, injury, and lineup context are incomplete.';
    }
    if ($historyAvailable || $recentCount > 0) {
        $score += 8;
        $signals[] = 'Recent history sample';
    }
    if ($playerCount > 0) {
        $score += 6;
        $signals[] = 'Player leaders';
    }
    if ($injuryCount > 0) {
        $score += 4;
        $signals[] = 'Listed injury context';
    }
    if ($boxscoreAvailable) {
        $score += 5;
    }
    if ($weatherAvailable) {
        $score += 3;
    }

    $score = (int) aegis_sports_clamp($score, 10, 100);
    $label = $score >= 82 ? 'Institutional' : ($score >= 68 ? 'Strong public' : ($score >= 52 ? 'Public partial' : ($score >= 36 ? 'Thin public' : 'Fallback')));
    $cap = $score >= 82 ? 82 : ($score >= 68 ? 76 : ($score >= 52 ? 70 : ($score >= 36 ? 64 : 60)));
    if ($sportsbookLines === 0) {
        $cap = min($cap, 68);
    }
    if ($statusKey === 'live') {
        $cap = min($cap, 76);
    }
    if ($statusKey === 'final') {
        $cap = min($cap, 55);
    }

    return [
        'score' => $score,
        'label' => $label,
        'confidenceCap' => $cap,
        'availableSportsbookLines' => $sportsbookLines,
        'signals' => array_values(array_unique($signals)),
        'warnings' => array_values(array_unique($warnings)),
    ];
}

function aegis_sports_apply_confidence_calibration(array $prediction, array $game, array $links = [], array $context = []): array
{
    $quality = aegis_sports_prediction_data_quality($game, $links, $context);
    $raw = (int) ($prediction['rawConfidenceValue'] ?? $prediction['confidenceValue'] ?? 58);
    $raw = (int) aegis_sports_clamp($raw, 1, 99);
    $cap = (int) ($quality['confidenceCap'] ?? 68);
    $confidence = min($raw, $cap);
    if ((int) ($quality['score'] ?? 0) < 52) {
        $confidence = (int) round(50 + (($confidence - 50) * 0.72));
    }
    if ((int) ($quality['score'] ?? 0) < 36) {
        $confidence = (int) round(50 + (($confidence - 50) * 0.55));
    }
    $confidence = (int) aegis_sports_clamp($confidence, 50, 84);
    $statusKey = (string) ($prediction['statusKey'] ?? ($game['statusKey'] ?? 'scheduled'));
    $hasActionableLine = (int) ($quality['availableSportsbookLines'] ?? 0) > 0;

    $prediction['rawConfidenceValue'] = $raw;
    $prediction['confidenceValue'] = $confidence;
    $prediction['confidence'] = $confidence . '%';
    $prediction['fairProbability'] = number_format($confidence, 1) . '%';
    $prediction['fairOdds'] = aegis_sports_probability_to_american($confidence / 100);
    $prediction['dataQuality'] = $quality;

    if ($statusKey === 'final') {
        $prediction['canBet'] = false;
        $prediction['stake'] = '0.00u';
        $prediction['edge'] = '+0.0%';
        $prediction['expectedValue'] = aegis_sports_money(0);
        $prediction['verdict'] = 'Review only';
        return $prediction;
    }

    $prediction['canBet'] = $hasActionableLine;
    $prediction['stake'] = $hasActionableLine ? number_format(aegis_sports_clamp(($confidence - 52) / 30, 0.10, 0.70), 2) . 'u' : '0.00u';
    if (!$hasActionableLine) {
        $prediction['edge'] = 'Needs book line';
        $prediction['expectedValue'] = aegis_sports_money(0);
        $prediction['verdict'] = 'Watch only';
        $prediction['actionLabel'] = 'AI Watch';
        $prediction['reason'] = 'Lineforge has public context, but no live sportsbook price is attached. Keep this as a watchlist read until a verified market price is available.';
    } else {
        $prediction['edge'] = (string) ($prediction['edge'] ?? aegis_sports_signed(max(0, ($confidence - 50) / 2.4)));
        $prediction['expectedValue'] = aegis_sports_money(max(0, ($confidence - 50) * 1.85));
        $prediction['verdict'] = $confidence >= 74 && (int) ($quality['score'] ?? 0) >= 68 ? 'Strong lean' : ($confidence >= 64 ? 'Lean' : 'Watch only');
    }

    if ($confidence < $raw) {
        $why = array_values((array) ($prediction['why'] ?? []));
        array_unshift($why, 'Confidence calibrated down from ' . $raw . '% because data quality is ' . strtolower((string) ($quality['label'] ?? 'partial')) . '.');
        $prediction['why'] = array_slice(array_values(array_unique($why)), 0, 5);
    }

    return $prediction;
}

function aegis_sports_reprice_market_links(array $links, array $prediction): array
{
    $modelProbability = aegis_sports_clamp(((float) ($prediction['confidenceValue'] ?? 58)) / 100, 0.01, 0.99);
    $fairOdds = aegis_sports_probability_to_american($modelProbability);

    foreach ($links as &$link) {
        if (!is_array($link)) {
            continue;
        }

        $link['fairOdds'] = $fairOdds;
        if (!empty($link['available']) && (string) ($link['kind'] ?? '') === 'Sportsbook' && (string) ($link['price'] ?? '--') !== '--') {
            $bookProbability = aegis_sports_american_to_probability((string) $link['price']);
            if ($bookProbability !== null) {
                $link['bookProbability'] = number_format($bookProbability * 100, 1) . '%';
                $link['modelEdge'] = aegis_sports_signed(($modelProbability - $bookProbability) * 100);
            }
        } elseif ((string) ($link['kind'] ?? '') === 'Sportsbook') {
            $link['modelEdge'] = 'Needs book line';
        }
    }
    unset($link);

    return $links;
}

function aegis_sports_build_predictions(array $games, int $modelCount, int $bucket): array
{
    $preferred = array_values(
        array_filter(
            $games,
            static fn(array $game): bool => ($game['statusKey'] ?? '') !== 'final'
        )
    );

    if (!$preferred) {
        $preferred = $games;
    }

    $limit = max(5, min(count($preferred), $modelCount + 3));
    $predictions = [];

    foreach (array_slice($preferred, 0, $limit) as $index => $game) {
        $predictions[] = aegis_sports_prediction_for_game($game, $index, $bucket);
    }

    return $predictions;
}

function aegis_sports_feed_freshness(array $games): string
{
    $timestamps = array_values(
        array_filter(
            array_map(
                static fn(array $game): int => strtotime((string) ($game['fetchedAt'] ?? '')) ?: 0,
                $games
            )
        )
    );

    if (!$timestamps) {
        return 'Unavailable';
    }

    return aegis_remote_data_relative_time(gmdate('c', max($timestamps)));
}

function aegis_sports_game_sections(array $games): array
{
    $sections = [
        'live' => [],
        'scheduled' => [],
        'final' => [],
        'alert' => [],
    ];

    foreach ($games as $game) {
        $key = (string) ($game['statusKey'] ?? 'scheduled');
        if (!isset($sections[$key])) {
            $key = 'alert';
        }

        $sections[$key][] = $game;
    }

    return $sections;
}

function aegis_sports_coverage_summary(array $games, array $activeLeagues): array
{
    $allLeagues = aegis_sports_provider_leagues();
    $groups = [];
    foreach ($allLeagues as $league) {
        $group = (string) ($league['group'] ?? 'Sports');
        if (!isset($groups[$group])) {
            $groups[$group] = [
                'label' => $group,
                'configured' => 0,
                'activeScan' => 0,
                'games' => 0,
                'live' => 0,
                'scheduled' => 0,
                'final' => 0,
            ];
        }

        $groups[$group]['configured'] += 1;
    }

    foreach ($activeLeagues as $league) {
        $group = (string) ($league['group'] ?? 'Sports');
        if (isset($groups[$group])) {
            $groups[$group]['activeScan'] += 1;
        }
    }

    foreach ($games as $game) {
        $group = (string) ($game['sportGroup'] ?? 'Sports');
        if (!isset($groups[$group])) {
            $groups[$group] = [
                'label' => $group,
                'configured' => 0,
                'activeScan' => 0,
                'games' => 0,
                'live' => 0,
                'scheduled' => 0,
                'final' => 0,
            ];
        }

        $groups[$group]['games'] += 1;
        $status = (string) ($game['statusKey'] ?? 'scheduled');
        if (isset($groups[$group][$status])) {
            $groups[$group][$status] += 1;
        }
    }

    uasort(
        $groups,
        static fn(array $left, array $right): int => ($right['games'] <=> $left['games']) ?: strcmp($left['label'], $right['label'])
    );

    return [
        'configuredLeagues' => count($allLeagues),
        'activeLeagues' => count($activeLeagues),
        'groups' => array_values($groups),
    ];
}

function aegis_sports_build_book_grid(array $games): array
{
    $rows = [];

    foreach (array_slice($games, 0, 4) as $game) {
        $bestLine = is_array($game['bestLine'] ?? null) ? $game['bestLine'] : null;
        if ($bestLine) {
            $rows[] = [
                'book' => (string) ($bestLine['title'] ?? 'Provider'),
                'line' => trim((string) (($bestLine['market'] ?? 'Market') . ' ' . ($bestLine['line'] ?? ''))),
                'odds' => (string) ($bestLine['price'] ?? '--'),
                'latency' => (string) ($bestLine['source'] ?? 'Provider'),
            ];
            continue;
        }

        $market = ($game['spread']['favoriteLine'] ?? '--') !== '--'
            ? (string) ($game['spread']['favoriteLine'] ?? '--')
            : (string) ($game['total']['over'] ?? '--');

        $rows[] = [
            'book' => (string) ($game['league'] ?? 'Feed') . ' feed',
            'line' => $market !== '--' ? $market : 'Status only',
            'odds' => (string) ($game['statusLabel'] ?? 'Watch'),
            'latency' => (string) ($game['feedAgeLabel'] ?? 'Now'),
        ];
    }

    return $rows;
}

function aegis_sports_build_alerts(array $games): array
{
    $alerts = [];

    foreach (array_slice($games, 0, 3) as $game) {
        $alerts[] = [
            'name' => (string) ($game['statusLabel'] ?? 'Board update'),
            'detail' => (string) ($game['matchup'] ?? 'Matchup') . ' is currently ' . strtolower((string) ($game['detail'] ?? ($game['clock'] ?? 'on the board'))) . '.',
            'time' => (string) ($game['feedAgeLabel'] ?? 'Now'),
        ];
    }

    return $alerts;
}

function aegis_sports_state(array $limits = []): array
{
    $trackedGames = aegis_sports_limit_int($limits, 'tracked_games', 3);
    $models = aegis_sports_limit_int($limits, 'models', 2);
    $refreshSeconds = aegis_sports_limit_int($limits, 'refresh_seconds', 60);
    $tier = aegis_sports_tier_from_limits($limits);
    $bucket = aegis_sports_bucket($refreshSeconds);
    $activeLeagues = aegis_sports_active_provider_leagues($trackedGames, $refreshSeconds);

    $games = aegis_sports_live_games($limits);
    $predictions = aegis_sports_build_predictions($games, $models, $bucket);
    $marketAccessState = aegis_sports_enrich_market_access($games, $predictions, $refreshSeconds, $bucket);
    $games = $marketAccessState['games'];
    $predictions = $marketAccessState['predictions'];
    $marketAccess = $marketAccessState['marketAccess'];
    $arbitrage = is_array($marketAccessState['arbitrage'] ?? null) ? $marketAccessState['arbitrage'] : [];
    $gameSections = aegis_sports_game_sections($games);
    $coverage = aegis_sports_coverage_summary($games, $activeLeagues);
    $topPrediction = $predictions[0] ?? [
        'pick' => 'Watch the live board',
        'matchup' => 'Board',
        'market' => 'Monitor',
        'league' => 'Coverage',
        'sportGroup' => 'Sports',
        'statusKey' => 'scheduled',
        'statusLabel' => 'Watch',
        'actionLabel' => 'AI Watch',
        'verdict' => 'Watch only',
        'risk' => 'No market depth',
        'stake' => '0.00u',
        'canBet' => false,
        'why' => ['No strong pick is available yet', 'Watch the live board', 'Wait for a real market snapshot'],
        'confidenceValue' => 58,
        'confidence' => '58%',
        'fairProbability' => '58.0%',
        'fairOdds' => '-138',
        'marketLinks' => [],
        'edge' => '+4.0%',
        'expectedValue' => '$12.00',
        'reason' => 'Use the live board as an informational watchlist until stronger market depth is connected.',
    ];
    $topGame = $games[0] ?? [
        'matchup' => 'Board',
        'league' => 'Feed',
        'spread' => ['favoriteLine' => '--'],
        'history' => [42, 45, 48, 52, 57, 61, 66, 71],
        'statusKey' => 'scheduled',
        'statusLabel' => 'Watch',
        'feedAgeLabel' => 'Now',
    ];

    $liveGames = count($gameSections['live'] ?? []);
    $finalGames = count($gameSections['final'] ?? []);
    $scheduledGames = count($gameSections['scheduled'] ?? []);
    $marketGames = count(
        array_filter(
            $games,
            static fn(array $game): bool => ($game['spread']['favoriteLine'] ?? '--') !== '--' || ($game['total']['over'] ?? '--') !== '--'
        )
    );
    $availableBookLines = (int) ($marketAccess['availableLines'] ?? 0);
    $freshness = aegis_sports_feed_freshness($games);
    $latestFeedTimestamp = max(
        array_merge(
            [0],
            array_map(
                static fn(array $game): int => strtotime((string) ($game['fetchedAt'] ?? '')) ?: 0,
                $games
            )
        )
    );
    $feedAgeSeconds = $latestFeedTimestamp > 0 ? max(0, time() - $latestFeedTimestamp) : null;
    $remoteTransport = aegis_remote_data_transport_state();
    $isFallbackBoard = str_contains(strtolower((string) ($games[0]['source'] ?? '')), 'fallback');
    $staleFeedThreshold = max(180, $refreshSeconds * 6);
    $isCachedBoard = !$isFallbackBoard && ($feedAgeSeconds === null || $feedAgeSeconds > $staleFeedThreshold);
    $scoreboardDetail = $isFallbackBoard
        ? 'Provider scoreboard is unavailable, so the sports board is using the built-in fallback slate.'
        : (!$remoteTransport['https_capable']
            ? 'This PHP runtime cannot refresh HTTPS scoreboards right now, so the sports board is showing cached provider data until cURL or OpenSSL is enabled.'
            : ($isCachedBoard
                ? 'Provider scoreboard data is cached and older than the live threshold, so the page is labeling it as cached instead of live.'
                : 'Current event status comes from the public multi-league scoreboard feed instead of staged clocks.'));
    $refreshHealthDetail = !$remoteTransport['https_capable']
        ? 'The server is missing HTTPS feed transport, so freshness is limited to whatever was cached previously.'
        : ($isCachedBoard
            ? 'The latest scoreboard refresh is older than the live threshold, so the page is keeping the cached label visible.'
            : 'Feed freshness is cached and reused so the page stays responsive without inventing live states.');
    $books = aegis_sports_build_book_grid($games);
    $dataArchitecture = lineforge_public_intelligence_build_state($games, $predictions, $marketAccess, $arbitrage, $remoteTransport, $refreshSeconds);
    $modelSources = aegis_sports_model_sources($marketAccess, $remoteTransport);

    $factors = [
        ['name' => 'Live scoreboard', 'weight' => $liveGames . ' live', 'detail' => $scoreboardDetail],
        ['name' => 'Pregame board', 'weight' => $scheduledGames . ' upcoming', 'detail' => 'Scheduled matchups stay on the board so users can monitor the next betting windows.'],
        ['name' => 'Final audit set', 'weight' => $finalGames . ' final', 'detail' => 'Completed games are kept for review and grading instead of still being labeled live.'],
        ['name' => 'Sports universe', 'weight' => $coverage['configuredLeagues'] . ' leagues', 'detail' => 'Lineforge now tracks a broad global sports catalog and rotates lower-volume leagues through the scan queue.'],
        ['name' => 'Market coverage', 'weight' => $marketGames . ' with lines', 'detail' => 'Spread and total displays only show provider snapshots when the feed supplies them.'],
        ['name' => 'Book access', 'weight' => $availableBookLines > 0 ? $availableBookLines . ' live lines' : 'Links ready', 'detail' => (string) ($marketAccess['note'] ?? 'Provider links are shown as fast outbound access, not automated bet execution.')],
        ['name' => 'Public intelligence', 'weight' => (string) ($dataArchitecture['summary']['activePublicModules'] ?? 0) . ' modules', 'detail' => 'Public/free sources, local archives, and inference systems stay active before premium APIs are required.'],
        ['name' => 'Refresh health', 'weight' => $freshness, 'detail' => $refreshHealthDetail],
    ];

    $edgeStack = [
        ['name' => 'Live board', 'value' => (string) $liveGames, 'detail' => 'Number of matchups currently flagged live by the provider feed.'],
        ['name' => 'Covered markets', 'value' => (string) $marketGames, 'detail' => 'Games that currently include a spread or total snapshot.'],
        ['name' => 'Freshness', 'value' => $freshness, 'detail' => 'How recently the active sports feeds were refreshed.'],
        ['name' => 'Audit set', 'value' => (string) $finalGames, 'detail' => 'Final games retained for postgame analysis and model grading.'],
    ];

    $rules = [
        ['name' => 'Status honesty', 'state' => 'Active', 'detail' => 'Final games are marked final and no longer presented as live opportunities.'],
        ['name' => 'Market guard', 'state' => $marketGames > 0 ? 'Watching' : 'Sparse feed', 'detail' => $scoreboardDetail],
        ['name' => 'Confidence cap', 'state' => 'Conservative', 'detail' => 'Keep confidence clipped while provider coverage, injuries, and advanced matchup feeds are incomplete.'],
        ['name' => 'Refresh cadence', 'state' => $refreshSeconds . 's UI', 'detail' => 'Interface refresh can be fast while provider fetches stay cached and controlled.'],
        ['name' => 'Execution mode', 'state' => 'Informational only', 'detail' => 'This workflow is for analytics and monitoring, not automated sportsbook execution.'],
    ];

    $opportunities = [
        ['name' => 'Live board coverage', 'tag' => $liveGames > 0 ? 'Active' : 'Watching', 'value' => $liveGames . ' live events', 'detail' => 'Use live games for pacing and status-aware review instead of trusting stale mock data.'],
        ['name' => 'Pregame queue', 'tag' => $scheduledGames > 0 ? 'Ready' : 'Quiet', 'value' => $scheduledGames . ' scheduled', 'detail' => 'Upcoming events stay staged for review before kickoff or tip-off.'],
        ['name' => 'Postgame audit', 'tag' => $finalGames > 0 ? 'Useful' : 'Empty', 'value' => $finalGames . ' completed', 'detail' => 'Finished games are useful for grading model posture and UI accuracy.'],
        ['name' => 'Global sports scan', 'tag' => 'Expanded', 'value' => $coverage['activeLeagues'] . '/' . $coverage['configuredLeagues'] . ' leagues', 'detail' => 'High-volume sports refresh every cycle while niche sports rotate through the active scan set.'],
        ['name' => 'Sportsbook access', 'tag' => $availableBookLines > 0 ? 'Live lines' : 'Connect feed', 'value' => $availableBookLines . ' provider lines', 'detail' => $availableBookLines > 0 ? 'Matched sportsbook prices are available for the current board.' : 'Add AEGIS_ODDS_API_KEY to show FanDuel, DraftKings, BetMGM, and other live line snapshots.'],
        ['name' => 'Odds depth', 'tag' => $marketGames > 0 ? 'Partial' : 'Missing', 'value' => $marketGames . ' line snapshots', 'detail' => 'Scoreboard spread and total coverage is separated from bookmaker line availability for honesty.'],
    ];

    $alerts = aegis_sports_build_alerts($games);
    $bankroll = [
        'balance' => 3250.45,
        'profit' => 512.34,
        'profitPercent' => 18.7,
        'winRate' => 62.4,
        'roi' => 12.8,
        'streak' => '5W',
    ];

    $performance = [
        ['label' => 'Live status accuracy', 'value' => $isFallbackBoard ? 'Fallback board' : ($isCachedBoard ? 'Cached feed' : ($liveGames > 0 ? 'Real feed' : 'Feed quiet')), 'detail' => $scoreboardDetail],
        ['label' => 'Audit coverage', 'value' => (string) $finalGames, 'detail' => 'Final games remain visible for review and grading.'],
        ['label' => 'Market depth', 'value' => (string) $marketGames, 'detail' => 'Count of games with current spread or total snapshots.'],
        ['label' => 'Refresh health', 'value' => $freshness, 'detail' => $refreshHealthDetail],
    ];

    $riskControls = [
        ['name' => 'Kelly stake', 'value' => '0.38u'],
        ['name' => 'Max exposure', 'value' => '4.0u'],
        ['name' => 'Loss stop', 'value' => 'Armed'],
        ['name' => 'Chase guard', 'value' => 'On'],
    ];

    $tape = array_map(
        static fn(array $game): array => [
            'label' => (string) ($game['league'] ?? 'Feed'),
            'value' => (string) ($game['matchup'] ?? 'Matchup'),
            'state' => (string) ($game['statusLabel'] ?? 'Watch'),
        ],
        array_slice($games, 0, 5)
    );

    $metrics = [
        ['label' => 'Tracked games', 'value' => (string) min($trackedGames, count($games))],
        ['label' => 'Live now', 'value' => (string) $liveGames],
        ['label' => 'Scheduled', 'value' => (string) $scheduledGames],
        ['label' => 'Final/Audit', 'value' => (string) $finalGames],
        ['label' => 'Sports covered', 'value' => (string) count($coverage['groups'])],
        ['label' => 'League scan', 'value' => $coverage['activeLeagues'] . '/' . $coverage['configuredLeagues']],
        ['label' => 'Feed freshness', 'value' => $freshness],
        ['label' => 'Refresh', 'value' => $refreshSeconds . 's'],
    ];

    $sourceBadge = $isFallbackBoard
        ? 'Fallback'
        : ($isCachedBoard ? 'Cached feed' : 'Live feed');
    $sourceLabel = $isFallbackBoard
        ? 'Fallback Lineforge board - provider feed unavailable'
        : ($isCachedBoard
            ? 'Cached public scoreboard - waiting for a fresh provider refresh'
            : 'Live public scoreboard + Lineforge modeling');

    $primaryLine = (string) ($topGame['spread']['favoriteLine'] ?? '--');
    if ($primaryLine === '--') {
        $primaryLine = (string) ($topGame['total']['over'] ?? '--');
    }

    return [
        'sourceBadge' => $sourceBadge,
        'sourceLabel' => $sourceLabel,
        'tier' => $tier,
        'games' => $games,
        'gameSections' => $gameSections,
        'coverage' => $coverage,
        'marketAccess' => $marketAccess,
        'arbitrage' => $arbitrage,
        'dataArchitecture' => $dataArchitecture,
        'modelSources' => $modelSources,
        'predictions' => $predictions,
        'topPick' => $topPrediction,
        'factors' => $factors,
        'books' => $books,
        'edgeStack' => $edgeStack,
        'rules' => $rules,
        'opportunities' => $opportunities,
        'alerts' => $alerts,
        'bankroll' => $bankroll,
        'performance' => $performance,
        'riskControls' => $riskControls,
        'tape' => $tape,
        'metrics' => $metrics,
        'insight' => [
            'title' => 'Why this market matters now',
            'copy' => (string) ($topPrediction['reason'] ?? 'Use the live board for pacing, grading, and cleaner market review.'),
        ],
        'primaryMarket' => (string) ($topGame['matchup'] ?? 'Primary market') . ' - ' . (string) ($topPrediction['market'] ?? 'Monitor'),
        'selectedMarket' => (string) ($topPrediction['pick'] ?? 'Watch the board'),
        'marketHistory' => is_array($topGame['history'] ?? null) ? $topGame['history'] : [42, 45, 48, 52, 57, 61, 66, 71],
        'bookSummary' => [
            'opened' => $primaryLine,
            'current' => (string) ($topPrediction['pick'] ?? $primaryLine),
            'move' => (string) ($topGame['statusLabel'] ?? 'Watch'),
        ],
    ];
}
