<?php

require_once __DIR__ . '/config/product_layout.php';
require_once __DIR__ . '/config/execution.php';

$account = aegis_sports_product_require_auth('/account');
$providerSettings = aegis_sports_product_provider_settings_public();
$executionState = lineforge_execution_public_state($account);
$connections = (array) ($executionState['providerConnections'] ?? []);
$riskLimits = (array) ($executionState['riskLimits'] ?? []);
$auditLogs = array_slice((array) ($executionState['auditLogs'] ?? []), 0, 3);
$securityEvents = aegis_sports_product_recent_security_events(3);
sports_page_header('Account', '/account', [
    'description' => 'Lineforge account workspace.',
    'canonical' => '/account',
    'robots' => 'noindex,nofollow',
]);
?>
<main>
<section class="sports-product-section">
    <span class="sports-product-kicker">Operator workspace</span>
    <h1>Account control center.</h1>
    <p>Your Lineforge account manages dashboard access, provider readiness, subscription behavior, credential posture, and execution safety controls.</p>
    <div class="sports-control-radar" aria-hidden="true">
        <span style="--a: 16deg">provider</span>
        <span style="--a: 104deg">audit</span>
        <span style="--a: 196deg">risk</span>
        <span style="--a: 286deg">keys</span>
    </div>
    <div class="sports-account-grid">
        <aside class="sports-account-panel is-featured">
            <span>Profile</span>
            <strong><?= aegis_sports_product_e($account['username']); ?></strong>
            <div class="sports-account-row"><div><span>Email</span><strong><?= aegis_sports_product_e($account['email']); ?></strong></div><b>Verified locally</b></div>
            <div class="sports-account-row"><div><span>Tier</span><strong><?= aegis_sports_product_e(ucfirst($account['tier'])); ?></strong></div><b>Workspace</b></div>
            <div class="sports-product-cta">
                <a class="sports-product-button is-primary" href="/app">Open App</a>
                <form method="post" action="/logout">
                    <input type="hidden" name="csrf" value="<?= aegis_sports_product_e(aegis_sports_product_csrf_token()); ?>">
                    <button class="sports-product-button" type="submit">Log Out</button>
                </form>
            </div>
        </aside>
        <div class="sports-account-panel">
            <span>Subscription and access</span>
            <strong><?= aegis_sports_product_e(ucfirst($account['tier'])); ?> intelligence workspace</strong>
            <div class="sports-account-row"><div><span>Live board</span><strong>Enabled</strong></div><b>Active</b></div>
            <div class="sports-account-row"><div><span>Paper tracking</span><strong><?= !empty($connections['paper']['status']) ? aegis_sports_product_e((string) $connections['paper']['status']) : 'Available'; ?></strong></div><b>Manual</b></div>
            <div class="sports-account-row"><div><span>Execution posture</span><strong><?= !empty($executionState['liveEnabled']) ? 'Live enabled' : 'Paper/demo first'; ?></strong></div><b><?= !empty($executionState['emergencyStop']) ? 'Stopped' : 'Guarded'; ?></b></div>
        </div>
    </div>
</section>

<section class="sports-product-section is-wide">
    <span class="sports-product-kicker">Provider and security posture</span>
    <h2>Connections, keys, and controls.</h2>
    <div class="sports-account-grid">
        <div class="sports-account-panel">
            <strong>Provider connections</strong>
            <?php foreach ($connections as $connection): ?>
                <div class="sports-account-row">
                    <div>
                        <span><?= aegis_sports_product_e((string) ($connection['label'] ?? $connection['provider'] ?? 'Provider')); ?></span>
                        <strong><?= aegis_sports_product_e((string) ($connection['message'] ?? 'Connection state available in Execution Center.')); ?></strong>
                    </div>
                    <b><?= aegis_sports_product_e((string) ($connection['status'] ?? 'unknown')); ?></b>
                </div>
            <?php endforeach; ?>
            <div class="sports-account-row">
                <div><span>Sportsbook odds</span><strong><?= aegis_sports_product_e((string) ($providerSettings['oddsSource'] ?? 'Not connected')); ?></strong></div>
                <b><?= !empty($providerSettings['oddsConnected']) ? 'Connected' : 'Setup'; ?></b>
            </div>
        </div>
        <div class="sports-account-panel">
            <strong>Security and API management</strong>
            <div class="sports-account-row"><div><span>Provider secret storage</span><strong><?= aegis_sports_product_e((string) ($providerSettings['secretStorage']['message'] ?? 'Configure provider storage.')); ?></strong></div><b><?= !empty($providerSettings['secretStorage']['encryptedAtRest']) ? 'Encrypted' : 'Action'; ?></b></div>
            <div class="sports-account-row"><div><span>CSRF protection</span><strong>Protected account and provider forms.</strong></div><b>Enabled</b></div>
            <div class="sports-account-row"><div><span>Rate limits</span><strong>Login, registration, API, and global request throttles.</strong></div><b>Enabled</b></div>
            <div class="sports-account-row"><div><span>Session hardening</span><strong>Session rotation, idle expiry, and secure cookie controls.</strong></div><b>Enabled</b></div>
            <div class="sports-account-row"><div><span>Provider API keys</span><strong>Never exposed to frontend app state.</strong></div><b>Server</b></div>
            <div class="sports-product-cta">
                <a class="sports-product-button" href="/app#settings">Provider Settings</a>
                <a class="sports-product-button" href="/app#execution">Execution Center</a>
            </div>
        </div>
    </div>
</section>

<section class="sports-product-section">
    <span class="sports-product-kicker">Activity and limits</span>
    <h2>Recent operating state.</h2>
    <div class="sports-product-grid">
        <article class="sports-product-card"><span>Max stake</span><strong>$<?= aegis_sports_product_e((string) ($riskLimits['maxStakePerOrder'] ?? '50')); ?></strong><p>Configured in Execution Center risk limits.</p></article>
        <article class="sports-product-card"><span>Daily loss limit</span><strong>$<?= aegis_sports_product_e((string) ($riskLimits['maxDailyLoss'] ?? '250')); ?></strong><p>Blocks live actions when exceeded.</p></article>
        <article class="sports-product-card"><span>Cooldown</span><strong><?= aegis_sports_product_e((string) ($riskLimits['cooldownMinutes'] ?? '10')); ?> min</strong><p>Responsible-use pacing guard.</p></article>
    </div>
    <div class="sports-feed-stack">
        <?php foreach ($securityEvents as $entry): ?>
            <div class="sports-feed-item">
                <b><?= aegis_sports_product_e((string) ($entry['severity'] ?? 'info')); ?></b>
                <span><?= aegis_sports_product_e((string) ($entry['message'] ?? 'Security event recorded.')); ?></span>
                <strong><?= aegis_sports_product_e((string) ($entry['createdAt'] ?? 'Now')); ?></strong>
            </div>
        <?php endforeach; ?>
        <?php if ($auditLogs === []): ?>
            <div class="sports-feed-item"><b>Audit</b><span>No recent execution audit entries yet.</span><strong>Idle</strong></div>
        <?php else: ?>
            <?php foreach ($auditLogs as $entry): ?>
                <div class="sports-feed-item">
                    <b><?= aegis_sports_product_e((string) ($entry['severity'] ?? 'info')); ?></b>
                    <span><?= aegis_sports_product_e((string) ($entry['message'] ?? 'Audit event recorded.')); ?></span>
                    <strong><?= aegis_sports_product_e((string) ($entry['createdAt'] ?? 'Now')); ?></strong>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>
</main>
<?php sports_page_footer(); ?>
