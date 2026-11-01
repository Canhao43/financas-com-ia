#!/bin/sh
# Atualiza o app para a imagem mais nova do GHCR. Roda no servidor, na pasta do compose.
set -e
cd "$(dirname "$0")"

# Backup antes de trocar o código: a versão nova pode trazer migration, e no SQLite
# desfazer uma migration que deu errado é restaurar o arquivo. Sem backup, não atualiza.
docker compose exec -T agendador php artisan backup:criar

docker compose pull
docker compose up -d --wait
docker image prune -f
