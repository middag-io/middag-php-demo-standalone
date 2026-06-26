# DOCKER.md — run the Demo standalone in Docker

A single PHP 8.4 image serves the PSR-15 app (built-in server) + the Messenger
worker. DB = **SQLite file** (`var/demo.sqlite`), no external DB server. The `ui/`
layer (React/Vite) is **not included yet** — it lives in a profile-gated Node
service, ready for when `middag-react` unblocks.

## TL;DR

```bash
docker compose up --build         # web (8080) + worker
open http://localhost:8080        # demo login (seeded in demo_users)
```

First boot: the entrypoint creates `var/`, installs vendor (if missing), and runs
`bin/console install:db` (creates + seeds the SQLite DB). Later boots skip the
install (guarded on the `var/demo.sqlite` file).

## Dockerfile only (no compose)

```bash
docker build -t middag-demo-standalone:dev .
docker run --rm -p 8080:8080 middag-demo-standalone:dev          # serve
docker run --rm middag-demo-standalone:dev worker                # worker
docker run --rm middag-demo-standalone:dev composer test         # phpunit
```

Without a volume the SQLite DB is ephemeral (dies with the container). To persist
it, mount `./var`: `-v "$PWD/var:/app/var"`.

## Handy commands

```bash
docker compose up --build              # web + worker (default)
docker compose run --rm app composer test          # run tests
docker compose run --rm app php bin/console        # list console commands
docker compose exec app sh                         # shell into the web container
docker compose down                                # stop
```

## Extras (profiles — do not start on a default `up`)

```bash
docker compose --profile redis up      # + Redis on :6379 (validate cache/session/transport)
docker compose --profile ui up         # + Vite dev server on :5173 (FUTURE: ui/)
```

- **redis**: there is no Redis wiring in the demo today. The service is here only
  to experiment (point Messenger session/cache/transport at Redis).
- **ui**: BLOCKED until `middag-react`. There is no `package.json` in `ui/` yet, so
  the service fails on purpose until the layer exists. Once it does: it produces the
  `/build/app.js` bundle the PHP shell (`DemoBootstrap::inertiaHtmlBootstrap()`)
  references. See `FRONTEND-PLAN.md`.

## Notes

- **Public packages — no auth.** `middag-io/framework` + `middag-io/ui` are on
  Packagist (Apache-2.0), so the build needs no credentials: composer resolves them
  from the committed `composer.lock`.
- **dev = bind mount.** `.:/app` in compose: editing code reflects immediately.
  `vendor` and `ui/node_modules` are anonymous volumes (so they don't shadow what
  the image installed).
- **dev deps are runtime.** `symfony/console` (console + worker), `doctrine/dbal`
  (Doctrine domain), and `whoops` (dev error page) are in `require-dev` but are
  required for the app to run — so the image installs without `--no-dev`.
- **Don't copy `.env.example`.** Its example `DB_DSN` uses `%PROJECT_ROOT%`, which
  `DemoBootstrap` does not expand (Symfony DI treats it as a missing parameter and
  the boot breaks). Pass `DB_DSN`/`APP_*` via the environment (the Dockerfile and
  compose already do).
- **Serving static assets.** `serve` uses `php -S ... public/index.php` (same as the
  composer `serve` script), which routes everything through the kernel. When `ui/`
  lands and the prod bundle must be served by PHP, switch to a router that does
  `return false` for existing files — or serve via Vite/CDN. In dev, Vite (the `ui`
  profile) serves assets with HMR.
