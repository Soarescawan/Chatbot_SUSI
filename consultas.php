<?php

require_once __DIR__ . '/database.php';

function buscarUnidade(string $nome): ?array
{
    $pdo = conectarBanco();
    $nome = trim($nome);

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
            h.codigo AS codigo_horario,
            h.descricao AS horario,
            GROUP_CONCAT(
                DISTINCT TRIM(
                    CONCAT(
                        COALESCE(t.telefone, ''),
                        CASE
                            WHEN t.observacao IS NOT NULL AND t.observacao <> '' THEN CONCAT(' (', t.observacao, ')')
                            ELSE ''
                        END
                    )
                )
                SEPARATOR ' | '
            ) AS telefones

        FROM unidade_saude AS u

        JOIN endereco AS e
            ON u.id_endereco = e.id_endereco

        LEFT JOIN telefone_unidade AS t
            ON u.id_unidade = t.id_unidade

        LEFT JOIN horario_padrao AS h
            ON u.id_horario_padrao = h.id_horario_padrao

        WHERE (
            u.nome_fantasia LIKE :busca_nome
            OR e.bairro LIKE :busca_bairro
            OR e.logradouro LIKE :busca_logradouro
            OR u.tipo_unidade LIKE :busca_tipo
        )

        GROUP BY
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
            h.codigo,
            h.descricao

        ORDER BY
            CASE
                WHEN u.nome_fantasia LIKE :prefixo_nome THEN 0
                WHEN e.bairro LIKE :prefixo_bairro THEN 1
                WHEN e.logradouro LIKE :prefixo_logradouro THEN 2
                ELSE 3
            END,
            CHAR_LENGTH(u.nome_fantasia) ASC

        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':busca_nome' => "%{$nome}%",
        ':busca_bairro' => "%{$nome}%",
        ':busca_logradouro' => "%{$nome}%",
        ':busca_tipo' => "%{$nome}%",
        ':prefixo_nome' => "{$nome}%",
        ':prefixo_bairro' => "{$nome}%",
        ':prefixo_logradouro' => "{$nome}%",
    ]);

    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

    return $resultado ?: null;
}
