# Deploy

Como o app roda em produção, e como subir, fazer backup, restaurar e atualizar. Servidor e domínio são gratuitos: uma VM do Always Free da Oracle Cloud e um subdomínio do DuckDNS.

## O que roda

```
         nome.duckdns.org (80 e 443)
                     │
┌─ VM Oracle Always Free (São Paulo) ───────────────────────┐
│  app         FrankenPHP: Caddy (HTTPS automático) + PHP   │
│  agendador   php artisan schedule:work                    │
│                └─ todo dia às 03:00: backup:criar         │
│                                                           │
│  volume "dados": financas.sqlite · backups/ · rclone.conf │
└────────────────────┼──────────────────────────────────────┘
                     │  cifrado com age, enviado pelo rclone
                     ▼
              Google Drive (pasta financas-backups)
```

Os dois serviços usam a mesma imagem, `ghcr.io/canhao43/financas-com-ia`. O CI a publica a cada merge no `main`, depois de subi-la como em produção e testar de fora: HTTPS, cabeçalhos de segurança, migration, um backup de verdade aberto com a chave privada e a restauração desse backup ([`imagem.yml`](../.github/workflows/imagem.yml)).

**O risco de ser grátis:** a Oracle pode recuperar uma VM do Always Free que fique ociosa por 7 dias (CPU e rede abaixo de 20%), e um app de uma pessoa fica assim quase sempre. Por isso o backup sai do servidor todo dia: se a VM sumir, o pior caso é o site fora do ar até recriar a VM e restaurar o backup da madrugada (passos 1 a 4), não dado perdido.

## Antes de começar

- **Conta na Oracle Cloud**, no plano Always Free, com **São Paulo** como região inicial. Ela não muda depois, e a VM grátis só pode ser criada nela. **Não faça upgrade da conta** para Pay As You Go: na conta grátis, a Oracle não tem como cobrar nada.
- **Subdomínio no DuckDNS** ([duckdns.org](https://www.duckdns.org), entra com a conta do Google): crie `seu-nome.duckdns.org` e guarde o token que aparece no topo da página.
- **No seu PC:** uma chave SSH só para este servidor, e o `age` e o `rclone` para o backup.

```bash
ssh-keygen -t ed25519 -f ~/.ssh/financas -C financas
winget install FiloSottile.age Rclone.Rclone
```

## 1. Criar a VM

No console da Oracle, em _Compute → Instances → Create instance_:

| Campo        | Valor                                                                                              |
| ------------ | -------------------------------------------------------------------------------------------------- |
| Image        | **Canonical Ubuntu 24.04**: a normal, não a Minimal                                                |
| Shape        | **VM.Standard.E2.1.Micro** (AMD, "Always Free-eligible"). Não use Ampere: a imagem do app é só x86 |
| Networking   | Criar uma VCN nova, com sub-rede pública, e marcar _Assign a public IPv4 address_                  |
| Add SSH keys | Colar o conteúdo de `~/.ssh/financas.pub`                                                          |
| Boot volume  | O padrão (50 GB, dentro dos 200 GB grátis)                                                         |

**Abrir as portas na rede da Oracle**, que é o primeiro firewall: _Networking → Virtual cloud networks →_ a VCN criada _→ Security Lists → Default Security List → Add Ingress Rules_. Uma regra para cada, todas com origem `0.0.0.0/0`:

- TCP, porta de destino `80` (o Let's Encrypt valida por ela, e o HTTP vira HTTPS)
- TCP, porta de destino `443`
- UDP, porta de destino `443` (HTTP/3)

## 2. Preparar o servidor

```bash
ssh -i ~/.ssh/financas ubuntu@IP-DA-VM
```

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y unattended-upgrades   # atualizações de segurança automáticas

# SSH só com chave (a imagem já vem assim; isto garante).
echo 'PasswordAuthentication no' | sudo tee /etc/ssh/sshd_config.d/00-so-chave.conf
sudo systemctl reload ssh

# Memória de troca: 1 GB de RAM é justo para o Docker, o site e o agendador juntos.
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# O segundo firewall, o da VM. A imagem da Oracle recusa tudo que não é SSH com regras de
# iptables que ela mesma precisa: não troque por UFW. As de 80 e 443 entram antes da recusa.
sudo sed -i '/^-A INPUT -j REJECT/i -A INPUT -p tcp -m state --state NEW -m multiport --dports 80,443 -j ACCEPT\n-A INPUT -p udp -m udp --dport 443 -j ACCEPT' /etc/iptables/rules.v4
sudo netfilter-persistent reload

# Docker, pelo repositório oficial. Depois das regras de firewall: o Docker põe as dele por
# cima, e recarregar o iptables com ele rodando as apagaria.
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list
sudo apt update && sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo usermod -aG docker "$USER"   # saia e entre de novo para valer
```

**O DuckDNS apontando para a VM**, e se mantendo assim: se a VM for recriada, o IP muda, e o cron corrige o subdomínio em até 5 minutos.

```bash
install -m 600 /dev/null ~/.duckdns && nano ~/.duckdns
# cole uma linha só: https://www.duckdns.org/update?domains=SEU-NOME&token=SEU-TOKEN&ip=
curl -fsS "$(cat ~/.duckdns)"; echo   # tem que responder OK
(crontab -l 2>/dev/null; echo '*/5 * * * * curl -fsS "$(cat $HOME/.duckdns)" > /dev/null') | crontab -
```

O token do DuckDNS fica nesse arquivo, legível só pelo seu usuário, e não no histórico do terminal.

## 3. Subir o app

```bash
sudo mkdir -p /opt/financas && sudo chown "$USER" /opt/financas && cd /opt/financas
base=https://raw.githubusercontent.com/Canhao43/financas-com-ia/main/deploy
curl -fsSLO "$base/compose.yaml"
curl -fsSLO "$base/atualizar.sh" && chmod +x atualizar.sh
curl -fsSL -o .env "$base/.env.producao.example" && chmod 600 .env

docker run --rm ghcr.io/canhao43/financas-com-ia php artisan key:generate --show
nano .env   # cole a chave em APP_KEY e troque seu-nome em SERVER_NAME e APP_URL

docker compose up -d --wait
docker compose exec app php artisan db:seed --force   # categorias de partida
docker compose exec app php artisan usuario:criar
```

Abra `https://seu-nome.duckdns.org`, entre e ative a autenticação em dois fatores em _Configurações → Segurança_; os códigos de recuperação vão para o mesmo lugar da chave do backup. O painel vai avisar que nenhum backup deu certo ainda: é o passo seguinte.

## 4. Backup

**A chave.** No seu PC, nunca no servidor:

```bash
age-keygen -o financas-backup.key
```

O comando mostra a chave pública (`age1...`), que vai em `BACKUP_CHAVE_PUBLICA` no `.env` do servidor. O arquivo `financas-backup.key` é a chave privada: guarde no gerenciador de senhas e numa cópia fora do PC. **Sem ele, nenhum backup abre.** E é por ele não estar no servidor que quem invadir o servidor também não abre os backups.

**O destino.** No servidor:

```bash
docker compose exec agendador rclone config
```

Crie um remoto novo (`n`) chamado `gdrive`, do tipo `drive`. Deixe client_id e client_secret vazios e escolha o **scope `drive.file`**: com ele, o servidor só enxerga os arquivos que ele mesmo criou, e o resto do seu Drive fica fora de alcance. Recuse a configuração avançada e, quando perguntar se pode abrir o navegador, responda `n`. Ele mostra um comando `rclone authorize "drive" "..."`: rode esse comando no seu PC, autorize no navegador e cole a resposta de volta no servidor.

**O teste.** Com `BACKUP_CHAVE_PUBLICA` e `BACKUP_DESTINO=gdrive:financas-backups` no `.env`:

```bash
docker compose up -d --wait   # recria os containers com o .env novo
docker compose exec agendador php artisan backup:criar
```

A resposta é `Backup financas-AAAA-MM-DD-HHMMSS.sqlite.age enviado (N KB).`, e o arquivo aparece na pasta `financas-backups` do Drive. A partir daí ele roda sozinho todo dia às 3h, e o painel avisa se o último que deu certo passar de 36 horas.

## 5. Restaurar

Backup que nunca foi restaurado é só uma suposição. Faça o ensaio logo depois do passo 4, e de novo a cada alguns meses.

**Abrir um backup**, no seu PC, com o `.age` baixado do Drive:

```bash
age -d -i financas-backup.key -o financas.sqlite financas-AAAA-MM-DD-HHMMSS.sqlite.age
```

O `financas.sqlite` abre em qualquer leitor de SQLite (o DB Browser for SQLite, por exemplo).

**Pôr de volta no servidor**, com o `financas.sqlite` já copiado para `/opt/financas` (`scp -i ~/.ssh/financas financas.sqlite ubuntu@IP-DA-VM:/opt/financas/`):

```bash
docker compose stop
docker compose run --rm --no-deps -T agendador \
  sh -c 'rm -f /dados/financas.sqlite-wal /dados/financas.sqlite-shm && cat > /dados/financas.sqlite' < financas.sqlite
docker compose up -d --wait
rm financas.sqlite
```

Os dois cuidados do segundo comando: apagar o `-wal` e o `-shm` do banco antigo, que o SQLite tentaria aplicar por cima do restaurado, e gravar pelo container, como o usuário do app (com `docker cp`, o arquivo ficaria do root e o app não conseguiria escrever nele).

## 6. Atualizar

Depois de cada merge no `main`, o CI publica a imagem nova. No servidor:

```bash
/opt/financas/atualizar.sh
```

O script faz um backup antes de tudo (se o backup falhar, não atualiza), baixa a imagem, recria os containers e espera o site responder. As migrations rodam na subida.

**Voltar para uma versão anterior:** cada imagem também é publicada com o hash do commit (`sha-1a2b3c4`, na aba _Packages_ do GitHub).

```bash
VERSAO=sha-1a2b3c4 docker compose up -d --wait
```

Se a versão nova estragou dados com uma migration, restaure também o backup que o `atualizar.sh` fez antes dela (passo 5).

## Quando algo dá errado

| Sintoma                                    | Onde olhar                                                                                                                                                                                                                      |
| ------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Qualquer erro                              | `docker compose logs -f app` ou `agendador`                                                                                                                                                                                     |
| O certificado não sai                      | O DuckDNS ainda não aponta para a VM (`curl "$(cat ~/.duckdns)"` tem que dar `OK`), ou falta a porta 80 na lista de segurança da Oracle ou no `iptables` da VM (passos 1 e 2)                                                   |
| O site parou depois de mexer no `iptables` | Recarregar o firewall apaga as regras que o Docker criou. `sudo systemctl restart docker` as recria                                                                                                                             |
| A Oracle parou ou apagou a VM              | Parada: religar no console. Apagada: refazer os passos 1 a 4 e restaurar o backup mais recente do Drive (passo 5). O cron do DuckDNS aponta o nome para o IP novo                                                               |
| O painel avisa de backup atrasado          | O aviso traz a última falha. Para ver de novo: `docker compose exec agendador php artisan backup:criar`                                                                                                                         |
| Esqueceu a senha                           | Não há servidor de e-mail configurado, então o "Esqueci a senha" não envia nada. Pelo servidor: `docker compose exec app php artisan tinker` e, dentro dele, `App\Models\User::first()->update(['password' => 'a senha nova'])` |
| Perdeu o celular do 2FA                    | Os códigos de recuperação mostrados quando o 2FA foi ativado                                                                                                                                                                    |
