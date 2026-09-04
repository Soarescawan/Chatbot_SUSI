====================
PERGUNTA: qual telefone da ubs santa terezinha
RAW GEMINI:
SELECT u.nome_fantasia, e.logradouro, e.numero, e.bairro, u.telefone FROM unidade_saude u JOIN endereco e ON u.id_endereco = e.id_endereco WHERE u.nome_fantasia LIKE '%santa terezinha%' COLLATE utf8mb4_general_ci;

====================
PERGUNTA: endereço da ubs areal leste
RAW GEMINI:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco FROM unidade_saude u JOIN endereco e ON u.id_endereco = e.id_endereco WHERE u.nome_fantasia LIKE '%areal leste%' COLLATE utf8mb4_general_ci;


====================
PERGUNTA: Endereço da UBS py crespo
RAW GEMINI:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco FROM unidade_saude u JOIN endereco e ON u.id_endereco = e.id_endereco WHERE u.nome_fantasia LIKE '%py crespo%' COLLATE utf8mb4_general_ci;


====================
PERGUNTA: Obrigado!
RAW GEMINI:
Ok.


====================
PERGUNTA: Precisa ir em uma UBS no centro
RAW GEMINI:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco FROM unidade_saude u JOIN endereco e ON u.id_endereco = e.id_endereco WHERE e.bairro LIKE '%centro%' COLLATE utf8mb4_general_ci;


====================
PERGUNTA: Preciso de uma UBS com dentista
RAW GEMINI:
SELECT u.nome_fantasia, e.logradouro, e.numero, e.bairro FROM unidade_saude u JOIN endereco e ON u.id_endereco = e.id_endereco WHERE u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci;


====================
PERGUNTA: Todas possuem atendimento odontológico?
RAW GEMINI:
Não posso te dar essa informação.


====================
PERGUNTA: Preciso ir em uma ubs
RAW GEMINI:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci;


====================
PERGUNTA: Preciso ir em uma UBS
RAW GEMINI:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci


====================
PERGUNTA: Preciso ir em uma ubs
RAW GEMINI:
Qual o bairro ou região que você deseja?


====================
PERGUNTA: Laranjal
RAW GEMINI:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco FROM unidade_saude u JOIN endereco e ON u.id_endereco = e.id_endereco WHERE e.bairro LIKE '%Laranjal%' COLLATE utf8mb4_general_ci


====================
PERGUNTA: Preciso de uma UBS com dentista no porto
RAW GEMINI:
O banco não possui informações sobre atendimento odontológico.


====================
PERGUNTA: Não
RAW GEMINI:
O banco não possui informações sobre atendimento odontológico.


====================
PERGUNTA: UBS no centro
RAW GEMINI:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci
AND e.bairro LIKE '%centro%' COLLATE utf8mb4_general_ci


====================
PERGUNTA: preciso de um caps
RAW GEMINI:
```sql
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco FROM unidade_saude u JOIN endereco e ON u.id_endereco = e.id_endereco WHERE u.tipo_unidade LIKE '%CAPS%' COLLATE utf8mb4_general_ci;
```

====================
PERGUNTA: não
RAW GEMINI:
Por favor, especifique o que você precisa. Por exemplo, você precisa de uma UBS? Em qual bairro ou região?


====================
PERGUNTA: preciso da ubs mais próxima
RAW:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci;


====================
PERGUNTA: preciso ir em uma ubs
RAW:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci


====================
PERGUNTA: UBS no bairro bairro laranjal
RAW:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco FROM unidade_saude u JOIN endereco e ON u.id_endereco = e.id_endereco WHERE u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci AND e.bairro LIKE '%laranjal%' COLLATE utf8mb4_general_ci


====================
PERGUNTA: tem dentista na ubs laranjal?
RAW:
O banco não possui informações sobre atendimento odontológico.


====================
PERGUNTA: endereço da ubs csu cruzeiro
RAW:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco FROM unidade_saude u JOIN endereco e ON u.id_endereco = e.id_endereco WHERE u.nome_fantasia LIKE '%csu cruzeiro%' COLLATE utf8mb4_general_ci AND u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci


====================
PERGUNTA: telefone da ubs obelisco
RAW:
O banco não possui informações sobre telefones.


====================
PERGUNTA: endereço da ubs areal leste
RAW:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci
AND e.bairro LIKE '%areal leste%' COLLATE utf8mb4_general_ci


====================
PERGUNTA: quais ubs existem no areal
RAW:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci
AND e.bairro LIKE '%areal%' COLLATE utf8mb4_general_ci


====================
PERGUNTA: endereço da ubs areal leste
RAW:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.nome_fantasia LIKE '%areal leste%' COLLATE utf8mb4_general_ci;


====================
PERGUNTA: UBS no bairro centro
RAW:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.tipo_unidade LIKE '%UBS%' COLLATE utf8mb4_general_ci
AND e.bairro LIKE '%centro%' COLLATE utf8mb4_general_ci


====================
PERGUNTA: quero ir em um posto
RAW:
Qual o bairro ou região que você deseja encontrar a UBS?


====================
PERGUNTA: tres vendas
RAW:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.nome_fantasia LIKE '%tres vendas%' COLLATE utf8mb4_general_ci;


====================
PERGUNTA: preciso ir em um posto
RAW:
Qual o bairro ou região que você deseja encontrar a UBS?


====================
PERGUNTA: tres vendas
RAW:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.nome_fantasia LIKE '%tres vendas%' COLLATE utf8mb4_general_ci;


====================
PERGUNTA: Endereço
RAW:
```sql
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco FROM unidade_saude u JOIN endereco e ON u.id_endereco = e.id_endereco;
```

====================
PERGUNTA: Obelisco
RAW:
SELECT u.nome_fantasia, CONCAT(e.logradouro, ', ', e.numero, ', ', e.bairro) AS endereco
FROM unidade_saude u
JOIN endereco e ON u.id_endereco = e.id_endereco
WHERE u.nome_fantasia LIKE '%obelisco%' COLLATE utf8mb4_general_ci;

