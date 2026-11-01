# Finanças com IA

Controle financeiro pessoal com um assistente de IA: importa o extrato do banco, organiza os lançamentos por categoria e responde perguntas sobre os próprios gastos — _"quanto gastei com mercado em outubro?"_ — com o Claude consultando os dados por ferramentas, sem nunca fazer conta sozinho.

Não é demonstração: é o app que eu uso para as minhas finanças. Por isso o repositório só tem dado fictício, não existe cadastro público e a segurança vem antes das funcionalidades.

> Construído com agentes de IA (Claude Code) e revisado por mim em cada PR. As regras que os agentes seguem estão em [`CLAUDE.md`](CLAUDE.md); cada decisão, com as alternativas descartadas e o teste que a prova, está no [registro de decisões](docs/decisoes/README.md).

## Roteiro

| Marco  | O que entra                                                                                                      | Estado |
| ------ | ---------------------------------------------------------------------------------------------------------------- | ------ |
| **M0** | Base segura: login sem cadastro público, autenticação em dois fatores e passkeys, usuário criado por comando, CI | ✅     |
| **M1** | Contas, categorias e lançamentos — telas em português, que funcionam no celular                                  | ✅     |
| **M2** | Importação de extrato OFX sem duplicar lançamento, e categorização por regras                                    | ✅     |
| **M3** | Deploy: servidor, HTTPS e backup — imagem e roteiro prontos; a publicação fica para depois                       | 🟡     |
| **M4** | Relatórios calculados no código: o mês por categoria e a comparação com o anterior                               | ✅     |
| **M5** | Assistente com o Claude: perguntas respondidas por ferramentas que consultam o banco                             | ✅     |
| **M6** | Relatório mensal escrito pelo Claude e conferido contra os números calculados                                    | ✅     |
| **M7** | Registro de decisões: 20 ADRs, cada um apontando o teste que o prova                                             | ✅     |

## Como rodar

Precisa de PHP 8.4, Composer e Node.

```bash
git clone https://github.com/Canhao43/financas-com-ia.git
cd financas-com-ia
composer setup            # dependências, .env, chave, migrations e build do front
php artisan db:seed       # categorias de partida; rodar de novo só cria as que faltam
php artisan usuario:criar # o único usuário do app — não existe cadastro público
composer dev              # servidor, fila e Vite
```

O app fica em `http://localhost:8000`. No primeiro acesso, ative a autenticação em dois fatores em _Configurações → Segurança_.

O assistente e a análise do mês usam a API da Anthropic: coloque a sua chave em `ANTHROPIC_API_KEY` no `.env`. Sem ela, os dois ficam desligados e o resto do app funciona igual. O modelo padrão é o Claude Opus 5.5; para outro, `ANTHROPIC_MODEL`.

Verificações (as mesmas do CI): `composer ci:check` — lint, checagem de tipos do front, PHPStan e testes.

## Produção

Uma imagem Docker com FrankenPHP (HTTPS automático pelo Caddy) e SQLite, publicada no GHCR a cada merge — depois de o CI subi-la como em produção e testar de fora, inclusive fazer e restaurar um backup. Roda de graça: uma VM do Always Free da Oracle Cloud e um subdomínio do DuckDNS. Passo a passo em [`docs/deploy.md`](docs/deploy.md).

## Princípios

- **Dado real nunca entra no repositório.** Seeds e testes usam dado fictício; extrato e banco ficam fora do Git.
- **Um usuário só, criado no servidor.** Sem formulário de cadastro que qualquer um na internet alcance.
- **A IA não faz conta, e o app confere.** O Claude só consulta: as ferramentas devolvem os valores já calculados e formatados, e todo valor em reais ou porcentagem da resposta é comparado com o que as consultas devolveram. O que não saiu de nenhuma aparece marcado como "sem fonte". Na análise do mês, o Claude recebe a lista exata do que não bateu e reescreve uma vez antes de o aviso aparecer, e a análise avisa quando os números do mês mudam depois dela.
- **O assistente só lê.** Nenhuma ferramenta grava, apaga ou envia nada: um texto malicioso num extrato importado pode, no máximo, confundir uma resposta.
- **Um cálculo só para cada número.** O relatório, o painel e o assistente leem os totais do mesmo serviço, então nunca discordam. Transferência entre contas, como o pagamento da fatura, fica fora de receitas e despesas: a compra no cartão já é o gasto.
- **Importar o mesmo extrato de novo não duplica nada.** Cada linha é reconhecida pelo identificador do banco ou por uma impressão digital dela, nada é gravado antes de uma prévia, e o app confere o próprio saldo com o que o banco informa.
- **O mínimo de dado sai para a API de IA:** só o necessário para responder. A análise do mês leva só os totais por categoria; nenhum lançamento, descrição ou nome de conta.
- **Backup que ninguém confere não é backup.** Todo dia ele sai do servidor cifrado com uma chave que o servidor não tem, o painel avisa quando atrasa, e o CI restaura um a cada build.

## Decisões

O detalhe (contexto, alternativas descartadas, consequências e o teste que prova) está no [registro de decisões](docs/decisoes/README.md). Em uma linha cada:

- [**Um usuário só, criado por comando**](docs/decisoes/0001-um-usuario-so-criado-por-comando.md): sem cadastro público, com dois fatores e passkeys.
- [**Dinheiro e porcentagem só em inteiros**](docs/decisoes/0002-dinheiro-e-porcentagem-so-em-inteiros.md): centavos com sinal e pontos-base; nenhum centavo passa por `float`.
- [**O histórico não se apaga**](docs/decisoes/0003-o-historico-nao-se-apaga.md): conta é arquivada, e categoria com lançamentos não troca de tipo.
- [**Fuso de São Paulo e mês semiaberto**](docs/decisoes/0004-fuso-de-sao-paulo-e-mes-semiaberto.md): o dia 31 não some, e às 22h ainda é hoje.
- [**Leitor de OFX próprio**](docs/decisoes/0005-leitor-de-ofx-proprio.md): a biblioteca mais usada lia `-150` como R$ 1,50.
- [**Duplicado pelo FITID ou pela impressão**](docs/decisoes/0006-duplicado-pelo-fitid-ou-pela-impressao.md): reimportar não duplica, e dois cafés iguais no mesmo dia continuam dois.
- [**Prévia, vínculo e desfazer**](docs/decisoes/0007-previa-vinculo-e-desfazer.md): nada é gravado antes de conferir, e o lançamento feito à mão não duplica.
- [**Regras de categoria**](docs/decisoes/0008-regras-de-categoria.md): "contém", a mais longa vence, e nunca por cima da escolha da pessoa.
- [**SQLite em produção**](docs/decisoes/0009-sqlite-em-producao.md): um usuário, o mesmo banco dos testes, e o backup é um arquivo.
- [**Backup cifrado fora do servidor**](docs/decisoes/0010-backup-cifrado-fora-do-servidor.md): o servidor cifra mas não consegue abrir, e o painel avisa quando atrasa.
- [**Imagem testada pelo CI, deploy manual**](docs/decisoes/0011-imagem-testada-pelo-ci-e-deploy-manual.md): vai para o servidor exatamente a imagem que passou, e a chave do servidor não fica no GitHub.
- [**Hospedagem grátis**](docs/decisoes/0012-hospedagem-gratis-oracle-e-duckdns.md): Oracle Always Free e DuckDNS, com o backup cobrindo o risco de a VM ser recuperada.
- [**Transferência como tipo de categoria**](docs/decisoes/0013-transferencia-como-tipo-de-categoria.md): a fatura do cartão não conta duas vezes.
- [**Um serviço só para todo número**](docs/decisoes/0014-um-servico-para-todo-numero-de-relatorio.md): a tela, o painel e a IA nunca discordam.
- [**SDK oficial da Anthropic**](docs/decisoes/0015-sdk-oficial-da-anthropic.md): os recursos da API primeiro; o modelo é uma linha do `.env`.
- [**A IA não faz conta**](docs/decisoes/0016-a-ia-nao-faz-conta.md): números prontos, e cada valor da resposta conferido.
- [**O assistente só lê**](docs/decisoes/0017-o-assistente-so-le.md): um texto malicioso no extrato não apaga nem envia nada.
- [**Histórico só em texto**](docs/decisoes/0018-historico-so-em-texto.md): o raciocínio assinado do modelo não quebra a conversa.
- [**IA testada sem chamar a API**](docs/decisoes/0019-ia-testada-sem-chamar-a-api.md): o SDK de verdade, com o transporte HTTP trocado.
- [**Análise do mês**](docs/decisoes/0020-analise-do-mes.md): uma chamada, uma correção com a lista exata, e o aviso quando os números mudam.

## Stack

Laravel 13 · PHP 8.4 · Inertia 3 · Vue 3 · TypeScript · Tailwind 4 · Fortify (2FA e passkeys) · SDK oficial da Anthropic para PHP · PHPUnit 12 · PHPStan · Docker · FrankenPHP · GitHub Actions

## Licença

[MIT](LICENSE).
