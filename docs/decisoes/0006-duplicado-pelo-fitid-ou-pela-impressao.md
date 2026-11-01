# 0006 — Duplicado: FITID com o mesmo valor, ou a impressão digital com a ordem

**Marco:** M2

## Contexto

O mesmo extrato é importado de novo, e extratos de períodos que se sobrepõem trazem as mesmas transações. O FITID é o identificador oficial da transação no OFX, mas há banco que o muda entre uma exportação e outra, e banco que reaproveita o número do documento. E duas compras iguais no mesmo dia (dois cafés na mesma padaria) são duas compras, não uma duplicada.

## Decisão

- Uma linha do extrato **já existe** se tem o mesmo FITID **e** o mesmo valor de um lançamento da conta, **ou** a mesma impressão digital.
- A **impressão** é o SHA-256 de data + valor + descrição normalizada + **a ordem dessa combinação no arquivo**: o primeiro café do dia é "…|1", o segundo é "…|2".
- FITID repetido dentro do mesmo arquivo é descartado.
- A impressão é **guardada e nunca recalculada**: editar a descrição, a data ou o valor de um lançamento importado não faz ele voltar na próxima importação.
- Índice único `(conta_id, impressao)` no banco, como trava final.

## Alternativas descartadas

- **Só o FITID.** Banco que muda o FITID duplicaria tudo; banco que o reaproveita esconderia lançamento novo.
- **Data + valor + descrição, sem a ordem.** O segundo café do dia sumiria como duplicado.
- **Recalcular a impressão a partir da descrição atual.** Um lançamento editado voltaria como novo.
- **Checar só no código.** Duas requisições ao mesmo tempo passariam juntas pela checagem.

## Consequências

- Se um banco mudar o FITID **e** a descrição da mesma transação, ela entra de novo. A conferência de saldo do resumo da importação (ver [0007](0007-previa-vinculo-e-desfazer.md)) é a rede para esse caso.
- FITID sozinho, com outro valor, não basta: o erro exige número e valor iguais.

## Como é verificado

- `LinhaTest::test_duas_compras_iguais_no_mesmo_dia_tem_impressoes_diferentes`
- `LinhaTest::test_a_mesma_transacao_tem_a_mesma_impressao_em_outro_arquivo`
- `LinhaTest::test_diferenca_de_acento_caixa_e_espaco_nao_muda_a_impressao`
- `LinhaTest::test_fitid_repetido_no_arquivo_e_descartado`
- `ImportacaoTest::test_importar_o_mesmo_arquivo_de_novo_nao_duplica_nada`
- `ImportacaoTest::test_periodos_sobrepostos_trazem_so_o_que_falta`
- `ImportacaoTest::test_compras_identicas_no_mesmo_dia_entram_todas_e_so_uma_vez`
- `ImportacaoTest::test_o_fitid_reconhece_a_transacao_mesmo_com_outra_descricao`
- `ImportacaoTest::test_fitid_reaproveitado_com_outro_valor_nao_esconde_lancamento_novo`
- `ImportacaoTest::test_editar_o_lancamento_importado_nao_faz_ele_voltar_na_proxima_importacao`
