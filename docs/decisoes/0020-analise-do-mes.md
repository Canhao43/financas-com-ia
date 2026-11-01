# 0020 — Análise do mês: uma chamada, uma correção e o aviso de desatualizada

**Marco:** M6

## Contexto

O relatório do mês mostra números; a análise escrita pelo Claude explica o que eles dizem. Diferente do chat, aqui não há pergunta: os números do mês são fixos e cabem inteiros num pedido. O texto fica gravado, e os lançamentos do mês podem mudar depois que ele foi escrito. E cada análise custa dinheiro do dono.

## Decisão

- **Uma chamada, sem ferramentas**, com os mesmos números que o assistente consulta (ver [0014](0014-um-servico-para-todo-numero-de-relatorio.md)) e a informação de que o mês ainda está em andamento.
- **Saída estruturada** (`output_config.format`, com esquema JSON): a resposta vem com `resumo` e `destaques` separados, sem o app adivinhar onde termina cada parte. Recusa e resposta cortada pelo tamanho chegam fora do formato e viram mensagem.
- **Uma correção com a lista exata.** Se a conferência (ver [0016](0016-a-ia-nao-faz-conta.md)) achar valor sem fonte, o Claude recebe a própria resposta e "Estes valores não estão nos dados: …", e reescreve uma vez. O que sobrar fica gravado e a tela avisa. O teto é configurável.
- **Impressão dos números gravada com o texto** (SHA-256 dos dados enviados). Se a impressão de agora for outra (lançamento novo, categoria trocada, o mês acabou), a tela avisa que a análise ficou desatualizada.
- **Só os totais por categoria vão para a API**: nenhum lançamento, descrição ou nome de conta.
- **Escrita pelo botão**, nunca sozinha. Se falhar, a análise anterior continua.
- **Sem cache de prompt**: é uma chamada por mês, e gravar o cache custa 25% a mais; só a correção, que deve ser rara, o leria.

## Alternativas descartadas

- **Reusar o assistente com ferramentas.** Não há o que buscar; o loop só acrescentaria custo.
- **Texto livre.** O app teria que adivinhar onde terminam o resumo e cada destaque.
- **Só avisar, sem correção.** Um deslize de cópia, que uma chance resolveria, viraria aviso.
- **Tentar até acertar.** Cada tentativa custa, e quem insiste uma vez costuma insistir de novo.
- **Reescrever sozinha quando os números mudam, ou todo dia 1º.** Gastaria o dinheiro do dono sem ele pedir.
- **Mandar os maiores gastos com a descrição.** Mais dado sai do app sem necessidade.

## Consequências

- A primeira chamada com o esquema demora mais: a API compila o esquema e o guarda por 24 horas.
- A análise do mês em andamento fica desatualizada quando o mês acaba, mesmo sem lançamento novo, porque "em andamento" faz parte dos números.

## Como é verificado

- `AnalistaDoMesTest::test_escreve_a_analise_com_os_numeros_do_mes_e_confere_os_valores` — confere a saída estruturada, a ausência de ferramentas e de cache, e que nem a descrição nem o nome da conta saem do app
- `AnalistaDoMesTest::test_valor_sem_fonte_volta_para_o_claude_corrigir`
- `AnalistaDoMesTest::test_valor_sem_fonte_que_persiste_fica_gravado_para_a_tela_avisar`
- `AnalistaDoMesTest::test_resposta_sem_analise_nao_apaga_a_anterior`
- `AnalistaDoMesTest::test_analise_fica_desatualizada_quando_os_numeros_do_mes_mudam`
- `AnalistaDoMesTest::test_analise_do_mes_em_andamento_fica_desatualizada_quando_o_mes_acaba`
- `AnaliseTest::test_relatorio_avisa_quando_a_analise_ficou_para_tras`
- `AnaliseTest::test_erro_da_api_aparece_no_cartao_e_a_analise_anterior_fica`
