# 0008 — Regras de categoria: "contém", e a mais longa vence

**Marco:** M2

## Contexto

Categorizar cada linha do extrato à mão não dura um mês. O nome do estabelecimento quase sempre aparece na descrição ("UBER *TRIP", "IFOOD *RESTAURANTE"). As regras são cadastradas pelo celular, e regras parecidas competem: "UBER EATS" é comida, "UBER" é transporte.

## Decisão

- Regra = **"se a descrição contém X, a categoria é Y"**, comparando sem acento, sem diferença de maiúscula e com espaços normalizados.
- **A regra mais longa vence**, sem ordem manual. No empate, a mais antiga.
- A regra **só vale na direção da categoria**: regra de despesa não categoriza entrada ("ESTORNO UBER" não vira gasto com transporte). Transferência vale nas duas direções.
- **Nunca sobrescreve categoria**: nem no botão que aplica as regras, nem no lançamento manual vinculado numa importação.
- O texto é guardado normalizado, e normalizado **antes** da validação, para o `unique` ver que "Uber" e "UBER" são a mesma regra. Mínimo de 3 caracteres.
- Excluir a categoria leva as regras dela.

## Alternativas descartadas

- **Expressão regular.** Erro de sintaxe na mão de quem cadastra pelo celular, e expressão mal escrita pode travar o servidor.
- **Ordem manual (arrastar para ordenar).** É estado que alguém tem que lembrar de manter a cada regra nova.
- **Ignorar o sinal do valor.** Estorno viraria gasto e inflaria a despesa do mês.
- **Reaplicar as regras em tudo.** A escolha feita à mão vale mais que a regra.

## Consequências

- Regra genérica demais ("PAG") categoriza coisa demais; o mínimo de 3 caracteres só reduz o risco.
- Categorizar pela IA ficou fora: o assistente do M5 só lê (ver [0017](0017-o-assistente-so-le.md)).

## Como é verificado

- `RegraTest::test_cria_a_regra_com_o_texto_normalizado`
- `RegraTest::test_texto_repetido_ou_curto_e_recusado`
- `RegraTest::test_acento_e_caixa_nao_importam`
- `RegraTest::test_a_regra_mais_especifica_vence_sem_depender_da_ordem_de_cadastro`
- `RegraTest::test_a_regra_so_vale_para_a_direcao_da_categoria`
- `RegraTest::test_regra_de_transferencia_vale_nas_duas_direcoes`
- `RegraTest::test_aplicar_categoriza_so_os_lancamentos_sem_categoria`
- `RegraTest::test_excluir_a_categoria_leva_as_regras_dela`
- `ImportacaoTest::test_as_regras_categorizam_o_que_a_importacao_cria`
