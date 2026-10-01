<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/whatsapp.php';

// Lê aas credenciais do ambiente do servidor ou do arquivo .env local.
$localEnv = loadEnvFile(__DIR__ . '/.env');


$META_ACCESS_TOKEN = configValue(['WHATSAPP_ACCESS_TOKEN', 'META_ACCESS_TOKEN'], $localEnv);
$WHATSAPP_PHONE_ID = configValue(['WHATSAPP_PHONE_NUMBER_ID', 'WHATSAPP_PHONE_ID'], $localEnv);
$GRAPH_API_VERSION = configValue('GRAPH_API_VERSION', $localEnv) ?: 'v23.0';

header('Content-Type: application/json');


$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!is_array($data)) {
    $data = [];
}

$message_text = $data['text'] ?? $data['message'] ?? $_POST['text'] ?? $_POST['message'] ?? null;
$to_number = $data['to_number'] ?? $data['to'] ?? $_POST['to_number'] ?? $_POST['to'] ?? null;

if (empty($message_text) || empty($to_number)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Mensagem ou número de destino ausente.',
        'expected' => ['text' => 'Sua mensagem', 'to_number' => '5511999999999'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($META_ACCESS_TOKEN === '' || $WHATSAPP_PHONE_ID === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Credenciais da Meta não configuradas no servidor.']);
    exit;
}

try {
    sendWhatsAppMessage((string) $to_number, (string) $message_text, $META_ACCESS_TOKEN, $WHATSAPP_PHONE_ID, $GRAPH_API_VERSION);
    echo json_encode([
        'success' => true,
        'message' => 'Mensagem enviada com sucesso para ' . preg_replace('/\D+/', '', (string) $to_number),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Falha ao enviar mensagem: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}


?>
