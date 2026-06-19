<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/access.php';

const DEEPSEEK_URL = 'https://api.deepseek.com/chat/completions';
const DEFAULT_DEEPSEEK_MODEL = 'deepseek-v4-flash';
const MAX_REQUEST_BYTES = 65536;

debaite_bootstrap();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debaite_send_json(405, ['error' => 'Méthode non autorisée.']);
}

$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength <= 0 || $contentLength > MAX_REQUEST_BYTES) {
    debaite_send_json(400, ['error' => 'Requête vide ou trop volumineuse.']);
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    debaite_send_json(400, ['error' => 'JSON invalide.']);
}

$systemInstruction = trim((string)($payload['systemInstruction'] ?? ''));
$prompt = trim((string)($payload['prompt'] ?? ''));
$debateId = trim((string)($payload['debateId'] ?? ''));

if ($systemInstruction === '' || $prompt === '') {
    debaite_send_json(400, ['error' => 'Instruction système ou prompt manquant.']);
}

try {
    $access = debaite_require_generation_access($debateId);
} catch (DebaiteAccessException $error) {
    debaite_send_json(403, [
        'error' => $error->getMessage(),
        'code' => $error->reason,
        'access' => debaite_access_status(),
    ]);
} catch (RuntimeException $error) {
    debaite_send_json(503, [
        'error' => $error->getMessage(),
        'access' => debaite_access_status(),
    ]);
}

$apiKey = debaite_config_value('DEEPSEEK_API_KEY');
if ($apiKey === '') {
    debaite_send_json(503, ['error' => 'Configuration IA absente.', 'access' => debaite_access_status()]);
}

$model = debaite_config_value('DEEPSEEK_MODEL') ?: DEFAULT_DEEPSEEK_MODEL;
$requestBody = [
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => $systemInstruction],
        ['role' => 'user', 'content' => $prompt],
    ],
    'thinking' => ['type' => 'disabled'],
    'temperature' => 0.8,
    'max_tokens' => 2000,
    'stream' => false,
];

try {
    [$status, $responseBody] = deepseek_request($apiKey, $requestBody);
} catch (RuntimeException $error) {
    debaite_send_json(502, ['error' => $error->getMessage(), 'access' => debaite_access_status()]);
}

$response = json_decode($responseBody, true);
if ($status < 200 || $status >= 300) {
    debaite_send_json(502, ['error' => upstream_error($response), 'access' => debaite_access_status()]);
}

$text = $response['choices'][0]['message']['content'] ?? '';
debaite_send_json(200, [
    'text' => $text ?: "Réponse vide générée par l'IA.",
    'access' => $access,
]);

function deepseek_request(string $apiKey, array $requestBody): array
{
    $body = json_encode($requestBody, JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        throw new RuntimeException('Impossible de préparer la requête IA.');
    }

    if (function_exists('curl_init')) {
        $ch = curl_init(DEEPSEEK_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $responseBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException($error ?: 'Erreur réseau IA.');
        }
        return [$status, (string)$responseBody];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$apiKey}\r\n",
            'content' => $body,
            'timeout' => 60,
            'ignore_errors' => true,
        ],
    ]);
    $responseBody = file_get_contents(DEEPSEEK_URL, false, $context);
    if ($responseBody === false) {
        throw new RuntimeException('Erreur réseau IA.');
    }

    $status = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches)) {
        $status = (int)$matches[1];
    }
    return [$status, (string)$responseBody];
}

function upstream_error(mixed $response): string
{
    if (is_array($response)) {
        $message = $response['error']['message'] ?? $response['message'] ?? '';
        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }
    }
    return 'Erreur du fournisseur IA.';
}
