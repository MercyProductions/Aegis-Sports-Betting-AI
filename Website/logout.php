<?php

require_once __DIR__ . '/config/sports_product.php';

aegis_sports_product_bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !aegis_sports_product_verify_csrf($_POST['csrf'] ?? null)) {
    aegis_sports_product_record_security_event('logout_rejected', 'Logout request failed CSRF or method check.', 'warning');
    aegis_sports_product_flash('error', 'Logout request expired. Try again from your account page.');
    aegis_sports_product_redirect(aegis_sports_product_account()['signedIn'] ? '/account' : '/');
}

aegis_sports_product_logout_user();
aegis_sports_product_flash('success', 'You have been logged out.');
aegis_sports_product_redirect('/');
