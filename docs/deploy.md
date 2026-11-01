# Deploy

Como o app roda em produção, e como subir, fazer backup, restaurar e atualizar.

## O que roda

```
              internet (80 e 443)
                     │
┌─ servidor ─────────┼──────────────────────────────────────┐
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

## Antes de começar

- **Servidor** Linux x86-64 com 1 GB de RAM, acesso SSH por chave. Os exemplos usam Ubuntu 24.04.
- **Domínio** apontando para o IP do servidor (registro A). O certificado só sai quando o DNS já responde com o IP certo.
- **No seu PC:** `age` e `rclone` (`winget install FiloSottile.age Rclone.Rclone`).

## 1. Preparar o servidor

```bash
# Atualizações de segurança automáticas
sudo apt update && sudo apt upgrade -y
sudo apt install -y unattended-upgrades

# SSH só com chave. O 00- vem antes do 50-cloud-init.conf, que costuma liberar senha.
echo 'PasswordAuthentication no' | sudo tee /etc/ssh/sshd_config.d/00-so-chave.conf
sudo systemctl reload ssh

# Firewall: SSH, HTTP e HTTPS (443/udp é o HTTP/3)
sudo ufw allow OpenSSH
sudo ufw allow 80,443/tcp
sudo ufw allow 443/udp
sudo ufw enable

# Docker, pelo repositório oficial
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list
sudo apt update && sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo usermod -aG docker "$USER"   # saia e entre de novo para valer
```

> **Docker e UFW:** as portas que o Docker publica passam por fora do UFW. Aqui só 80 e 443 são publicadas, e elas já estão abertas. Nunca publique outra porta contando com o UFW para bloqueá-la.

## 2. Subir o app

```bash
sudo mkdir -p /opt/financas && sudo chown "$USER" /opt/financas && cd /opt/financas
base=https://raw.githubusercontent.com/Canhao43/financas-com-ia/main/deploy
curl -fsSLO "$base/compose.yaml"
curl -fsSLO "$base/atualizar.sh" && chmod +x atualizar.sh
curl -fsSL -o .env "$base/.env.producao.example" && chmod 600 .env

docker run --rm ghcr.io/canhao43/financas-com-ia php artisan key:generate --show
nano .env   # cole a chave em APP_KEY e preencha SERVER_NAME e APP_URL

docker compose up -d --wait
docker compose exec app php artisan usuario:criar
```

Abra o domínio, entre e ative a autenticação em dois fatores em _Configurações → Segurança_. O painel vai avisar que nenhum backup deu certo ainda: é o passo seguinte.

## 3. Backup

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

## 4. Restaurar

Backup que nunca foi restaurado é só uma suposição. Faça o ensaio logo depois do passo 3, e de novo a cada alguns meses.

**Abrir um backup**, no seu PC, com o `.age` baixado do Drive:

```bash
age -d -i financas-backup.key -o financas.sqlite financas-AAAA-MM-DD-HHMMSS.sqlite.age
```

O `financas.sqlite` abre em qualquer leitor de SQLite (o DB Browser for SQLite, por exemplo).

**Pôr de volta no servidor**, com o `financas.sqlite` já copiado para `/opt/financas` (`scp`):

```bash
docker compose stop
docker compose run --rm --no-deps -T agendador \
  sh -c 'rm -f /dados/financas.sqlite-wal /dados/financas.sqlite-shm && cat > /dados/financas.sqlite' < financas.sqlite
docker compose up -d --wait
rm financas.sqlite
```

Os dois cuidados do segundo comando: apagar o `-wal` e o `-shm` do banco antigo, que o SQLite tentaria aplicar por cima do restaurado, e gravar pelo container, como o usuário do app (com `docker cp`, o arquivo ficaria do root e o app não conseguiria escrever nele).

## 5. Atualizar

Depois de cada merge no `main`, o CI publica a imagem nova. No servidor:

```bash
/opt/financas/atualizar.sh
```

O script faz um backup antes de tudo (se o backup falhar, não atualiza), baixa a imagem, recria os containers e espera o site responder. As migrations rodam na subida.

**Voltar para uma versão anterior:** cada imagem também é publicada com o hash do commit (`sha-1a2b3c4`, na aba _Packages_ do GitHub).

```bash
VERSAO=sha-1a2b3c4 docker compose up -d --wait
```

Se a versão nova estragou dados com uma migration, restaure também o backup que o `atualizar.sh` fez antes dela (passo 4).

## Quando algo dá errado

| Sintoma                           | Onde olhar                                                                                                                                                                                                                      |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Qualquer erro                     | `docker compose logs -f app` ou `agendador`                                                                                                                                                                                     |
| O certificado não sai             | O DNS ainda não aponta para o servidor, ou a porta 80 está fechada no firewall do provedor                                                                                                                                      |
| O painel avisa de backup atrasado | O aviso traz a última falha. Para ver de novo: `docker compose exec agendador php artisan backup:criar`                                                                                                                         |
| Esqueceu a senha                  | Não há servidor de e-mail configurado, então o "Esqueci a senha" não envia nada. Pelo servidor: `docker compose exec app php artisan tinker` e, dentro dele, `App\Models\User::first()->update(['password' => 'a senha nova'])` |
| Perdeu o celular do 2FA           | Os códigos de recuperação mostrados quando o 2FA foi ativado. Guarde-os junto com a chave do backup                                                                                                                             |
