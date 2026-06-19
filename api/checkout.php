<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/access.php';

const STRIPE_CHECKOUT_URL = 'https://api.stripe.com/v1/checkout/sessions';

debaite_bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debaite_send_json(405, ['error' => 'Méthode non autorisée.']);
}

try {
    debaite_require_csrf();
} catch (DebaiteAccessException $error) {
    debaite_send_json(403, ['error' => $error->getMessage(), 'code' => $error->reason, 'access' => debaite_access_status()]);
}

$email = debaite_authenticated_email();
if ($email === '') {
    debaite_send_json(403, ['error' => 'Connexion Google requise avant achat.', 'code' => 'google_required', 'access' => debaite_access_status()]);
}

if (!debaite_billing_enabled()) {
    debaite_send_json(503, ['error' => 'Paiement pas encore configuré.', 'code' => 'billing_disabled', 'access' => debaite_access_status()]);
}

$stripeKey = debaite_config_value('DEBAITE_STRIPE_SECRET_KEY');
$identity = debaite_public_identity_hash();
$pack = debaite_credit_pack();
$description = sprintf('%d crédits Debaite', (int)$pack['credits']);

$fields = [
    'mode' => 'payment',
    'client_reference_id' => $identity,
    'customer_email' => $email,
    'success_url' => debaite_absolute_url('/debaite/?payment=success&session_id={CHECKOUT_SESSION_ID}'),
    'cancel_url' => debaite_absolute_url('/debaite/?payment=cancel'),
    'payment_method_types[0]' => 'card',
    'line_items[0][quantity]' => '1',
    'line_items[0][price_data][currency]' => $pack['currency'],
    'line_items[0][price_data][unit_amount]' => (string)$pack['amountCents'],
    'line_items[0][price_data][product_data][name]' => $description,
    'line_items[0][price_data][product_data][description]' => 'Recharge de crédits pour débats IA.',
    'metadata[identity]' => $identity,
    'metadata[credits]' => (string)$pack['credits'],
    'metadata[pack]' => 'debaite_low_cost_pack',
];

try {
    $session = debaite_stripe_post($stripeKey, $fields);
} catch (RuntimeException $error) {
    debaite_send_json(502, ['error' => $error->getMessage(), 'access' => debaite_access_status()]);
}

$url = is_array($session) ? (string)($session['url'] ?? '') : '';
if ($url === '' || !str_starts_with($url, 'https://')) {
    debaite_send_json(502, ['error' => 'Session de paiement invalide.', 'access' => debaite_access_status()]);
}

debaite_send_json(200, ['url' => $url, 'access' => debaite_access_status()]);

function debaite_stripe_post(string $apiKey, array $fields): array
{
    $body = http_build_query($fields, '', '&');
    $headers = [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Bearer ' . $apiKey,
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init(STRIPE_CHECKOUT_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $responseBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException($error ?: 'Erreur réseau paiement.');
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $responseBody = file_get_contents(STRIPE_CHECKOUT_URL, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException('Erreur réseau paiement.');
        }
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
            $status = (int)$matches[1];
        }
    }

    $data = json_decode((string)$responseBody, true);
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        $message = is_array($data) ? (string)($data['error']['message'] ?? '') : '';
        throw new RuntimeException($message ?: 'Paiement indisponible.');
    }

    return $data;
}

function debaite_absolute_url(string $path): string
{
    $host = preg_replace('/[^a-z0-9.\-:]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'julienpiron.fr'));
    if ($host === '') {
        $host = 'julienpiron.fr';
    }
    return 'https://' . $host . $path;
}
