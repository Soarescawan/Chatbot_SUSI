<?php
/**
 * SUSi - Assistente Virtual do SUS Pelotas
 */

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ============================================================
   CONEXÃO COM O BANCO (PDO)
   ============================================================ */
function db_conectar() {
    $env = loadEnvFile(__DIR__ . '/../.env');
    $host = configValue('DB_HOST', $env);
    $user = configValue('DB_USERNAME', $env);
    $pass = configValue('DB_PASSWORD', $env);
    $db = configValue('DB_DATABASE', $env);
    $port = configValue('DB_PORT', $env) ?: '3306';

    if ($host === '' || $user === '' || $pass === '' || $db === '') {
        throw new RuntimeException('Configure as credenciais do banco no arquivo .env.');
    }

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;

    } catch (Exception $e) {
        // Se quiser, logue em arquivo em vez de exibir no browser
        echo "❌ Erro ao conectar ao banco: " . $e->getMessage();
        exit;
    }
}

/* ============================================================
   CHAVE GEMINI
   ============================================================ */
$apiKey = configValue('GEMINI_API_KEY', loadEnvFile(__DIR__ . '/../.env'));

if ($apiKey === '') {
    throw new RuntimeException('Configure GEMINI_API_KEY no arquivo .env.');
}

/* ============================================================
   FUNÇÃO PARA CHAMAR GEMINI
   ============================================================ */
function chamarGemini($prompt) {
    global $apiKey;

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$apiKey";

    $data = [
        "contents" => [
            [
                "parts" => [["text" => $prompt]]
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $res = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode($res, true);
    return $decoded["candidates"][0]["content"]["parts"][0]["text"] ?? "";
}

/* ============================================================
   RECEBE MENSAGEM
   ============================================================ */
$msgRaw = trim($_POST["prompt"] ?? $_POST["mensagem"] ?? "");
$msg = strtolower($msgRaw);

if (!$msg) {
    echo "Nenhuma pergunta recebida.";
    exit;
}

/* ============================================================
   HELPERS DE RESPOSTA
   - resposta(): responde e adiciona "Deseja mais alguma coisa?" (marca aguardando_confirmacao)
   - pergunta(): envia uma pergunta/solicitação SEM marcar aguardando_confirmacao
   ============================================================ */
function resposta($txt, $finalizar = false) {

    if ($finalizar === true) {
        echo $txt;
        exit;
    }

    // adiciona pergunta extra e marca aguardando confirmação
    $txt .= "\n\nDeseja mais alguma coisa? 😊 (sim ou não)";
    $_SESSION["aguardando_confirmacao"] = true;

    echo $txt;
    exit;
}

function pergunta($txt) {
    // mensagem que espera continuação do usuário (não marca aguardando_confirmacao)
    echo $txt;
    exit;
}

/* ============================================================
   INTERCEPTA FRASES DE ENCERRAMENTO
   ============================================================ */
if (in_array($msg, ["obrigado", "obrigada", "valeu", "era só", "era so", "só isso", "so isso"])) {
    resposta("De nada! 😊 Qualquer coisa é só chamar!", true);
}

/* ============================================================
   CONTINUAÇÃO OU ENCERRAMENTO (sim / não)
   ============================================================ */
if (isset($_SESSION["aguardando_confirmacao"]) && $_SESSION["aguardando_confirmacao"] === true) {

    if (preg_match("/^(não|nao|n)$/i", $msg)) {
        unset($_SESSION["aguardando_confirmacao"]);
        unset($_SESSION["esperando_bairro_ubs"]);
        resposta("Tudo bem! Foi um prazer te ajudar 😊 Até a próxima!", true);
    }

    if (preg_match("/^(sim|s)$/i", $msg)) {
        unset($_SESSION["aguardando_confirmacao"]);
        unset($_SESSION["esperando_bairro_ubs"]);
        resposta("Claro! Pode perguntar 😄", true);
    }

    // se cair aqui e não for sim/não, deixamos o fluxo seguir (p.ex., usuário escreveu outra pergunta)
}

/* ============================================================
   SAUDAÇÕES (apenas quando não estiver no meio de outro fluxo)
   ============================================================ */
$saudacoes = ["oi","oii","ola","olá","bom dia","boa tarde","boa noite"];

if (
    !isset($_SESSION["aguardando_confirmacao"]) &&
    !isset($_SESSION["esperando_bairro_ubs"]) &&
    in_array($msg, $saudacoes)
) {
    if (!isset($_SESSION["ja_conversou"])) {
        $_SESSION["ja_conversou"] = true;
        resposta("
Olá! 😊 Eu sou a SUSI.
Posso te ajudar com informações sobre unidades de saúde, como:
• Endereço
• Horário de funcionamento
• Turno odontológico
• Telefone
• Quais funcionam à tarde

O que você gostaria de saber?
        ", true);
    }

    resposta("Claro! O que você gostaria de saber sobre as unidades de saúde?", true);
}

/* ============================================================
   INTERCEPTA ODONTOLOGIA (DEVE VENCER QUALQUER PEDIDO QUE INCLUA 'dentista')
   ============================================================ */
if (preg_match("/dentista|odonto|odontológico|odontologico|odontologia/i", $msg)) {
    // Resposta direta (sem Gemini)
    resposta("O banco não possui informações sobre atendimento odontológico nas UBS. 😕  
Mas posso te informar endereço, horário ou telefone (quando disponível) das unidades.");
}

/* ============================================================
   INTERCEPTA PEDIDOS DE TELEFONE (SE AINDA NÃO EXISTIR NA TABELA)
   - Se futuramente a coluna telefone existir, esse bloco pode ser removido
   ============================================================ */
if (preg_match("/telefone|contato|fone/i", $msg)) {

    // Se o usuário citou uma UBS específica, tenta responder especificamente
    if (preg_match("/obelisco|ubelisco/i", $msg)) {
        resposta("Ainda não temos o telefone da UBS Obelisco cadastrado, mas em breve estará disponível! 😊  
Se quiser, posso te informar o endereço ou horário de funcionamento.");
    }

    // Pedido genérico: "preciso do telefone da ubs"
    resposta("Ainda não temos telefones cadastrados no sistema, mas isso será adicionado em breve! 😊  
Se quiser, posso te informar endereço, horário e nome das unidades.");
}

/* ============================================================
   PEDIDO GENÉRICO: "preciso de uma ubs / posto"
   - Este bloco ativa o estado esperando_bairro_ubs e pede o bairro/nome.
   - IMPORTANTE: usamos pergunta() (não seta aguardando_confirmacao)
   ============================================================ */
if (preg_match("/(preciso|quero|ir|procurar|busco).*(ubs|posto|postinho)/i", $msg)) {

    // limpa fluxos anteriores
    unset($_SESSION["aguardando_confirmacao"]);
    unset($_SESSION["esperando_bairro_ubs"]);

    $_SESSION["esperando_bairro_ubs"] = true;

    pergunta("Posso te ajudar a encontrar a UBS certa! 😊 Me diga o **nome da unidade** ou **o bairro** que você procura.");
}

/* ============================================================
   Caso o usuário escreva "bairro <nome>", extraímos o bairro
   e seguimos para o bloco de busca (manual)
   ============================================================ */
if (preg_match("/^\s*bairro\s+(.+)/i", $msg, $m)) {
    unset($_SESSION["aguardando_confirmacao"]);
    $_SESSION["esperando_bairro_ubs"] = true;
    $msg = trim($m[1]); // substitui a mensagem pelo nome do bairro
    // continua o fluxo para o bloco de busca manual abaixo
}

/* ============================================================
   BLOCO MANUAL: RESPOSTA QUANDO USUÁRIO INFORMOU BAIRRO OU NOME DA UBS
   - Executa a busca diretamente no banco (sem chamar Gemini)
   - Após responder, limpa o estado esperando_bairro_ubs
   ============================================================ */
if (isset($_SESSION["esperando_bairro_ubs"]) && $_SESSION["esperando_bairro_ubs"] === true) {

    // conecta ao banco e faz a busca (nome → bairro)
    try {
        $pdo = db_conectar();
    } catch (Exception $e) {
        // Erro ao conectar: retorna mensagem amigável
        unset($_SESSION["esperando_bairro_ubs"]);
        resposta("⚠ Erro ao consultar o sistema. Tente novamente mais tarde.");
    }

    $consulta = strtolower(trim($msg));

    // 1) Busca por nome (LIKE)
    $sql = "SELECT nome, endereco, bairro, telefone
            FROM unidades_saude
            WHERE LOWER(nome) LIKE :termo";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':termo' => "%$consulta%"]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2) Se não achou por nome, busca por bairro
    if (empty($result)) {
        $sql = "SELECT nome, endereco, bairro, telefone
                FROM unidades_saude
                WHERE LOWER(bairro) LIKE :termo";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':termo' => "%$consulta%"]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3) Se não encontrou nada
    if (empty($result)) {
        unset($_SESSION["esperando_bairro_ubs"]);
        resposta("Não encontrei nada com essa informação. Quer tentar outro nome ou bairro? 😊");
    }

    // 4) Monta e envia resposta (usa resposta() para perguntar se quer mais)
    $txt = "";
    foreach ($result as $row) {
        // Alguns bancos têm 'nome_fantasia' em vez de 'nome' — se for o caso, ajuste a query
        $displayNome = $row['nome'] ?? ($row['nome_fantasia'] ?? 'Nome não disponível');
        $displayEndereco = $row['endereco'] ?? 'Endereço não disponível';
        $displayBairro = $row['bairro'] ?? '';
        $txt .= "• {$displayNome} — {$displayEndereco}";
        if (!empty($displayBairro)) {
            $txt .= ", {$displayBairro}";
        }
        if (!empty($row['telefone'])) {
            $txt .= " — 📞 {$row['telefone']}";
        } else {
            $txt .= " — (telefone ainda não cadastrado)";
        }
        $txt .= "\n";
    }

    unset($_SESSION["esperando_bairro_ubs"]);
    resposta($txt);
}

/* ============================================================
   SE CHEGAMOS AQUI: NÃO ESTAMOS NO FLUXO MANUAL → PODEMOS CHAMAR GEMINI
   - O Gemini só será chamado quando não estivermos esperando bairro/confirmacao
   ============================================================ */

/* ============================================================
   PROMPT SQL PARA GEMINI
   ============================================================ */
$promptSQL = "
Você converte perguntas naturais do usuário em SQL MySQL e devolve SOMENTE o SQL quando necessário.

Tabelas:
- unidade_saude (id_unidade, cnes, nome_fantasia, tipo_unidade, id_endereco)
- endereco (id_endereco, cep, logradouro, numero, bairro, latitude, longitude)

⚠ REGRAS IMPORTANTES:
📞 SOBRE TELEFONE:
- O banco ainda NÃO possui a coluna telefone.
- Se o usuário pedir telefone, NÃO gere SQL. Apenas retorne:
  Ainda não temos telefones cadastrados.

- Nunca invente nomes, endereços ou dados.
- Responda SOMENTE com base nos dados fornecidos.
- Liste apenas o NOME DA UNIDADE e o ENDEREÇO (logradouro + número + bairro) e o telefone (se existir).
- unidade básica de saúde = ubs = posto = postinho.
- UBS é diferente de CAPS.
- Nunca invente informações que não existam no banco.

🦷 SOBRE ATENDIMENTO ODONTOLÓGICO:
- O banco NÃO possui coluna sobre atendimento odontológico.
- Portanto: SE o usuário pedir dentista, odontologia ou atendimento odontológico:
  RETORNE EXATAMENTE:
  O banco não possui informações sobre atendimento odontológico.
  (sem SQL)

📍 SOBRE UBS ESPECÍFICA:
Se o usuário mencionar UBS, então o SQL DEVE incluir:
  u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci

📍 UBS + BAIRRO:
Se o usuário disser algo como “UBS no centro”:
  WHERE u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci
    AND e.bairro LIKE '%centro%' COLLATE utf8mb4_general_ci

📍 CONSULTAS DE UNIDADE:
Sempre use WHERE com nome_fantasia quando o usuário mencionar uma unidade.

📍 CONSULTAS DE BAIRRO:
WHERE e.bairro LIKE '%<bairro>%' COLLATE utf8mb4_general_ci

📍 NUNCA retornar SELECT sem WHERE quando o usuário procurar algo específico.

📍 Nomes de unidades:
Quando o usuário disser algo como “UBS Areal Leste”, “Areal Leste”, “Obelisco”, “Simões Lopes”, etc:
Você DEVE gerar:
WHERE u.nome_fantasia LIKE '%areal leste%' COLLATE utf8mb4_general_ci

Exemplo:
Pergunta: endereço da ubs areal leste
SQL correto:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.nome_fantasia LIKE '%areal leste%' COLLATE utf8mb4_general_ci;

Pergunta do usuário:
$msgRaw

Agora gere somente o SQL correto ou a mensagem específica quando não houver coluna.
";

/* ============================================================
   CHAMA GEMINI (somente quando não estivermos em fluxo manual)
   ============================================================ */
$raw_gemini = chamarGemini($promptSQL);
$raw_clean = trim(preg_replace("/```(sql)?|```/", "", $raw_gemini));

file_put_contents(__DIR__ . "/../storage/debug_sql.txt",
"====================\nPERGUNTA: $msgRaw\nRAW:\n$raw_gemini\n\n",
FILE_APPEND);

/* ============================================================
   DENTISTA (resposta gerada pelo Gemini)
   - Se o Gemini retornar exatamente a mensagem sobre odontologia, reespondemos
   ============================================================ */
if ($raw_clean === "O banco não possui informações sobre atendimento odontológico.") {
    resposta("Infelizmente o nosso banco ainda não possui informações sobre atendimento odontológico nas UBS. 😕  
Se quiser, posso te informar endereço, horário e telefone das unidades.");
}

/* ============================================================
   EXECUTAR SQL SE VÁLIDO (resultado do Gemini)
   ============================================================ */
if (preg_match("/^SELECT/i", $raw_clean)) {

    if (!preg_match("/WHERE/i", $raw_clean)) {
        pergunta("Para te indicar a UBS certa, me diga o nome da unidade ou o bairro 😊");
    }

    try {
        $pdo = db_conectar();
        $rows = $pdo->query($raw_clean)->fetchAll();
    } catch (Exception $e) {
        resposta("⚠ Erro ao consultar o banco: " . $e->getMessage());
    }

    if (empty($rows)) {
        resposta("Não encontrei nada com essa informação. Quer tentar outro nome ou bairro?");
    }

    $txt = "";
    foreach ($rows as $r) {
        // Gemini tende a criar alias 'endereco' como no prompt; se não existir, adapte conforme seu schema
        $nome = $r['nome_fantasia'] ?? ($r['nome'] ?? 'Nome não disponível');
        $endereco = $r['endereco'] ?? ( (isset($r['logradouro']) ? ($r['logradouro'] . ', ' . ($r['numero'] ?? '') . ', ' . ($r['bairro'] ?? '')) : 'Endereço não disponível') );
        $txt .= "• <b>{$nome}</b> — {$endereco}\n";
    }

    resposta($txt);
}

/* ============================================================
   SE NÃO FOR SQL → ORIENTAR
   ============================================================ */
pergunta("Posso te ajudar a encontrar a UBS certa! 😊  
Me diga o **nome da unidade** ou **o bairro** que você procura.");
?>
