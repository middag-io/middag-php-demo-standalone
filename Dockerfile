# syntax=docker/dockerfile:1
#
# Demo standalone — single PHP image that serves the PSR-15 app (built-in server)
# and runs the Messenger worker. DB = SQLite file in var/ (no external DB server).
# The ui/ layer (React/Vite) is NOT part of this image — it runs in the compose
# `ui` service (Node), once middag-react is unblocked.

FROM php:8.4-cli-bookworm AS base

# Non-default extensions required by installed packages:
#   pdo_sqlite (file-based SQLite DB) + intl (hard require of middag-io/framework).
# The rest (ctype/curl/dom/mbstring/json/...) ship enabled in the php image.
# libsqlite3-dev + libicu-dev to build; unzip for composer --prefer-dist.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev libicu-dev unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite intl \
    && rm -rf /var/lib/apt/lists/*

# Composer pinned via the official image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Deps first (layer cache). dev deps ARE runtime here: bin/console and the worker
# use symfony/console; the Doctrine domain uses doctrine/dbal; whoops is the dev
# error handler. Hence a full install (no --no-dev).
COPY composer.json composer.lock ./
# middag-io/framework + middag-io/ui come from the private Satis (auth required).
# auth.json is passed as a BuildKit secret (COMPOSER_AUTH) — never written to layers.
#   build: docker compose build   (compose injects the secret)
#   or:    docker build --secret id=composer_auth,src=$HOME/.composer/auth.json .
RUN --mount=type=secret,id=composer_auth \
    COMPOSER_AUTH="$(cat /run/secrets/composer_auth 2>/dev/null || echo '{}')" \
    composer install --no-interaction --no-progress --no-scripts --prefer-dist

# App source.
COPY . .

# Optimized autoload with the full tree present.
RUN composer dump-autoload --optimize

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Defaults for a bare `docker run` (compose may override). DB_DSN is an absolute
# path — the demo reads $_ENV['DB_DSN'] directly, it does NOT expand %PROJECT_ROOT%.
ENV APP_ENV=dev \
    APP_DEBUG=1 \
    DB_DSN=sqlite:/app/var/demo.sqlite

EXPOSE 8080
ENTRYPOINT ["entrypoint"]
CMD ["serve"]
