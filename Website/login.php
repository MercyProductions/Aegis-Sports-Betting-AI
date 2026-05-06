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
    $ipLimit = aegis_sports_product_rate_limit_check('login_ip', $ip, aegis_sports_product_env_int('LINEFORGE_LOGIN_IP_LIMIT', 25), 900);
    $emailLimit = aegis_sports_product_rate_limit_check('login_email', $email !== '' ? $email : $ip, aegis_sports_product_env_int('LINEFORGE_LOGIN_EMAIL_LIMIT', 8), 900);
    if (empty($ipLimit['allowed']) || empty($emailLimit['allowed'])) {
        aegis_sports_product_record_security_event('login_rate_limited', 'Login attempt was rate limited.', 'warning', [
            'emailHash' => $email !== '' ? hash('sha256', $email) : '',
        ]);
        aegis_sports_product_flash('error', 'Too many login attempts. Try again shortly.');
        aegis_sports_product_redirect('/login?next=' . rawurlencode($next));
    }

    if (!aegis_sports_product_verify_csrf($_POST['csrf'] ?? null)) {
        aegis_sports_product_record_security_event('csrf_failed', 'Login CSRF check failed.', 'warning');
        aegis_sports_product_flash('error', 'Session expired. Try logging in again.');
        aegis_sports_product_redirect('/login?next=' . rawurlencode($next));
    }

    $user = aegis_sports_product_verify_user($email, (string) ($_POST['password'] ?? ''));
    if (!$user) {
        aegis_sports_product_record_security_event('login_failed', 'Login failed.', 'warning', [
            'emailHash' => $email !== '' ? hash('sha256', $email) : '',
        ]);
        aegis_sports_product_flash('error', 'Email or password was not recognized.');
        aegis_sports_product_redirect('/login?next=' . rawurlencode($next));
    }

    aegis_sports_product_login_user($user);
    aegis_sports_product_flash('success', 'Welcome back.');
    aegis_sports_product_redirect($next);
}

sports_page_header('Log In', '/login', [
    'description' => 'Log in to the protected Lineforge workspace.',
    'canonical' => '/login',
    'robots' => 'noindex,nofollow',
]);
?>
<main>
    <section class="sports-auth-shell">
        <aside class="sports-auth-ghost" aria-label="Lineforge access preview">
            <span class="sports-product-kicker">Secure operator access</span>
            <h1>Return to the command center.</h1>
            <p class="sports-product-muted">Access provider settings, live board intelligence, paper slips, calibrated confidence, and audit-ready execution research from one protected workspace.</p>
            <div class="sports-product-terminal">
                <div class="sports-product-terminal-head">
                    <strong>Workspace health</strong>
                    <span class="sports-product-status">Protected</span>
                </div>
                <div class="sports-market-row"><div><strong>Session controls</strong><span>CSRF-protected forms and HTTP-only cookies.</span></div><b>Active</b></div>
                <div class="sports-market-row"><div><strong>Provider keys</strong><span>Server-side only, encrypted when credential key is configured.</span></div><b>Guarded</b></div>
                <div class="sports-market-row"><div><strong>Execution mode</strong><span>Manual and paper-first by default.</span></div><b>Safe</b></div>
            </div>
        </aside>
        <div class="sports-auth-card">
            <div class="sports-product-auth-head">
                <h1>Log In</h1>
            </div>
            <p>Access the live board, model reads, provider settings, and account workspace.</p>
            <form method="post" action="/login">
                <input type="hidden" name="csrf" value="<?= aegis_sports_product_e(aegis_sports_product_csrf_token()); ?>">
                <input type="hidden" name="next" value="<?= aegis_sports_product_e($next); ?>">
                <label>
                    <span>Email</span>
                    <input type="email" name="email" autocomplete="email" required>
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" name="password" autocomplete="current-password" maxlength="4096" required>
                </label>
                <button class="sports-product-button is-primary" type="submit">Log In</button>
            </form>
            <p class="sports-auth-meta">No account yet? <a href="/register">Create one</a>.</p>
        </div>
    </section>
</main>
<?php sports_page_footer(); ?>
