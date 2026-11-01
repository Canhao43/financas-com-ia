#!/bin/sh
set -e

# Cache de configuração, rotas, views e eventos. Na subida, e não no build, porque a
# configuração vem do .env do servidor. A saída vai para o stderr: assim o stdout fica só com
# a do comando pedido (o "key:generate --show" do docs/deploy.md devolve só a chave).
php artisan optimize >&2

# Só o site migra. O agendador roda a mesma imagem e não pode disputar a migration com ele.
if [ "$1" = "frankenphp" ]; then
    php artisan migrate --force
fi

exec "$@"
