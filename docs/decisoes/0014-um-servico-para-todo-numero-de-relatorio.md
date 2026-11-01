# 0014 — Um serviço só para todo número de relatório

**Marco:** M4 · M5 · M6

## Contexto

Antes do relatório, o painel somava os totais do seu jeito. Com a transferência (ver [0013](0013-transferencia-como-tipo-de-categoria.md)), a regra de "o que conta como despesa" mudou, e precisaria mudar igual em cada tela. Depois vieram o assistente e a análise do mês: se cada um somasse sozinho, a tela e a IA poderiam dar dois números para a mesma pergunta.

## Decisão

- **`App\Relatorios\RelatorioMensal` calcula**: receitas, despesas e resultado do mês, cada um com o mês anterior, e o total por categoria. O painel, a tela de relatório, as ferramentas do assistente e a análise do mês leem dele.
- As linhas são separadas **pelo sinal do valor**, não pelo tipo da categoria: a soma das linhas é sempre o total, mesmo com dado fora do padrão.
- **"Sem categoria" vira uma linha** de cada lado.
- **A categoria que zerou continua na lista**: "parei de gastar com isso" é parte da comparação.
- A variação compara **pelo tamanho** (gastar R$ 120 depois de R$ 100 é +20%, em despesa ou receita). Sem valor no mês anterior, a variação fica vazia.

## Alternativas descartadas

- **Cada tela somando do seu jeito.** Era o que o painel fazia; uma regra nova chegaria numa tela e não na outra.
- **Linhas pelo tipo da categoria.** Uma entrada numa categoria de despesa sumiria do relatório, e as linhas não fechariam com o total.
- **Mostrar 0% ou 100% quando não há mês anterior.** "Infinito por cento" não diz nada.

## Consequências

- Mudar uma regra de soma é mudar um arquivo, e todos os lugares acompanham.
- Cada linha do relatório abre os lançamentos daquela categoria no mês: o total é conferível.

## Como é verificado

- `RelatorioTest::test_o_painel_mostra_os_mesmos_totais_do_relatorio`
- `RelatorioTest::test_soma_por_categoria_do_maior_para_o_menor`
- `RelatorioTest::test_sem_categoria_vira_uma_linha_de_cada_lado`
- `RelatorioTest::test_compara_cada_categoria_com_o_mes_anterior`
- `RelatorioTest::test_mes_vazio_da_zero_e_nenhuma_linha`
- `LancamentoTest::test_filtra_pela_categoria_que_vem_do_relatorio`
- `FerramentasDoAssistenteTest::test_resumo_do_mes_entrega_o_relatorio_formatado`
- `AnalistaDoMesTest::test_escreve_a_analise_com_os_numeros_do_mes_e_confere_os_valores`
