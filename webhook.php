<?php
declare(strict_types=1);

/*
 * Ponto de entrada do webhook da Meta.
 * Este arquivo cuida somente da validação e do recebimento do evento.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/processar_mensagem.php';

const LOG_FILE = __DIR__ . '/recebido.txt';

$localEnv = loadEnvFile(__DIR__ . '/.env');
$verifyToken = configValue(['WHATSAPP_VERIFY_TOKEN', 'META_VERIFY_TOKEN'], $localEnv);
$accessToken = configValue(['WHATSAPP_ACCESS_TOKEN', 'META_ACCESS_TOKEN'], $localEnv);
$phoneId = configValue(['WHATSAPP_PHONE_NUMBER_ID', 'WHATSAPP_PHONE_ID'], $localEnv);
$apiVersion = configValue('GRAPH_API_VERSION', $localEnv) ?: 'v23.0';
$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    validarWebhookMeta($verifyToken);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    exit;
}

$raw = file_get_contents('php://input');
file_put_contents(LOG_FILE, date(DATE_ATOM) . ' ' . $raw . PHP_EOL, FILE_APPEND | LOCK_EX);
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    echo 'JSON inválido';
    exit;
}

foreach (extractIncomingMessages($payload) as $message) {
    processarMensagemRecebida($message, $accessToken, $phoneId, $apiVersion);
}

http_response_code(200);
echo 'OK';

function validarWebhookMeta(string $verifyToken): void
{
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';

    if ($mode === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, (string) $token)) {
        echo $challenge;
        return;
    }

    http_response_code(403);
    echo 'Token inválido';
}

function extractIncomingMessages(array $payload): array
{
    $received = [];
    foreach ($payload['entry'] ?? [] as $entry) {
        foreach ($entry['changes'] ?? [] as $change) {
            foreach (($change['value']['messages'] ?? []) as $message) {
                $received[] = [
                    'from' => (string) ($message['from'] ?? ''),
                    'type' => (string) ($message['type'] ?? ''),
                    'text' => trim((string) ($message['text']['body'] ?? '')),
                ];
            }
        }
    }

    return array_filter($received, static fn(array $message): bool => $message['from'] !== '');
}
