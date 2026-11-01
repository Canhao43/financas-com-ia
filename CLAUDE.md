# Como agentes trabalham neste repositório

Este projeto é construído com agentes de IA (Claude Code) e revisado por mim, Caio. As regras abaixo valem para qualquer agente que mexa aqui.

## O critério

**Eu preciso saber explicar cada decisão.** Código que funciona, mas que eu não consigo defender numa conversa técnica, não entra.

## Este app guarda dado financeiro real

- **Nenhum dado real no repositório.** Seed, teste e exemplo usam dado fictício. Extrato, OFX e banco de dados ficam fora do Git.
- **Não existe cadastro público.** O usuário é criado por `php artisan usuario:criar`; qualquer rota nova exige login.
- **Segredo só no `.env`** (chave da API da Anthropic, senha de banco). Nunca em código, teste, log ou commit.
- **A IA nunca faz conta.** Total, soma e comparação são calculados no código e testados; o modelo só recebe os números prontos.
- **O mínimo de dado sai para a API de IA:** só o necessário para responder a pergunta.

## Fluxo

- Um marco do roteiro = uma branch = um PR. Nada vai direto para o `main`.
- Commits pequenos e lógicos, em português, com mensagem curta e direta.
- Descrição de PR resumida: o que entra, em poucos tópicos.
- Commits e PRs sem trailer ou rodapé automático (`Co-Authored-By`, link de sessão, "Generated with").
- Quem mergeia sou eu, depois de ler.

## Código

- Domínio em português (lançamento, conta, categoria); o framework continua em inglês.
- Teste junto com a funcionalidade. `composer ci:check` precisa passar (lint, tipos, PHPStan, testes).
- Toda tela funciona no celular: o app é usado pelo navegador do telefone.
- Comentário explica o porquê, não o quê.
