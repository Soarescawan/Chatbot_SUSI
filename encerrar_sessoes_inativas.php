<?php
declare(strict_types=1);

/*
 * Execute este arquivo a cada minuto pelo cron para encerrar sessões inativas.
 * O envio da mensagem de encerramento está temporariamente desativado.
 *
 * Exemplo de cron:
 ** /usr/bin/php /caminho/para/chatbot/encerrar_sessoes_inativas.php
 */

require_once __DIR__ . '/conversas.php';

$states = loadStates();
$now = time();
$houveAlteracao = false;

foreach ($states as $phone => $state) {
    if (!sessionExpired($state, $now)) continue;

    unset($states[$phone]);
    $houveAlteracao = true;
}

if ($houveAlteracao) saveStates($states);
