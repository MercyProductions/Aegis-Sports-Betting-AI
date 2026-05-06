<?php

require __DIR__ . '/../config/sports_product.php';
require __DIR__ . '/../config/execution.php';

aegis_sports_product_bootstrap();
aegis_sports_product_enforce_rate_limit('api_execution_center', aegis_sports_product_client_ip(), 180, 60);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$account = aegis_sports_product_account();
if (!$account['signedIn']) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'message' => 'Sign in to access the Lineforge Execution Center.',
        'loginUrl' => '/login?next=%2Fapp%23execution',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'ok' => true,
        'csrfToken' => aegis_sports_product_csrf_token(),
        'state' => lineforge_execution_public_state($account),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'message' => 'Unsupported execution method.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

aegis_sports_product_enforce_rate_limit('api_execution_center_write', aegis_sports_product_client_ip(), 60, 60);

$raw = file_get_contents('php://input');
$input = json_decode((string) $raw, true);
if (!is_array($input)) {
    $input = $_POST;
}

$csrf = (string) ($_SERVER['HTTP_X_AEGIS_CSRF'] ?? ($input['csrf'] ?? ''));
if (!aegis_sports_product_verify_csrf($csrf)) {
    aegis_sports_product_record_security_event('csrf_failed', 'Execution Center CSRF check failed.', 'warning');
    http_response_code(419);
    echo json_encode([
        'ok' => false,
        'message' => 'Session expired. Refresh and try again.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$action = (string) ($input['action'] ?? '');

try {
    if ($action === 'dryRun' || $action === 'evaluateRules') {
        $result = lineforge_execution_evaluate_rules($account, $action === 'dryRun');
        echo json_encode([
            'ok' => true,
            'message' => $action === 'dryRun' ? 'Dry-run simulation completed.' : 'Rule evaluation completed.',
            'csrfToken' => aegis_sports_product_csrf_token(),
            'evaluation' => $result['evaluation'] ?? [],
            'state' => $result['state'] ?? lineforge_execution_public_state($account),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $state = lineforge_execution_with_user_state($account, function (array &$state) use ($input, $action): void {
        $now = lineforge_execution_now();

        switch ($action) {
            case 'connectPaper':
                $startingBalance = max(100, min(10000000, lineforge_execution_safe_float($input['startingBalance'] ?? $state['paperBalance'] ?? 10000, 10000)));
                $state['paperBalance'] = round($startingBalance, 2);
                $state['providerConnections']['paper'] = array_merge($state['providerConnections']['paper'] ?? [], [
                    'provider' => 'paper',
                    'label' => 'Paper Simulation',
                    'status' => 'connected',
                    'executionSupported' => true,
                    'lastCheckedAt' => $now,
                    'message' => 'Paper simulation is ready.',
                ]);
                lineforge_execution_append_audit($state, 'provider_connected', 'Paper simulation connected.', [
                    'provider' => 'paper',
                    'startingBalance' => $startingBalance,
                ], 'success');
                break;

            case 'connectKalshi':
                $environment = strtolower(trim((string) ($input['environment'] ?? 'demo'))) === 'live' ? 'live' : 'demo';
                $keyId = trim((string) ($input['keyId'] ?? ''));
                $privateKey = trim((string) ($input['privateKey'] ?? ''));
                $current = (array) ($state['providerConnections']['kalshi'] ?? []);
                $next = array_merge($current, [
                    'provider' => 'kalshi',
                    'label' => 'Kalshi',
                    'environment' => $environment,
                    'executionSupported' => true,
                    'officialApiOnly' => true,
                    'lastCheckedAt' => $now,
                ]);

                if (!empty($input['clearCredentials'])) {
                    unset($next['encryptedPrivateKey'], $next['keyId']);
                    $next['credentialsEncrypted'] = false;
                    $next['keyIdMasked'] = '';
                    $next['status'] = 'not_connected';
                    $next['message'] = 'Kalshi credentials cleared.';
                    lineforge_execution_append_audit($state, 'provider_credentials_cleared', 'Kalshi credentials cleared.', [
                        'provider' => 'kalshi',
                        'environment' => $environment,
                    ], 'warning');
                    $state['providerConnections']['kalshi'] = $next;
                    break;
                }

                if ($keyId !== '') {
                    $next['keyId'] = $keyId;
                    $next['keyIdMasked'] = lineforge_execution_mask_secret($keyId);
                }

                if ($privateKey !== '') {
                    $next['encryptedPrivateKey'] = lineforge_execution_encrypt_secret($privateKey);
                    $next['credentialsEncrypted'] = true;
                }

                $hasKey = trim((string) ($next['keyId'] ?? '')) !== '';
                $hasEncryptedSecret = is_array($next['encryptedPrivateKey'] ?? null);
                $next['status'] = $hasKey && $hasEncryptedSecret ? 'connected' : 'needs_credentials';
                $next['message'] = $hasKey && $hasEncryptedSecret
                    ? 'Kalshi credentials saved server-side for guarded official API calls.'
                    : 'Kalshi profile saved. Add encrypted API credentials before signed calls.';
                $state['providerConnections']['kalshi'] = $next;
                lineforge_execution_append_audit($state, 'provider_connected', 'Kalshi official API profile updated.', [
                    'provider' => 'kalshi',
                    'environment' => $environment,
                    'credentialsEncrypted' => $hasEncryptedSecret,
                ], $hasEncryptedSecret ? 'success' : 'warning');
                break;

            case 'saveRiskLimits':
                $state['riskLimits'] = lineforge_execution_validate_risk_limits((array) ($input['riskLimits'] ?? $input), (array) ($state['riskLimits'] ?? []));
                $state['emergencyStop'] = !empty($state['emergencyStop']) || !empty($state['riskLimits']['emergencyDisabled']);
                lineforge_execution_append_audit($state, 'risk_limits_updated', 'Responsible-use risk limits updated.', [
                    'riskLimits' => $state['riskLimits'],
                ], 'success');
                break;

            case 'createRule':
                $rule = lineforge_execution_create_rule((array) ($input['rule'] ?? $input));
                $state['rules'][] = $rule;
                lineforge_execution_append_audit($state, 'rule_created', 'Execution rule created in paused mode.', [
                    'ruleId' => $rule['id'],
                    'provider' => $rule['provider'],
                    'sentence' => lineforge_execution_rule_sentence($rule),
                ], 'success');
                break;

            case 'toggleRule':
                $ruleId = (string) ($input['ruleId'] ?? '');
                $enabled = !empty($input['enabled']);
                foreach ($state['rules'] as &$rule) {
                    if (($rule['id'] ?? '') === $ruleId) {
                        $rule['enabled'] = $enabled;
                        $rule['status'] = $enabled ? 'watching' : 'paused';
                        $rule['updatedAt'] = $now;
                        lineforge_execution_append_audit($state, 'rule_toggled', $enabled ? 'Execution rule enabled.' : 'Execution rule paused.', [
                            'ruleId' => $ruleId,
                            'enabled' => $enabled,
                        ], $enabled ? 'warning' : 'info');
                        break 2;
                    }
                }
                unset($rule);
                throw new InvalidArgumentException('Execution rule was not found.');

            case 'deleteRule':
                $ruleId = (string) ($input['ruleId'] ?? '');
                $before = count($state['rules']);
                $state['rules'] = array_values(array_filter((array) $state['rules'], static fn ($rule): bool => ($rule['id'] ?? '') !== $ruleId));
                unset($state['lastRuleActions'][$ruleId]);
                if (count($state['rules']) === $before) {
                    throw new InvalidArgumentException('Execution rule was not found.');
                }
                lineforge_execution_append_audit($state, 'rule_deleted', 'Execution rule deleted.', [
                    'ruleId' => $ruleId,
                ], 'warning');
                break;

            case 'setMode':
                $mode = strtolower(trim((string) ($input['mode'] ?? 'paper')));
                if (!in_array($mode, ['paper', 'demo', 'live'], true)) {
                    throw new InvalidArgumentException('Unsupported execution mode.');
                }
                if ($mode === 'live') {
                    if (empty($input['confirmLive'])) {
                        throw new InvalidArgumentException('Live mode requires explicit confirmation.');
                    }
                    if (!empty($state['riskLimits']['selfExcluded']) || !empty($state['emergencyStop'])) {
                        throw new InvalidArgumentException('Live mode is blocked while self-exclusion or emergency stop is active.');
                    }
                    $state['liveEnabled'] = true;
                    $state['liveOptInAt'] = $now;
                } else {
                    $state['liveEnabled'] = false;
                    if ($mode === 'paper') {
                        $state['liveOptInAt'] = '';
                    }
                }
                $state['mode'] = $mode;
                lineforge_execution_append_audit($state, 'mode_changed', 'Execution mode changed.', [
                    'mode' => $mode,
                    'liveEnabled' => $state['liveEnabled'],
                ], $mode === 'live' ? 'warning' : 'info');
                break;

            case 'emergencyStop':
                $state['emergencyStop'] = true;
                $state['liveEnabled'] = false;
                foreach ($state['rules'] as &$rule) {
                    $rule['enabled'] = false;
                    $rule['status'] = 'emergency_stopped';
                    $rule['updatedAt'] = $now;
                }
                unset($rule);
                if (!empty($input['cancelOpenOrders'])) {
                    foreach ($state['orders'] as &$order) {
                        if (($order['status'] ?? '') === 'open') {
                            $order['status'] = 'canceled';
                            $order['canceledAt'] = $now;
                        }
                    }
                    unset($order);
                }
                lineforge_execution_append_audit($state, 'emergency_stop', 'Emergency stop activated. Rules paused immediately.', [
                    'cancelOpenOrders' => !empty($input['cancelOpenOrders']),
                ], 'critical');
                break;

            case 'resetEmergencyStop':
                if (!empty($state['riskLimits']['selfExcluded'])) {
                    throw new InvalidArgumentException('Emergency stop cannot be reset while self-exclusion is active.');
                }
                $state['emergencyStop'] = false;
                $state['riskLimits']['emergencyDisabled'] = false;
                lineforge_execution_append_audit($state, 'emergency_stop_reset', 'Emergency stop reset. Rules remain paused until manually re-enabled.', [], 'warning');
                break;

            case 'healthCheck':
                foreach (['paper', 'kalshi', 'fanduel'] as $providerName) {
                    $provider = lineforge_execution_provider_for($state, $providerName);
                    $health = $provider->healthCheck();
                    $state['providerHealth'][$providerName] = [
                        'provider' => $providerName,
                        'status' => (string) ($health['status'] ?? (!empty($health['ok']) ? 'operational' : 'degraded')),
                        'latencyMs' => $health['latencyMs'] ?? null,
                        'checkedAt' => $now,
                        'message' => (string) ($health['message'] ?? 'Health check completed.'),
                    ];
                }
                lineforge_execution_append_audit($state, 'provider_health_check', 'Provider health check completed.', [
                    'providerHealth' => $state['providerHealth'],
                ], 'info');
                break;

            default:
                throw new InvalidArgumentException('Unsupported execution action.');
        }
    });

    echo json_encode([
        'ok' => true,
        'message' => 'Execution Center updated.',
        'csrfToken' => aegis_sports_product_csrf_token(),
        'state' => lineforge_execution_public_state_from_mutable($state),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => $error->getMessage(),
        'csrfToken' => aegis_sports_product_csrf_token(),
        'state' => lineforge_execution_public_state($account),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
