# 0002 — Dinheiro e porcentagem só em inteiros

**Marco:** M1 · M4

## Contexto

Todo número do app vira total, comparação e, depois, texto da IA. `float` erra centavo (0,1 + 0,2 não dá 0,3), e um centavo de diferença entre a tela e o extrato do banco destrói a confiança no resto. A porcentagem tem o mesmo problema: a mesma conta precisa dar sempre o mesmo resultado, até a última casa.

## Decisão

- Valores em **centavos, inteiros** (`bigint`), **com sinal**: saída negativa, entrada positiva. É o formato do OFX, e o saldo vira uma soma só.
- O valor digitado ("1.234,56") vira centavos sem passar por `float` (`App\Support\Dinheiro`). O OFX também (ver [0005](0005-leitor-de-ofx-proprio.md)).
- Porcentagem em **pontos-base** (2534 = 25,34%), calculada só com inteiros e arredondada para o mais próximo, com o meio para longe do zero (`App\Support\Porcentagem`).
- O texto para a IA ("R$ 1.234,56", "25,3%") também é montado só com inteiros (`App\Assistente\Formato`).
- O servidor calcula; a tela só formata.

## Alternativas descartadas

- **`float`.** Erra centavo.
- **`decimal` no banco.** Resolve no banco, mas o PHP lê como string e a conta volta a ser problema no código.
- **Valor sempre positivo e uma coluna de tipo.** Toda soma precisaria de `CASE`.
- **Porcentagem calculada na tela.** A mesma conta em dois lugares, e a IA leria outra.

## Consequências

- A tela recebe centavos e pontos-base e só formata.
- A soma das participações pode dar 99,9%: cada linha arredonda sozinha. Forçar 100% exigiria empurrar a sobra para uma linha qualquer, e essa linha mentiria.

## Como é verificado

- `DinheiroTest::test_converte_texto_em_centavos`
- `DinheiroTest::test_ida_e_volta_nao_perde_centavo`
- `LancamentoTest::test_saida_e_gravada_negativa_em_centavos`
- `LancamentoTest::test_entrada_e_gravada_positiva`
- `LeitorOfxTest::test_le_o_valor_em_centavos_sem_float`
- `ContaTest::test_saldo_atual_e_o_inicial_mais_os_lancamentos`
- `PorcentagemTest::test_calcula_em_pontos_base_arredondando_para_o_mais_proximo`
- `FormatoTest::test_formata_reais_so_com_inteiros`
- `FormatoTest::test_formata_porcentagem_como_a_tela`
