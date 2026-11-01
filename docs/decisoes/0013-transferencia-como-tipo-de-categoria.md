# 0013 — Transferência entre contas como tipo de categoria

**Marco:** M4

## Contexto

Antes do relatório, o app não tinha transferência. Pagar a fatura do cartão saía da conta corrente como **despesa**, e as compras do cartão já eram despesas, cada uma na sua categoria. A fatura, normalmente o maior gasto do mês, contaria **duas vezes**, e o "quanto gastei" do assistente sairia errado. As duas pontas de uma transferência chegam separadas: a saída no extrato da conta corrente, a entrada no do cartão, em dias e importações diferentes.

## Decisão

- A categoria ganha um terceiro tipo, **transferência**, ao lado de despesa e receita. Ela vale para saída **e** para entrada.
- Lançamento em categoria de transferência fica **fora de receitas e despesas** em todo lugar: painel, relatório, assistente e análise do mês. O relatório mostra o total transferido à parte.
- Cada ponta é marcada sozinha, à mão ou por regra ("FATURA CARTAO"). A semente cria "Transferência entre contas".

## Alternativas descartadas

- **Uma entidade "transferência" ligando dois lançamentos.** As pontas chegam em importações separadas, de contas diferentes; casar as duas seria outro problema do tamanho da importação.
- **Deixar a fatura como despesa.** Soma o mesmo gasto duas vezes.

## Consequências

- Se só uma ponta for marcada, a outra continua contando como receita ou despesa.
- O saldo total não muda com uma transferência, que é a prova de que ela não é gasto.

## Como é verificado

- `CategoriaTest::test_cria_categoria_de_transferencia`
- `LancamentoTest::test_transferencia_serve_para_saida_e_para_entrada`
- `RegraTest::test_regra_de_transferencia_vale_nas_duas_direcoes`
- `DashboardTest::test_transferencia_entre_contas_nao_e_receita_nem_despesa`
- `RelatorioTest::test_transferencia_fica_fora_dos_totais_e_aparece_somada`
- `FerramentasDoAssistenteTest::test_total_no_periodo_soma_varios_meses_sem_transferencias`
