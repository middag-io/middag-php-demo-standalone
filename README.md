# middag-php-demo-standalone

[![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

Standalone harness that boots `middag-io/framework` **0.4.0** + `middag-io/ui` **0.6.0**
with **no Moodle/WordPress host and no proprietary `middag-io/core`**. It is the
living proof that the framework works standalone, batteries included: a real
PSR-15 HTTP app **and** a Symfony Console command worker over SQLite, exercising
every OSS `@api` area — and proving each one with a test.

> Full `@api` → artifact → test matrix: **[COVERAGE.md](COVERAGE.md)** · `composer test` runs 50 tests.

## What it proves

- **Kernel / DI** — `ContainerFactory` composition root + `ServiceProvider` suffix auto-discovery + `AbstractModule` lifecycle + `HookFacade`.
- **Converged bus** (Symfony Messenger) — sync handlers by `{Command}Handler` convention, async commands routed to an in-memory transport and drained by a CLI worker, `SyncResult` batch returns, command serialization, declarative `#[Schedule]`.
- **Hooks** — WordPress-style filters + actions (the OSS replacement for the old core-only domain signals).
- **Two persistence paradigms on one engine** — Active Record (`Persistence\Model`, Eloquent-style) **and** Data Mapper (`Repository`+`Mapper`+`Entity`, Doctrine-style) over the **same** `demo_tasks` table, with parity tests.
- **Schema / migrations** — descriptor-driven `SchemaBuilder` + SQLite adapter + `MigrationRunner` version tracking (+ opt-in DBAL multi-engine).
- **HTTP + Inertia** — PSR-15 kernel, controllers, validated form requests, Inertia responses (first-visit HTML shell vs `X-Inertia` JSON) wired standalone via closures.
- **Forms** — `AbstractForm` + `Field` factory + conditional fields + entity-picker, rendered to ui Inertia props.
- **Logging** — Monolog-backed `LoggerFactory` + a log-cleanup command run through the bus.
- **ui 0.6.0 contracts** — a full `PageContract` + a partial `Fragment` as the JSON a React client consumes.

## Stack

- PHP `^8.2` (tested on 8.4)
- `middag-io/framework` `^0.4.0` — concern-first kernel (Bus / Database / Form / Http / Kernel / Logging / Persistence), Symfony-backed behind host-agnostic contracts
- `middag-io/ui` `^0.6.0` — zero-dependency page/fragment contract builders
- SQLite via `ext-pdo_sqlite`
- Symfony components — `messenger`, `routing`, `http-foundation`, `dependency-injection` … (transitive via the framework); `console`, `lock`, `finder`, `filesystem` (dev showcase)

No JS bundle ships; the Inertia HTML shell embeds the page payload and documents where a React client (`@middag-io/react`) would mount.

## Layout

```
bin/console        install:db | migrate:discover | worker:consume | logs:clean | debug:request
bin/debug.php      Shared\Util\{Environment,Typing,Debug} showcase
public/index.php   PSR-15 front controller (StandaloneKernel -> HttpKernel)
db/schema/*.php    table descriptors (loaded by SchemaBuilder)
src/
  Bootstrap/       DemoBootstrap (composition root) · DemoServiceProvider (auto-discovery) · DemoKernel (boot)
  Command/         CQRS commands + handlers (sync create · async notify · batch import)
  Domain/Eloquent/ Active-Record Task (Model)
  Domain/Doctrine/ Data-Mapper Task + Mapper + Repository (same table)
  Form/            TaskForm (Field factory) + TaskEntitySource
  Hook/            TaskHooks (filters + actions)
  Http/            controllers (Inertia · JSON API · ui contract) + CreateTaskRequest
  Logging/         CleanLogsHandler
  Module/          DemoModule (AbstractModule lifecycle)
  Schema/          DemoMigrationRunner
  Shortcode/       TaskSummary (#[TrustedOutput])
tests/             50 tests — one per matrix area (in-memory SQLite, real stack, no mocks)
var/               SQLite db + logs + manifests (gitignored)
```

## Run

```bash
composer install
composer console            # list commands
composer install:db         # create schema + record version
composer serve              # http://localhost:8080
composer test               # phpunit (50 tests)
```

### Routes

| Route | Returns |
|---|---|
| `GET /` | Inertia `Tasks/Index` (HTML shell on first visit; `X-Inertia` → JSON) |
| `GET /tasks/new` | Inertia `Tasks/Create` (form props) |
| `POST /tasks` | create via the bus, then redirect |
| `GET /tasks/{id}` | Inertia `Tasks/Show` (404 on missing; `#[Auth]` inert standalone) |
| `POST /api/tasks` | JSON `201` (validated `CreateTaskRequest`) |
| `POST /api/tasks/import` | JSON `SyncResult` summary |
| `GET /api/entities/tasks` | entity-picker source JSON |
| `GET /ui/page` | full `PageContract` JSON |
| `GET /ui/fragment` | partial `Fragment` JSON |

### Console

```bash
php bin/console install:db                # SchemaBuilder + MigrationRunner
php bin/console migrate:discover          # finder + filesystem -> var/migrations.json
php bin/console worker:consume --seed=3   # symfony/lock-guarded async drain
php bin/console logs:clean                # dispatch CleanLogsCommand through the bus
php bin/console debug:request /ui/page
php bin/debug.php                         # Environment / Typing / Debug helpers
```

## The two persistence paradigms (mirror)

`demo_tasks` is reached **two ways** to prove parity:

- **Active Record** — `Domain\Eloquent\Task` (Laravel-Eloquent-style `Persistence\Model`): `save()`, `find()`, `where()->get()`.
- **Data Mapper** — `Domain\Doctrine\Task` + `TaskMapper` + `TaskRepository` (Symfony-Doctrine-style): persistence-ignorant entity, explicit repository + QueryBuilder + `Page`.

Write through one, read through the other — see `PersistenceTest::paradigmParity*`.

## OSS ↔ core boundary

The demo is honest about what is **not** OSS. Domain **signals** (the old 3-tier
Signal/Outbox) moved to the proprietary `middag-io/core`; the "task created →
side effect" flow is re-modeled here with a **hook action** + an **async
command**. `#[Schedule]` execution, `#[Auth]` enforcement, and multi-tenant /
org-scope / licensing are host/core concerns. See
[COVERAGE.md → OSS ↔ core boundary](COVERAGE.md#oss--core-boundary).

## Notes

- **Local-lib development (Composer path-repo).** By default the framework + ui
  resolve from Satis (pinned in `composer.lock`). To iterate against local clones,
  add to `composer.json` and run `composer update middag-io/framework middag-io/ui`:
  ```json
  "repositories": [
    {"type": "path", "url": "../middag-php-framework", "options": {"symlink": true}},
    {"type": "path", "url": "../middag-php-ui", "options": {"symlink": true}}
  ]
  ```
- **In-memory transport is process-local.** Async commands queued during an HTTP
  request are not visible to a separate worker process. Tests prove dispatch →
  drain in-process; `worker:consume --seed=N` proves the full round-trip from a
  single CLI process. For cross-process delivery use a persistent transport
  (`symfony/doctrine-messenger`, opt-in).
- **Multi-engine schema.** `doctrine/dbal` (a dev dependency) lets `SchemaTest`
  prove the same descriptor targets another engine via `DbalSchemaBuilderAdapter`;
  drop the dep and the case skips itself.
- **Known framework gap** (noted upstream, worked around here): method-restricted
  routes need `RequestContext::fromRequest()` seeded by the front controller —
  see [COVERAGE.md → Framework gaps](COVERAGE.md#framework-gaps-found-upstream-tasks-not-fixed-here).
- License: **Apache-2.0**.
