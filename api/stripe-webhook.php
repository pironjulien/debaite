<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/access.php';

debaite_security_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debaite_send_json(405, ['error' => 'Méthode non autorisée.']);
}

$payload = (string)file_get_contents('php://input');
$signature = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
$secret = debaite_config_value('DEBAITE_STRIPE_WEBHOOK_SECRET');

if ($secret === '') {
    debaite_send_json(503, ['error' => 'Webhook non configuré.']);
}

if (!debaite_verify_stripe_signature($payload, $signature, $secret)) {
    debaite_send_json(400, ['error' => 'Signature Stripe invalide.']);
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    debaite_send_json(400, ['error' => 'Payload Stripe invalide.']);
}

$type = (string)($event['type'] ?? '');
if ($type === 'checkout.session.completed' || $type === 'checkout.session.async_payment_succeeded') {
    $session = $event['data']['object'] ?? null;
    if (is_array($session)) {
        debaite_fulfill_checkout_session($session);
    }
}

debaite_send_json(200, ['received' => true]);

function debaite_verify_stripe_signature(string $payload, string $header, string $secret): bool
{
    if ($payload === '' || $header === '') {
        return false;
    }

    $timestamp = '';
    $signatures = [];
    foreach (explode(',', $header) as $part) {
        [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($key === 't') {
            $timestamp = $value;
        } elseif ($key === 'v1' && $value !== '') {
            $signatures[] = $value;
        }
    }

    if ($timestamp === '' || !ctype_digit($timestamp) || !$signatures) {
        return false;
    }

    if (abs(time() - (int)$timestamp) > 300) {
        return false;
    }

    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            return true;
        }
    }

    return false;
}

function debaite_fulfill_checkout_session(array $session): void
{
    if (($session['mode'] ?? '') !== 'payment') {
        return;
    }

    $paymentStatus = (string)($session['payment_status'] ?? '');
    if ($paymentStatus !== 'paid' && $paymentStatus !== 'no_payment_required') {
        return;
    }

    $pack = debaite_credit_pack();
    $currency = strtolower((string)($session['currency'] ?? ''));
    $amount = (int)($session['amount_total'] ?? 0);
    if ($currency !== $pack['currency'] || $amount < (int)$pack['amountCents']) {
        return;
    }

    $metadata = is_array($session['metadata'] ?? null) ? $session['metadata'] : [];
    $identity = (string)($metadata['identity'] ?? $session['client_reference_id'] ?? '');
    $purchaseId = (string)($session['id'] ?? '');
    if ($identity === '' || $purchaseId === '') {
        return;
    }

    debaite_grant_credit_purchase($identity, (int)$pack['credits'], $purchaseId, $amount, $currency);
}
