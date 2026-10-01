<?php
declare(strict_types=1);

// Cliente da API do WhatsApp Cloud API.

function sendWhatsAppMessage(string $to, string $text, string $token, string $phoneId, string $apiVersion): void
{
    $text = trim($text);
    if ($text === '') {
        throw new RuntimeException('A mensagem de texto está vazia.');
    }

    enviarPayloadWhatsApp([
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => normalizarTelefoneWhatsApp($to),
        'type' => 'text',
        'text' => ['preview_url' => false, 'body' => limitarTextoWhatsApp($text, 4096)],
    ], $token, $phoneId, $apiVersion);
}

/** @param array<string, mixed> $interactive */
function sendWhatsAppInteractiveMessage(string $to, array $interactive, string $token, string $phoneId, string $apiVersion): void
{
    $payload = normalizarMensagemInterativa($interactive);
    enviarPayloadWhatsApp([
        'messaging_product' => 'whatsapp',
        'recipient_type' => 'individual',
        'to' => normalizarTelefoneWhatsApp($to),
        'type' => 'interactive',
        'interactive' => $payload,
    ], $token, $phoneId, $apiVersion);
}

/** @param array<string, mixed> $payload */
function enviarPayloadWhatsApp(array $payload, string $token, string $phoneId, string $apiVersion): void
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensão cURL do PHP não está instalada no servidor.');
    }

    if ($token === '' || $phoneId === '') {
        throw new RuntimeException('Credenciais do WhatsApp não configuradas.');
    }

    $ch = curl_init("https://graph.facebook.com/{$apiVersion}/{$phoneId}/messages");
    if ($ch === false) {
        throw new RuntimeException('Não foi possível iniciar a requisição cURL.');
    }

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($jsonPayload === false) {
        throw new RuntimeException('Falha ao converter payload do WhatsApp para JSON.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonPayload,
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

function normalizarTelefoneWhatsApp(string $to): string
{
    $onlyDigits = preg_replace('/\D+/', '', $to) ?? '';
    if ($onlyDigits === '') {
        throw new RuntimeException('Número de destino inválido para o WhatsApp.');
    }

    return $onlyDigits;
}

function limitarTextoWhatsApp(string $text, int $limit): string
{
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($text, 'UTF-8') > $limit ? mb_substr($text, 0, $limit, 'UTF-8') : $text;
    }

    return strlen($text) > $limit ? substr($text, 0, $limit) : $text;
}

/** @param array<string, mixed> $interactive */
function normalizarMensagemInterativa(array $interactive): array
{
    $type = (string) ($interactive['type'] ?? '');
    if (!in_array($type, ['list', 'button'], true)) {
        throw new RuntimeException('Tipo de mensagem interativa inválido.');
    }

    $bodyText = trim((string) ($interactive['body']['text'] ?? $interactive['body'] ?? ''));
    if ($bodyText === '') {
        throw new RuntimeException('Mensagem interativa sem texto principal.');
    }

    $payload = [
        'type' => $type,
        'body' => ['text' => limitarTextoWhatsApp($bodyText, 1024)],
        'action' => $interactive['action'] ?? [],
    ];

    $headerText = trim((string) ($interactive['header']['text'] ?? $interactive['header'] ?? ''));
    if ($headerText !== '') {
        $payload['header'] = ['type' => 'text', 'text' => limitarTextoWhatsApp($headerText, 60)];
    }

    $footerText = trim((string) ($interactive['footer']['text'] ?? $interactive['footer'] ?? ''));
    if ($footerText !== '') {
        $payload['footer'] = ['text' => limitarTextoWhatsApp($footerText, 60)];
    }

    if ($type === 'button') {
        $buttons = array_values(array_filter(array_map(
            static function (array $button): array {
                $reply = $button['reply'] ?? $button;
                return [
                    'type' => 'reply',
                    'reply' => [
                        'id' => limitarTextoWhatsApp((string) ($reply['id'] ?? ''), 256),
                        'title' => limitarTextoWhatsApp((string) ($reply['title'] ?? ''), 20),
                    ],
                ];
            },
            $payload['action']['buttons'] ?? []
        ), static fn(array $button): bool => $button['reply']['id'] !== '' && $button['reply']['title'] !== ''));

        if ($buttons === [] || count($buttons) > 3) {
            throw new RuntimeException('Mensagem interativa de botões precisa ter entre 1 e 3 botões válidos.');
        }

        $payload['action']['buttons'] = $buttons;
    }

    if ($type === 'list') {
        $payload['action']['button'] = limitarTextoWhatsApp((string) ($payload['action']['button'] ?? 'Ver opções'), 20);
        $sections = array_values(array_filter(array_map(
            static function (array $section): array {
                $rows = array_values(array_filter(array_map(
                    static function (array $row): array {
                        return [
                            'id' => limitarTextoWhatsApp((string) ($row['id'] ?? ''), 200),
                            'title' => limitarTextoWhatsApp((string) ($row['title'] ?? ''), 24),
                            'description' => limitarTextoWhatsApp((string) ($row['description'] ?? ''), 72),
                        ];
                    },
                    $section['rows'] ?? []
                ), static fn(array $row): bool => $row['id'] !== '' && $row['title'] !== ''));

                return [
                    'title' => limitarTextoWhatsApp((string) ($section['title'] ?? ''), 24),
                    'rows' => $rows,
                ];
            },
            $payload['action']['sections'] ?? []
        ), static fn(array $section): bool => $section['title'] !== '' && $section['rows'] !== []));

        $rowCount = 0;
        foreach ($sections as $section) {
            $rowCount += count($section['rows']);
        }

        if ($sections === [] || count($sections) > 10 || $rowCount > 10) {
            throw new RuntimeException('Mensagem interativa de lista precisa ter de 1 a 10 opções válidas.');
        }

        $payload['action']['sections'] = $sections;
    }

    return $payload;
}
