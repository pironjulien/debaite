<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/access.php';

debaite_bootstrap();

try {
    if (!debaite_google_enabled()) {
        throw new RuntimeException('Google OAuth non configuré.');
    }

    $expectedState = (string)($_SESSION['google_oauth_state'] ?? '');
    $providedState = (string)($_GET['state'] ?? '');
    unset($_SESSION['google_oauth_state']);

    if ($expectedState === '' || !hash_equals($expectedState, $providedState)) {
        throw new RuntimeException('Etat OAuth invalide.');
    }

    $code = (string)($_GET['code'] ?? '');
    if ($code === '') {
        throw new RuntimeException('Code OAuth manquant.');
    }

    $tokens = debaite_exchange_google_code($code, debaite_google_redirect_uri());
    $idToken = (string)($tokens['id_token'] ?? '');
    if ($idToken === '') {
        throw new RuntimeException('Jeton Google manquant.');
    }

    $claims = debaite_verify_google_id_token($idToken);
    $email = strtolower(trim((string)$claims['email']));

    session_regenerate_id(true);
    $_SESSION['google_email'] = $email;
    $_SESSION['google_name'] = (string)($claims['name'] ?? '');
    $_SESSION['google_sub'] = (string)($claims['sub'] ?? '');
    debaite_csrf_token();

    debaite_redirect_home('google-ok');
} catch (Throwable) {
    unset($_SESSION['google_email'], $_SESSION['google_name']);
    debaite_redirect_home('google-error');
}
