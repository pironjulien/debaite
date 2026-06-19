<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/access.php';

debaite_bootstrap();

if (!debaite_google_enabled()) {
    debaite_redirect_home('google-disabled');
}

$state = bin2hex(random_bytes(24));
$_SESSION['google_oauth_state'] = $state;

$params = [
    'client_id' => debaite_config_value('DEBAITE_GOOGLE_CLIENT_ID'),
    'redirect_uri' => debaite_google_redirect_uri(),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'prompt' => 'select_account',
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&'), true, 302);
exit;
