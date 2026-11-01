# Finanças com IA

Controle financeiro pessoal com um assistente de IA: importa o extrato do banco, organiza os lançamentos por categoria e responde perguntas sobre os próprios gastos — _"quanto gastei com mercado em outubro?"_ — com o Claude consultando os dados por ferramentas, sem nunca fazer conta sozinho.

Não é demonstração: é o app que eu uso para as minhas finanças. Por isso o repositório só tem dado fictício, não existe cadastro público e a segurança vem antes das funcionalidades.

> Construído com agentes de IA (Claude Code) e revisado por mim em cada PR. As regras que os agentes seguem estão em [`CLAUDE.md`](CLAUDE.md).

## Roteiro

| Marco  | O que entra                                                                                                      | Estado |
| ------ | ---------------------------------------------------------------------------------------------------------------- | ------ |
| **M0** | Base segura: login sem cadastro público, autenticação em dois fatores e passkeys, usuário criado por comando, CI | ✅     |
| **M1** | Contas, categorias e lançamentos — telas em português, que funcionam no celular                                  | ✅     |
| **M2** | Importação de extrato OFX sem duplicar lançamento, e categorização por regras                                    | 🟡     |
| **M3** | Deploy: servidor, HTTPS e backup — a partir daqui, uso de verdade                                                | ⏳     |
| **M4** | Relatórios calculados no código: o mês por categoria e a comparação com o anterior                               | ⏳     |
| **M5** | Assistente com o Claude: perguntas respondidas por ferramentas que consultam o banco                             | ⏳     |
| **M6** | Relatório mensal escrito pelo Claude e conferido contra os números calculados                                    | ⏳     |
| **M7** | Registro de decisões                                                                                             | ⏳     |

## Como rodar

Precisa de PHP 8.4, Composer e Node.

```bash
git clone https://github.com/Canhao43/financas-com-ia.git
cd financas-com-ia
composer setup            # dependências, .env, chave, migrations e build do front
php artisan usuario:criar # o único usuário do app — não existe cadastro público
composer dev              # servidor, fila e Vite
```

O app fica em `http://localhost:8000`. No primeiro acesso, ative a autenticação em dois fatores em _Configurações → Segurança_.

Verificações (as mesmas do CI): `composer ci:check` — lint, checagem de tipos do front, PHPStan e testes.

## Princípios

- **Dado real nunca entra no repositório.** Seeds e testes usam dado fictício; extrato e banco ficam fora do Git.
- **Um usuário só, criado no servidor.** Sem formulário de cadastro que qualquer um na internet alcance.
- **A IA não faz conta.** Somas e comparações são calculadas no código e testadas; o modelo recebe os números prontos.
- **Importar o mesmo extrato de novo não duplica nada.** Cada linha é reconhecida pelo identificador do banco ou por uma impressão digital dela, nada é gravado antes de uma prévia, e o app confere o próprio saldo com o que o banco informa.
- **O mínimo de dado sai para a API de IA:** só o necessário para responder.

## Stack

Laravel 13 · PHP 8.4 · Inertia 3 · Vue 3 · TypeScript · Tailwind 4 · Fortify (2FA e passkeys) · PHPUnit 12 · PHPStan · GitHub Actions

## Licença

[MIT](LICENSE).
