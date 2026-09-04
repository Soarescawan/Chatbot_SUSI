<?php

require_once __DIR__ . '/../../config/config.php';

function corrigirMensagem($mensagem)
{
    $env = loadEnvFile(dirname(__DIR__, 2) . '/.env');

    $apiKey = $env["OPENAI_API_KEY"] ?? ""; 

    if (!$apiKey) {
        error_log("[OPENAI] API KEY não encontrada.");
        return $mensagem;
    }

    $url = "https://api.openai.com/v1/responses";

    $dados = [

        "model" => "gpt-5.2",

        "instructions" =>
            "Você é um corretor de mensagens para um chatbot de atendimento da saúde.

            Sua única função é corrigir erros de português, 
            abreviações, erros de digitação e frases mal escritas.

            NÃO responda à pessoa.
            NÃO converse.
            NÃO explique o que corrigiu.
            NÃO adicione informações.
            NÃO mude o significado da mensagem.

            Apenas devolva a mesma mensagem corrigida em português.

            Se a mensagem já estiver correta, devolva exatamente a mesma mensagem.

            Exemplos:

            Entrada: quero marca uma consulta
            Saída: quero marcar uma consulta

            Entrada: bom dia eu quero sabe onde fica o postu
            Saída: Bom dia, eu quero saber onde fica o posto.

            Entrada: oi
            Saída: oi",

        "input" => $mensagem,

        "max_output_tokens" => 300

    ];


    $curl = curl_init();

    curl_setopt_array($curl, [

        CURLOPT_URL => $url,

        CURLOPT_POST => true,

        CURLOPT_POSTFIELDS => json_encode(
            $dados,
            JSON_UNESCAPED_UNICODE
        ),

        CURLOPT_HTTPHEADER => [

            "Authorization: Bearer " . $apiKey,

            "Content-Type: application/json"

        ],

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_TIMEOUT => 30

    ]);


    $resposta = curl_exec($curl);


    if ($resposta === false) {

        error_log(
            "[OPENAI] Erro CURL: " .
            curl_error($curl)
        );

        curl_close($curl);

        return $mensagem;
    }


    $httpCode = curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );


    curl_close($curl);


    if ($httpCode < 200 || $httpCode >= 300) {

        error_log(
            "[OPENAI] HTTP " .
            $httpCode .
            ": " .
            $resposta
        );

        return $mensagem;
    }


    $json = json_decode(
        $resposta,
        true
    );


    /*
     * A Responses API retorna os blocos de saída.
     * Procuramos o primeiro bloco de texto.
     */

    if (
        isset($json["output"]) &&
        is_array($json["output"])
    ) {

        foreach ($json["output"] as $item) {

            if (
                isset($item["content"]) &&
                is_array($item["content"])
            ) {

                foreach ($item["content"] as $content) {

                    if (
                        isset($content["type"]) &&
                        $content["type"] === "output_text"
                    ) {

                        return trim(
                            $content["text"]
                        );

                    }

                }

            }

        }

    }


    error_log(
        "[OPENAI] Não foi possível extrair a resposta."
    );

    return $mensagem;
}

/** @return array{intencao: string, nome_unidade: string} */
function interpretarMensagem(string $texto): array
{
    $fallback = interpretarMensagemLocalmente($texto);
    $env = loadEnvFile(dirname(__DIR__, 2) . '/.env');
    $apiKey = $env['OPENAI_API_KEY'] ?? '';

    if ($apiKey === '') {
        error_log('[OPENAI] API KEY não encontrada; usando interpretação local.');
        return $fallback;
    }

    $dados = [
        'model' => 'gpt-5.2',
        'instructions' => 'Classifique mensagens para um chatbot do SUS. Use consultar_ubs somente quando a pessoa pedir localização, endereço, horário, telefone ou outra informação sobre uma UBS, posto ou unidade de saúde. Em nome_unidade, extraia somente o nome ou bairro que identifica a unidade. Se não houver unidade identificável, use uma string vazia. Para menus, saudações e outros assuntos, use outra e nome_unidade vazio. Não invente nomes. Retorne somente o JSON do formato solicitado.',
        'input' => $texto,
        'max_output_tokens' => 120,
        'text' => [
            'format' => [
                'type' => 'json_schema',
                'name' => 'intencao_mensagem',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'intencao' => ['type' => 'string', 'enum' => ['consultar_ubs', 'outra']],
                        'nome_unidade' => ['type' => 'string'],
                    ],
                    'required' => ['intencao', 'nome_unidade'],
                    'additionalProperties' => false,
                ],
            ],
        ],
    ];

    $curl = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($dados, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $resposta = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $erro = curl_error($curl);
    curl_close($curl);

    if ($resposta === false || $httpCode < 200 || $httpCode >= 300) {
        error_log('[OPENAI] Falha ao interpretar mensagem: HTTP ' . $httpCode . ' ' . $erro);
        return $fallback;
    }

    $conteudo = extrairTextoResposta(json_decode($resposta, true) ?: []);
    $interpretacao = is_string($conteudo) ? json_decode($conteudo, true) : null;
    if (!is_array($interpretacao) || !isset($interpretacao['intencao'], $interpretacao['nome_unidade'])) {
        error_log('[OPENAI] Não foi possível interpretar a classificação da mensagem.');
        return $fallback;
    }

    return [
        'intencao' => $interpretacao['intencao'] === 'consultar_ubs' ? 'consultar_ubs' : 'outra',
        'nome_unidade' => trim((string) $interpretacao['nome_unidade']),
    ];
}

function extrairTextoResposta(array $json): ?string
{
    foreach ($json['output'] ?? [] as $item) {
        foreach ($item['content'] ?? [] as $content) {
            if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) return (string) $content['text'];
        }
    }
    return null;
}

/** Fallback para não consultar o banco indevidamente se a API estiver indisponível. */
function interpretarMensagemLocalmente(string $texto): array
{
    $normalizado = normalizarTextoParaIntencao($texto);
    $mencionaUbs = preg_match('/\\b(ubs|unidade basica|posto de saude|postinho)\\b/u', $normalizado) === 1;
    $pedeInformacao = preg_match('/\\b(onde|endereco|horario|abre|fecha|telefone|contato|localiza|fica|qual)\\b/u', $normalizado) === 1;
    return ($mencionaUbs && $pedeInformacao)
        ? ['intencao' => 'consultar_ubs', 'nome_unidade' => '']
        : ['intencao' => 'outra', 'nome_unidade' => ''];
}

function normalizarTextoParaIntencao(string $texto): string
{
    $texto = function_exists('mb_strtolower') ? mb_strtolower(trim($texto), 'UTF-8') : strtolower(trim($texto));
    return strtr($texto, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
}
