<?php

require_once __DIR__ . '/config.php';

function corrigirMensagem($mensagem)
{
    $env = loadEnvFile(__DIR__ . '/.env');

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
    $buscaDireta = interpretarBuscaDiretaUbs($texto);
    if ($buscaDireta !== null) return $buscaDireta;

    $fallback = interpretarMensagemLocalmente($texto);
    $env = loadEnvFile(__DIR__ . '/.env');
    $apiKey = $env['OPENAI_API_KEY'] ?? '';

    if ($apiKey === '') {
        error_log('[OPENAI] API KEY não encontrada; usando interpretação local.');
        return $fallback;
    }

    $dados = [
        'model' => 'gpt-5.2',
        'instructions' => 'Classifique mensagens para um chatbot do SUS em uma única intenção. Use: saudacao para oi/menu/inicio; psicossocial para tristeza, ansiedade, crise emocional, sofrimento mental, uso de álcool e drogas ou pedido de CAPS; urgencia para acidente, sangramento, dor no peito, falta de ar, AVC, convulsão, desmaio, risco imediato; primaria para sintomas comuns, dor de cabeça, gripe, febre, receita, vacina, consulta na UBS, acolhimento ou clínico geral; secundaria para especialista, exame especializado, consulta especializada ou encaminhamento; apoio para medicamentos, farmácia, laboratório, CRAS, CREAS, assistência social e serviços de apoio; consultar_ubs para endereço, horário, telefone, localização ou nome de UBS/posto/unidade de saúde. Em nome_unidade, extraia somente o nome da unidade ou o bairro quando isso ajudar na busca da UBS. Se não houver unidade identificável, use string vazia. Não invente nomes. Retorne somente o JSON do formato solicitado.',
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
                        'intencao' => ['type' => 'string', 'enum' => ['saudacao', 'psicossocial', 'urgencia', 'primaria', 'secundaria', 'apoio', 'consultar_ubs', 'outra']],
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
        'intencao' => normalizarIntencao((string) $interpretacao['intencao']),
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
    $buscaDireta = interpretarBuscaDiretaUbs($texto);
    if ($buscaDireta !== null) return $buscaDireta;

    $normalizado = normalizarTextoParaIntencao($texto);
    $nomeUnidade = extrairPossivelNomeUnidade($texto);

    if (preg_match('/\\b(menu|inicio|início|oi|ola|olá|bom dia|boa tarde|boa noite)\\b/u', $normalizado) === 1) {
        return ['intencao' => 'saudacao', 'nome_unidade' => ''];
    }

    if (preg_match('/\\b(caps|ansiedade|triste|depress|crise emocional|saude mental|saude emocional|angust|panico|pânico|choro|desanimo|desânimo|suicid|alcool|droga)\\b/u', $normalizado) === 1) {
        return ['intencao' => 'psicossocial', 'nome_unidade' => ''];
    }

    if (preg_match('/\\b(acidente|sangramento|sangrando|dor no peito|falta de ar|desmaio|desmaiou|convuls|avc|emergencia|urgencia|urgência|fratura|hemorrag|nao consigo respirar|não consigo respirar)\\b/u', $normalizado) === 1) {
        return ['intencao' => 'urgencia', 'nome_unidade' => ''];
    }

    if (preg_match('/\\b(especialista|especializada|encaminhamento|encaminhado|neurologista|cardiologista|ortopedista|psiquiatra|dermatologista|ultrassom|ressonancia|ressonância|tomografia|consulta especializada|exame especializado)\\b/u', $normalizado) === 1) {
        return ['intencao' => 'secundaria', 'nome_unidade' => ''];
    }

    if (preg_match('/\\b(remedio|remédio|medicamento|farmacia|farmácia|cras|creas|cram|centro pop|assistencia social|assistência social|laboratorio|laboratório|exame de sangue)\\b/u', $normalizado) === 1) {
        return ['intencao' => 'apoio', 'nome_unidade' => ''];
    }

    if (preg_match('/\\b(ubs|unidade basica|unidade básica|posto de saude|posto|postinho)\\b/u', $normalizado) === 1) {
        return ['intencao' => 'consultar_ubs', 'nome_unidade' => $nomeUnidade];
    }

    if (preg_match('/\\b(onde|endereco|endereço|horario|horário|abre|fecha|telefone|contato|localiza|localização|fica|qual ubs|qual posto)\\b/u', $normalizado) === 1) {
        return ['intencao' => 'consultar_ubs', 'nome_unidade' => $nomeUnidade];
    }

    if (preg_match('/\\b(dor de cabeca|dor de cabeça|dor|febre|gripe|tosse|resfriado|receita|vacina|acolhimento|clinico geral|clínico geral|consulta|posto de saude|posto de saúde|ubs)\\b/u', $normalizado) === 1) {
        return ['intencao' => 'primaria', 'nome_unidade' => ''];
    }

    return ['intencao' => 'outra', 'nome_unidade' => ''];
}

function normalizarTextoParaIntencao(string $texto): string
{
    $texto = function_exists('mb_strtolower') ? mb_strtolower(trim($texto), 'UTF-8') : strtolower(trim($texto));
    return strtr($texto, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
}

function normalizarIntencao(string $intencao): string
{
    $permitidas = ['saudacao', 'psicossocial', 'urgencia', 'primaria', 'secundaria', 'apoio', 'consultar_ubs', 'outra'];
    return in_array($intencao, $permitidas, true) ? $intencao : 'outra';
}

/** Reconhece nomes e perguntas sobre unidades sem depender da IA. */
function interpretarBuscaDiretaUbs(string $texto): ?array
{
    $unidade = '(?:ubs|unidade b[aá]sica(?: de sa[uú]de)?|unidade de sa[uú]de|posto(?: de sa[uú]de)?|postinho)';
    $consultaCurta = preg_match('/^' . $unidade . '(?:\s+.*)?[.!?]?$/iu', trim($texto)) === 1;
    $temUnidade = preg_match('/\b' . $unidade . '\b/iu', $texto) === 1;
    $temPergunta = preg_match('/\b(?:onde|aonde|endere[cç]o|localiza[cç][aã]o|como (?:eu )?(?:chego|chegar)|hor[aá]rio|telefone|contato)\b/iu', $texto) === 1;
    if (!$consultaCurta && !($temUnidade && $temPergunta)) return null;

    return ['intencao' => 'consultar_ubs', 'nome_unidade' => extrairPossivelNomeUnidade($texto)];
}

function extrairPossivelNomeUnidade(string $texto): string
{
    $texto = trim($texto);
    if ($texto === '') return '';

    $unidade = '(?:ubs|unidade b[aá]sica(?: de sa[uú]de)?|unidade de sa[uú]de|posto(?: de sa[uú]de)?|postinho)';
    $nome = '';
    if (preg_match('/\b' . $unidade . '\b\s*(.*)$/iu', $texto, $matches) === 1) {
        $nome = $matches[1];
    } elseif (preg_match('/\b(?:bairro|regi[aã]o)\s+(.+)$/iu', $texto, $matches) === 1) {
        $nome = $matches[1];
    }

    // Remove complementos da pergunta, preservando o nome composto da unidade.
    $nome = preg_replace('/(?:[,;!?].*|\s+por\s+favor\b.*|\s+(?:onde|aonde)\b.*|\s+(?:fica|est[aá] localizada|est[aá] localizado|[eé])\b.*)$/iu', '', $nome) ?? $nome;
    $nome = preg_replace('/^(?:(?:do|da|de|dos|das|no|na)\s+)?(?:bairro\s+)?/iu', '', trim($nome)) ?? $nome;
    return trim($nome, " \t\n\r\0\x0B.,;:!?");
}
