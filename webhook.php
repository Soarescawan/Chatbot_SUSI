<?php
declare(strict_types=1);

/*
 * Ponto de entrada do webhook da meta.
 */

// Este arquivo permanece na raiz: é a rota pública registrada na Meta.
// Todos os módulos da aplicação são carregados a partir da mesma raiz.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/processar_mensagem.php';

const LOG_FILE = __DIR__ . '/storage/recebido.txt';

if (!is_dir(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0775, true);
}

$localEnv = loadEnvFile(__DIR__ . '/.env');
$verifyToken = configValue(['WHATSAPP_VERIFY_TOKEN', 'META_VERIFY_TOKEN'], $localEnv);
$accessToken = configValue(['WHATSAPP_ACCESS_TOKEN', 'META_ACCESS_TOKEN'], $localEnv);
$phoneId = configValue(['WHATSAPP_PHONE_NUMBER_ID', 'WHATSAPP_PHONE_ID'], $localEnv);
$apiVersion = configValue('GRAPH_API_VERSION', $localEnv) ?: 'v23.0';
$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    validarWebhookMeta($verifyToken, $accessToken, $phoneId);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    exit;
}

$raw = file_get_contents('php://input');
$raw = is_string($raw) ? $raw : '';
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

function validarWebhookMeta(string $verifyToken, string $accessToken, string $phoneId): void
{
    $mode = $_GET['hub.mode'] ?? $_GET['hub_mode'] ?? '';
    $token = $_GET['hub.verify_token'] ?? $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub.challenge'] ?? $_GET['hub_challenge'] ?? '';

    if ($mode === '' && $token === '' && $challenge === '') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'route' => 'webhook.php',
            'message' => 'Webhook online. Use esta URL no painel da Meta.',
            'checks' => [
                'verify_token_configurado' => $verifyToken !== '',
                'access_token_configurado' => $accessToken !== '',
                'phone_number_id_configurado' => $phoneId !== '',
                'storage_gravavel' => is_writable(__DIR__) || is_writable(__DIR__ . '/storage'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return;
    }

    if ($mode === 'subscribe' && $verifyToken !== '' && hash_equals($verifyToken, (string) $token)) {
        header('Content-Type: text/plain; charset=utf-8');
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
                $type = (string) ($message['type'] ?? '');
                $interactive = is_array($message['interactive'] ?? null) ? $message['interactive'] : [];
                $selection = $interactive['button_reply']['id']
                    ?? $interactive['list_reply']['id']
                    ?? $interactive['button_reply']['title']
                    ?? $interactive['list_reply']['title']
                    ?? '';
                $received[] = [
                    'from' => (string) ($message['from'] ?? ''),
                    // Clique em lista/botão entra no fluxo como se fosse texto.
                    'type' => $type === 'interactive' ? 'text' : $type,
                    'text' => trim((string) ($selection !== '' ? $selection : ($message['text']['body'] ?? ''))),
                ];
            }
        }
    }

    return array_filter($received, static fn(array $message): bool => $message['from'] !== '');
}
