<?php

require_once "database.php";

function buscarUnidade(string $nome): ?array
{
    $pdo = conectarBanco();

    $sql = "
        SELECT
            u.id_unidade,
            u.cnes,
            u.nome_fantasia,
            u.tipo_unidade,

            e.logradouro,
            e.numero,
            e.bairro,
            e.cep,
            e.latitude,
            e.longitude,

            t.telefone,
            t.tipo_telefone,
            t.observacao AS observacao_telefone,
            t.principal,

            h.codigo AS codigo_horario,
            h.descricao AS horario

        FROM unidade_saude AS u

        JOIN endereco AS e
            ON u.id_endereco = e.id_endereco

        LEFT JOIN telefone_unidade AS t
            ON u.id_unidade = t.id_unidade

        LEFT JOIN horario_padrao AS h
            ON u.id_horario_padrao = h.id_horario_padrao

        WHERE u.nome_fantasia LIKE :nome

        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':nome' => "%{$nome}%"
    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    return $resultado ?: null;
}