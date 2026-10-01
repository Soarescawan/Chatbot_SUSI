<?php
declare(strict_types=1);

require_once __DIR__ . '/consultas.php';
require_once __DIR__ . '/conversas.php';
require_once __DIR__ . '/openia.php';
require_once __DIR__ . '/susi.php';
require_once __DIR__ . '/whatsapp.php';

function processarMensagemRecebida(array $message, string $accessToken, string $phoneId, string $apiVersion): void
{
    if ($accessToken === '' || $phoneId === '') {
        error_log('SUSI: WHATSAPP_ACCESS_TOKEN ou WHATSAPP_PHONE_NUMBER_ID não configurado.');
        appLog('Credenciais do WhatsApp ausentes ao processar mensagem.');
        return;
    }

    appLog('Mensagem recebida de ' . ($message['from'] ?? 'desconhecido') . ' tipo=' . ($message['type'] ?? '') . ' texto=' . json_encode($message['text'] ?? '', JSON_UNESCAPED_UNICODE));

    $states = loadStates();
    $state = $states[$message['from']] ?? null;

    /*
     * Encerra sessões expiradas caso o agendador atrase.
     * O envio da mensagem de encerramento está temporariamente desativado.
     */
    if (sessionExpired($state)) {
        unset($states[$message['from']]);
        saveStates($states);
        $state = null;
    }

    if ($message['type'] !== 'text' || $message['text'] === '') {
        enviarResposta($message['from'], 'Por enquanto consigo atender mensagens de texto. Digite *menu* para ver as opções.', $accessToken, $phoneId, $apiVersion);
        return;
    }

    $state ??= ['step' => 'menu'];
    $texto = trim($message['text']);
    $step = (string) ($state['step'] ?? 'menu');

    $buscaDireta = interpretarBuscaDiretaUbs($texto);

    // Comandos e números mantêm o fluxo do menu. Perguntas sobre UBS podem
    // iniciar uma busca também quando o usuário está em um submenu.
    if (ehComandoMenu($texto) || ctype_digit($texto)
        || ($buscaDireta === null && !in_array($step, ['menu', 'main_menu', 'ubs_search'], true))) {
        [$reply, $nextStep] = responderSusi($texto, $step);
        $states[$message['from']] = ['step' => $nextStep, 'updated_at' => time()];
        saveStates($states);
        enviarResposta($message['from'], $reply, $accessToken, $phoneId, $apiVersion);
        return;
    }

    // Fora de um fluxo numérico, a IA escolhe a área do menu e identifica buscas por UBS.
    $interpretacao = $buscaDireta ?? ($step === 'ubs_search'
        ? ['intencao' => 'consultar_ubs', 'nome_unidade' => extrairPossivelNomeUnidade($texto) ?: $texto]
        : interpretarMensagem($texto));
    $rotaDireta = fluxoDiretoPorIntencao($interpretacao['intencao']);

    if ($rotaDireta !== null) {
        [$reply, $nextStep] = $rotaDireta;
        $states[$message['from']] = ['step' => $nextStep, 'updated_at' => time()];
        saveStates($states);
        enviarResposta($message['from'], $reply, $accessToken, $phoneId, $apiVersion);
        return;
    }

    // Só consulta o banco após reconhecer uma busca por UBS.
    $falhaConsultaBanco = false;
    if ($interpretacao['intencao'] === 'consultar_ubs' && $interpretacao['nome_unidade'] !== '') {
        try {
            $unidade = buscarUnidade($interpretacao['nome_unidade']);
        } catch (Throwable $e) {
            error_log('SUSI: falha na consulta ao banco: ' . $e->getMessage());
            appLog('Falha na consulta ao banco para "' . $interpretacao['nome_unidade'] . '": ' . $e->getMessage());
            $falhaConsultaBanco = true;
            $unidade = null;
        }
    } else {
        $unidade = null;
    }

    if ($unidade !== null) {
        $reply = formatarRespostaUnidade($unidade);
        $nextStep = 'main_menu';
    } elseif ($falhaConsultaBanco) {
        $reply = 'Estou com dificuldade para consultar as unidades de saúde agora. Tente novamente em instantes.';
        $nextStep = 'main_menu';
    } elseif ($interpretacao['intencao'] === 'consultar_ubs' && $interpretacao['nome_unidade'] !== '') {
        $reply = 'Não localizei a UBS *' . $interpretacao['nome_unidade'] . '*. Confira o nome ou informe o bairro da unidade.';
        $nextStep = 'main_menu';
    } elseif ($interpretacao['intencao'] === 'consultar_ubs') {
        $reply = 'Qual UBS você quer consultar? Informe o nome ou o bairro da unidade para eu localizar endereço, horário e telefone.';
        $nextStep = 'ubs_search';
    } else {
        [$reply, $nextStep] = responderSusi($texto, $step);
    }

    $states[$message['from']] = ['step' => $nextStep, 'updated_at' => time()];
    saveStates($states);
    enviarResposta($message['from'], $reply, $accessToken, $phoneId, $apiVersion);
}

function formatarRespostaUnidade(array $unidade): string
{
    $nome = trim((string) ($unidade['nome_fantasia'] ?? 'Unidade de saúde'));
    $tipo = trim((string) ($unidade['tipo_unidade'] ?? 'Não informado'));
    $logradouro = trim((string) ($unidade['logradouro'] ?? ''));
    $numero = trim((string) ($unidade['numero'] ?? 's/n'));
    $bairro = trim((string) ($unidade['bairro'] ?? 'Não informado'));
    $horario = trim((string) ($unidade['horario'] ?? ''));
    $telefones = trim((string) ($unidade['telefones'] ?? $unidade['telefone'] ?? ''));

    $resposta = "*{$nome}*\n\n"
        . "Tipo: {$tipo}\n"
        . "Endereço: " . trim($logradouro . ', ' . $numero, ', ') . "\n"
        . "Bairro: {$bairro}";

    if ($horario !== '') {
        $resposta .= "\nHorário: {$horario}";
    }

    if ($telefones !== '') {
        $resposta .= "\nTelefone(s): {$telefones}";
    }

    return $resposta;
}

function enviarResposta(string $to, string $text, string $accessToken, string $phoneId, string $apiVersion): void
{
    $interactive = mensagemInterativaParaResposta($text);

    try {
        if ($interactive !== null) {
            sendWhatsAppInteractiveMessage($to, $interactive, $accessToken, $phoneId, $apiVersion);
            appLog('Mensagem interativa enviada para ' . $to);
            return;
        }

        sendWhatsAppMessage($to, $text, $accessToken, $phoneId, $apiVersion);
        appLog('Mensagem em texto enviada para ' . $to);
    } catch (RuntimeException $e) {
        error_log('SUSI: falha ao enviar mensagem: ' . $e->getMessage());
        appLog('Falha ao enviar mensagem para ' . $to . ': ' . $e->getMessage());

        if ($interactive !== null) {
            try {
                sendWhatsAppMessage($to, $text, $accessToken, $phoneId, $apiVersion);
                appLog('Fallback para texto executado com sucesso para ' . $to);
                return;
            } catch (RuntimeException $fallbackError) {
                error_log('SUSI: falha no fallback de texto: ' . $fallbackError->getMessage());
                appLog('Falha no fallback de texto para ' . $to . ': ' . $fallbackError->getMessage());
            }
        }
    }
}
