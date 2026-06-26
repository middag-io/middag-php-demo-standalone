# syntax=docker/dockerfile:1
#
# Demo standalone — single PHP image that serves the PSR-15 app (built-in server)
# and runs the Messenger worker. DB = SQLite file in var/ (no external DB server).
# The React/Vite UI (ui/) is built in the Node stage below and baked into
# public/build, so a bare `docker run` serves the complete, styled app.

# --- UI build stage ---------------------------------------------------------
# Pinned to BUILDPLATFORM (the runner's native arch) regardless of the target
# image arch: the JS/CSS bundle is architecture-independent, so it's built once
# and copied into every target image — avoids a slow arm64 npm build under QEMU.
FROM --platform=$BUILDPLATFORM node:22-bookworm-slim AS ui
WORKDIR /app/ui
# Deps first (layer cache). @middag-io/react lives on GitHub Packages, so a
# scoped registry + auth token is required. The token arrives as a BuildKit
# secret (never baked into a layer); the literal ${NODE_AUTH_TOKEN} in .npmrc is
# expanded by npm at runtime from the env var set only for that RUN.
COPY ui/package.json ui/package-lock.json ./
RUN printf '@middag-io:registry=https://npm.pkg.github.com\n//npm.pkg.github.com/:_authToken=${NODE_AUTH_TOKEN}\n' > .npmrc
RUN --mount=type=secret,id=github_token \
    NODE_AUTH_TOKEN="$(cat /run/secrets/github_token)" npm ci
# Source + production build. build:host (vite.config.custom.ts) writes the app
# entry (app.js + style.css + hashed chunks) to /app/public/build.
COPY ui/ ./
RUN npm run build:host

# --- PHP runtime stage ------------------------------------------------------
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
# middag-io/framework + middag-io/ui are public on Packagist (Apache-2.0), so the
# build needs no auth — composer resolves them from the committed lock.
#   build: docker compose build   (or: docker build -t middag-demo-standalone:dev .)
RUN composer install --no-interaction --no-progress --no-scripts --prefer-dist

# App source.
COPY . .

# Optimized autoload with the full tree present.
RUN composer dump-autoload --optimize

# Bake the prebuilt UI bundle (from the ui stage) into the doc root so the PHP
# built-in server serves /build/app.js + /build/style.css. public/build is
# gitignored + dockerignored, so the `COPY . .` above never carries a stale copy.
COPY --from=ui /app/public/build ./public/build

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
