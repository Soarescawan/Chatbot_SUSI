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
        return;
    }

    if ($message['type'] !== 'text' || $message['text'] === '') {
        enviarResposta($message['from'], 'Por enquanto consigo atender mensagens de texto. Digite *menu* para ver as opções.', $accessToken, $phoneId, $apiVersion);
        return;
    }

    $states = loadStates();
    $state = $states[$message['from']] ?? ['step' => 'menu'];
    $mensagemCorrigida = corrigirMensagem($message['text']);
    $interpretacao = interpretarMensagem($mensagemCorrigida);

    // Só consulta o banco após reconhecer uma busca por UBS com unidade identificada.
    if ($interpretacao['intencao'] === 'consultar_ubs' && $interpretacao['nome_unidade'] !== '') {
        try {
            $unidade = buscarUnidade($interpretacao['nome_unidade']);
        } catch (Throwable $e) {
            error_log('SUSI: falha na consulta ao banco: ' . $e->getMessage());
            $unidade = null;
        }
    } else {
        $unidade = null;
    }

    if ($unidade !== null) {
        $reply = formatarRespostaUnidade($unidade);
        $nextStep = 'main_menu';
    } elseif ($interpretacao['intencao'] === 'consultar_ubs' && $interpretacao['nome_unidade'] !== '') {
        $reply = 'Não localizei a UBS *' . $interpretacao['nome_unidade'] . '*. Confira o nome ou informe o bairro da unidade.';
        $nextStep = 'main_menu';
    } elseif ($interpretacao['intencao'] === 'consultar_ubs') {
        $reply = 'Qual UBS você quer consultar? Informe o nome ou o bairro da unidade para eu localizar endereço, horário e telefone.';
        $nextStep = 'main_menu';
    } else {
        [$reply, $nextStep] = responderSusi($mensagemCorrigida, (string) $state['step']);
    }

    $states[$message['from']] = ['step' => $nextStep, 'updated_at' => time()];
    saveStates($states);
    enviarResposta($message['from'], $reply, $accessToken, $phoneId, $apiVersion);
}

function formatarRespostaUnidade(array $unidade): string
{
    $resposta = "*{$unidade['nome_fantasia']}*\n\n"
        . "Tipo: {$unidade['tipo_unidade']}\n"
        . "Endereço: {$unidade['logradouro']}, {$unidade['numero']}\n"
        . "Bairro: {$unidade['bairro']}";

    if (!empty($unidade['horario'])) {
        $resposta .= "\nHorário: {$unidade['horario']}";
    }

    return $resposta;
}

function enviarResposta(string $to, string $text, string $accessToken, string $phoneId, string $apiVersion): void
{
    try {
        sendWhatsAppMessage($to, $text, $accessToken, $phoneId, $apiVersion);
    } catch (RuntimeException $e) {
        error_log('SUSI: falha ao enviar mensagem: ' . $e->getMessage());
    }
}
