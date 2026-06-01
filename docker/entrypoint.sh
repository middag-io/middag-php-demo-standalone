#!/bin/sh
#
# demo-standalone image entrypoint. Prepares the runtime (var/, vendor, SQLite)
# and dispatches the command: `serve` (HTTP) or `worker` (Messenger consume).
# Any other argument runs as a raw command (e.g. `composer test`).
set -e

# Runtime dirs (var/ is gitignored: SQLite + logs live here).
mkdir -p var/log/demo/system var/log/demo/debug

# Do NOT copy .env.example -> .env: its example DSN uses %PROJECT_ROOT%, which
# DemoBootstrap does NOT expand (Symfony DI treats it as a missing parameter and
# the boot breaks). APP_ENV/APP_DEBUG/DB_DSN come from the environment instead
# (Dockerfile/compose).

# A dev bind mount may bring a tree without vendor: install on demand.
if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --no-progress --prefer-dist
fi

# Create + seed the SQLite DB on first boot. Keyed on the default DSN path
# (sqlite:/app/var/demo.sqlite). For a custom DSN, adjust this guard.
prepare_db() {
    if [ ! -f var/demo.sqlite ]; then
        php bin/console install:db
    fi
}

# The worker does not install the DB: it waits for the web service to create it
# (avoids a first-boot race).
wait_db() {
    i=0
    while [ ! -f var/demo.sqlite ] && [ "$i" -lt 30 ]; do
        sleep 1
        i=$((i + 1))
    done
}

case "$1" in
    serve)
        prepare_db
        exec php -S 0.0.0.0:8080 -t public public/index.php
        ;;
    worker)
        wait_db
        exec php bin/console worker:consume
        ;;
    *)
        exec "$@"
        ;;
esac
