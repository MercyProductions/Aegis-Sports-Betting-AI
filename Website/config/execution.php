<?php

require_once __DIR__ . '/sports_product.php';

interface LineforgeProviderInterface
{
    public function connectAccount(array $credentials): array;
    public function getAccountStatus(): array;
    public function getBalance(): array;
    public function getMarkets(array $filters = []): array;
    public function getMarketPrice(string $marketId): array;
    public function getOrderBook(string $marketId): array;
    public function placeOrder(array $order): array;
    public function cancelOrder(string $orderId): array;
    public function sellPosition(array $position): array;
    public function getOpenOrders(): array;
    public function getPositions(): array;
    public function getTradeHistory(array $filters = []): array;
    public function healthCheck(): array;
}

function lineforge_execution_now(): string
{
    return gmdate('c');
}

function lineforge_execution_state_path(): string
{
    return aegis_sports_product_storage_dir() . DIRECTORY_SEPARATOR . 'execution-state.json';
}

function lineforge_execution_user_key(array $account): string
{
    $id = trim((string) ($account['id'] ?? ''));
    if ($id !== '') {
        return $id;
    }

    $email = strtolower(trim((string) ($account['email'] ?? 'local-operator')));
    return hash('sha256', $email !== '' ? $email : 'local-operator');
}

function lineforge_execution_root_default(): array
{
    return [
        'schema_version' => 1,
        'users' => [],
    ];
}

function lineforge_execution_default_user_state(array $account = []): array
{
    $now = lineforge_execution_now();

    return [
        'userAccount' => [
            'id' => (string) ($account['id'] ?? ''),
            'email' => (string) ($account['email'] ?? ''),
            'displayName' => (string) ($account['username'] ?? 'Lineforge Operator'),
            'tier' => (string) ($account['tier'] ?? 'pro'),
            'createdAt' => (string) ($account['created_at'] ?? ''),
        ],
        'mode' => 'paper',
        'liveEnabled' => false,
        'liveOptInAt' => '',
        'emergencyStop' => false,
        'paperBalance' => 10000.00,
        'dailyRealizedLoss' => 0.00,
        'dailyLossDate' => gmdate('Y-m-d'),
        'providerConnections' => [
            'paper' => [
                'provider' => 'paper',
                'label' => 'Paper Simulation',
                'status' => 'connected',
                'mode' => 'paper',
                'executionSupported' => true,
                'connectedAt' => $now,
                'lastCheckedAt' => $now,
            ],
            'kalshi' => [
                'provider' => 'kalshi',
                'label' => 'Kalshi',
                'status' => 'not_connected',
                'environment' => 'demo',
                'executionSupported' => true,
                'officialApiOnly' => true,
                'credentialsEncrypted' => false,
                'keyIdMasked' => '',
                'lastCheckedAt' => '',
                'message' => 'Connect demo API credentials before live execution.',
            ],
            'fanduel' => [
                'provider' => 'fanduel',
                'label' => 'FanDuel',
                'status' => 'data_only',
                'executionSupported' => false,
                'officialApiOnly' => true,
                'credentialsEncrypted' => false,
                'message' => 'Execution is unsupported unless FanDuel provides an approved partner API.',
            ],
        ],
        'watchlists' => [],
        'markets' => [],
        'rules' => [],
        'orders' => [],
        'positions' => [],
        'trades' => [],
        'auditLogs' => [],
        'providerHealth' => [
            'paper' => [
                'provider' => 'paper',
                'status' => 'operational',
                'latencyMs' => 0,
                'checkedAt' => $now,
                'message' => 'Paper simulation is local and available.',
            ],
            'kalshi' => [
                'provider' => 'kalshi',
                'status' => 'not_connected',
                'latencyMs' => null,
                'checkedAt' => '',
                'message' => 'Awaiting official API credentials.',
            ],
            'fanduel' => [
                'provider' => 'fanduel',
                'status' => 'data_only',
                'latencyMs' => null,
                'checkedAt' => $now,
                'message' => 'Odds/data only. No execution route is enabled.',
            ],
        ],
        'riskLimits' => [
            'maxStakePerOrder' => 25.00,
            'maxDailyLoss' => 100.00,
            'cooldownMinutes' => 5,
            'selfExcluded' => false,
            'emergencyDisabled' => false,
            'requireManualConfirmation' => true,
            'blockStaleMarketDataSeconds' => 120,
            'allowLiveAuto' => false,
            'legalEligibilityRequired' => true,
        ],
        'lastRuleActions' => [],
        'lastLiveActionAt' => '',
        'modelCatalog' => lineforge_execution_model_catalog(),
        'createdAt' => $now,
        'updatedAt' => $now,
    ];
}

function lineforge_execution_model_catalog(): array
{
    return [
        'UserAccount' => ['id', 'email', 'displayName', 'tier', 'createdAt'],
        'ProviderConnection' => ['provider', 'status', 'environment', 'credentialsEncrypted', 'officialApiOnly', 'lastCheckedAt'],
        'Market' => ['provider', 'ticker', 'title', 'status', 'probability', 'liquidity', 'updatedAt'],
        'Watchlist' => ['id', 'name', 'markets', 'createdAt'],
        'ExecutionRule' => ['id', 'provider', 'marketTicker', 'side', 'stake', 'entry', 'exit', 'expiration', 'confirmationMode', 'status'],
        'RuleCondition' => ['metric', 'operator', 'value'],
        'RuleAction' => ['type', 'side', 'stake', 'maxPrice', 'clientOrderId'],
        'Order' => ['id', 'provider', 'marketTicker', 'side', 'status', 'clientOrderId', 'estimatedCost', 'providerResponse'],
        'Position' => ['id', 'provider', 'marketTicker', 'side', 'contracts', 'averagePrice', 'unrealizedPnl'],
        'Trade' => ['id', 'orderId', 'provider', 'marketTicker', 'side', 'contracts', 'price', 'fees', 'createdAt'],
        'AuditLog' => ['id', 'type', 'severity', 'message', 'context', 'createdAt'],
        'RiskLimit' => ['maxStakePerOrder', 'maxDailyLoss', 'cooldownMinutes', 'selfExcluded', 'emergencyDisabled'],
        'ProviderHealth' => ['provider', 'status', 'latencyMs', 'message', 'checkedAt'],
    ];
}

function lineforge_execution_deep_merge(array $base, array $overlay): array
{
    foreach ($overlay as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = lineforge_execution_deep_merge($base[$key], $value);
            continue;
        }

        $base[$key] = $value;
    }

    return $base;
}

function lineforge_execution_sanitize_state(array $state): array
{
    $state['auditLogs'] = array_slice(array_values((array) ($state['auditLogs'] ?? [])), -160);
    $state['orders'] = array_slice(array_values((array) ($state['orders'] ?? [])), -120);
    $state['trades'] = array_slice(array_values((array) ($state['trades'] ?? [])), -160);
    $state['positions'] = array_values((array) ($state['positions'] ?? []));
    $state['rules'] = array_values((array) ($state['rules'] ?? []));
    $state['watchlists'] = array_values((array) ($state['watchlists'] ?? []));
    $state['markets'] = array_values((array) ($state['markets'] ?? []));
    $state['updatedAt'] = lineforge_execution_now();

    if (($state['dailyLossDate'] ?? '') !== gmdate('Y-m-d')) {
        $state['dailyRealizedLoss'] = 0.00;
        $state['dailyLossDate'] = gmdate('Y-m-d');
    }

    return $state;
}

function lineforge_execution_load_root(): array
{
    $path = lineforge_execution_state_path();
    if (!is_file($path)) {
        return lineforge_execution_root_default();
    }

    $payload = @file_get_contents($path);
    $decoded = json_decode((string) $payload, true);

    return lineforge_execution_deep_merge(
        lineforge_execution_root_default(),
        is_array($decoded) ? $decoded : []
    );
}

function lineforge_execution_read_user_state(array $account): array
{
    $root = lineforge_execution_load_root();
    $key = lineforge_execution_user_key($account);
    $state = is_array($root['users'][$key] ?? null) ? $root['users'][$key] : [];

    return lineforge_execution_sanitize_state(
        lineforge_execution_deep_merge(lineforge_execution_default_user_state($account), $state)
    );
}

function lineforge_execution_with_user_state(array $account, callable $mutator): array
{
    $path = lineforge_execution_state_path();
    $handle = @fopen($path, 'c+b');
    if (!$handle) {
        throw new RuntimeException('Unable to open execution storage.');
    }

    flock($handle, LOCK_EX);
    rewind($handle);
    $payload = stream_get_contents($handle);
    $decoded = json_decode((string) $payload, true);
    $root = lineforge_execution_deep_merge(
        lineforge_execution_root_default(),
        is_array($decoded) ? $decoded : []
    );

    $key = lineforge_execution_user_key($account);
    $state = lineforge_execution_deep_merge(
        lineforge_execution_default_user_state($account),
        is_array($root['users'][$key] ?? null) ? $root['users'][$key] : []
    );

    $result = $mutator($state);
    $state = lineforge_execution_sanitize_state($state);
    $root['users'][$key] = $state;

    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($root, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return is_array($result) ? $result : $state;
}

function lineforge_execution_public_state(array $account): array
{
    $state = lineforge_execution_read_user_state($account);
    $state = lineforge_execution_apply_runtime_health($state);

    unset($state['encryptedCredentials'], $state['credentialVault']);
    foreach (($state['providerConnections'] ?? []) as $key => $connection) {
        unset($state['providerConnections'][$key]['encryptedPrivateKey']);
        unset($state['providerConnections'][$key]['privateKey']);
        unset($state['providerConnections'][$key]['apiKey']);
        unset($state['providerConnections'][$key]['keyId']);
    }

    $state['credentialSecurity'] = [
        'encryptionAvailable' => lineforge_execution_crypto_available(),
        'signerAvailable' => lineforge_execution_kalshi_signer_available(),
        'credentialKeyConfigured' => trim(aegis_env('LINEFORGE_CREDENTIAL_KEY', aegis_env('AEGIS_CREDENTIAL_KEY', ''))) !== '',
        'message' => lineforge_execution_crypto_available()
            ? 'Credential encryption is available for server-side provider keys.'
            : 'Credential storage is blocked until PHP OpenSSL and LINEFORGE_CREDENTIAL_KEY are configured.',
    ];
    $state['capabilities'] = [
        'paper' => ['execution' => true, 'liveMoney' => false, 'message' => 'Simulation only.'],
        'kalshi' => ['execution' => true, 'liveMoney' => true, 'officialApiOnly' => true, 'demoFirst' => true],
        'fanduel' => ['execution' => false, 'liveMoney' => false, 'message' => 'Data-only until an approved FanDuel execution API is available.'],
    ];

    return $state;
}

function lineforge_execution_append_audit(array &$state, string $type, string $message, array $context = [], string $severity = 'info'): array
{
    $entry = [
        'id' => 'aud_' . bin2hex(random_bytes(8)),
        'type' => $type,
        'severity' => $severity,
        'message' => $message,
        'context' => $context,
        'createdAt' => lineforge_execution_now(),
    ];

    $state['auditLogs'][] = $entry;
    $state['auditLogs'] = array_slice(array_values($state['auditLogs']), -160);

    return $entry;
}

function lineforge_execution_mask_secret(string $value): string
{
    return aegis_sports_product_mask_secret($value);
}

function lineforge_execution_crypto_available(): bool
{
    return extension_loaded('openssl')
        && function_exists('openssl_encrypt')
        && function_exists('openssl_decrypt')
        && trim(aegis_env('LINEFORGE_CREDENTIAL_KEY', aegis_env('AEGIS_CREDENTIAL_KEY', ''))) !== '';
}

function lineforge_execution_kalshi_signer_available(): bool
{
    $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'kalshi-sign.js';
    return function_exists('proc_open') && is_file($script);
}

function lineforge_execution_kalshi_sign_message(string $privateKeyPem, string $message): string
{
    if (!lineforge_execution_kalshi_signer_available()) {
        throw new RuntimeException('Kalshi RSA-PSS signer is unavailable.');
    }

    $node = trim(aegis_env('LINEFORGE_NODE_PATH', 'node')) ?: 'node';
    $script = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'kalshi-sign.js';
    $command = escapeshellarg($node) . ' ' . escapeshellarg($script);
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open($command, $descriptor, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start Kalshi signing process.');
    }

    fwrite($pipes[0], json_encode([
        'privateKeyPem' => $privateKeyPem,
        'message' => $message,
    ], JSON_UNESCAPED_SLASHES));
    fclose($pipes[0]);

    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    $decoded = json_decode((string) $output, true);

    if ($status !== 0 || !is_array($decoded) || empty($decoded['ok']) || empty($decoded['signature'])) {
        throw new RuntimeException(trim((string) ($decoded['message'] ?? $error ?: 'Kalshi signing failed.')));
    }

    return (string) $decoded['signature'];
}

function lineforge_execution_encrypt_secret(string $secret): array
{
    if (!lineforge_execution_crypto_available()) {
        throw new RuntimeException('Credential encryption is unavailable. Configure PHP OpenSSL and LINEFORGE_CREDENTIAL_KEY before storing provider keys.');
    }

    $keyMaterial = hash('sha256', aegis_env('LINEFORGE_CREDENTIAL_KEY', aegis_env('AEGIS_CREDENTIAL_KEY', '')), true);
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', $keyMaterial, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Unable to encrypt provider credentials.');
    }

    return [
        'cipher' => 'aes-256-gcm',
        'iv' => base64_encode($iv),
        'tag' => base64_encode($tag),
        'value' => base64_encode($ciphertext),
        'createdAt' => lineforge_execution_now(),
    ];
}

function lineforge_execution_decrypt_secret(array $payload): string
{
    if (!lineforge_execution_crypto_available()) {
        throw new RuntimeException('Credential decryption is unavailable in this runtime.');
    }

    $keyMaterial = hash('sha256', aegis_env('LINEFORGE_CREDENTIAL_KEY', aegis_env('AEGIS_CREDENTIAL_KEY', '')), true);
    $plaintext = openssl_decrypt(
        base64_decode((string) ($payload['value'] ?? ''), true) ?: '',
        'aes-256-gcm',
        $keyMaterial,
        OPENSSL_RAW_DATA,
        base64_decode((string) ($payload['iv'] ?? ''), true) ?: '',
        base64_decode((string) ($payload['tag'] ?? ''), true) ?: ''
    );

    if ($plaintext === false) {
        throw new RuntimeException('Unable to decrypt provider credentials.');
    }

    return $plaintext;
}

function lineforge_execution_apply_runtime_health(array $state): array
{
    $now = lineforge_execution_now();
    $state['providerHealth']['paper'] = [
        'provider' => 'paper',
        'status' => !empty($state['emergencyStop']) ? 'paused' : 'operational',
        'latencyMs' => 0,
        'checkedAt' => $now,
        'message' => !empty($state['emergencyStop'])
            ? 'Emergency stop is active. Paper rules are paused.'
            : 'Paper simulation is operational.',
    ];

    $kalshi = $state['providerConnections']['kalshi'] ?? [];
    if (($kalshi['status'] ?? '') === 'connected') {
        $state['providerHealth']['kalshi'] = [
            'provider' => 'kalshi',
            'status' => lineforge_execution_kalshi_signer_available() ? 'configured' : 'degraded',
            'latencyMs' => null,
            'checkedAt' => $now,
            'message' => lineforge_execution_kalshi_signer_available()
                ? 'Official API credentials are configured. Signed calls remain guarded by risk controls.'
                : 'Kalshi credentials are present, but this PHP runtime cannot produce RSA-PSS signatures.',
        ];
    } else {
        $state['providerHealth']['kalshi'] = [
            'provider' => 'kalshi',
            'status' => 'not_connected',
            'latencyMs' => null,
            'checkedAt' => (string) ($kalshi['lastCheckedAt'] ?? ''),
            'message' => (string) ($kalshi['message'] ?? 'Awaiting official API credentials.'),
        ];
    }

    $state['providerHealth']['fanduel'] = [
        'provider' => 'fanduel',
        'status' => 'data_only',
        'latencyMs' => null,
        'checkedAt' => $now,
        'message' => 'Execution is intentionally unavailable without an official approved API.',
    ];

    return $state;
}

function lineforge_execution_safe_float($value, float $fallback = 0.0): float
{
    if (is_numeric($value)) {
        return (float) $value;
    }

    if (preg_match('/[+-]?\d+(?:\.\d+)?/', (string) $value, $match)) {
        return (float) $match[0];
    }

    return $fallback;
}

function lineforge_execution_money(float $value): string
{
    return '$' . number_format($value, 2);
}

function lineforge_execution_condition_met(float $actual, string $operator, float $target): bool
{
    return match ($operator) {
        '<=' => $actual <= $target,
        '>=' => $actual >= $target,
        '<' => $actual < $target,
        '>' => $actual > $target,
        '=' => abs($actual - $target) < 0.00001,
        default => false,
    };
}

function lineforge_execution_provider_for(array $state, string $provider): LineforgeProviderInterface
{
    $provider = strtolower(trim($provider));
    if ($provider === 'kalshi') {
        return new LineforgeKalshiProvider($state['providerConnections']['kalshi'] ?? []);
    }

    if ($provider === 'fanduel') {
        return new LineforgeFanDuelProvider();
    }

    return new LineforgePaperProvider($state);
}

function lineforge_execution_validate_risk_limits(array $input, array $current): array
{
    return [
        'maxStakePerOrder' => max(1.00, min(100000.00, lineforge_execution_safe_float($input['maxStakePerOrder'] ?? $current['maxStakePerOrder'] ?? 25.00, 25.00))),
        'maxDailyLoss' => max(1.00, min(1000000.00, lineforge_execution_safe_float($input['maxDailyLoss'] ?? $current['maxDailyLoss'] ?? 100.00, 100.00))),
        'cooldownMinutes' => max(0, min(1440, (int) lineforge_execution_safe_float($input['cooldownMinutes'] ?? $current['cooldownMinutes'] ?? 5, 5))),
        'selfExcluded' => !empty($input['selfExcluded']),
        'emergencyDisabled' => !empty($input['emergencyDisabled']),
        'requireManualConfirmation' => array_key_exists('requireManualConfirmation', $input) ? !empty($input['requireManualConfirmation']) : !empty($current['requireManualConfirmation']),
        'blockStaleMarketDataSeconds' => max(15, min(3600, (int) lineforge_execution_safe_float($input['blockStaleMarketDataSeconds'] ?? $current['blockStaleMarketDataSeconds'] ?? 120, 120))),
        'allowLiveAuto' => !empty($input['allowLiveAuto']),
        'legalEligibilityRequired' => array_key_exists('legalEligibilityRequired', $input) ? !empty($input['legalEligibilityRequired']) : true,
    ];
}

function lineforge_execution_create_rule(array $input): array
{
    $provider = strtolower(trim((string) ($input['provider'] ?? 'paper')));
    if (!in_array($provider, ['paper', 'kalshi', 'fanduel'], true)) {
        throw new InvalidArgumentException('Unsupported provider for execution rules.');
    }

    $marketTicker = strtoupper(trim((string) ($input['marketTicker'] ?? '')));
    if ($marketTicker === '') {
        throw new InvalidArgumentException('Select or enter a market ticker before saving a rule.');
    }

    $side = strtoupper(trim((string) ($input['side'] ?? 'YES')));
    if (!in_array($side, ['YES', 'NO'], true)) {
        throw new InvalidArgumentException('Rule side must be YES or NO.');
    }

    $stakeType = (string) ($input['stakeType'] ?? 'fixed');
    if (!in_array($stakeType, ['fixed', 'contracts', 'percent_balance'], true)) {
        $stakeType = 'fixed';
    }

    $confirmationMode = (string) ($input['confirmationMode'] ?? 'manual');
    if (!in_array($confirmationMode, ['manual', 'semi_auto', 'paper_auto', 'live_auto'], true)) {
        $confirmationMode = 'manual';
    }

    return [
        'id' => 'rule_' . bin2hex(random_bytes(6)),
        'provider' => $provider,
        'marketTicker' => $marketTicker,
        'marketLabel' => trim((string) ($input['marketLabel'] ?? $marketTicker)),
        'side' => $side,
        'enabled' => false,
        'status' => 'paused',
        'allowDuplicates' => !empty($input['allowDuplicates']),
        'entry' => [
            'probabilityOperator' => in_array((string) ($input['probabilityOperator'] ?? '<='), ['<=', '>=', '<', '>', '='], true) ? (string) $input['probabilityOperator'] : '<=',
            'probability' => max(1, min(99, lineforge_execution_safe_float($input['entryProbability'] ?? 45, 45))),
            'edgeMin' => max(0, min(100, lineforge_execution_safe_float($input['edgeMin'] ?? 0, 0))),
            'confidenceMin' => max(0, min(100, lineforge_execution_safe_float($input['confidenceMin'] ?? 70, 70))),
            'liquidityMin' => max(0, lineforge_execution_safe_float($input['liquidityMin'] ?? 0, 0)),
            'currentProbability' => max(1, min(99, lineforge_execution_safe_float($input['currentProbability'] ?? ($input['entryProbability'] ?? 45), 45))),
            'currentLiquidity' => max(0, lineforge_execution_safe_float($input['currentLiquidity'] ?? 1000, 1000)),
        ],
        'stake' => [
            'type' => $stakeType,
            'amount' => max(1, lineforge_execution_safe_float($input['stakeAmount'] ?? 25, 25)),
            'maxContracts' => max(1, (int) lineforge_execution_safe_float($input['maxContracts'] ?? 10, 10)),
            'percentBalance' => max(0.1, min(100, lineforge_execution_safe_float($input['percentBalance'] ?? 1, 1))),
            'maxPrice' => max(0.01, min(0.99, lineforge_execution_safe_float($input['maxPrice'] ?? 0.45, 0.45))),
        ],
        'exit' => [
            'sellIfProbabilityFallsTo' => max(1, min(99, lineforge_execution_safe_float($input['exitProbabilityLow'] ?? 35, 35))),
            'takeProfitProbability' => max(1, min(99, lineforge_execution_safe_float($input['takeProfitProbability'] ?? 65, 65))),
            'stopLossProbability' => max(1, min(99, lineforge_execution_safe_float($input['stopLossProbability'] ?? 30, 30))),
            'profitPercent' => max(0, lineforge_execution_safe_float($input['profitPercent'] ?? 20, 20)),
            'lossPercent' => max(0, lineforge_execution_safe_float($input['lossPercent'] ?? 20, 20)),
            'holdBetweenLow' => max(1, min(99, lineforge_execution_safe_float($input['holdBetweenLow'] ?? 36, 36))),
            'holdBetweenHigh' => max(1, min(99, lineforge_execution_safe_float($input['holdBetweenHigh'] ?? 64, 64))),
        ],
        'expiration' => [
            'cancelAfter' => trim((string) ($input['cancelAfter'] ?? '')),
            'cancelIfMarketCloses' => true,
            'liquidityMin' => max(0, lineforge_execution_safe_float($input['cancelLiquidityBelow'] ?? 100, 100)),
        ],
        'confirmationMode' => $confirmationMode,
        'createdAt' => lineforge_execution_now(),
        'updatedAt' => lineforge_execution_now(),
    ];
}

function lineforge_execution_rule_sentence(array $rule): string
{
    return sprintf(
        'WHEN probability is %s %.0f%% AND confidence >= %.0f%% AND liquidity >= %s THEN buy %s on %s with %s max price %.0fc.',
        (string) ($rule['entry']['probabilityOperator'] ?? '<='),
        (float) ($rule['entry']['probability'] ?? 0),
        (float) ($rule['entry']['confidenceMin'] ?? 0),
        lineforge_execution_money((float) ($rule['entry']['liquidityMin'] ?? 0)),
        (string) ($rule['side'] ?? 'YES'),
        (string) ($rule['marketTicker'] ?? 'MARKET'),
        lineforge_execution_money((float) ($rule['stake']['amount'] ?? 0)),
        (float) ($rule['stake']['maxPrice'] ?? 0) * 100
    );
}

function lineforge_execution_estimate_order(array $state, array $rule, array $snapshot): array
{
    $price = max(0.01, min(0.99, (float) ($rule['stake']['maxPrice'] ?? 0.45)));
    $balance = (float) ($state['paperBalance'] ?? 0);
    $stake = match ((string) ($rule['stake']['type'] ?? 'fixed')) {
        'contracts' => ((int) ($rule['stake']['maxContracts'] ?? 1)) * $price,
        'percent_balance' => $balance * (((float) ($rule['stake']['percentBalance'] ?? 1)) / 100),
        default => (float) ($rule['stake']['amount'] ?? 25),
    };
    $stake = max(0.01, min($stake, (float) ($rule['stake']['amount'] ?? $stake)));
    $contracts = max(1, (int) floor($stake / $price));
    $cost = $contracts * $price;
    $fees = 0.00;

    return [
        'stake' => round($stake, 2),
        'contracts' => $contracts,
        'price' => round($price, 4),
        'estimatedCost' => round($cost + $fees, 2),
        'maxLoss' => round($cost + $fees, 2),
        'expectedPayout' => round($contracts * 1.00, 2),
        'estimatedFees' => $fees,
        'liquidity' => (float) ($snapshot['liquidity'] ?? 0),
    ];
}

function lineforge_execution_market_snapshot(array $state, array $rule): array
{
    $providerName = strtolower((string) ($rule['provider'] ?? 'paper'));
    $entry = (array) ($rule['entry'] ?? []);
    $snapshot = [
        'provider' => $providerName,
        'marketTicker' => (string) ($rule['marketTicker'] ?? ''),
        'probability' => (float) ($entry['currentProbability'] ?? $entry['probability'] ?? 50),
        'edgeScore' => (float) ($rule['edgeScore'] ?? $entry['edgeMin'] ?? 0),
        'confidence' => max((float) ($entry['confidenceMin'] ?? 0), (float) ($rule['confidence'] ?? 0)),
        'liquidity' => (float) ($entry['currentLiquidity'] ?? $entry['liquidityMin'] ?? 0),
        'stale' => false,
        'ageSeconds' => 0,
        'source' => $providerName === 'paper' ? 'paper_snapshot' : 'rule_snapshot',
        'updatedAt' => lineforge_execution_now(),
    ];

    if ($providerName === 'kalshi' && ($rule['marketTicker'] ?? '') !== '') {
        $provider = lineforge_execution_provider_for($state, 'kalshi');
        $market = $provider->getMarketPrice((string) $rule['marketTicker']);
        if (!empty($market['ok'])) {
            $marketData = (array) ($market['data']['market'] ?? $market['data'] ?? []);
            $yesBid = lineforge_execution_safe_float($marketData['yes_bid_dollars'] ?? $marketData['yes_bid'] ?? 0, 0);
            $lastPrice = lineforge_execution_safe_float($marketData['last_price_dollars'] ?? $marketData['last_price'] ?? 0, 0);
            $probability = $lastPrice > 0 ? $lastPrice * 100 : ($yesBid > 0 ? $yesBid * 100 : $snapshot['probability']);
            $volume = lineforge_execution_safe_float($marketData['volume_24h_dollars'] ?? $marketData['volume'] ?? $snapshot['liquidity'], $snapshot['liquidity']);
            $snapshot['probability'] = max(1, min(99, $probability));
            $snapshot['liquidity'] = max(0, $volume);
            $snapshot['source'] = 'kalshi_official_market';
        } else {
            $snapshot['stale'] = true;
            $snapshot['source'] = 'rule_snapshot_provider_unavailable';
            $snapshot['providerError'] = (string) ($market['message'] ?? 'Kalshi market data unavailable.');
        }
    }

    return $snapshot;
}

function lineforge_execution_risk_blocks(array $state, array $rule, array $estimate, array $snapshot): array
{
    $blocks = [];
    $risk = (array) ($state['riskLimits'] ?? []);
    $provider = strtolower((string) ($rule['provider'] ?? 'paper'));
    $mode = strtolower((string) ($state['mode'] ?? 'paper'));

    if (!empty($state['emergencyStop']) || !empty($risk['emergencyDisabled'])) {
        $blocks[] = 'Emergency stop is active.';
    }

    if (!empty($risk['selfExcluded'])) {
        $blocks[] = 'Self-exclusion is enabled.';
    }

    if ((float) ($estimate['estimatedCost'] ?? 0) > (float) ($risk['maxStakePerOrder'] ?? 0)) {
        $blocks[] = 'Estimated cost exceeds the max stake per order.';
    }

    $remainingDailyLoss = max(0, (float) ($risk['maxDailyLoss'] ?? 0) - (float) ($state['dailyRealizedLoss'] ?? 0));
    if ((float) ($estimate['maxLoss'] ?? 0) > $remainingDailyLoss) {
        $blocks[] = 'Max loss would exceed the remaining daily loss limit.';
    }

    $cooldownMinutes = (int) ($risk['cooldownMinutes'] ?? 0);
    if ($cooldownMinutes > 0 && !empty($state['lastLiveActionAt'])) {
        $seconds = time() - strtotime((string) $state['lastLiveActionAt']);
        if ($seconds < $cooldownMinutes * 60) {
            $blocks[] = 'Cooldown window is still active.';
        }
    }

    if (!empty($snapshot['stale']) || (int) ($snapshot['ageSeconds'] ?? 0) > (int) ($risk['blockStaleMarketDataSeconds'] ?? 120)) {
        $blocks[] = 'Market data is stale or unavailable.';
    }

    $health = (array) ($state['providerHealth'][$provider] ?? []);
    if ($provider !== 'paper' && !in_array((string) ($health['status'] ?? ''), ['operational', 'configured'], true)) {
        $blocks[] = 'Provider health is not operational.';
    }

    if ($mode === 'live') {
        if (empty($state['liveEnabled'])) {
            $blocks[] = 'Live mode has not been explicitly enabled.';
        }
        if ($rule['confirmationMode'] !== 'live_auto' && !empty($risk['requireManualConfirmation'])) {
            $blocks[] = 'Manual confirmation is required before live-money action.';
        }
        if (!empty($risk['legalEligibilityRequired'])) {
            $blocks[] = 'Provider legal/location eligibility has not been verified for this session.';
        }
    }

    if ($provider === 'fanduel') {
        $blocks[] = 'FanDuel execution is unsupported without an approved official execution API.';
    }

    if ($provider === 'kalshi' && $mode !== 'paper' && !lineforge_execution_kalshi_signer_available()) {
        $blocks[] = 'Kalshi live/demo execution requires RSA-PSS signing support in the backend runtime.';
    }

    if ($provider === 'paper' && (float) ($estimate['estimatedCost'] ?? 0) > (float) ($state['paperBalance'] ?? 0)) {
        $blocks[] = 'Paper balance is insufficient.';
    }

    return $blocks;
}

function lineforge_execution_apply_paper_order(array &$state, array $rule, array $estimate, string $clientOrderId): array
{
    $orderId = 'ord_' . bin2hex(random_bytes(6));
    $tradeId = 'trd_' . bin2hex(random_bytes(6));
    $marketTicker = (string) ($rule['marketTicker'] ?? 'MARKET');
    $side = (string) ($rule['side'] ?? 'YES');
    $cost = (float) ($estimate['estimatedCost'] ?? 0);
    $contracts = (int) ($estimate['contracts'] ?? 0);
    $price = (float) ($estimate['price'] ?? 0);
    $now = lineforge_execution_now();

    $order = [
        'id' => $orderId,
        'provider' => 'paper',
        'ruleId' => (string) ($rule['id'] ?? ''),
        'marketTicker' => $marketTicker,
        'side' => $side,
        'status' => 'filled',
        'clientOrderId' => $clientOrderId,
        'contracts' => $contracts,
        'price' => $price,
        'estimatedCost' => $cost,
        'maxLoss' => (float) ($estimate['maxLoss'] ?? $cost),
        'expectedPayout' => (float) ($estimate['expectedPayout'] ?? 0),
        'fees' => (float) ($estimate['estimatedFees'] ?? 0),
        'providerResponse' => ['mode' => 'paper', 'message' => 'Simulated fill.'],
        'createdAt' => $now,
    ];

    $trade = [
        'id' => $tradeId,
        'orderId' => $orderId,
        'provider' => 'paper',
        'marketTicker' => $marketTicker,
        'side' => $side,
        'contracts' => $contracts,
        'price' => $price,
        'fees' => (float) ($estimate['estimatedFees'] ?? 0),
        'createdAt' => $now,
    ];

    $positionId = 'pos_' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $marketTicker . '_' . $side));
    $existingIndex = null;
    foreach ((array) ($state['positions'] ?? []) as $index => $position) {
        if (($position['id'] ?? '') === $positionId) {
            $existingIndex = $index;
            break;
        }
    }

    if ($existingIndex === null) {
        $state['positions'][] = [
            'id' => $positionId,
            'provider' => 'paper',
            'marketTicker' => $marketTicker,
            'side' => $side,
            'contracts' => $contracts,
            'averagePrice' => $price,
            'costBasis' => $cost,
            'unrealizedPnl' => 0.00,
            'updatedAt' => $now,
        ];
    } else {
        $position = (array) $state['positions'][$existingIndex];
        $oldContracts = (int) ($position['contracts'] ?? 0);
        $oldCost = (float) ($position['costBasis'] ?? 0);
        $newContracts = $oldContracts + $contracts;
        $newCost = $oldCost + $cost;
        $position['contracts'] = $newContracts;
        $position['costBasis'] = round($newCost, 2);
        $position['averagePrice'] = $newContracts > 0 ? round($newCost / $newContracts, 4) : $price;
        $position['updatedAt'] = $now;
        $state['positions'][$existingIndex] = $position;
    }

    $state['paperBalance'] = round(max(0, (float) ($state['paperBalance'] ?? 0) - $cost), 2);
    $state['orders'][] = $order;
    $state['trades'][] = $trade;
    $state['lastLiveActionAt'] = $now;

    return $order;
}

function lineforge_execution_evaluate_rules(array $account, bool $dryRun = false): array
{
    return lineforge_execution_with_user_state($account, function (array &$state) use ($dryRun): array {
        $state = lineforge_execution_apply_runtime_health($state);
        $results = [];

        foreach ((array) ($state['rules'] ?? []) as $index => $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $ruleId = (string) ($rule['id'] ?? '');
            if (empty($rule['enabled'])) {
                $results[] = ['ruleId' => $ruleId, 'status' => 'skipped', 'reason' => 'Rule is paused.'];
                continue;
            }

            if (!$dryRun && empty($rule['allowDuplicates']) && !empty($state['lastRuleActions'][$ruleId])) {
                lineforge_execution_append_audit($state, 'rule_skipped', 'Duplicate order blocked for rule.', [
                    'ruleId' => $ruleId,
                    'clientOrderId' => $state['lastRuleActions'][$ruleId],
                ], 'warning');
                $results[] = ['ruleId' => $ruleId, 'status' => 'skipped', 'reason' => 'Duplicate order blocked.'];
                continue;
            }

            $snapshot = lineforge_execution_market_snapshot($state, $rule);
            $entry = (array) ($rule['entry'] ?? []);
            $estimate = lineforge_execution_estimate_order($state, $rule, $snapshot);
            $conditions = [
                'probability' => lineforge_execution_condition_met(
                    (float) $snapshot['probability'],
                    (string) ($entry['probabilityOperator'] ?? '<='),
                    (float) ($entry['probability'] ?? 0)
                ),
                'edge' => (float) ($snapshot['edgeScore'] ?? 0) >= (float) ($entry['edgeMin'] ?? 0),
                'confidence' => (float) ($snapshot['confidence'] ?? 0) >= (float) ($entry['confidenceMin'] ?? 0),
                'liquidity' => (float) ($snapshot['liquidity'] ?? 0) >= (float) ($entry['liquidityMin'] ?? 0),
            ];

            $passed = !in_array(false, $conditions, true);
            $blocks = $passed ? lineforge_execution_risk_blocks($state, $rule, $estimate, $snapshot) : [];

            $auditContext = [
                'ruleId' => $ruleId,
                'marketTicker' => $rule['marketTicker'] ?? '',
                'snapshot' => $snapshot,
                'conditions' => $conditions,
                'estimate' => $estimate,
                'dryRun' => $dryRun,
                'blocks' => $blocks,
            ];

            if (!$passed) {
                lineforge_execution_append_audit($state, 'rule_evaluation', 'Rule conditions were not met.', $auditContext, 'info');
                $state['rules'][$index]['status'] = 'watching';
                $state['rules'][$index]['lastEvaluationAt'] = lineforge_execution_now();
                $results[] = ['ruleId' => $ruleId, 'status' => 'watching', 'conditions' => $conditions, 'snapshot' => $snapshot];
                continue;
            }

            if ($blocks) {
                lineforge_execution_append_audit($state, 'rule_blocked', 'Rule passed but execution safeguards blocked action.', $auditContext, 'warning');
                $state['rules'][$index]['status'] = 'blocked';
                $state['rules'][$index]['lastEvaluationAt'] = lineforge_execution_now();
                $results[] = ['ruleId' => $ruleId, 'status' => 'blocked', 'blocks' => $blocks, 'snapshot' => $snapshot];
                continue;
            }

            $clientOrderId = 'lf_' . substr(hash('sha256', lineforge_execution_user_key($state['userAccount'] ?? []) . '|' . $ruleId . '|' . ($rule['marketTicker'] ?? '') . '|' . gmdate('YmdHi')), 0, 24);
            $confirmationMode = (string) ($rule['confirmationMode'] ?? 'manual');
            $provider = strtolower((string) ($rule['provider'] ?? 'paper'));

            if ($dryRun || in_array($confirmationMode, ['manual', 'semi_auto'], true)) {
                lineforge_execution_append_audit($state, $dryRun ? 'dry_run' : 'approval_required', $dryRun ? 'Dry-run action simulated.' : 'Rule requires user approval before action.', array_merge($auditContext, [
                    'clientOrderId' => $clientOrderId,
                    'sentence' => lineforge_execution_rule_sentence($rule),
                ]), $dryRun ? 'info' : 'warning');
                $state['rules'][$index]['status'] = $dryRun ? 'dry_run_ready' : 'approval_required';
                $state['rules'][$index]['lastEvaluationAt'] = lineforge_execution_now();
                $results[] = ['ruleId' => $ruleId, 'status' => $dryRun ? 'dry_run_ready' : 'approval_required', 'estimate' => $estimate, 'snapshot' => $snapshot];
                continue;
            }

            if ($provider === 'paper' && $confirmationMode === 'paper_auto') {
                $order = lineforge_execution_apply_paper_order($state, $rule, $estimate, $clientOrderId);
                $state['lastRuleActions'][$ruleId] = $clientOrderId;
                $state['rules'][$index]['status'] = 'filled';
                $state['rules'][$index]['lastActionAt'] = lineforge_execution_now();
                $state['rules'][$index]['lastEvaluationAt'] = lineforge_execution_now();
                lineforge_execution_append_audit($state, 'paper_order_filled', 'Paper order simulated successfully.', array_merge($auditContext, [
                    'clientOrderId' => $clientOrderId,
                    'order' => $order,
                ]), 'success');
                $results[] = ['ruleId' => $ruleId, 'status' => 'filled', 'order' => $order, 'snapshot' => $snapshot];
                continue;
            }

            lineforge_execution_append_audit($state, 'rule_blocked', 'Live-auto action was not allowed by current safeguards.', $auditContext, 'warning');
            $state['rules'][$index]['status'] = 'blocked';
            $state['rules'][$index]['lastEvaluationAt'] = lineforge_execution_now();
            $results[] = ['ruleId' => $ruleId, 'status' => 'blocked', 'blocks' => ['Live-auto action is disabled by safeguard policy.']];
        }

        return [
            'evaluation' => $results,
            'state' => lineforge_execution_public_state_from_mutable($state),
        ];
    });
}

function lineforge_execution_public_state_from_mutable(array $state): array
{
    $state = lineforge_execution_apply_runtime_health(lineforge_execution_sanitize_state($state));
    unset($state['encryptedCredentials'], $state['credentialVault']);
    foreach (($state['providerConnections'] ?? []) as $key => $connection) {
        unset($state['providerConnections'][$key]['encryptedPrivateKey'], $state['providerConnections'][$key]['privateKey'], $state['providerConnections'][$key]['keyId']);
    }
    $state['credentialSecurity'] = [
        'encryptionAvailable' => lineforge_execution_crypto_available(),
        'signerAvailable' => lineforge_execution_kalshi_signer_available(),
        'credentialKeyConfigured' => trim(aegis_env('LINEFORGE_CREDENTIAL_KEY', aegis_env('AEGIS_CREDENTIAL_KEY', ''))) !== '',
        'message' => lineforge_execution_crypto_available()
            ? 'Credential encryption is available for server-side provider keys.'
            : 'Credential storage is blocked until PHP OpenSSL and LINEFORGE_CREDENTIAL_KEY are configured.',
    ];
    return $state;
}

class LineforgePaperProvider implements LineforgeProviderInterface
{
    private array $state;

    public function __construct(array $state = [])
    {
        $this->state = $state;
    }

    public function connectAccount(array $credentials): array
    {
        return ['ok' => true, 'status' => 'connected', 'message' => 'Paper simulation connected.'];
    }

    public function getAccountStatus(): array
    {
        return ['ok' => true, 'status' => 'connected', 'mode' => 'paper'];
    }

    public function getBalance(): array
    {
        return [
            'ok' => true,
            'balance' => (float) ($this->state['paperBalance'] ?? 0),
            'portfolioValue' => (float) ($this->state['paperBalance'] ?? 0),
            'currency' => 'USD',
        ];
    }

    public function getMarkets(array $filters = []): array
    {
        return ['ok' => true, 'markets' => array_values((array) ($this->state['markets'] ?? []))];
    }

    public function getMarketPrice(string $marketId): array
    {
        foreach ((array) ($this->state['markets'] ?? []) as $market) {
            if (($market['ticker'] ?? '') === $marketId) {
                return ['ok' => true, 'data' => $market];
            }
        }

        return ['ok' => true, 'data' => ['ticker' => $marketId, 'probability' => 50, 'source' => 'paper_default']];
    }

    public function getOrderBook(string $marketId): array
    {
        return ['ok' => true, 'orderbook' => ['yes' => [['0.4500', '100.00']], 'no' => [['0.5500', '100.00']]]];
    }

    public function placeOrder(array $order): array
    {
        return ['ok' => true, 'mode' => 'paper', 'message' => 'Paper orders are applied by the rule engine.'];
    }

    public function cancelOrder(string $orderId): array
    {
        return ['ok' => true, 'orderId' => $orderId, 'message' => 'Paper cancellation recorded.'];
    }

    public function sellPosition(array $position): array
    {
        return ['ok' => true, 'position' => $position, 'message' => 'Paper sell simulated.'];
    }

    public function getOpenOrders(): array
    {
        return ['ok' => true, 'orders' => array_values(array_filter((array) ($this->state['orders'] ?? []), static fn ($order): bool => ($order['status'] ?? '') === 'open'))];
    }

    public function getPositions(): array
    {
        return ['ok' => true, 'positions' => array_values((array) ($this->state['positions'] ?? []))];
    }

    public function getTradeHistory(array $filters = []): array
    {
        return ['ok' => true, 'trades' => array_values((array) ($this->state['trades'] ?? []))];
    }

    public function healthCheck(): array
    {
        return ['ok' => true, 'status' => 'operational', 'message' => 'Paper simulation is available.'];
    }
}

class LineforgeKalshiProvider implements LineforgeProviderInterface
{
    private array $connection;
    private string $environment;

    public function __construct(array $connection = [])
    {
        $this->connection = $connection;
        $this->environment = strtolower((string) ($connection['environment'] ?? 'demo')) === 'live' ? 'live' : 'demo';
    }

    private function baseUrl(): string
    {
        return $this->environment === 'live'
            ? 'https://external-api.kalshi.com/trade-api/v2'
            : 'https://external-api.demo.kalshi.co/trade-api/v2';
    }

    private function transportAvailable(): bool
    {
        if (extension_loaded('curl')) {
            return true;
        }

        return (bool) ini_get('allow_url_fopen') && extension_loaded('openssl');
    }

    private function request(string $method, string $path, array $body = null, bool $signed = false): array
    {
        if (!$this->transportAvailable()) {
            return [
                'ok' => false,
                'code' => 'transport_unavailable',
                'message' => 'Kalshi HTTPS requests require PHP curl or OpenSSL stream support.',
            ];
        }

        $headers = ['Accept: application/json'];
        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }

        if ($signed) {
            $auth = $this->authHeaders($method, $path);
            if (empty($auth['ok'])) {
                return $auth;
            }
            foreach ($auth['headers'] as $key => $value) {
                $headers[] = $key . ': ' . $value;
            }
        }

        $url = $this->baseUrl() . $path;
        $started = microtime(true);

        if (extension_loaded('curl')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 12,
            ]);
            if ($payload !== null) {
                curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
            }
            $response = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $error = curl_error($handle);
            curl_close($handle);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => strtoupper($method),
                    'header' => implode("\r\n", $headers),
                    'content' => $payload ?? '',
                    'timeout' => 12,
                    'ignore_errors' => true,
                ],
            ]);
            $response = @file_get_contents($url, false, $context);
            $status = 0;
            $error = '';
            foreach (($http_response_header ?? []) as $line) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', (string) $line, $match)) {
                    $status = (int) $match[1];
                    break;
                }
            }
        }

        $latencyMs = (int) round((microtime(true) - $started) * 1000);
        if ($response === false) {
            return ['ok' => false, 'status' => $status, 'latencyMs' => $latencyMs, 'message' => $error ?: 'Kalshi request failed.'];
        }

        $decoded = json_decode((string) $response, true);
        $ok = $status >= 200 && $status < 300;

        return [
            'ok' => $ok,
            'status' => $status,
            'latencyMs' => $latencyMs,
            'data' => is_array($decoded) ? $decoded : ['raw' => (string) $response],
            'message' => $ok ? 'Kalshi request completed.' : 'Kalshi request was rejected or failed.',
        ];
    }

    private function authHeaders(string $method, string $path): array
    {
        if (!lineforge_execution_kalshi_signer_available()) {
            return [
                'ok' => false,
                'code' => 'signer_unavailable',
                'message' => 'Kalshi signed calls require backend RSA-PSS support. This runtime does not expose it.',
            ];
        }

        if (empty($this->connection['keyId']) || empty($this->connection['encryptedPrivateKey'])) {
            return [
                'ok' => false,
                'code' => 'credentials_missing',
                'message' => 'Kalshi API key ID and encrypted private key are required for signed calls.',
            ];
        }

        $privateKey = lineforge_execution_decrypt_secret((array) $this->connection['encryptedPrivateKey']);
        $timestamp = (string) round(microtime(true) * 1000);
        $pathWithoutQuery = explode('?', '/trade-api/v2' . $path, 2)[0];
        $message = $timestamp . strtoupper($method) . $pathWithoutQuery;
        try {
            $signature = lineforge_execution_kalshi_sign_message($privateKey, $message);
        } catch (Throwable $error) {
            return [
                'ok' => false,
                'code' => 'signature_failed',
                'message' => $error->getMessage(),
            ];
        }

        return [
            'ok' => true,
            'headers' => [
                'KALSHI-ACCESS-KEY' => (string) $this->connection['keyId'],
                'KALSHI-ACCESS-SIGNATURE' => $signature,
                'KALSHI-ACCESS-TIMESTAMP' => $timestamp,
            ],
        ];
    }

    public function connectAccount(array $credentials): array
    {
        return $this->getAccountStatus();
    }

    public function getAccountStatus(): array
    {
        if (($this->connection['status'] ?? '') !== 'connected') {
            return ['ok' => false, 'status' => 'not_connected', 'message' => 'Kalshi credentials are not connected.'];
        }

        return [
            'ok' => lineforge_execution_kalshi_signer_available(),
            'status' => lineforge_execution_kalshi_signer_available() ? 'connected' : 'degraded',
            'environment' => $this->environment,
            'message' => lineforge_execution_kalshi_signer_available()
                ? 'Kalshi official API connection is ready for guarded signed calls.'
                : 'Kalshi connection is saved, but RSA-PSS signing support is unavailable in this PHP runtime.',
        ];
    }

    public function getBalance(): array
    {
        return $this->request('GET', '/portfolio/balance', null, true);
    }

    public function getMarkets(array $filters = []): array
    {
        $query = $filters ? '?' . http_build_query($filters) : '';
        return $this->request('GET', '/markets' . $query, null, false);
    }

    public function getMarketPrice(string $marketId): array
    {
        return $this->request('GET', '/markets/' . rawurlencode($marketId), null, false);
    }

    public function getOrderBook(string $marketId): array
    {
        return $this->request('GET', '/markets/' . rawurlencode($marketId) . '/orderbook', null, false);
    }

    public function placeOrder(array $order): array
    {
        $side = strtoupper((string) ($order['side'] ?? 'YES'));
        $price = max(0.01, min(0.99, (float) ($order['price'] ?? 0.45)));
        $payload = [
            'ticker' => (string) ($order['ticker'] ?? $order['marketTicker'] ?? ''),
            'client_order_id' => (string) ($order['clientOrderId'] ?? ''),
            'side' => $side === 'NO' ? 'ask' : 'bid',
            'count' => number_format(max(1, (float) ($order['contracts'] ?? 1)), 2, '.', ''),
            'price' => number_format($side === 'NO' ? (1 - $price) : $price, 4, '.', ''),
            'time_in_force' => 'fill_or_kill',
            'self_trade_prevention_type' => 'taker_at_cross',
            'post_only' => false,
            'cancel_order_on_pause' => true,
            'reduce_only' => false,
            'subaccount' => 0,
        ];

        return $this->request('POST', '/portfolio/events/orders', $payload, true);
    }

    public function cancelOrder(string $orderId): array
    {
        return $this->request('DELETE', '/portfolio/events/orders/' . rawurlencode($orderId), null, true);
    }

    public function sellPosition(array $position): array
    {
        return $this->placeOrder([
            'ticker' => $position['marketTicker'] ?? $position['ticker'] ?? '',
            'side' => $position['side'] ?? 'YES',
            'contracts' => $position['contracts'] ?? 1,
            'price' => $position['price'] ?? $position['averagePrice'] ?? 0.50,
            'clientOrderId' => 'lf_sell_' . substr(hash('sha256', json_encode($position) . microtime(true)), 0, 20),
        ]);
    }

    public function getOpenOrders(): array
    {
        return $this->request('GET', '/portfolio/orders?status=resting', null, true);
    }

    public function getPositions(): array
    {
        return $this->request('GET', '/portfolio/positions', null, true);
    }

    public function getTradeHistory(array $filters = []): array
    {
        $query = $filters ? '?' . http_build_query($filters) : '';
        return $this->request('GET', '/portfolio/fills' . $query, null, true);
    }

    public function healthCheck(): array
    {
        if (!$this->transportAvailable()) {
            return ['ok' => false, 'status' => 'degraded', 'message' => 'PHP HTTPS transport is unavailable.'];
        }

        $markets = $this->getMarkets(['limit' => 1, 'status' => 'open']);
        return [
            'ok' => !empty($markets['ok']),
            'status' => !empty($markets['ok']) ? 'operational' : 'degraded',
            'latencyMs' => $markets['latencyMs'] ?? null,
            'message' => !empty($markets['ok']) ? 'Kalshi market data endpoint reached.' : (string) ($markets['message'] ?? 'Kalshi health check failed.'),
        ];
    }
}

class LineforgeFanDuelProvider implements LineforgeProviderInterface
{
    private function unsupported(): array
    {
        return [
            'ok' => false,
            'status' => 'unsupported_for_execution',
            'message' => 'FanDuel execution is disabled. Use only licensed data feeds unless FanDuel provides an approved official execution API.',
        ];
    }

    public function connectAccount(array $credentials): array
    {
        return $this->unsupported();
    }

    public function getAccountStatus(): array
    {
        return ['ok' => true, 'status' => 'data_only', 'message' => 'FanDuel is available only as data/odds context.'];
    }

    public function getBalance(): array
    {
        return $this->unsupported();
    }

    public function getMarkets(array $filters = []): array
    {
        return ['ok' => true, 'markets' => [], 'message' => 'Use a licensed odds data provider for FanDuel display.'];
    }

    public function getMarketPrice(string $marketId): array
    {
        return $this->unsupported();
    }

    public function getOrderBook(string $marketId): array
    {
        return $this->unsupported();
    }

    public function placeOrder(array $order): array
    {
        return $this->unsupported();
    }

    public function cancelOrder(string $orderId): array
    {
        return $this->unsupported();
    }

    public function sellPosition(array $position): array
    {
        return $this->unsupported();
    }

    public function getOpenOrders(): array
    {
        return $this->unsupported();
    }

    public function getPositions(): array
    {
        return $this->unsupported();
    }

    public function getTradeHistory(array $filters = []): array
    {
        return $this->unsupported();
    }

    public function healthCheck(): array
    {
        return ['ok' => true, 'status' => 'data_only', 'message' => 'No FanDuel execution route is implemented.'];
    }
}
