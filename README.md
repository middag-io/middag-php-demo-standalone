# middag-io/demo-standalone

[![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

A standalone harness that boots **`middag-io/framework`** + **`middag-io/ui`** with
**no Moodle/WordPress host and no proprietary `middag-io/core`**. It is the living
proof that the OSS stack works standalone, batteries included: a real **help-desk**
PSR-15 web app **and** a Symfony Console command worker over SQLite, exercising every
OSS `@api` area — and proving each one with a test.

> Full `@api` → artifact → test matrix: **[COVERAGE.md](COVERAGE.md)**. Run the suite
> with `composer test` (boots the real stack against in-memory SQLite, no mocks).

## What it proves

- **Kernel / DI** — `ContainerFactory` composition root + `ServiceProvider` suffix
  auto-discovery + `AbstractModule` lifecycle + `HookFacade`.
- **Converged bus** (Symfony Messenger) — sync handlers by `{Command}Handler`
  convention, async commands routed to an in-memory transport and drained by a CLI
  worker, batch returns, command serialization, declarative `#[Schedule]`.
- **HTTP + Inertia** — PSR-15 kernel, controllers, session auth + the `#[Auth]` gate,
  the middleware pipeline, CSRF, flash/PRG, and Inertia responses (first-visit HTML
  shell vs `X-Inertia` JSON) wired standalone.
- **Two request-validation styles, side by side** — the help-desk create path is
  shown **both** ways the framework supports:
  - `CreateTicketRequest` — the declarative **`rules()`-array** `AbstractFormRequest`
    (`field => Symfony Constraint`), at `POST /api/tickets`.
  - `TicketDto` — a plain typed DTO whose properties carry `#[Assert]` (and `#[Field]`),
    mapped by the framework's **`#[ValidatedDto]`** attribute, at `POST /api/tickets/dto`.
- **Two persistence paradigms on one engine** — **Active Record** (`Persistence\Model`,
  Eloquent-style: tickets, users, comments) **and** **Data Mapper** (`Repository` +
  `Mapper` + `Entity`, Doctrine-style: customers, agents, SLA policies) over the same
  SQLite connection; the `/parity` page demonstrates the two side by side.
- **Forms** — `AbstractForm` + field factory + conditional fields + the entity-picker
  source, rendered to ui Inertia props; a multi-step ticket wizard.
- **Schema / migrations** — descriptor-driven `SchemaBuilder` + SQLite adapter +
  `MigrationRunner` version tracking (+ opt-in DBAL multi-engine).
- **Logging** — Monolog-backed `LoggerFactory` + a log-cleanup command run via the bus.
- **ui contracts** — full `PageContract` + partial `Fragment` JSON, the payload a React
  client consumes.

## Stack

- PHP `^8.2` (tested on 8.4)
- **`middag-io/framework`** `^0.11.3` — concern-first kernel (Bus / Database / Form /
  Http / Kernel / Logging / Persistence), Symfony-backed behind host-agnostic contracts.
- **`middag-io/ui`** `^1.0` — zero-dependency page/fragment contract builders.
- SQLite via `ext-pdo_sqlite`.
- Symfony components — `messenger`, `routing`, `http-foundation`, `dependency-injection`,
  `serializer`, `validator` … (transitive via the framework); `console`, `lock`,
  `finder`, `filesystem` (dev showcase).

No JS bundle ships: the Inertia HTML shell embeds the page payload and documents where
a React client (`@middag-io/react`) would mount. The server-emitted **contract** is what
this repo proves; rendering it is a separate, downstream concern.

## Run

```bash
composer install
composer install:db         # create + seed the SQLite schema
composer serve              # http://localhost:8080  (login: demo@middag.io / middag)
composer test               # PHPUnit — real stack, in-memory SQLite, no mocks
composer check              # PHP-CS-Fixer + Rector (dry-run) + PHPStan (level 6)
```

### Routes

| Route | Returns |
|---|---|
| `GET /` | dashboard (Inertia: HTML shell first visit; `X-Inertia` → JSON) |
| `GET /tickets` · `GET /tickets/{id}` | ticket list / detail |
| `GET /tickets/new` → `POST /tickets/new` → `POST /tickets/new/confirm` | multi-step ticket wizard |
| `POST /tickets` · `GET /tickets/{id}/edit` · `PUT /tickets/{id}` | web create / edit / update (PRG) |
| `GET /agents` · `GET /customers` · `GET /parity` · `GET /help` · `GET /coverage` | help-desk pages |
| `POST /api/tickets` | JSON `201` — validated via the **`rules()`-array** `CreateTicketRequest` |
| `POST /api/tickets/dto` | JSON `201` — validated via the **`#[ValidatedDto]`** typed `TicketDto` |
| `GET /api/entities/customers` · `GET /api/entities/agents` | entity-picker source JSON |
| `GET /ui/page` · `GET /ui/fragment` | full `PageContract` / partial `Fragment` JSON |
| `GET /login` · `POST /login` · `POST /logout` | session auth |

### Console

```bash
php bin/console install:db                # SchemaBuilder + MigrationRunner (+ seed)
php bin/console migrate:discover          # finder + filesystem -> migration manifest
php bin/console worker:consume --seed=3   # symfony/lock-guarded async drain
php bin/console logs:clean                # dispatch the log-cleanup command through the bus
```

## OSS ↔ core boundary

The demo is honest about what is **not** OSS. Domain **signals** (the old 3-tier
Signal/Outbox) live in the proprietary `middag-io/core`; here the "ticket created →
side effect" flow is re-modeled with a **hook action** + an **async command**.
Multi-tenant / org-scope / licensing are host/core concerns. See
[COVERAGE.md → OSS ↔ core boundary](COVERAGE.md#oss--core-boundary).

## Notes

- **Local-lib development (Composer path-repo).** By default the framework + ui resolve
  from their published releases (pinned in `composer.lock`). To iterate against local
  clones, add a path repository and run
  `composer update middag-io/framework middag-io/ui` (local-only; do not commit it):
  ```json
  "repositories": [
    {"type": "path", "url": "../middag-php-framework", "options": {"symlink": true}},
    {"type": "path", "url": "../middag-php-ui", "options": {"symlink": true}}
  ]
  ```
- **In-memory transport is process-local.** Async commands queued during an HTTP request
  are not visible to a separate worker process. Tests prove dispatch → drain in-process;
  `worker:consume --seed=N` proves the full round-trip from a single CLI process. For
  cross-process delivery use a persistent transport (`symfony/doctrine-messenger`, opt-in).
- **Multi-engine schema.** `doctrine/dbal` (a dev dependency) lets the schema test prove
  the same descriptor targets another engine; drop the dep and the case skips itself.

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md). The branch base is `develop`; commits follow
[Conventional Commits](https://www.conventionalcommits.org/); `composer check && composer test`
must be green. License: **Apache-2.0**.
