# 0004 — Fuso de São Paulo, e o mês como intervalo semiaberto

**Marco:** M1 · M3

## Contexto

"Que dia é hoje" e "quais lançamentos são de outubro" decidem todo total do app. Os testes pegaram dois erros antes de produção:

- Com `between` até o dia 31, o SQLite deixava o **dia 31 de fora**: a data é guardada como texto ("2026-10-31 00:00:00"), que é maior que "2026-10-31".
- Com o padrão do Laravel (UTC), depois das 21h em São Paulo **já era amanhã**: o lançamento novo nascia com a data errada, e no último dia do mês o painel pulava para o mês seguinte.

## Decisão

- Fuso **`America/Sao_Paulo`** na configuração do app.
- Todo período é um **intervalo semiaberto**: `data >= primeiro dia` e `data < dia seguinte ao último`, num escopo só (`Lancamento::noPeriodo`), usado pelo mês, pelo relatório e pelas ferramentas da IA.

## Alternativas descartadas

- **UTC no banco e conversão na tela.** É o certo para usuários em fusos diferentes; aqui cada `today()` precisaria lembrar do fuso, e esquecer uma vez já cria o erro.
- **`between` com o último dia.** É o erro do dia 31.

## Consequências

- Um usuário em outro fuso exigiria voltar a esta decisão. O Brasil não tem horário de verão desde 2019, então o deslocamento é fixo.
- As datas que importam (do lançamento) são `DATE`, sem hora.

## Como é verificado

- `RelatorioTest::test_o_ultimo_dia_do_mes_entra_e_o_primeiro_do_seguinte_nao`
- `RelatorioTest::test_janeiro_compara_com_dezembro_do_ano_anterior`
- `DashboardTest::test_o_mes_do_painel_segue_o_horario_de_sao_paulo` — falha com UTC
- `LancamentoTest::test_a_data_padrao_e_o_dia_de_hoje_em_sao_paulo` — falha com UTC
