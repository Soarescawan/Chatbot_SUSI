<?php
declare(strict_types=1);

// Fluxos conversacionais e respostas da SUSi principal parte do catbot.

function responderSusi(string $input, string $step): array
{
    $text = normalizar($input);
    if (ehComandoMenu($input)) return [menuInicial(), 'main_menu'];

    if ($step === 'main_menu' || $step === 'menu') {
        return match ($text) {
            '1' => [menuPsicossocialQuem(), 'psychosocial_who'],
            '2' => [menuUrgencia(), 'emergency_type'], '3' => [menuPrimaria(), 'primary_type'], '4' => [menuSecundaria(), 'secondary_type'], '5' => [menuApoio(), 'support_type'],
            default => ["Não entendi a opção. Responda com um número de 1 a 5.\n\n" . menuInicial(), 'main_menu'],
        };
    }
    if ($step === 'psychosocial_who') {
        if (!in_array($text, ['1', '2'], true)) return ['Responda *1* se você precisa de ajuda ou *2* se quer ajudar outra pessoa.', 'psychosocial_who'];
        return ['Para eu orientar melhor, conte o que está acontecendo. Pode incluir tristeza, ansiedade, estresse, medo, angústia, desânimo, crises de choro, dificuldade para dormir, uso de álcool ou drogas, ou pensamentos de se machucar.', 'psychosocial_reason'];
    }
    if ($step === 'psychosocial_reason') {
        if (temRiscoImediato($text)) return [orientacaoRiscoImediato(), 'main_menu'];
        if (temIdeacao($text)) return [orientacaoIdeacao(), 'main_menu'];
        return [menuPsicossocialDetalhe(), 'psychosocial_detail'];
    }
    if ($step === 'psychosocial_detail') return match ($text) {
        '1', '3', '4' => ["Você pode procurar o CAPS de referência durante o horário de funcionamento. Se não conseguir esperar, procure o Hospital Espírita de Pelotas (HEP), que atende 24 horas. Em risco imediato, ligue 192.\n\n🏠 Para voltar ao início e ver todas as opções, digite *menu*.", 'main_menu'],
        '2' => ["O CAPS também pode acolher situações relacionadas ao uso de álcool e outras drogas. Se houver mal-estar grave, desmaio, confusão ou risco imediato, ligue 192 ou procure atendimento de urgência.\n\n🏠 Para voltar ao início e ver todas as opções, digite *menu*.", 'main_menu'],
        default => ['Responda com uma opção de 1 a 4. Se houver risco imediato, ligue 192.', 'psychosocial_detail'],
    };
    if ($step === 'emergency_type') {
        if ($text === '1' || str_contains($text, 'acident')) return [menuSangramento(), 'accident_bleeding'];
        if (in_array($text, ['2', '3', '4'], true)) return ["Isso pode precisar de atendimento imediato. Ligue para o SAMU pelo *192*. Se for seguro, permaneça com a pessoa e siga as orientações da equipe.\n\n🏠 Para voltar ao início e ver todas as opções, digite *menu*.", 'main_menu'];
        return ['Responda com uma opção de 1 a 4. Em qualquer risco de vida, ligue *192* agora.', 'emergency_type'];
    }
    if ($step === 'accident_bleeding') {
        if ($text === '1' || $text === 'sim') return ["Tente estancar o sangramento com compressão direta usando pano limpo ou gaze, se possível. Ligue imediatamente para o *SAMU (192)*.\n\nEvite movimentar a vítima, principalmente se houver suspeita de trauma na cabeça, pescoço, coluna ou fratura. Aguarde a equipe em local seguro.", 'main_menu'];
        if (in_array($text, ['2', 'nao', 'não'], true)) return ['Ligue para o *SAMU (192)* para receber orientação. Evite movimentar a vítima se houver suspeita de trauma na cabeça, pescoço, coluna ou fratura.', 'main_menu'];
        return ['Responda *1* para sim ou *2* para não. Em risco de vida, ligue 192.', 'accident_bleeding'];
    }
    if ($step === 'primary_type') {
        if ($text === '3') return ['🏥 Qual UBS você procura? Digite o nome ou o bairro, por exemplo: *Fragata*.', 'ubs_search'];
        if ($text === '1') return ["Procure sua UBS durante o horário de funcionamento e passe pelo acolhimento. A equipe avaliará sua necessidade e realizará o atendimento ou encaminhamento conforme a gravidade.\n\n🏠 Para voltar ao início e ver todas as opções, digite *menu*.", 'main_menu'];
        if ($text === '2') return ["Procure sua UBS e passe pelo acolhimento para agendar a consulta ou receber as orientações necessárias. Isso inclui pré-natal, puericultura, consultas de rotina, preventivo e acompanhamento de condições crônicas.\n\n🏠 Para voltar ao início e ver todas as opções, digite *menu*.", 'main_menu'];
        return ['Responda *1* para acolhimento hoje, *2* para agendar uma consulta ou *3* para buscar uma UBS.', 'primary_type'];
    }
    if ($step === 'secondary_type') {
        if (in_array($text, ['1', '2', '3'], true)) return ["Para consulta ou exame especializado, mantenha o encaminhamento e procure a UBS/serviço que realizou a solicitação para verificar agendamento, preparo e local de atendimento.\n\n🏠 Para voltar ao início e ver todas as opções, digite *menu*.", 'main_menu'];
        return ['Responda *1* para consulta especializada, *2* para exame ou *3* para outra informação de encaminhamento.', 'secondary_type'];
    }
    if ($step === 'support_type') {
        if (in_array($text, ['1', '2', '3', '4', '5'], true)) return ["Este canal ainda não possui a lista atualizada de endereços e horários desse serviço. Procure sua UBS ou a Secretaria Municipal de Saúde para a orientação mais atual.\n\n🏠 Para voltar ao início e ver todas as opções, digite *menu*.", 'main_menu'];
        return ['Responda com uma opção de 1 a 5.', 'support_type'];
    }
    return [menuInicial(), 'main_menu'];
}

function ehComandoMenu(string $input): bool
{
    return in_array(normalizar($input), ['menu', 'inicio', 'início', 'oi', 'ola', 'olá', 'bom dia', 'boa tarde', 'boa noite'], true);
}

/** @return array{0: string, 1: string}|null */
function fluxoDiretoPorIntencao(string $intencao): ?array
{
    return match ($intencao) {
        'saudacao' => [menuInicial(), 'main_menu'],
        'psicossocial' => [menuPsicossocialQuem(), 'psychosocial_who'],
        'urgencia' => [menuUrgencia(), 'emergency_type'],
        'primaria' => [menuPrimaria(), 'primary_type'],
        'secundaria' => [menuSecundaria(), 'secondary_type'],
        'apoio' => [menuApoio(), 'support_type'],
        default => null,
    };
}

function menuInicial(): string { return "💙 *SUSI | Assistente do SUS*\n\nOlá! 👋\nEstou aqui para ajudar você a encontrar informações e serviços de saúde.\n\n *1* • Atenção Psicossocial\n *2* • Urgência e Emergência\n *3* • Atenção Primária (UBS)\n *4* • Consultas e Exames\n *5* • Serviços de Apoio\n\n━━━━━━━━━━━━━━━━\n\n *Digite o número da opção desejada.*\n\n"; }
function menuPsicossocialQuem(): string { return 'Você precisa de ajuda para você ou quer ajudar outra pessoa?'; }
function menuPsicossocialDetalhe(): string { return "Entendo. Qual opção descreve melhor a situação?\n\nSe houver risco imediato de se machucar, ligue 192."; }
function menuSangramento(): string { return 'Você ou a pessoa acidentada apresenta sangramento ativo, com sangue saindo continuamente da ferida?'; }
function menuUrgencia(): string { return "ATENDIMENTO DE URGÊNCIA E EMERGÊNCIA\n\n━━━━━━━━━━━━━━━━━━━━\nQual é a situação?\n1  -  Acidente\n2  -  Dor no peito, falta de ar ou desmaio\n3  -  Convulsão, alteração de consciência ou sinais de AVC\n4  -  Outra emergência\n━━━━━━━━━━━━━━━━━━━━\nEm caso de risco de vida, ligue *192* imediatamente."; }
function menuPrimaria(): string { return "Como posso ajudar na Atenção Primária?\n\n1. Problema de saúde para avaliar hoje (acolhimento)\n2. Agendar uma consulta\n3. Buscar UBS por nome ou bairro\n\nExemplos: sintomas gripais, dor, ferimento leve, receita, pré-natal, preventivo ou acompanhamento de doenças crônicas."; }
function menuSecundaria(): string { return "Você precisa de quê?\n\n1. Consulta especializada\n2. Exame especializado\n3. Informação sobre encaminhamento"; }
function menuApoio(): string { return "Qual serviço você procura?\n\n1. Farmácia/medicamentos\n2. Laboratórios e exames\n3. CRAS/assistência social\n4. CREAS, CRAM ou Centro POP\n5. Outros canais da rede"; }
function orientacaoRiscoImediato(): string { return 'Parece haver risco imediato. Ligue para o *SAMU (192)* agora ou vá ao serviço de urgência mais próximo, acompanhado(a) se possível. Não fique sozinho(a). Se puder, peça ajuda a alguém de confiança neste momento.'; }
function orientacaoIdeacao(): string { return "Sinto muito que você esteja passando por isso. Você não precisa enfrentar esse momento sozinho(a).\n\nSe sentir que pode agir contra si ou não consegue se manter em segurança, ligue 192 ou procure urgência imediatamente. Você também pode falar com o CVV, 24 horas, pelo *188*. Durante o funcionamento da rede, procure o CAPS de referência. O Hospital Espírita de Pelotas (HEP) atende 24 horas."; }
function temRiscoImediato(string $text): bool { return preg_match('/(me cortei|me machuquei|tentei me matar|tentativa|vou me matar|vou tirar minha vida|agora|nao consigo me controlar|não consigo me controlar)/u', $text) === 1; }
function temIdeacao(string $text): bool { return preg_match('/(me machucar|me matar|tirar minha vida|suicid|autoagress|pensamento)/u', $text) === 1; }
function normalizar(string $text): string { $text = trim(function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text)); return strtr($text, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']); }

/** @return array<string, mixed>|null  */
function mensagemInterativaParaResposta(string $text): ?array
{
    if ($text === menuInicial()) return listaInterativa("💙 *SUSI | Assistente do SUS*\n\nComo posso ajudar você hoje?", 'Ver opções', [[
        'title' => 'Serviços de saúde',
        'rows' => [
            ['id' => '1', 'title' => 'Atenção psicossocial', 'description' => 'Saúde mental e apoio emocional'],
            ['id' => '2', 'title' => 'Urgência e emergência', 'description' => 'Acidentes e situações graves'],
            ['id' => '3', 'title' => 'Atenção primária', 'description' => 'UBS, acolhimento e consultas'],
            ['id' => '4', 'title' => 'Consultas e exames', 'description' => 'Especialidades e encaminhamentos'],
            ['id' => '5', 'title' => 'Serviços de apoio', 'description' => 'Medicamentos e assistência social'],
        ],
    ]]);

    if ($text === menuUrgencia()) return listaInterativa("🚨 *Urgência e emergência*\n\nQual é a situação?", 'Escolher caso', [[
        'title' => 'Situação',
        'rows' => [
            ['id' => '1', 'title' => 'Acidente', 'description' => 'Queda, colisão, trauma ou ferimento'],
            ['id' => '2', 'title' => 'Dor ou falta de ar', 'description' => 'Dor no peito, falta de ar ou desmaio'],
            ['id' => '3', 'title' => 'Convulsão ou AVC', 'description' => 'Consciência alterada ou sinais de AVC'],
            ['id' => '4', 'title' => 'Outra emergência', 'description' => 'Outra situação com risco de vida'],
        ],
    ]]);

    if ($text === menuPrimaria()) return botoesInterativos("🏥 *Atenção Primária*\n\nComo posso ajudar?", [
        ['id' => '1', 'title' => 'Acolhimento hoje'],
        ['id' => '2', 'title' => 'Agendar consulta'],
        ['id' => '3', 'title' => 'Buscar UBS'],
    ]);

    if ($text === menuSecundaria()) return botoesInterativos("🔎 *Consultas e exames*\n\nO que você precisa?", [
        ['id' => '1', 'title' => 'Consulta'],
        ['id' => '2', 'title' => 'Exame'],
        ['id' => '3', 'title' => 'Encaminhamento'],
    ]);

    if ($text === menuApoio()) return listaInterativa("🤝 *Serviços de apoio*\n\nQual serviço você procura?", 'Escolher serviço', [[
        'title' => 'Serviços',
        'rows' => [
            ['id' => '1', 'title' => 'Medicamentos', 'description' => 'Farmácia e medicamentos'],
            ['id' => '2', 'title' => 'Laboratórios', 'description' => 'Laboratórios e exames'],
            ['id' => '3', 'title' => 'CRAS', 'description' => 'Assistência social'],
            ['id' => '4', 'title' => 'CREAS, CRAM ou POP', 'description' => 'Proteção e assistência especializada'],
            ['id' => '5', 'title' => 'Outros canais', 'description' => 'Outros serviços da rede'],
        ],
    ]]);

    if ($text === menuPsicossocialQuem()) return botoesInterativos("💙 *Atenção psicossocial*\n\n" . $text, [
        ['id' => '1', 'title' => 'Preciso de ajuda'],
        ['id' => '2', 'title' => 'Quero ajudar alguém'],
    ]);

    if ($text === menuPsicossocialDetalhe()) return listaInterativa($text, 'Escolher opção', [[
        'title' => 'Situação',
        'rows' => [
            ['id' => '1', 'title' => 'Sofrimento emocional', 'description' => 'Tristeza, ansiedade, medo ou estresse'],
            ['id' => '2', 'title' => 'Álcool e outras drogas', 'description' => 'Uso de álcool ou outras drogas'],
            ['id' => '3', 'title' => 'Já faço acompanhamento', 'description' => 'Acompanhamento em saúde mental'],
            ['id' => '4', 'title' => 'Outra situação', 'description' => 'Outro motivo para buscar apoio'],
        ],
    ]]);

    if ($text === menuSangramento()) return botoesInterativos("🚨 *Acidente*\n\n" . $text, [
        ['id' => '1', 'title' => 'Sim'],
        ['id' => '2', 'title' => 'Não'],
    ]);

    return null;
}

/** @param array<int, array<string, mixed>> $sections */
function listaInterativa(string $body, string $button, array $sections): array
{
    return [
        'type' => 'list',
        'body' => ['text' => $body],
        'footer' => ['text' => 'Escolha uma opção para continuar'],
        'action' => ['button' => $button, 'sections' => $sections],
    ];
}

/** @param array<int, array{id: string, title: string}> $buttons */
function botoesInterativos(string $body, array $buttons): array
{
    return [
        'type' => 'button',
        'body' => ['text' => $body],
        'footer' => ['text' => 'Toque em uma opção'],
        'action' => ['buttons' => array_map(static fn(array $button): array => ['type' => 'reply', 'reply' => $button], $buttons)],
    ];
}
