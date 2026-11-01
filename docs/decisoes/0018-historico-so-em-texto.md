# 0018 — Histórico da conversa só em texto, e a data dentro da pergunta

**Marco:** M5

## Contexto

No Claude Opus 5.5, o raciocínio do modelo volta assinado e preso à conversa exata em que foi produzido. Reenviar um histórico editado (sem uma consulta, com outro prompt de sistema) faz a API recusar com erro 400 em contas criadas a partir de 31/08/2026. Além disso, reenviar todas as consultas e todo o raciocínio de cada pergunta anterior encarece cada pergunta nova. E "este mês" e "mês passado" dependem de saber que dia é hoje.

## Decisão

- **Entre perguntas, o histórico vai só em texto**: a pergunta e a resposta final, das últimas 6 perguntas respondidas. Pergunta que deu erro ou foi recusada fica de fora.
- **Dentro de uma pergunta**, cada resposta do Claude volta inteira, com o raciocínio, exatamente como veio, até ele terminar de consultar.
- **A data de hoje vai num bloco de texto da própria pergunta**, gravada com ela. O prompt do sistema é fixo.
- Os valores das consultas anteriores continuam valendo na conferência (ver [0016](0016-a-ia-nao-faz-conta.md)), mesmo sem as consultas no histórico.

## Alternativas descartadas

- **Reenviar consultas e raciocínio de todas as perguntas.** Mais caro, e qualquer mudança no histórico invalidaria o raciocínio antigo.
- **A data no prompt do sistema.** Mudaria todo dia: o cache do prompt não reaproveitaria nada, e a conversa de ontem teria outro sistema.

## Consequências

- Para um detalhe de uma pergunta anterior, o Claude consulta de novo.
- A pergunta de ontem volta no histórico com a data de ontem: a data é gravada com ela, e o histórico sai igual em todas as perguntas seguintes.

## Como é verificado

- `AssistenteTest::test_o_historico_leva_so_o_texto_das_perguntas_respondidas`
- `AssistenteTest::test_responde_consultando_o_banco_e_confere_os_valores` — confere a data na pergunta e o raciocínio assinado voltando inteiro na segunda chamada
