# 0016 — A IA não faz conta, e o app confere

**Marco:** M5 · M6

## Contexto

Modelo de linguagem erra conta com a mesma confiança com que acerta. Num app de finanças, "você gastou R$ 1.300 com mercado" errado é pior que nenhuma resposta. Uma instrução no prompt ("não faça contas") reduz o problema, mas não diz quando ele aconteceu.

## Decisão

Em duas camadas:

- **Prevenção: o modelo recebe todo número pronto.** As ferramentas do assistente e os dados da análise devolvem texto formatado ("R$ 718,90", "+15,3%", "R$ 95,45 a mais"), nunca centavos crus. Toda conta comum vira ferramenta: total de um período qualquer, comparação de dois períodos já com a diferença e a variação. O prompt proíbe somar, subtrair e juntar resultados de chamadas diferentes.
- **Verificação: cada valor da resposta é conferido** (`App\Assistente\ConferenciaDeValores`). O app extrai todo valor em reais e toda porcentagem do texto final e compara com os valores que as consultas devolveram (nesta pergunta e nas anteriores da conversa) e com os que a própria pergunta trouxe. A comparação ignora o sinal e o separador de milhar. O que não bate aparece na tela como **sem fonte**.

## Alternativas descartadas

- **Confiar só na instrução do prompt.** Não diz quando falhou.
- **Dar centavos crus para o modelo.** Até dividir por cem é conta.
- **Deixar o modelo chamar o total duas vezes e subtrair.** É exatamente a conta que se quer evitar; por isso existe a ferramenta de comparação.

## Consequências

- A conferência só enxerga valores escritos com "R$" ou "%": "718,90 reais" passaria sem ser conferido. O prompt pede a forma exata, e a conferência cobre o caso comum.
- Cada conta nova que a pessoa queira fazer pelo chat precisa virar ferramenta (ou dado pronto) antes.
- Na análise do mês, o valor sem fonte volta para o Claude corrigir uma vez antes do aviso (ver [0020](0020-analise-do-mes.md)).

## Como é verificado

- `ConferenciaDeValoresTest::test_acha_reais_e_porcentagens_e_ignora_sinal_e_separador`
- `ConferenciaDeValoresTest::test_aponta_so_o_que_nao_veio_das_consultas`
- `FormatoTest::test_comparacao_sai_sem_o_sinal_de_menos`
- `FerramentasDoAssistenteTest::test_comparar_periodos_ja_entrega_diferenca_e_variacao`
- `AssistenteTest::test_valor_que_o_claude_calculou_sozinho_fica_marcado_sem_fonte`
- `AssistenteTest::test_valor_citado_na_pergunta_nao_e_sem_fonte`
- `AnalistaDoMesTest::test_valor_sem_fonte_que_persiste_fica_gravado_para_a_tela_avisar`
