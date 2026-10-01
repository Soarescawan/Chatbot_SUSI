<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$env = loadEnvFile(__DIR__ . '/.env');
$storageDir = __DIR__ . '/storage';
$storageReady = is_dir($storageDir) || @mkdir($storageDir, 0775, true);
$stateFile = $storageDir . '/conversas.json';
$logFile = $storageDir . '/recebido.txt';

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ok' => true,
    'timestamp' => date(DATE_ATOM),
    'routes' => [
        'webhook' => 'webhook.php',
        'send_message' => 'send_message.php',
        'health' => 'health.php',
    ],
    'checks' => [
        'php_version' => PHP_VERSION,
        'curl_extension' => function_exists('curl_init'),
        'pdo_extension' => extension_loaded('pdo'),
        'pdo_mysql_extension' => extension_loaded('pdo_mysql'),
        'env_file_present' => is_file(__DIR__ . '/.env'),
        'storage_directory_ready' => $storageReady,
        'storage_directory_writable' => is_writable($storageDir),
        'state_file_writable' => !is_file($stateFile) ? is_writable($storageDir) : is_writable($stateFile),
        'webhook_log_writable' => !is_file($logFile) ? is_writable($storageDir) : is_writable($logFile),
        'whatsapp_verify_token' => configValue(['WHATSAPP_VERIFY_TOKEN', 'META_VERIFY_TOKEN'], $env) !== '',
        'whatsapp_access_token' => configValue(['WHATSAPP_ACCESS_TOKEN', 'META_ACCESS_TOKEN'], $env) !== '',
        'whatsapp_phone_number_id' => configValue(['WHATSAPP_PHONE_NUMBER_ID', 'WHATSAPP_PHONE_ID'], $env) !== '',
        'graph_api_version' => configValue('GRAPH_API_VERSION', $env) !== '',
        'openai_api_key' => configValue('OPENAI_API_KEY', $env) !== '',
        'database_host' => configValue('DB_HOST', $env) !== '',
        'database_name' => configValue('DB_DATABASE', $env) !== '',
        'database_user' => configValue('DB_USERNAME', $env) !== '',
        'database_password' => configValue('DB_PASSWORD', $env) !== '',
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
