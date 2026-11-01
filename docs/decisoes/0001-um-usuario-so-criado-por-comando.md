# 0001 — Um usuário só, criado por comando

**Marco:** M0

## Contexto

O app guarda o dado financeiro real de uma pessoa e vai ficar na internet. O kit oficial do Laravel vem com cadastro público, verificação de e-mail, exclusão de conta e um seed que cria `test@example.com` com a senha `password`. Num app de um usuário, o cadastro é uma porta aberta para qualquer um, o seed abriria uma conta com senha conhecida se rodasse em produção, e excluir a conta apagaria todo o histórico sem volta.

## Decisão

- Sem cadastro público: o cadastro e a verificação de e-mail saíram pela ferramenta do próprio kit, que apaga tela, rota, ação e testes juntos.
- O usuário é criado no servidor por `php artisan usuario:criar`, já verificado, com senha de pelo menos 12 caracteres, com letras e números. O comando recusa criar um segundo.
- Ficam a autenticação em dois fatores, as passkeys, a confirmação de senha e a recuperação de senha.
- A conta não se exclui pela interface.
- O seed padrão não cria usuário.
- Toda página exige login.

## Alternativas descartadas

- **Manter o cadastro e só esconder o link.** A rota continuaria respondendo.
- **Usuário criado pelo seed.** Um `db:seed` em produção abriria uma conta com senha conhecida.
- **Cadastro com lista de e-mails permitidos.** Mais código para proteger uma tela que não precisa existir.

## Consequências

- Enquanto não houver e-mail configurado, a senha esquecida é trocada pelo `tinker` no servidor (roteiro em `docs/deploy.md`).
- Um segundo usuário exigiria rever a decisão: nenhuma tabela do domínio é separada por usuário.

## Como é verificado

- `UsuarioUnicoTest::test_nao_existe_cadastro_publico`
- `UsuarioUnicoTest::test_comando_cria_o_usuario_ja_verificado`
- `UsuarioUnicoTest::test_comando_recusa_criar_um_segundo_usuario`
- `SeedersTest::test_seed_padrao_nao_cria_usuario`
- `SeedersTest::test_demonstracao_nao_roda_em_producao`
- `ProfileUpdateTest::test_a_conta_nao_pode_ser_excluida_pela_interface`
- `AcessoTest::test_toda_pagina_exige_login`
- `imagem.yml`: _Troca a senha pelo tinker, como no docs/deploy.md_ — o caminho da senha esquecida funciona na imagem de produção
