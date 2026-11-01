# 0012 — Hospedagem grátis: Oracle Always Free e DuckDNS

**Marco:** M3 · escolhida em 01/11/2026; a publicação ainda não aconteceu

## Contexto

O app precisa de um servidor Linux x86-64 com 1 GB de RAM ou mais, e de um domínio para o HTTPS. A preferência é custo zero por enquanto. Pesquisa de 01/11/2026 (preços e limites mudam):

- O plano barato da Hetzner aparecia indisponível.
- A VM grátis do Google Cloud (e2-micro) cobra o IPv4 desde 2024.
- Render e serviços parecidos dormem quando ociosos e não têm disco persistente: o SQLite sumiria a cada deploy.

## Decisão

- **VM AMD do Always Free da Oracle Cloud**, na região de São Paulo (1 GB de RAM, IPv4 público incluso), com Ubuntu 24.04.
- **Subdomínio grátis do DuckDNS**, atualizado por um cron no servidor.
- Portas 80 e 443 abertas na lista de segurança da rede da Oracle **e** nas regras de `iptables` que a imagem da Oracle já traz. Swap de 2 GB.
- A conta fica no plano grátis: sem upgrade para o pagamento por uso.
- Passo a passo em `docs/deploy.md`.

## Alternativas descartadas

- **VPS paga** (Hetzner, DigitalOcean, Vultr, Magalu Cloud). Ficam para quando o grátis não servir.
- **Oracle Ampere (ARM).** A imagem do app é só x86; exigiria build para duas arquiteturas.
- **Domínio `.com.br` próprio** (cerca de R$ 40 por ano). Tira a dependência do DuckDNS; adiado para manter o custo zero.

## Consequências

- **A Oracle pode recuperar instância ociosa**, e um app de uma pessoa fica ocioso quase sempre. O backup diário fora do servidor (ver [0010](0010-backup-cifrado-fora-do-servidor.md)) faz desse caso um "site fora do ar", não um "dado perdido": recria a VM, sobe a imagem e restaura o backup da madrugada.
- O DuckDNS é mantido por voluntários: se cair, o site some e o certificado não renova.
- 1 GB de RAM é justo; o swap cobre os picos.

## Como é verificado

Ainda não foi: o roteiro **não rodou num servidor de verdade**. A parte da Oracle (rede, firewall, DuckDNS) é a que mais pode precisar de ajuste. O que não depende do provedor roda no CI a cada build:

- `imagem.yml`: _Restaura com os comandos do docs/deploy.md_
- `imagem.yml`: _Troca a senha pelo tinker, como no docs/deploy.md_
