# syntax=docker/dockerfile:1

# Imagem de produção. Toda imagem e binário de fora vem fixado por hash: o build de hoje e o
# de daqui a um mês saem da mesma base. Quem propõe atualizar é o Dependabot, num PR.

# FrankenPHP: o Caddy (servidor web com HTTPS automático) e o PHP num binário só.
FROM dunglas/frankenphp:1.13.4-php8.4-trixie@sha256:afc8e39e245f0f8084fa47ac55070c9da088bee472fa71ca291f703bdd47f4cc AS base
WORKDIR /app
# O .dockerignore deixa o storage local de fora; as pastas que o Laravel espera nascem aqui.
RUN mkdir -p storage/app/private storage/app/public storage/logs \
    storage/framework/cache/data storage/framework/sessions storage/framework/views


# Dependências do PHP, sem as de desenvolvimento.
FROM base AS dependencias
COPY --from=composer:2.11.1@sha256:537fb03057ed901ae06bde7ef25af348903485ae1acc7fc789b80d8bac666f35 /usr/bin/composer /usr/local/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress


# Front. Precisa do PHP junto: o plugin do Wayfinder roda o artisan durante o build do Vite.
FROM dependencias AS front
COPY --from=node:22-trixie-slim@sha256:2117f713a417f3aff682ac8f5bc2ebbdf1beed51f36e67ab1e0fd3866b6d2d8f /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-trixie-slim@sha256:2117f713a417f3aff682ac8f5bc2ebbdf1beed51f36e67ab1e0fd3866b6d2d8f /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm
COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && npm run build


# rclone: o binário oficial, conferido contra o SHA-256 que o projeto publica.
FROM base AS rclone
ARG RCLONE_VERSAO=1.75.2
ARG RCLONE_SHA256=349ac8fba6ff65d6247043f1750cdcb518ec5d500ef91463a10d37c0ccdf3702
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && curl -fsSLo /tmp/rclone.zip "https://downloads.rclone.org/v${RCLONE_VERSAO}/rclone-v${RCLONE_VERSAO}-linux-amd64.zip" \
    && echo "${RCLONE_SHA256}  /tmp/rclone.zip" | sha256sum -c - \
    && unzip -j /tmp/rclone.zip "rclone-v${RCLONE_VERSAO}-linux-amd64/rclone" -d /usr/local/bin


FROM base

LABEL org.opencontainers.image.source=https://github.com/Canhao43/financas-com-ia

# age cifra o backup. Vem do repositório do Debian, que assina os pacotes.
RUN apt-get update \
    && apt-get install -y --no-install-recommends age \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions opcache \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-financas.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=755 docker/iniciar.sh /usr/local/bin/iniciar
COPY --from=rclone /usr/local/bin/rclone /usr/local/bin/rclone

# O código pertence ao root e o app roda com outro usuário: o processo do PHP lê o próprio
# código, mas não consegue reescrevê-lo. Só storage, cache e /dados aceitam escrita.
COPY . .
COPY --from=front /app/vendor ./vendor
COPY --from=front /app/bootstrap/cache ./bootstrap/cache
COPY --from=front /app/public/build ./public/build
RUN useradd --create-home financas \
    && mkdir /dados \
    && chown -R financas:financas /dados storage bootstrap/cache /config/caddy /data/caddy

# O que vale para toda instalação fica na imagem; o .env do servidor guarda só o que é dele.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/dados/financas.sqlite \
    SESSION_SECURE_COOKIE=true \
    QUEUE_CONNECTION=sync \
    BACKUP_PASTA=/dados/backups \
    RCLONE_CONFIG=/dados/rclone.conf

USER financas

# A imagem tem dois papéis (site e agendador), cada um com a sua checagem de saúde, no
# compose. A herdada da imagem base usa a API de administração do Caddy, que o Caddyfile desliga.
HEALTHCHECK NONE

ENTRYPOINT ["iniciar"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
