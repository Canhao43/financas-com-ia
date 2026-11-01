# 0011 — Imagem testada pelo CI, publicada como passou, e deploy manual

**Marco:** M3

## Contexto

O app vai rodar num servidor pequeno, cuidado por uma pessoa, com dado financeiro dentro. O que chega no servidor precisa ser exatamente o que foi testado, e quem tomar a conta do GitHub não pode tomar o servidor junto.

## Decisão

- **Uma imagem Docker com FrankenPHP** (Caddy e PHP num binário, HTTPS automático pelo Let's Encrypt), no modo clássico, sem o modo worker.
- **O processo roda sem root, e o código é do root**: o PHP lê o próprio código, mas não o reescreve. Só o `storage`, o cache e o volume do banco aceitam escrita.
- **Toda imagem e binário de terceiros fixado por hash**, com o Dependabot propondo as atualizações em PR.
- **O CI sobe a imagem como em produção** e testa de fora: HTTPS, cabeçalhos de segurança, migration, backup e restauração. Só então **publica no GHCR exatamente a imagem que passou**. A imagem é pública: o código é aberto e ela não tem segredo.
- **Deploy manual** (`deploy/atualizar.sh`, rodado no servidor), que faz **backup antes** de trocar a versão.
- Logs no stderr, com o driver `local` do Docker, que gira os arquivos.

## Alternativas descartadas

- **nginx + PHP-FPM.** Duas peças e o certificado à parte.
- **Modo worker do FrankenPHP.** Mantém o app na memória entre requisições (estado pode vazar de uma para outra), e o ganho não importa para um usuário.
- **Tags flutuantes (`latest`).** O build de hoje e o de daqui a um mês sairiam de bases diferentes.
- **Montar a imagem de novo para publicar.** Publicaria uma imagem que não foi a testada.
- **Deploy automático pelo CI, por SSH.** A chave do servidor ficaria no GitHub: uma conta tomada ou uma action comprometida tomaria o servidor com o dado financeiro.
- **Imagem privada.** O servidor precisaria guardar um token só para baixar.

## Consequências

- Atualizar é um comando no servidor; voltar uma versão é subir a imagem anterior pelo hash e restaurar o backup feito antes da atualização.
- Correção de segurança da imagem base chega como PR do Dependabot, passa pelo mesmo CI e vai para o servidor no próximo `atualizar.sh`.

## Como é verificado

- `imagem.yml`: _Sobe como em produção_
- `imagem.yml`: _O site responde por HTTPS, com os cabeçalhos de segurança_ — inclui o redirecionamento do HTTP, o PHP sem anunciar a versão e o cadastro respondendo 404
- `imagem.yml`: _Restaura com os comandos do docs/deploy.md_
- `imagem.yml`: _Publica a imagem que passou no teste_
