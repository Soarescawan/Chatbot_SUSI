<?php
declare(strict_types=1);

const STATE_FILE = __DIR__ . '/storage/conversas.json';
const SESSION_TIMEOUT_SECONDS = 300;

function loadStates(): array
{
    if (!is_file(STATE_FILE)) return [];
    $states = json_decode((string) file_get_contents(STATE_FILE), true);
    return is_array($states) ? $states : [];
}

function saveStates(array $states): void
{
    $limit = time() - 86400 * 7;
    $states = array_filter($states, static fn(array $state): bool => ($state['updated_at'] ?? 0) >= $limit);
    if (!is_dir(dirname(STATE_FILE))) {
        mkdir(dirname(STATE_FILE), 0775, true);
    }

    $json = json_encode($states, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Falha ao serializar o estado das conversas.');
    }

    file_put_contents(STATE_FILE, $json, LOCK_EX);
}

/** Uma conversa deixa de ser válida após cinco minutos sem mensagem do usuário. */
function sessionExpired(?array $state, ?int $now = null): bool
{
    if ($state === null || !isset($state['updated_at'])) return false;

    return ($now ?? time()) - (int) $state['updated_at'] >= SESSION_TIMEOUT_SECONDS;
}

function mensagemEncerramentoSessao(): string
{
    return "⏱️ *Sessão encerrada*\n\nComo não recebemos uma resposta em 5 minutos, esta conversa foi encerrada.\n\nQuando quiser falar novamente, envie *menu*.";
}
