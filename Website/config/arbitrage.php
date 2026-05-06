<?php

interface ProviderOddsAdapter
{
    public function providerName(): string;
    public function getSports(): array;
    public function getEvents(array $filters = []): array;
    public function getMarkets(array $event): array;
    public function getOdds(array $filters = []): array;
    public function getLastUpdated(): string;
    public function getProviderHealth(): array;
    public function normalizeMarket(array $market): array;
    public function normalizeTeamName(string $name): string;
    public function normalizeOddsFormat($odds, string $format = 'american'): array;
}

class LineforgeTheOddsApiAdapter implements ProviderOddsAdapter
{
    private array $oddsIndex;
    private string $lastUpdated = '';

    public function __construct(array $oddsIndex)
    {
        $this->oddsIndex = $oddsIndex;
    }

    public function providerName(): string
    {
        return 'The Odds API';
    }

    public function getSports(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn(array $record): string => (string) ($record['sportKey'] ?? ''),
            $this->oddsIndex
        ))));
    }

    public function getEvents(array $filters = []): array
    {
        return array_values(array_filter(array_map(
            static fn(array $record): array => is_array($record['event'] ?? null) ? $record['event'] : [],
            $this->oddsIndex
        )));
    }

    public function getMarkets(array $event): array
    {
        $markets = [];
        foreach ((array) ($event['bookmakers'] ?? []) as $bookmaker) {
            foreach ((array) ($bookmaker['markets'] ?? []) as $market) {
                if (is_array($market)) {
                    $markets[] = $market;
                }
            }
        }

        return $markets;
    }

    public function getOdds(array $filters = []): array
    {
        $rows = [];
        foreach ($this->oddsIndex as $record) {
            $event = is_array($record['event'] ?? null) ? $record['event'] : [];
            if (!$event) {
                continue;
            }

            $sportKey = (string) ($record['sportKey'] ?? $event['sport_key'] ?? '');
            $sportTitle = (string) ($event['sport_title'] ?? $sportKey);
            $eventId = (string) ($event['id'] ?? hash('sha256', json_encode($event)));
            $home = (string) ($event['home_team'] ?? '');
            $away = (string) ($event['away_team'] ?? '');
            $eventName = trim($away . ' @ ' . $home);
            $startTime = (string) ($event['commence_time'] ?? '');

            foreach ((array) ($event['bookmakers'] ?? []) as $bookmaker) {
                if (!is_array($bookmaker)) {
                    continue;
                }

                $providerKey = (string) ($bookmaker['key'] ?? '');
                $providerName = (string) ($bookmaker['title'] ?? $providerKey);
                foreach ((array) ($bookmaker['markets'] ?? []) as $market) {
                    if (!is_array($market)) {
                        continue;
                    }

                    $marketKey = (string) ($market['key'] ?? '');
                    $marketType = $this->normalizeMarket($market)['marketType'];
                    $lastUpdate = (string) ($market['last_update'] ?? $bookmaker['last_update'] ?? '');
                    if ($lastUpdate !== '') {
                        $this->lastUpdated = max($this->lastUpdated, $lastUpdate);
                    }

                    $marketOutcomes = array_values(array_filter((array) ($market['outcomes'] ?? []), 'is_array'));
                    $providerOutcomeCount = count($marketOutcomes);
                    foreach ($marketOutcomes as $outcome) {
                        if (!is_array($outcome)) {
                            continue;
                        }

                        $normalizedOdds = $this->normalizeOddsFormat($outcome['price'] ?? null, 'american');
                        if (empty($normalizedOdds['valid'])) {
                            continue;
                        }

                        $point = is_numeric($outcome['point'] ?? null) ? (float) $outcome['point'] : null;
                        $selection = (string) ($outcome['name'] ?? '');
                        $lineKey = lineforge_arbitrage_line_key($marketType, $point);
                        $selectionKey = lineforge_arbitrage_selection_key($marketType, $selection, $point);
                        $marketGroupId = lineforge_arbitrage_market_group_id($sportKey, $eventId, $marketType, $lineKey, 'full_game');
                        $ageSeconds = $lastUpdate !== '' ? max(0, time() - (strtotime($lastUpdate) ?: time())) : null;

                        $rows[] = [
                            'source' => 'licensed_provider',
                            'sourceProvider' => $this->providerName(),
                            'providerName' => $providerName,
                            'providerKey' => $providerKey,
                            'providerReliability' => lineforge_arbitrage_provider_reliability($providerKey),
                            'sport' => $sportTitle,
                            'sportKey' => $sportKey,
                            'league' => strtoupper(str_replace(['_', '-'], ' ', $sportKey)),
                            'eventId' => $eventId,
                            'eventName' => $eventName !== '@' ? $eventName : (string) ($event['id'] ?? 'Event'),
                            'homeTeam' => $home,
                            'awayTeam' => $away,
                            'normalizedHomeTeam' => $this->normalizeTeamName($home),
                            'normalizedAwayTeam' => $this->normalizeTeamName($away),
                            'startTime' => $startTime,
                            'period' => 'full_game',
                            'marketKey' => $marketKey,
                            'marketType' => $marketType,
                            'marketLabel' => lineforge_arbitrage_market_label($marketType),
                            'line' => $point,
                            'lineKey' => $lineKey,
                            'marketGroupId' => $marketGroupId,
                            'selection' => $selection,
                            'selectionKey' => $selectionKey,
                            'providerOutcomeCount' => $providerOutcomeCount,
                            'oddsAmerican' => $normalizedOdds['american'],
                            'decimalOdds' => $normalizedOdds['decimal'],
                            'fractionalOdds' => $normalizedOdds['fractional'],
                            'rawImpliedProbability' => $normalizedOdds['impliedProbability'],
                            'noVigProbability' => null,
                            'sportsbookMargin' => null,
                            'lastUpdated' => $lastUpdate,
                            'ageSeconds' => $ageSeconds,
                            'freshnessScore' => lineforge_arbitrage_freshness_score($ageSeconds),
                            'sourceConfidence' => lineforge_arbitrage_source_confidence($providerKey, $ageSeconds),
                            'liquidityScore' => lineforge_arbitrage_liquidity_score($providerKey, $marketType),
                            'betLimitKnown' => false,
                            'status' => 'active',
                        ];
                    }
                }
            }
        }

        return lineforge_arbitrage_attach_no_vig($rows);
    }

    public function getLastUpdated(): string
    {
        return $this->lastUpdated;
    }

    public function getProviderHealth(): array
    {
        $events = $this->getEvents();
        return [
            'provider' => $this->providerName(),
            'status' => $events ? 'connected' : 'not_configured',
            'events' => count($events),
            'lastUpdated' => $this->lastUpdated,
            'message' => $events
                ? 'Licensed odds feed returned normalized bookmaker markets.'
                : 'No The Odds API events are available. Configure a key or wait for supported events.',
        ];
    }

    public function normalizeMarket(array $market): array
    {
        $key = strtolower((string) ($market['key'] ?? 'unknown'));
        $type = match ($key) {
            'h2h', 'moneyline' => 'moneyline',
            'spreads', 'spread' => 'spread',
            'totals', 'total' => 'total',
            default => $key,
        };

        return [
            'marketType' => $type,
            'marketLabel' => lineforge_arbitrage_market_label($type),
        ];
    }

    public function normalizeTeamName(string $name): string
    {
        return lineforge_arbitrage_normalize_name($name);
    }

    public function normalizeOddsFormat($odds, string $format = 'american'): array
    {
        return lineforge_arbitrage_normalize_odds($odds, $format);
    }
}

function lineforge_arbitrage_market_label(string $marketType): string
{
    return match ($marketType) {
        'moneyline' => 'Moneyline',
        'spread' => 'Spread',
        'total' => 'Total',
        default => ucwords(str_replace('_', ' ', $marketType)),
    };
}

function lineforge_arbitrage_normalize_name(string $name): string
{
    $name = strtolower(trim($name));
    $name = preg_replace('/\b(fc|sc|cf|the|team)\b/', '', $name) ?? $name;
    $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;
    return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
}

function lineforge_arbitrage_normalize_odds($odds, string $format = 'american'): array
{
    if ($odds === null || $odds === '') {
        return ['valid' => false];
    }

    $format = strtolower($format);
    $decimal = null;
    $american = null;
    $fractional = '';

    if ($format === 'decimal') {
        $decimal = (float) $odds;
        $american = lineforge_arbitrage_decimal_to_american($decimal);
    } elseif ($format === 'fractional') {
        $parts = explode('/', (string) $odds);
        if (count($parts) === 2 && (float) $parts[1] !== 0.0) {
            $decimal = ((float) $parts[0] / (float) $parts[1]) + 1;
            $american = lineforge_arbitrage_decimal_to_american($decimal);
            $fractional = (string) $odds;
        }
    } elseif ($format === 'probability') {
        $probability = max(0.01, min(0.99, (float) $odds));
        $decimal = 1 / $probability;
        $american = lineforge_arbitrage_decimal_to_american($decimal);
    } else {
        $american = (int) $odds;
        if ($american > 0) {
            $decimal = 1 + ($american / 100);
        } elseif ($american < 0) {
            $decimal = 1 + (100 / abs($american));
        }
    }

    if ($decimal === null || $decimal <= 1.0) {
        return ['valid' => false];
    }

    return [
        'valid' => true,
        'american' => $american,
        'decimal' => round($decimal, 6),
        'fractional' => $fractional !== '' ? $fractional : lineforge_arbitrage_decimal_to_fractional($decimal),
        'impliedProbability' => 1 / $decimal,
    ];
}

function lineforge_arbitrage_decimal_to_american(float $decimal): int
{
    if ($decimal >= 2.0) {
        return (int) round(($decimal - 1) * 100);
    }

    return (int) round(-100 / max(0.01, $decimal - 1));
}

function lineforge_arbitrage_decimal_to_fractional(float $decimal): string
{
    $profit = max(0.01, $decimal - 1);
    $denominator = 100;
    $numerator = (int) round($profit * $denominator);
    $gcd = lineforge_arbitrage_gcd($numerator, $denominator);
    return (int) ($numerator / $gcd) . '/' . (int) ($denominator / $gcd);
}

function lineforge_arbitrage_gcd(int $a, int $b): int
{
    while ($b !== 0) {
        $temp = $b;
        $b = $a % $b;
        $a = $temp;
    }

    return max(1, abs($a));
}

function lineforge_arbitrage_line_key(string $marketType, ?float $line): string
{
    if ($marketType === 'moneyline' || $line === null) {
        return 'na';
    }

    if ($marketType === 'spread') {
        return number_format(abs($line), 1, '.', '');
    }

    return number_format($line, 1, '.', '');
}

function lineforge_arbitrage_selection_key(string $marketType, string $selection, ?float $line): string
{
    $selection = lineforge_arbitrage_normalize_name($selection);
    if ($marketType === 'total') {
        $side = str_contains($selection, 'under') ? 'under' : 'over';
        return $side . ':' . number_format((float) $line, 1, '.', '');
    }

    if ($marketType === 'spread') {
        return $selection . ':' . number_format((float) $line, 1, '.', '');
    }

    return $selection;
}

function lineforge_arbitrage_market_group_id(string $sportKey, string $eventId, string $marketType, string $lineKey, string $period): string
{
    return hash('sha256', strtolower($sportKey . '|' . $eventId . '|' . $marketType . '|' . $lineKey . '|' . $period));
}

function lineforge_arbitrage_provider_reliability(string $providerKey): float
{
    return match (strtolower($providerKey)) {
        'pinnacle', 'circa' => 0.96,
        'draftkings', 'fanduel', 'betmgm', 'williamhill_us', 'caesars', 'betrivers', 'espnbet', 'fanatics' => 0.88,
        default => 0.78,
    };
}

function lineforge_arbitrage_source_confidence(string $providerKey, ?int $ageSeconds): float
{
    return round(lineforge_arbitrage_provider_reliability($providerKey) * lineforge_arbitrage_freshness_score($ageSeconds), 4);
}

function lineforge_arbitrage_freshness_score(?int $ageSeconds): float
{
    if ($ageSeconds === null) {
        return 0.55;
    }

    if ($ageSeconds <= 30) return 1.0;
    if ($ageSeconds <= 90) return 0.92;
    if ($ageSeconds <= 180) return 0.80;
    if ($ageSeconds <= 600) return 0.58;
    return 0.30;
}

function lineforge_arbitrage_liquidity_score(string $providerKey, string $marketType): float
{
    $base = match (strtolower($providerKey)) {
        'pinnacle', 'draftkings', 'fanduel', 'betmgm', 'williamhill_us', 'caesars' => 0.86,
        default => 0.68,
    };

    $marketBoost = $marketType === 'moneyline' ? 0.05 : ($marketType === 'spread' ? 0.03 : 0.0);
    return min(1.0, $base + $marketBoost);
}

function lineforge_arbitrage_attach_no_vig(array $rows): array
{
    $groups = [];
    foreach ($rows as $index => $row) {
        $key = implode('|', [
            $row['providerKey'] ?? '',
            $row['eventId'] ?? '',
            $row['marketType'] ?? '',
            $row['lineKey'] ?? '',
            $row['period'] ?? '',
            $row['lastUpdated'] ?? '',
        ]);
        $groups[$key][] = $index;
    }

    foreach ($groups as $indexes) {
        $total = 0.0;
        foreach ($indexes as $index) {
            $total += (float) ($rows[$index]['rawImpliedProbability'] ?? 0);
        }

        if ($total <= 0) {
            continue;
        }

        foreach ($indexes as $index) {
            $raw = (float) ($rows[$index]['rawImpliedProbability'] ?? 0);
            $rows[$index]['noVigProbability'] = $raw / $total;
            $rows[$index]['sportsbookMargin'] = $total - 1;
        }
    }

    return $rows;
}

function lineforge_arbitrage_build_state(array $games, array $oddsIndex, array $marketAccess, int $refreshSeconds): array
{
    $adapter = new LineforgeTheOddsApiAdapter($oddsIndex);
    $rows = $adapter->getOdds();
    $groups = lineforge_arbitrage_group_rows($rows);
    $audit = [];
    $rejected = [];
    $pure = [];
    $middles = [];
    $positiveEv = [];

    foreach ($groups as $groupId => $groupRows) {
        $marketAudit = [
            'normalizedMarketId' => $groupId,
            'rowCount' => count($groupRows),
            'calculationSteps' => [],
        ];

        $validation = lineforge_arbitrage_validate_group($groupRows);
        if (!$validation['valid']) {
            $rejected[] = lineforge_arbitrage_rejection($groupRows, $validation['reason'], $validation['confidence']);
            $marketAudit['rejectionReason'] = $validation['reason'];
            $audit[] = $marketAudit;
            continue;
        }

        $consensus = lineforge_arbitrage_consensus($groupRows);
        $bestByOutcome = lineforge_arbitrage_best_by_outcome($groupRows);
        $arbSum = array_sum(array_map(static fn(array $row): float => 1 / (float) $row['decimalOdds'], $bestByOutcome));
        $marketAudit['calculationSteps'][] = ['step' => 'best_outcomes', 'outcomes' => array_keys($bestByOutcome)];
        $marketAudit['calculationSteps'][] = ['step' => 'arb_sum', 'value' => $arbSum];

        if ($arbSum < 1) {
            $opportunity = lineforge_arbitrage_opportunity($groupRows, $bestByOutcome, $consensus, $arbSum);
            $pure[] = $opportunity;
            $marketAudit['finalGrade'] = $opportunity['grade'];
            $marketAudit['arbSum'] = $arbSum;
        } else {
            $rejected[] = lineforge_arbitrage_rejection($groupRows, 'No pure arbitrage: best-outcome implied sum is >= 1.', $validation['confidence'], $arbSum);
            $marketAudit['rejectionReason'] = 'No pure arbitrage';
            $marketAudit['arbSum'] = $arbSum;
        }

        $middle = lineforge_arbitrage_middle_candidate($groupRows, $consensus);
        if ($middle) {
            $middles[] = $middle;
        }

        $positiveEv = array_merge($positiveEv, lineforge_arbitrage_positive_ev($groupRows, $consensus));
        $audit[] = $marketAudit;
    }

    $middles = array_merge($middles, lineforge_arbitrage_cross_line_middles($rows));
    usort($pure, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: ($b['roiPercent'] <=> $a['roiPercent']));
    usort($middles, static fn(array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
    usort($positiveEv, static fn(array $a, array $b): int => $b['edgePercent'] <=> $a['edgePercent']);

    $freshRows = array_filter($rows, static fn(array $row): bool => (int) ($row['ageSeconds'] ?? 99999) <= 180);
    $averageAge = $rows
        ? (int) round(array_sum(array_map(static fn(array $row): int => (int) ($row['ageSeconds'] ?? 600), $rows)) / count($rows))
        : null;
    $providers = array_values(array_unique(array_map(static fn(array $row): string => (string) ($row['providerName'] ?? ''), $rows)));
    $providerHealth = lineforge_arbitrage_provider_health($marketAccess, $adapter->getProviderHealth(), $rows);

    return [
        'summary' => [
            'activeArbs' => count($pure),
            'bestGuaranteedRoi' => $pure ? round((float) ($pure[0]['roiPercent'] ?? 0), 2) : 0,
            'averageFreshnessSeconds' => $averageAge,
            'averageFreshnessLabel' => $averageAge === null ? 'No odds' : lineforge_arbitrage_age_label($averageAge),
            'connectedBooks' => count($providers),
            'rejectedOpportunities' => count($rejected),
            'providerHealth' => $providerHealth['overall'],
            'normalizedOdds' => count($rows),
            'freshRows' => count($freshRows),
        ],
        'providerHealth' => $providerHealth,
        'opportunities' => array_slice($pure, 0, 30),
        'rejected' => array_slice($rejected, 0, 40),
        'middles' => array_slice($middles, 0, 20),
        'scalps' => [],
        'positiveEv' => array_slice($positiveEv, 0, 20),
        'normalizedOdds' => array_slice($rows, 0, 500),
        'audit' => array_slice($audit, -120),
        'refresh' => [
            'intervalSeconds' => max(90, min(900, $refreshSeconds * 6)),
            'lastUpdated' => $adapter->getLastUpdated(),
            'requiresPreActionRecheck' => true,
            'message' => 'Odds are cached for provider-budget safety and must be re-checked before manual or future live execution.',
        ],
        'adapterContract' => [
            'providerName',
            'getSports',
            'getEvents',
            'getMarkets',
            'getOdds',
            'getLastUpdated',
            'getProviderHealth',
            'normalizeMarket',
            'normalizeTeamName',
            'normalizeOddsFormat',
        ],
        'supportedSources' => lineforge_arbitrage_supported_sources($marketAccess),
    ];
}

function lineforge_arbitrage_group_rows(array $rows): array
{
    $groups = [];
    foreach ($rows as $row) {
        $groups[(string) ($row['marketGroupId'] ?? 'unknown')][] = $row;
    }

    return $groups;
}

function lineforge_arbitrage_validate_group(array $rows): array
{
    if (count($rows) < 2) {
        return ['valid' => false, 'reason' => 'Only one outcome/provider row exists.', 'confidence' => 0.1];
    }

    $marketTypes = array_unique(array_map(static fn(array $row): string => (string) ($row['marketType'] ?? ''), $rows));
    $lineKeys = array_unique(array_map(static fn(array $row): string => (string) ($row['lineKey'] ?? ''), $rows));
    $periods = array_unique(array_map(static fn(array $row): string => (string) ($row['period'] ?? ''), $rows));
    if (count($marketTypes) !== 1 || count($lineKeys) !== 1 || count($periods) !== 1) {
        return ['valid' => false, 'reason' => 'Mismatched market type, line, or period.', 'confidence' => 0.2];
    }

    $outcomes = array_unique(array_map(static fn(array $row): string => (string) ($row['selectionKey'] ?? ''), $rows));
    if (count($outcomes) < 2) {
        return ['valid' => false, 'reason' => 'Market does not have enough distinct outcomes.', 'confidence' => 0.2];
    }

    $expectedOutcomeCount = max(
        count($outcomes),
        ...array_values(array_filter(array_map(static fn(array $row): int => (int) ($row['providerOutcomeCount'] ?? 0), $rows)))
    );
    if ($expectedOutcomeCount > count($outcomes)) {
        return ['valid' => false, 'reason' => 'Incomplete outcome set for this market; missing outcomes would create a false arbitrage.', 'confidence' => 0.25];
    }

    $providers = array_unique(array_map(static fn(array $row): string => (string) ($row['providerKey'] ?? ''), $rows));
    if (count($providers) < 2) {
        return ['valid' => false, 'reason' => 'Only one sportsbook/provider is present.', 'confidence' => 0.45];
    }

    $staleCount = count(array_filter($rows, static fn(array $row): bool => (int) ($row['ageSeconds'] ?? 99999) > 600));
    if ($staleCount === count($rows)) {
        return ['valid' => false, 'reason' => 'All odds rows are stale beyond the pure-arbitrage threshold.', 'confidence' => 0.15];
    }
    $confidence = max(0.2, 1 - ($staleCount / max(1, count($rows))) * 0.45);

    return ['valid' => true, 'reason' => '', 'confidence' => $confidence];
}

function lineforge_arbitrage_consensus(array $rows): array
{
    $byOutcome = [];
    foreach ($rows as $row) {
        $key = (string) ($row['selectionKey'] ?? '');
        $weight = ((float) ($row['freshnessScore'] ?? 0.5))
            * ((float) ($row['providerReliability'] ?? 0.75))
            * ((float) ($row['liquidityScore'] ?? 0.65));
        $byOutcome[$key][] = ['row' => $row, 'weight' => max(0.01, $weight)];
    }

    $outcomes = [];
    foreach ($byOutcome as $key => $items) {
        $weightedProbability = 0.0;
        $weightSum = 0.0;
        $decimals = [];
        foreach ($items as $item) {
            $row = $item['row'];
            $weight = (float) $item['weight'];
            $probability = (float) ($row['noVigProbability'] ?? $row['rawImpliedProbability'] ?? 0);
            $weightedProbability += $probability * $weight;
            $weightSum += $weight;
            $decimals[] = (float) ($row['decimalOdds'] ?? 0);
        }
        sort($decimals);
        $outcomes[$key] = [
            'selectionKey' => $key,
            'selection' => (string) ($items[0]['row']['selection'] ?? $key),
            'weightedNoVigProbability' => $weightSum > 0 ? $weightedProbability / $weightSum : 0,
            'averageDecimalOdds' => $decimals ? array_sum($decimals) / count($decimals) : 0,
            'medianDecimalOdds' => $decimals ? $decimals[(int) floor((count($decimals) - 1) / 2)] : 0,
            'providerCount' => count($items),
        ];
    }

    return [
        'outcomes' => $outcomes,
        'providerCount' => count(array_unique(array_map(static fn(array $row): string => (string) ($row['providerKey'] ?? ''), $rows))),
    ];
}

function lineforge_arbitrage_best_by_outcome(array $rows): array
{
    $best = [];
    foreach ($rows as $row) {
        $key = (string) ($row['selectionKey'] ?? '');
        if ($key === '') {
            continue;
        }

        if (!isset($best[$key]) || (float) ($row['decimalOdds'] ?? 0) > (float) ($best[$key]['decimalOdds'] ?? 0)) {
            $best[$key] = $row;
        }
    }

    return $best;
}

function lineforge_arbitrage_opportunity(array $rows, array $bestByOutcome, array $consensus, float $arbSum): array
{
    $first = $rows[0];
    $totalStake = 100.00;
    $stakePlan = [];
    foreach ($bestByOutcome as $key => $row) {
        $stake = $totalStake * ((1 / (float) $row['decimalOdds']) / $arbSum);
        $payout = $stake * (float) $row['decimalOdds'];
        $stakePlan[] = [
            'selectionKey' => $key,
            'selection' => (string) ($row['selection'] ?? $key),
            'book' => (string) ($row['providerName'] ?? 'Book'),
            'providerKey' => (string) ($row['providerKey'] ?? ''),
            'decimalOdds' => (float) $row['decimalOdds'],
            'americanOdds' => (int) $row['oddsAmerican'],
            'stake' => round($stake, 2),
            'expectedPayout' => round($payout, 2),
            'ageSeconds' => $row['ageSeconds'],
        ];
    }

    $payout = $stakePlan ? min(array_map(static fn(array $leg): float => (float) $leg['expectedPayout'], $stakePlan)) : 0;
    $profit = $payout - $totalStake;
    $roi = $totalStake > 0 ? ($profit / $totalStake) * 100 : 0;
    $averageAge = (int) round(array_sum(array_map(static fn(array $row): int => (int) ($row['ageSeconds'] ?? 600), $bestByOutcome)) / max(1, count($bestByOutcome)));
    $confidence = lineforge_arbitrage_opportunity_confidence($rows, $roi, $averageAge, count($bestByOutcome));
    $warnings = lineforge_arbitrage_risk_warnings($rows, $averageAge);
    $grade = lineforge_arbitrage_grade($roi, $confidence, $averageAge, $warnings);
    $score = ($roi * 10) + ($confidence * 50) - min(30, count($warnings) * 6) - min(20, $averageAge / 60);

    return [
        'id' => 'arb_' . substr(hash('sha256', ($first['marketGroupId'] ?? '') . json_encode($stakePlan)), 0, 12),
        'sport' => (string) ($first['sport'] ?? 'Sports'),
        'league' => (string) ($first['league'] ?? ''),
        'event' => (string) ($first['eventName'] ?? 'Event'),
        'eventId' => (string) ($first['eventId'] ?? ''),
        'market' => (string) ($first['marketLabel'] ?? 'Market'),
        'marketType' => (string) ($first['marketType'] ?? ''),
        'line' => (string) ($first['lineKey'] ?? 'na'),
        'period' => (string) ($first['period'] ?? 'full_game'),
        'outcomes' => array_values($bestByOutcome),
        'stakePlan' => $stakePlan,
        'totalStake' => $totalStake,
        'guaranteedPayout' => round($payout, 2),
        'guaranteedProfit' => round($profit, 2),
        'roiPercent' => round($roi, 3),
        'arbSum' => round($arbSum, 6),
        'arbMarginPercent' => round((1 - $arbSum) * 100, 3),
        'dataAgeSeconds' => $averageAge,
        'dataAgeLabel' => lineforge_arbitrage_age_label($averageAge),
        'confidence' => round($confidence * 100),
        'grade' => $grade,
        'score' => round($score, 2),
        'consensus' => $consensus,
        'allOdds' => array_values($rows),
        'riskWarnings' => $warnings,
        'manualChecklist' => [
            'Re-check every price inside the official sportsbook or licensed provider.',
            'Confirm exact market, line, period, and settlement rules match.',
            'Confirm account eligibility, location, limits, and market status.',
            'Enter legs quickly, smallest/most fragile limit first.',
            'Cancel the plan if any price moves or market suspends.',
        ],
        'exportSlipNotes' => lineforge_arbitrage_slip_notes($stakePlan, $profit, $roi),
    ];
}

function lineforge_arbitrage_opportunity_confidence(array $rows, float $roi, int $averageAge, int $outcomes): float
{
    $providerCount = count(array_unique(array_map(static fn(array $row): string => (string) ($row['providerKey'] ?? ''), $rows)));
    $freshness = lineforge_arbitrage_freshness_score($averageAge);
    $avgReliability = array_sum(array_map(static fn(array $row): float => (float) ($row['providerReliability'] ?? 0.75), $rows)) / max(1, count($rows));
    $avgLiquidity = array_sum(array_map(static fn(array $row): float => (float) ($row['liquidityScore'] ?? 0.65), $rows)) / max(1, count($rows));
    $providerDepth = min(1, $providerCount / max(2, $outcomes + 1));
    $roiStability = $roi >= 0.2 ? 1.0 : max(0.4, $roi / 0.2);

    return max(0.05, min(1.0, ($freshness * 0.32) + ($avgReliability * 0.25) + ($avgLiquidity * 0.18) + ($providerDepth * 0.15) + ($roiStability * 0.10)));
}

function lineforge_arbitrage_grade(float $roi, float $confidence, int $averageAge, array $warnings): string
{
    if ($roi <= 0 || $confidence < 0.35 || count($warnings) >= 5) return 'F';
    if ($averageAge > 600 || count($warnings) >= 4) return 'D';
    if ($roi >= 1.2 && $confidence >= 0.86 && $averageAge <= 90 && count($warnings) <= 1) return 'A+';
    if ($roi >= 0.65 && $confidence >= 0.76 && $averageAge <= 180 && count($warnings) <= 2) return 'A';
    if ($roi >= 0.30 && $confidence >= 0.62 && $averageAge <= 360) return 'B';
    return 'C';
}

function lineforge_arbitrage_risk_warnings(array $rows, int $averageAge): array
{
    $warnings = [];
    if ($averageAge > 180) {
        $warnings[] = 'Odds data is older than three minutes; re-check before acting.';
    }
    if (count(array_unique(array_map(static fn(array $row): string => (string) ($row['providerKey'] ?? ''), $rows))) < 3) {
        $warnings[] = 'Limited provider depth; account limits or stale lines can erase the margin.';
    }
    if (count(array_filter($rows, static fn(array $row): bool => empty($row['betLimitKnown']))) > 0) {
        $warnings[] = 'Sportsbook bet limits are unknown.';
    }
    if (count(array_filter($rows, static fn(array $row): bool => (float) ($row['liquidityScore'] ?? 0) < 0.7)) > 0) {
        $warnings[] = 'Liquidity is estimated or thin on at least one side.';
    }
    if (count(array_filter($rows, static fn(array $row): bool => (string) ($row['status'] ?? 'active') !== 'active')) > 0) {
        $warnings[] = 'One or more markets may not be active.';
    }

    return array_values(array_unique($warnings));
}

function lineforge_arbitrage_rejection(array $rows, string $reason, float $confidence = 0.0, ?float $arbSum = null): array
{
    $first = $rows[0] ?? [];
    return [
        'id' => 'rej_' . substr(hash('sha256', ($first['marketGroupId'] ?? '') . $reason), 0, 12),
        'event' => (string) ($first['eventName'] ?? 'Market'),
        'market' => (string) ($first['marketLabel'] ?? 'Market'),
        'line' => (string) ($first['lineKey'] ?? 'na'),
        'reason' => $reason,
        'matchingConfidence' => round($confidence * 100),
        'arbSum' => $arbSum !== null ? round($arbSum, 6) : null,
        'sourceRows' => count($rows),
        'createdAt' => gmdate('c'),
    ];
}

function lineforge_arbitrage_middle_candidate(array $rows, array $consensus): ?array
{
    $marketType = (string) ($rows[0]['marketType'] ?? '');
    if (!in_array($marketType, ['spread', 'total'], true)) {
        return null;
    }

    $points = array_values(array_filter(array_map(static fn(array $row) => $row['line'], $rows), 'is_numeric'));
    if (count(array_unique($points)) < 1) {
        return null;
    }

    $bestByOutcome = lineforge_arbitrage_best_by_outcome($rows);
    $roiProxy = 0;
    foreach ($bestByOutcome as $row) {
        $roiProxy += max(0, (float) ($row['decimalOdds'] ?? 0) - 1.90);
    }

    if ($roiProxy <= 0.05) {
        return null;
    }

    $first = $rows[0];
    return [
        'id' => 'mid_' . substr(hash('sha256', ($first['marketGroupId'] ?? '') . 'middle'), 0, 12),
        'type' => $marketType === 'total' ? 'Middle' : 'Spread middle',
        'event' => (string) ($first['eventName'] ?? 'Event'),
        'market' => (string) ($first['marketLabel'] ?? 'Market'),
        'line' => (string) ($first['lineKey'] ?? ''),
        'grade' => 'B',
        'detail' => 'Not a pure arbitrage. Treat as a middle/scalp candidate and verify exact lines.',
        'bestLegs' => array_values($bestByOutcome),
        'consensus' => $consensus,
    ];
}

function lineforge_arbitrage_cross_line_middles(array $rows): array
{
    $clusters = [];
    foreach ($rows as $row) {
        $marketType = (string) ($row['marketType'] ?? '');
        if (!in_array($marketType, ['spread', 'total'], true) || !is_numeric($row['line'] ?? null)) {
            continue;
        }

        $key = implode('|', [
            $row['eventId'] ?? '',
            $marketType,
            $row['period'] ?? 'full_game',
        ]);
        $clusters[$key][] = $row;
    }

    $candidates = [];
    $seen = [];
    foreach ($clusters as $clusterRows) {
        $count = count($clusterRows);
        for ($leftIndex = 0; $leftIndex < $count; $leftIndex += 1) {
            for ($rightIndex = $leftIndex + 1; $rightIndex < $count; $rightIndex += 1) {
                $left = $clusterRows[$leftIndex];
                $right = $clusterRows[$rightIndex];
                if ((string) ($left['providerKey'] ?? '') === (string) ($right['providerKey'] ?? '')) {
                    continue;
                }

                $candidate = lineforge_arbitrage_cross_line_middle_pair($left, $right);
                if (!$candidate) {
                    continue;
                }

                $dedupeKey = $candidate['event'] . '|' . $candidate['market'] . '|' . $candidate['line'] . '|' . implode('|', array_map(
                    static fn(array $leg): string => (string) ($leg['providerKey'] ?? '') . ':' . (string) ($leg['selectionKey'] ?? ''),
                    $candidate['bestLegs']
                ));
                if (isset($seen[$dedupeKey])) {
                    continue;
                }

                $seen[$dedupeKey] = true;
                $candidates[] = $candidate;
            }
        }
    }

    return $candidates;
}

function lineforge_arbitrage_cross_line_middle_pair(array $left, array $right): ?array
{
    $marketType = (string) ($left['marketType'] ?? '');
    if ($marketType !== (string) ($right['marketType'] ?? '')) {
        return null;
    }

    $leftLine = (float) $left['line'];
    $rightLine = (float) $right['line'];
    $legs = [];
    $width = 0.0;
    $type = '';

    if ($marketType === 'total') {
        $leftSide = str_contains((string) ($left['selectionKey'] ?? ''), 'over') ? 'over' : (str_contains((string) ($left['selectionKey'] ?? ''), 'under') ? 'under' : '');
        $rightSide = str_contains((string) ($right['selectionKey'] ?? ''), 'over') ? 'over' : (str_contains((string) ($right['selectionKey'] ?? ''), 'under') ? 'under' : '');
        if ($leftSide === $rightSide || $leftSide === '' || $rightSide === '') {
            return null;
        }

        $over = $leftSide === 'over' ? $left : $right;
        $under = $leftSide === 'under' ? $left : $right;
        $overLine = (float) $over['line'];
        $underLine = (float) $under['line'];
        if ($overLine >= $underLine) {
            return null;
        }

        $width = $underLine - $overLine;
        $legs = [$over, $under];
        $type = 'Total middle';
    } elseif ($marketType === 'spread') {
        if ((string) ($left['selectionKey'] ?? '') === (string) ($right['selectionKey'] ?? '')) {
            return null;
        }

        $negative = $leftLine < 0 ? $left : ($rightLine < 0 ? $right : null);
        $positive = $leftLine > 0 ? $left : ($rightLine > 0 ? $right : null);
        if (!$negative || !$positive) {
            return null;
        }

        $width = abs((float) $positive['line']) - abs((float) $negative['line']);
        if ($width <= 0) {
            return null;
        }

        $legs = [$negative, $positive];
        $type = 'Spread middle';
    }

    if ($width <= 0 || !$legs) {
        return null;
    }

    $minOdds = min(array_map(static fn(array $leg): float => (float) ($leg['decimalOdds'] ?? 0), $legs));
    if ($minOdds < 1.82) {
        return null;
    }

    $averageAge = (int) round(array_sum(array_map(static fn(array $leg): int => (int) ($leg['ageSeconds'] ?? 600), $legs)) / max(1, count($legs)));
    $oddsScore = array_sum(array_map(static fn(array $leg): float => max(0.0, (float) ($leg['decimalOdds'] ?? 0) - 1.90), $legs));
    $score = ($width * 12) + ($oddsScore * 18) + (lineforge_arbitrage_freshness_score($averageAge) * 10);
    $grade = $width >= 1.5 && $averageAge <= 180 ? 'A' : ($width >= 1.0 ? 'B' : 'C');
    $lineLabel = implode(' / ', array_map(
        static fn(array $leg): string => (string) ($leg['selection'] ?? 'Leg') . ' ' . number_format((float) ($leg['line'] ?? 0), 1),
        $legs
    ));
    $first = $legs[0];

    return [
        'id' => 'mid_' . substr(hash('sha256', ($first['eventId'] ?? '') . $marketType . $lineLabel), 0, 12),
        'type' => $type,
        'event' => (string) ($first['eventName'] ?? 'Event'),
        'market' => (string) ($first['marketLabel'] ?? 'Market'),
        'line' => $lineLabel,
        'middleWidth' => round($width, 2),
        'grade' => $grade,
        'score' => round($score, 2),
        'detail' => sprintf('Cross-line middle window of %0.1f. This is not guaranteed arbitrage; verify exact settlement rules, limits, and current prices.', $width),
        'bestLegs' => array_values($legs),
        'dataAgeSeconds' => $averageAge,
        'dataAgeLabel' => lineforge_arbitrage_age_label($averageAge),
        'consensus' => [],
    ];
}

function lineforge_arbitrage_positive_ev(array $rows, array $consensus): array
{
    $items = [];
    foreach ($rows as $row) {
        $key = (string) ($row['selectionKey'] ?? '');
        $fair = (float) ($consensus['outcomes'][$key]['weightedNoVigProbability'] ?? 0);
        if ($fair <= 0) {
            continue;
        }

        $marketImplied = (float) ($row['rawImpliedProbability'] ?? 0);
        $edge = ($fair - $marketImplied) * 100;
        if ($edge < 2.0) {
            continue;
        }

        $items[] = [
            'id' => 'pev_' . substr(hash('sha256', ($row['marketGroupId'] ?? '') . $key . ($row['providerKey'] ?? '')), 0, 12),
            'event' => (string) ($row['eventName'] ?? 'Event'),
            'market' => (string) ($row['marketLabel'] ?? 'Market'),
            'selection' => (string) ($row['selection'] ?? ''),
            'book' => (string) ($row['providerName'] ?? ''),
            'odds' => (int) ($row['oddsAmerican'] ?? 0),
            'edgePercent' => round($edge, 2),
            'consensusProbability' => round($fair * 100, 2),
            'warning' => 'Positive EV is not guaranteed profit and must be separated from pure arbitrage.',
        ];
    }

    return $items;
}

function lineforge_arbitrage_age_label(?int $seconds): string
{
    if ($seconds === null) return 'No odds';
    if ($seconds < 60) return $seconds . 's';
    if ($seconds < 3600) return floor($seconds / 60) . 'm';
    return floor($seconds / 3600) . 'h';
}

function lineforge_arbitrage_slip_notes(array $stakePlan, float $profit, float $roi): string
{
    $lines = ['Lineforge arb plan - verify prices before action'];
    foreach ($stakePlan as $leg) {
        $lines[] = sprintf('%s: %s at %s (%+d) stake $%0.2f', $leg['book'], $leg['selection'], $leg['decimalOdds'], $leg['americanOdds'], $leg['stake']);
    }
    $lines[] = sprintf('Projected guaranteed profit: $%0.2f / ROI %0.2f%%', $profit, $roi);
    return implode("\n", $lines);
}

function lineforge_arbitrage_provider_health(array $marketAccess, array $theOddsHealth, array $rows): array
{
    $officialProviders = [
        'the_odds_api' => [
            'name' => 'The Odds API',
            'status' => !empty($marketAccess['oddsProviderConfigured']) ? ($rows ? 'connected' : 'configured_no_rows') : 'needs_key',
            'execution' => 'data_only',
            'message' => (string) ($theOddsHealth['message'] ?? 'Licensed odds data adapter.'),
        ],
        'sportsdataio' => [
            'name' => 'SportsDataIO',
            'status' => getenv('AEGIS_SPORTSDATA_API_KEY') ? 'configured' : 'not_configured',
            'execution' => 'data_only',
            'message' => 'Use official SportsDataIO odds feeds when an API key is configured.',
        ],
        'sportradar' => [
            'name' => 'Sportradar',
            'status' => getenv('AEGIS_SPORTRADAR_API_KEY') ? 'configured' : 'not_configured',
            'execution' => 'data_only',
            'message' => 'Use official Sportradar odds APIs/UOF through approved credentials.',
        ],
        'kalshi' => [
            'name' => 'Kalshi',
            'status' => ((int) ($marketAccess['kalshiMarketsCached'] ?? 0)) > 0 ? 'market_data' : 'not_configured',
            'execution' => 'official_api_available',
            'message' => 'Kalshi event contracts are compared only where rules and market type are equivalent.',
        ],
        'direct_sportsbooks' => [
            'name' => 'Direct sportsbook feeds',
            'status' => 'requires_partner_access',
            'execution' => 'unavailable',
            'message' => 'DraftKings, FanDuel, BetMGM, Caesars, and others are data-only unless an official feed or licensed provider is configured.',
        ],
    ];

    $connected = count(array_filter($officialProviders, static fn(array $provider): bool => in_array($provider['status'], ['connected', 'configured', 'market_data'], true)));
    $overall = $connected > 0 ? 'operational' : 'needs_data';

    return [
        'overall' => $overall,
        'providers' => array_values($officialProviders),
    ];
}

function lineforge_arbitrage_supported_sources(array $marketAccess): array
{
    return [
        ['name' => 'The Odds API', 'status' => !empty($marketAccess['oddsProviderConfigured']) ? 'Connected' : 'Needs API key', 'mode' => 'Licensed odds data'],
        ['name' => 'SportsDataIO', 'status' => getenv('AEGIS_SPORTSDATA_API_KEY') ? 'Configured' : 'Not configured', 'mode' => 'Official/licensed odds data'],
        ['name' => 'Sportradar', 'status' => getenv('AEGIS_SPORTRADAR_API_KEY') ? 'Configured' : 'Not configured', 'mode' => 'Official odds API/UOF'],
        ['name' => 'Pinnacle data', 'status' => getenv('AEGIS_PINNACLE_ODDS_KEY') ? 'Configured' : 'Not configured', 'mode' => 'Official/licensed data only'],
        ['name' => 'DraftKings/FanDuel/BetMGM/Caesars', 'status' => 'Partner or licensed feed required', 'mode' => 'No scraping or browser automation'],
        ['name' => 'Kalshi', 'status' => ((int) ($marketAccess['kalshiMarketsCached'] ?? 0)) > 0 ? 'Market data cached' : 'Official API ready for config', 'mode' => 'Official API only'],
    ];
}
