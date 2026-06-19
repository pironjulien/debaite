<?php
declare(strict_types=1);

const DEBAITE_DEFAULT_TRIAL_DEBATE_LIMIT = 1;
const DEBAITE_DEFAULT_TRIAL_STEP_LIMIT = 8;
const DEBAITE_DEFAULT_IP_DAILY_STEP_LIMIT = 40;

final class DebaiteAccessException extends RuntimeException
{
    public string $reason;

    public function __construct(string $reason, string $message)
    {
        parent::__construct($message);
        $this->reason = $reason;
    }
}

function debaite_bootstrap(): void
{
    debaite_security_headers();

    if (session_status() === PHP_SESSION_NONE) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        session_name('debaite_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/debaite',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function debaite_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
}

function debaite_config_value(string $name): string
{
    static $env = null;

    $value = getenv($name);
    if (is_string($value) && trim($value) !== '') {
        return trim($value);
    }

    if ($env === null) {
        $env = [];
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (is_file($envPath) && is_readable($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $rawValue] = explode('=', $line, 2);
                $env[trim($key)] = trim(trim($rawValue), "\"'");
            }
        }
    }

    return trim((string)($env[$name] ?? ''));
}

function debaite_config_list(string $name): array
{
    $value = debaite_config_value($name);
    if ($value === '') {
        return [];
    }

    $parts = preg_split('/[\s,;]+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return array_values(array_unique(array_map('trim', $parts)));
}

function debaite_trial_limit(): int
{
    return debaite_int_config('DEBAITE_TRIAL_DEBATE_LIMIT', DEBAITE_DEFAULT_TRIAL_DEBATE_LIMIT, 1, 5);
}

function debaite_trial_step_limit(): int
{
    return debaite_int_config('DEBAITE_TRIAL_STEP_LIMIT', DEBAITE_DEFAULT_TRIAL_STEP_LIMIT, 1, 24);
}

function debaite_ip_daily_limit(): int
{
    return debaite_int_config('DEBAITE_PUBLIC_IP_DAILY_STEP_LIMIT', DEBAITE_DEFAULT_IP_DAILY_STEP_LIMIT, 8, 240);
}

function debaite_int_config(string $name, int $default, int $min, int $max): int
{
    $value = debaite_config_value($name);
    if ($value === '' || !ctype_digit($value)) {
        return $default;
    }
    return max($min, min($max, (int)$value));
}

function debaite_app_secret(): string
{
    $secret = debaite_config_value('DEBAITE_APP_SECRET');
    if ($secret !== '') {
        return $secret;
    }

    $deepseekKey = debaite_config_value('DEEPSEEK_API_KEY');
    if ($deepseekKey !== '') {
        return $deepseekKey;
    }

    return 'debaite-local-development-secret';
}

function debaite_csrf_token(): string
{
    debaite_bootstrap();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function debaite_require_csrf(): void
{
    debaite_bootstrap();

    $expected = debaite_csrf_token();
    $provided = (string)($_SERVER['HTTP_X_DEBAITE_CSRF'] ?? '');
    if (!hash_equals($expected, $provided)) {
        throw new DebaiteAccessException('csrf', 'Session invalide. Rechargez Debaite.');
    }
}

function debaite_google_enabled(): bool
{
    return debaite_config_value('DEBAITE_GOOGLE_CLIENT_ID') !== ''
        && debaite_config_value('DEBAITE_GOOGLE_CLIENT_SECRET') !== '';
}

function debaite_google_allowed_emails(): array
{
    return debaite_config_list('DEBAITE_GOOGLE_ALLOWED_EMAILS');
}

function debaite_allowed_google_email(?string $email): bool
{
    if (!$email) {
        return false;
    }

    return in_array(strtolower(trim($email)), debaite_google_allowed_emails(), true);
}

function debaite_authenticated_email(): string
{
    debaite_bootstrap();

    $email = $_SESSION['google_email'] ?? '';
    return is_string($email) ? strtolower(trim($email)) : '';
}

function debaite_has_unlimited_access(): bool
{
    return debaite_allowed_google_email(debaite_authenticated_email());
}

function debaite_contact_url(): string
{
    return debaite_config_value('DEBAITE_CONTACT_URL') ?: 'https://twitter.com/julienpironfr';
}

function debaite_access_status(): array
{
    debaite_bootstrap();

    $email = debaite_authenticated_email();
    $authenticated = $email !== '';
    $unlimited = debaite_has_unlimited_access();
    $trial = debaite_trial_status();

    return [
        'authenticated' => $authenticated,
        'email' => $email,
        'unlimited' => $unlimited,
        'canGenerate' => $unlimited || ($authenticated && !$trial['blocked']),
        'googleEnabled' => debaite_google_enabled(),
        'loginUrl' => 'api/google/start',
        'logoutUrl' => 'api/logout',
        'contactUrl' => debaite_contact_url(),
        'csrfToken' => debaite_csrf_token(),
        'trial' => $trial,
    ];
}

function debaite_trial_status(): array
{
    $limit = debaite_trial_limit();
    $stepLimit = debaite_trial_step_limit();
    $identity = debaite_public_identity_hash();
    $data = debaite_read_usage_store();
    $record = $data['identities'][$identity] ?? [];
    $used = debaite_record_debates_used($record);
    $activeDebateId = is_string($record['activeDebateId'] ?? null) ? (string)$record['activeDebateId'] : '';
    if (!preg_match('/^[a-f0-9-]{16,80}$/', $activeDebateId)) {
        $activeDebateId = '';
    }
    $activeSteps = max(0, (int)($record['activeDebateSteps'] ?? 0));
    $active = $activeDebateId !== '' && $activeSteps < $stepLimit;
    $remaining = max(0, $limit - $used);

    $status = [
        'limit' => $limit,
        'used' => $used,
        'remaining' => $active ? 1 : $remaining,
        'blocked' => $remaining <= 0 && !$active,
        'active' => $active,
        'stepLimit' => $stepLimit,
        'stepsUsed' => min($activeSteps, $stepLimit),
    ];

    if ($active) {
        $status['activeDebateId'] = $activeDebateId;
    }

    return $status;
}

function debaite_record_debates_used(array $record): int
{
    if (isset($record['debatesUsed'])) {
        return max(0, (int)$record['debatesUsed']);
    }

    return ((int)($record['stepsUsed'] ?? 0)) > 0 ? 1 : 0;
}

function debaite_normalize_debate_id(string $debateId): string
{
    $debateId = strtolower(trim($debateId));
    if (!preg_match('/^[a-f0-9-]{16,80}$/', $debateId)) {
        throw new DebaiteAccessException('invalid_debate', 'Débat invalide. Rechargez Debaite.');
    }

    return $debateId;
}

function debaite_require_generation_access(string $debateId): array
{
    debaite_require_csrf();

    if (debaite_has_unlimited_access()) {
        return debaite_access_status();
    }

    $debateId = debaite_normalize_debate_id($debateId);

    if (debaite_authenticated_email() === '') {
        throw new DebaiteAccessException('google_required', 'Connexion Google requise pour tester Debaite.');
    }

    debaite_reserve_public_step($debateId);
    return debaite_access_status();
}

function debaite_reserve_public_step(string $debateId): void
{
    $identity = debaite_public_identity_hash();
    $ipKey = debaite_public_ip_hash();
    $today = gmdate('Y-m-d');
    $debateLimit = debaite_trial_limit();
    $stepLimit = debaite_trial_step_limit();
    $ipLimit = debaite_ip_daily_limit();

    debaite_mutate_usage_store(function (array $data) use ($identity, $ipKey, $today, $debateLimit, $stepLimit, $ipLimit, $debateId): array {
        $now = gmdate('c');
        $data['version'] = 1;
        $data['identities'] = $data['identities'] ?? [];
        $data['ipDays'] = $data['ipDays'] ?? [];

        $record = $data['identities'][$identity] ?? [
            'firstSeen' => $now,
            'stepsUsed' => 0,
        ];

        $debatesUsed = debaite_record_debates_used($record);
        $activeDebateId = is_string($record['activeDebateId'] ?? null) ? (string)$record['activeDebateId'] : '';
        $activeSteps = max(0, (int)($record['activeDebateSteps'] ?? 0));

        if ($activeDebateId === '' || !hash_equals($activeDebateId, $debateId)) {
            if ($debatesUsed >= $debateLimit) {
                throw new DebaiteAccessException('trial_exhausted', 'Essai public terminé. Contactez Julien pour débloquer l’accès.');
            }
            $activeDebateId = $debateId;
            $activeSteps = 0;
            $debatesUsed++;
            $record['activeDebateId'] = $activeDebateId;
            $record['activeDebateStartedAt'] = $now;
        }

        if ($activeSteps >= $stepLimit) {
            throw new DebaiteAccessException('trial_exhausted', 'Essai public terminé. Contactez Julien pour débloquer l’accès.');
        }

        $dayKey = $ipKey . ':' . $today;
        $ipDay = $data['ipDays'][$dayKey] ?? [
            'date' => $today,
            'stepsUsed' => 0,
        ];
        if ((int)($ipDay['stepsUsed'] ?? 0) >= $ipLimit) {
            throw new DebaiteAccessException('rate_limited', 'Trop d’essais publics depuis cette connexion aujourd’hui.');
        }

        $record['debatesUsed'] = $debatesUsed;
        $record['activeDebateId'] = $activeDebateId;
        $record['activeDebateSteps'] = $activeSteps + 1;
        $record['stepsUsed'] = (int)($record['stepsUsed'] ?? 0) + 1;
        $record['lastSeen'] = $now;
        if ($record['activeDebateSteps'] >= $stepLimit) {
            $record['completedAt'] = $now;
        }

        $ipDay['stepsUsed'] = (int)($ipDay['stepsUsed'] ?? 0) + 1;
        $ipDay['lastSeen'] = $now;

        $data['identities'][$identity] = $record;
        $data['ipDays'][$dayKey] = $ipDay;
        $data['ipDays'] = debaite_prune_ip_days($data['ipDays']);

        return $data;
    });
}

function debaite_prune_ip_days(array $ipDays): array
{
    $cutoff = strtotime('-14 days');
    foreach ($ipDays as $key => $record) {
        $date = (string)($record['date'] ?? '');
        if ($date === '' || strtotime($date) < $cutoff) {
            unset($ipDays[$key]);
        }
    }
    return $ipDays;
}

function debaite_public_identity_hash(): string
{
    $email = debaite_authenticated_email();
    if ($email !== '') {
        $sub = (string)($_SESSION['google_sub'] ?? '');
        $stableGoogleId = $sub !== '' ? $sub : $email;
        return hash_hmac('sha256', 'google|' . $stableGoogleId, debaite_app_secret());
    }

    $device = (string)($_SERVER['HTTP_X_DEBAITE_DEVICE'] ?? '');
    if (!preg_match('/^[a-f0-9-]{16,64}$/i', $device)) {
        $device = 'unknown-device';
    }

    $raw = implode('|', [
        debaite_client_ip(),
        strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? '')),
        strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')),
        strtolower($device),
    ]);

    return hash_hmac('sha256', $raw, debaite_app_secret());
}

function debaite_public_ip_hash(): string
{
    return hash_hmac('sha256', debaite_client_ip(), debaite_app_secret());
}

function debaite_client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function debaite_usage_store_path(): string
{
    $configured = debaite_config_value('DEBAITE_USAGE_STORE');
    if ($configured !== '') {
        return $configured;
    }

    return dirname(__DIR__, 2) . '/.runtime/usage.json';
}

function debaite_read_usage_store(): array
{
    $path = debaite_usage_store_path();
    if (!is_file($path) || !is_readable($path)) {
        return ['version' => 1, 'identities' => [], 'ipDays' => []];
    }

    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : ['version' => 1, 'identities' => [], 'ipDays' => []];
}

function debaite_mutate_usage_store(callable $mutator): void
{
    $path = debaite_usage_store_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Impossible de préparer le quota Debaite.');
    }

    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Impossible d’ouvrir le quota Debaite.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Impossible de verrouiller le quota Debaite.');
        }

        $raw = stream_get_contents($handle);
        $data = $raw ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            $data = ['version' => 1, 'identities' => [], 'ipDays' => []];
        }

        $data = $mutator($data);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('Impossible d’enregistrer le quota Debaite.');
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $json . "\n");
        fflush($handle);
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function debaite_exchange_google_code(string $code, string $redirectUri): array
{
    $body = http_build_query([
        'code' => $code,
        'client_id' => debaite_config_value('DEBAITE_GOOGLE_CLIENT_ID'),
        'client_secret' => debaite_config_value('DEBAITE_GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ], '', '&');

    return debaite_http_json('https://oauth2.googleapis.com/token', 'POST', $body, [
        'Content-Type: application/x-www-form-urlencoded',
    ]);
}

function debaite_verify_google_id_token(string $idToken): array
{
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken);
    $claims = debaite_http_json($url, 'GET');

    if (($claims['aud'] ?? '') !== debaite_config_value('DEBAITE_GOOGLE_CLIENT_ID')) {
        throw new RuntimeException('Audience Google invalide.');
    }

    $exp = (int)($claims['exp'] ?? 0);
    if ($exp > 0 && $exp < time()) {
        throw new RuntimeException('Session Google expirée.');
    }

    $email = strtolower(trim((string)($claims['email'] ?? '')));
    $verified = (string)($claims['email_verified'] ?? '') === 'true' || $claims['email_verified'] === true;
    if ($email === '' || !$verified) {
        throw new RuntimeException('Email Google non vérifié.');
    }

    return $claims;
}

function debaite_http_json(string $url, string $method = 'GET', ?string $body = null, array $headers = []): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException($error ?: 'Erreur réseau.');
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body ?? '',
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException('Erreur réseau.');
        }
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $status = (int)$matches[1];
        }
    }

    $data = json_decode((string)$responseBody, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        throw new RuntimeException('Réponse externe invalide.');
    }

    return $data;
}

function debaite_google_redirect_uri(): string
{
    $configured = debaite_config_value('DEBAITE_GOOGLE_REDIRECT_URI');
    if ($configured !== '') {
        return $configured;
    }

    $host = (string)($_SERVER['HTTP_HOST'] ?? 'julienpiron.fr');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
    return $scheme . '://' . $host . '/debaite/api/google/callback';
}

function debaite_redirect_home(string $status): never
{
    header('Location: /debaite/?auth=' . rawurlencode($status), true, 302);
    exit;
}

function debaite_send_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
