# 0005 — Leitor de OFX próprio

**Marco:** M2

## Contexto

A entrada principal do app é o extrato OFX do banco. A biblioteca de OFX mais usada em PHP (`asgrim/ofxparser`) não tem versão nova desde 2018, devolve o valor em `float` e **lê `-150` como R$ 1,50** (conferido rodando o código dela). E os bancos brasileiros variam: OFX 1.x (SGML, com tags sem fechamento), OFX 2.x (XML), arquivo inteiro numa linha só, texto em Windows-1252 com o cabeçalho dizendo outra coisa.

## Decisão

- Um leitor próprio e pequeno (`App\Ofx\LeitorOfx`, cerca de 160 linhas), só com as tags que o app usa.
- Uma regra só para SGML e XML: o valor de uma tag vai até o próximo `<` ou a quebra de linha.
- A codificação é decidida pelo conteúdo, não pelo cabeçalho: texto em Windows-1252 com acento quase nunca é UTF-8 válido.
- O valor é lido como texto e vira centavos sem `float` (ver [0002](0002-dinheiro-e-porcentagem-so-em-inteiros.md)).
- O que não dá para ler com certeza (valor, data, arquivo que não é extrato) é recusado com o motivo, em vez de virar um número errado.

## Alternativas descartadas

- **A biblioteca.** Erra o valor e não recebe correção há anos.
- **Converter o SGML em XML e usar um leitor de XML.** Mais uma etapa, e ela precisaria da mesma regra para saber onde cada valor termina.
- **Confiar no `CHARSET` do cabeçalho.** Banco declara uma codificação e manda outra.

## Consequências

- Manter o leitor é responsabilidade do projeto: um banco com formato novo vira uma amostra fictícia nova nos testes.
- O arquivo enviado não fica em disco: só as linhas lidas vão para a prévia (ver [0007](0007-previa-vinculo-e-desfazer.md)).

## Como é verificado

- `LeitorOfxTest::test_le_extrato_sgml_em_windows_1252`
- `LeitorOfxTest::test_le_extrato_de_cartao_em_xml`
- `LeitorOfxTest::test_le_arquivo_inteiro_numa_linha_so`
- `LeitorOfxTest::test_aceita_utf8_com_bom`
- `LeitorOfxTest::test_le_o_valor_em_centavos_sem_float`
- `LeitorOfxTest::test_recusa_valor_que_nao_da_para_ler_com_certeza`
- `LeitorOfxTest::test_recusa_data_invalida`
- `LeitorOfxTest::test_recusa_arquivo_que_nao_e_um_extrato`
