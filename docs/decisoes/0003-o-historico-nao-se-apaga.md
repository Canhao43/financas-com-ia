# 0003 — O histórico não se apaga

**Marco:** M1 · M2

## Contexto

Os lançamentos antigos são o histórico que os relatórios e a IA comparam. Excluir uma conta levaria os lançamentos dela junto. Trocar o tipo de uma categoria que já tem lançamentos ("Mercado" virando receita) deixaria saídas antigas numa categoria de entrada. As duas coisas mudam o passado sem aviso.

## Decisão

- **Conta não se exclui, é arquivada.** A arquivada some do formulário de lançamento, da importação e do saldo total do painel, mas os lançamentos dela continuam no relatório e editáveis.
- **Categoria com lançamentos não troca de tipo.** A trava é na validação do servidor, não só na tela.
- **Excluir uma categoria deixa os lançamentos sem categoria** (`nullOnDelete`): o gasto existiu e continua contando. As regras da categoria vão junto (ver [0008](0008-regras-de-categoria.md)).

## Alternativas descartadas

- **Exclusão em cascata.** Apagaria o histórico.
- **Esconder a opção só na tela.** Uma requisição montada à mão passaria.
- **Proibir excluir categoria com lançamentos.** Obrigaria a recategorizar tudo antes; "sem categoria" já é um estado que o app trata.

## Consequências

- A lista de contas mostra as arquivadas para sempre, marcadas como "Arquivada".
- "Sem categoria" vira uma linha nos relatórios, para a soma das linhas bater com o total.

## Como é verificado

- `ContaTest::test_conta_nao_se_exclui`
- `ContaTest::test_arquiva_e_desarquiva`
- `DashboardTest::test_soma_contas_ativas_e_separa_o_mes_atual`
- `LancamentoTest::test_conta_arquivada_nao_recebe_lancamento_novo`
- `LancamentoTest::test_lancamento_de_conta_arquivada_continua_editavel`
- `ImportacaoTest::test_conta_arquivada_nao_recebe_importacao`
- `RelatorioTest::test_conta_arquivada_continua_no_relatorio`
- `CategoriaTest::test_tipo_nao_muda_quando_ja_tem_lancamentos`
- `CategoriaTest::test_tipo_muda_quando_ainda_nao_tem_lancamentos`
- `CategoriaTest::test_excluir_deixa_os_lancamentos_sem_categoria`
