<?php

require_once __DIR__ . '/config/product_layout.php';

aegis_sports_product_bootstrap();
$next = aegis_sports_product_safe_next((string) ($_GET['next'] ?? $_POST['next'] ?? '/app'));

if (aegis_sports_product_account()['signedIn']) {
    aegis_sports_product_redirect($next);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = aegis_sports_product_normalize_email((string) ($_POST['email'] ?? ''));
    $ip = aegis_sports_product_client_ip();
    $ipLimit = aegis_sports_product_rate_limit_check('register_ip', $ip, aegis_sports_product_env_int('LINEFORGE_REGISTER_IP_LIMIT', 8), 3600);
    $emailLimit = aegis_sports_product_rate_limit_check('register_email', $email !== '' ? $email : $ip, aegis_sports_product_env_int('LINEFORGE_REGISTER_EMAIL_LIMIT', 3), 3600);
    if (empty($ipLimit['allowed']) || empty($emailLimit['allowed'])) {
        aegis_sports_product_record_security_event('register_rate_limited', 'Registration attempt was rate limited.', 'warning', [
            'emailHash' => $email !== '' ? hash('sha256', $email) : '',
        ]);
        aegis_sports_product_flash('error', 'Too many account creation attempts. Try again shortly.');
        aegis_sports_product_redirect('/register?next=' . rawurlencode($next));
    }

    if (!aegis_sports_product_verify_csrf($_POST['csrf'] ?? null)) {
        aegis_sports_product_record_security_event('csrf_failed', 'Registration CSRF check failed.', 'warning');
        aegis_sports_product_flash('error', 'Session expired. Try creating the account again.');
        aegis_sports_product_redirect('/register?next=' . rawurlencode($next));
    }

    try {
        $user = aegis_sports_product_create_user(
            $email,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['display_name'] ?? '')
        );
        aegis_sports_product_login_user($user);
        aegis_sports_product_flash('success', 'Account created. You are in.');
        aegis_sports_product_redirect($next);
    } catch (Throwable $error) {
        aegis_sports_product_flash('error', $error->getMessage());
        aegis_sports_product_redirect('/register?next=' . rawurlencode($next));
    }
}

sports_page_header('Create Account', '/register', [
    'description' => 'Create a local Lineforge account for the protected research workspace.',
    'canonical' => '/register',
    'robots' => 'noindex,nofollow',
]);
?>
<main>
    <section class="sports-auth-shell">
        <aside class="sports-auth-ghost" aria-label="Lineforge onboarding preview">
            <span class="sports-product-kicker">Operator onboarding</span>
            <h1>Create a research workspace.</h1>
            <p class="sports-product-muted">Start with public intelligence mode, then connect official or licensed data providers as the platform grows.</p>
            <div class="sports-flow-diagram">
                <article class="sports-flow-node"><strong>Account</strong><span>Local workspace access and tier behavior.</span></article>
                <article class="sports-flow-node"><strong>Providers</strong><span>Optional odds, lineup, injury, and execution connections.</span></article>
                <article class="sports-flow-node"><strong>Controls</strong><span>Paper-first mode, risk limits, and manual verification.</span></article>
                <article class="sports-flow-node"><strong>Audit</strong><span>Track rules, skips, errors, and decision history.</span></article>
            </div>
        </aside>
        <div class="sports-auth-card">
            <h1>Create Account</h1>
            <p>This standalone auth pass stores accounts locally in a locked JSON file for product work and can be swapped to a database later.</p>
            <form method="post" action="/register">
                <input type="hidden" name="csrf" value="<?= aegis_sports_product_e(aegis_sports_product_csrf_token()); ?>">
                <input type="hidden" name="next" value="<?= aegis_sports_product_e($next); ?>">
                <label>
                    <span>Display name</span>
                    <input type="text" name="display_name" autocomplete="name" required>
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="email" autocomplete="email" required>
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" name="password" autocomplete="new-password" minlength="10" maxlength="4096" required>
                </label>
                <button class="sports-product-button is-primary" type="submit">Create Account</button>
            </form>
            <p class="sports-auth-meta">Already have an account? <a href="/login">Log in</a>.</p>
        </div>
    </section>
</main>
<?php sports_page_footer(); ?>
