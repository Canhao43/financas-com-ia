# Registro de decisões

Uma decisão por arquivo, no formato ADR (_Architecture Decision Record_): o contexto, a decisão, as alternativas descartadas, as consequências e **como ela é verificada**, apontando o teste ou a etapa do CI que a prova.

O próprio registro é testado (`tests/Unit/RegistroDeDecisoesTest.php`): toda decisão está neste índice, segue as mesmas seções, e cada teste ou etapa do CI citada como prova existe. Renomear um teste sem atualizar a decisão quebra o build.

| #                                                        | Decisão                                                                | Marco        |
| -------------------------------------------------------- | ---------------------------------------------------------------------- | ------------ |
| [0001](0001-um-usuario-so-criado-por-comando.md)         | Um usuário só, criado por comando                                      | M0           |
| [0002](0002-dinheiro-e-porcentagem-so-em-inteiros.md)    | Dinheiro e porcentagem só em inteiros                                  | M1 · M4      |
| [0003](0003-o-historico-nao-se-apaga.md)                 | O histórico não se apaga                                               | M1 · M2      |
| [0004](0004-fuso-de-sao-paulo-e-mes-semiaberto.md)       | Fuso de São Paulo, e o mês como intervalo semiaberto                   | M1 · M3      |
| [0005](0005-leitor-de-ofx-proprio.md)                    | Leitor de OFX próprio                                                  | M2           |
| [0006](0006-duplicado-pelo-fitid-ou-pela-impressao.md)   | Duplicado: FITID com o mesmo valor, ou a impressão digital com a ordem | M2           |
| [0007](0007-previa-vinculo-e-desfazer.md)                | Importação com prévia, vínculo ao lançamento manual e desfazer         | M2           |
| [0008](0008-regras-de-categoria.md)                      | Regras de categoria: "contém", e a mais longa vence                    | M2           |
| [0009](0009-sqlite-em-producao.md)                       | SQLite em produção                                                     | M3           |
| [0010](0010-backup-cifrado-fora-do-servidor.md)          | Backup diário, cifrado com chave pública, fora do servidor             | M3           |
| [0011](0011-imagem-testada-pelo-ci-e-deploy-manual.md)   | Imagem testada pelo CI, publicada como passou, e deploy manual         | M3           |
| [0012](0012-hospedagem-gratis-oracle-e-duckdns.md)       | Hospedagem grátis: Oracle Always Free e DuckDNS                        | M3           |
| [0013](0013-transferencia-como-tipo-de-categoria.md)     | Transferência entre contas como tipo de categoria                      | M4           |
| [0014](0014-um-servico-para-todo-numero-de-relatorio.md) | Um serviço só para todo número de relatório                            | M4 · M5 · M6 |
| [0015](0015-sdk-oficial-da-anthropic.md)                 | SDK oficial da Anthropic, com o modelo no `.env`                       | M5           |
| [0016](0016-a-ia-nao-faz-conta.md)                       | A IA não faz conta, e o app confere                                    | M5 · M6      |
| [0017](0017-o-assistente-so-le.md)                       | O assistente só lê                                                     | M5           |
| [0018](0018-historico-so-em-texto.md)                    | Histórico da conversa só em texto, e a data dentro da pergunta         | M5           |
| [0019](0019-ia-testada-sem-chamar-a-api.md)              | A IA testada sem chamar a API                                          | M5 · M6      |
| [0020](0020-analise-do-mes.md)                           | Análise do mês: uma chamada, uma correção e o aviso de desatualizada   | M6           |

## Quando escrever uma decisão nova

Quando houver uma escolha real entre alternativas, e alguém lendo o código daqui a um ano perguntaria "por que assim?". Uma decisão que mudou não é apagada: ganha um texto novo dizendo o que mudou e por quê, e a numeração continua sem buraco.
