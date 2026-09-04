<?php
declare(strict_types=1);

// Cliente da API do WhatsApp.

function sendWhatsAppMessage(string $to, string $text, string $token, string $phoneId, string $apiVersion): void
{
    $ch = curl_init("https://graph.facebook.com/{$apiVersion}/{$phoneId}/messages");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['messaging_product' => 'whatsapp', 'to' => $to, 'type' => 'text', 'text' => ['body' => $text]], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        throw new RuntimeException("HTTP {$status}: {$error} {$response}");
    }
}
