<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/access.php';

try {
    debaite_require_csrf();
} catch (DebaiteAccessException $error) {
    debaite_send_json(403, [
        'error' => $error->getMessage(),
        'code' => $error->reason,
        'access' => debaite_access_status(),
    ]);
}

unset($_SESSION['google_email'], $_SESSION['google_name'], $_SESSION['google_sub']);
debaite_send_json(200, ['access' => debaite_access_status()]);
