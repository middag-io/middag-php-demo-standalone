# MIDDAG — Demo Standalone

Backend image for the **MIDDAG help-desk reference demo** — a standalone PSR-15
application that exercises the `middag-io/framework` (OSS) + `middag-io/ui`
contract surface end-to-end, with **no Moodle/WordPress host and no proprietary
core**.

It is a contract-driven [Inertia](https://inertiajs.com/) app (session auth +
`#[Auth]` gate, middleware pipeline, CSRF, flash/PRG) plus a Symfony Messenger
worker, over SQLite — demonstrating Active Record **and** Data Mapper in one
request, a converged Messenger bus, hooks, the form pipeline, schema migrations,
and a self-verifying coverage manifest.

---

## What's in this image

- **PHP 8.4 CLI** + the demo app, dependencies resolved from the private Satis.
- Two run modes via the entrypoint:
  - `serve` (default) — PHP built-in HTTP server on **port 8080**.
  - `worker` — Symfony Messenger consumer (async SLA escalation).
- First boot seeds a SQLite DB: **12 tickets, 4 agents, 6 customers, 4 SLA policies**.

> **Backend-only image.** The React/Vite frontend (`ui/`) is **not** baked in —
> by design it runs in the Compose `ui` service. A bare `docker run` serves the
> app and its JSON contracts, but the returned HTML references a `/build/` asset
> bundle that is not in this image. For the **full visual demo**, use Compose
> (see below).

## Tags

| Tag | Meaning |
|-----|---------|
| `0.2.0` | Pinned release |
| `latest` | Newest build |

## Quick start (backend + contracts)

```bash
docker run --rm -p 8080:8080 middagtec/middag-demo-standalone:0.2.0
# → http://localhost:8080   (redirects to /login)
```

Inspect the raw page contract (what the React layer renders) on any route:

```bash
curl -s http://localhost:8080/login -H "X-Inertia: true" | jq .
```

**Login:** `demo@middag.io` / `middag`

## Full demo (backend + React UI) via Compose

The visual app needs the Vite frontend. Clone the source repo and run Compose
(which builds the UI in the `ui` service):

```bash
git clone https://github.com/middag-io/middag-php-demo-standalone
cd middag-php-demo-standalone

docker compose up                 # app (:8080) + worker
docker compose --profile ui up    # + Vite dev server (:5176) serving the React UI
```

## Run the async worker

```bash
docker run --rm middagtec/middag-demo-standalone:0.2.0 worker
```

## Environment variables

| Variable | Default | Notes |
|----------|---------|-------|
| `APP_ENV` | `dev` | `dev` or `prod` |
| `APP_DEBUG` | `1` | Whoops error page when `1` |
| `DB_DSN` | `sqlite:/app/var/demo.sqlite` | SQLite file path (absolute) |

## Links

- **Source:** https://github.com/middag-io/middag-php-demo-standalone
- **License:** Apache-2.0
