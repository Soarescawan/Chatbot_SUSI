<?php
// send_message.php

// A função send_whatsapp_message() deve ser copiada do seu webhook.php ou incluída.

// Lê as credenciais do ambiente do servidor ou do arquivo .env local.
$localEnv = carregarEnvLocal(__DIR__ . '/.env');


$META_ACCESS_TOKEN = valorEnv(['WHATSAPP_ACCESS_TOKEN', 'META_ACCESS_TOKEN'], $localEnv);
$WHATSAPP_PHONE_ID = valorEnv(['WHATSAPP_PHONE_NUMBER_ID', 'WHATSAPP_PHONE_ID'], $localEnv);
$GRAPH_API_VERSION = valorEnv('GRAPH_API_VERSION', $localEnv) ?: 'v23.0';

header('Content-Type: application/json');


$input = file_get_contents('php://input');
$data = json_decode($input, true);

$message_text = $data['text'] ?? null;
$to_number = "555195106387";//$data['to_number'] ?? null;

if (empty($message_text) || empty($to_number)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Mensagem ou número de destino ausente.']);
    exit;
}

if ($META_ACCESS_TOKEN === '' || $WHATSAPP_PHONE_ID === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Credenciais da Meta não configuradas no servidor.']);
    exit;
}

try {
   
    send_whatsapp_message($to_number, $message_text, $META_ACCESS_TOKEN, $WHATSAPP_PHONE_ID, $GRAPH_API_VERSION);
    
    echo json_encode(['success' => true, 'message' => 'Mensagem enviada com sucesso para ' . $to_number]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Falha ao enviar mensagem: ' . $e->getMessage()]);
}


function send_whatsapp_message(string $to_number, string $text_message, string $access_token, string $whatsapp_phone_id, string $api_version): void
{
    $url = "https://graph.facebook.com/$api_version/$whatsapp_phone_id/messages";

    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $to_number,
        'type' => 'text',
        'text' => ['body' => $text_message]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Verifica se houve erro na API da Meta
    if ($http_code < 200 || $http_code >= 300) {
        throw new \Exception("Erro na API da Meta. Código: $http_code. Resposta: $response");
    }
}

function carregarEnvLocal(string $path): array
{
    if (!is_readable($path)) return [];
    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
    return $values;
}

function valorEnv(string|array $keys, array $localEnv): string
{
    foreach ((array) $keys as $key) {
        $value = getenv($key);
        if (is_string($value) && $value !== '') return $value;
        if (isset($localEnv[$key]) && $localEnv[$key] !== '') return (string) $localEnv[$key];
    }
    return '';
}
?>
