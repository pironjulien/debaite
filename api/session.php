<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/access.php';

debaite_bootstrap();
debaite_send_json(200, ['access' => debaite_access_status()]);
