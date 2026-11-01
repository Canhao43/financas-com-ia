# Finanças com IA

Controle financeiro pessoal com um assistente de IA: importa o extrato do banco, organiza os lançamentos por categoria e responde perguntas sobre os próprios gastos — _"quanto gastei com mercado em outubro?"_ — com o Claude consultando os dados por ferramentas, sem nunca fazer conta sozinho.

Não é demonstração: é o app que eu uso para as minhas finanças. Por isso o repositório só tem dado fictício, não existe cadastro público e a segurança vem antes das funcionalidades.

> Construído com agentes de IA (Claude Code) e revisado por mim em cada PR. As regras que os agentes seguem estão em [`CLAUDE.md`](CLAUDE.md).

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
| **M7** | Registro de decisões                                                                                             | ⏳     |

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

## Stack

Laravel 13 · PHP 8.4 · Inertia 3 · Vue 3 · TypeScript · Tailwind 4 · Fortify (2FA e passkeys) · SDK oficial da Anthropic para PHP · PHPUnit 12 · PHPStan · Docker · FrankenPHP · GitHub Actions

## Licença

[MIT](LICENSE).
