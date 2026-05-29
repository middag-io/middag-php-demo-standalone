# middag-php-demo-standalone

[![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

Standalone harness that boots `middag-io/framework` + `middag-io/ui` with no Moodle/WordPress adapter. It exists to do three things end-to-end:

1. **Validate the `middag-io/ui` 0.5.0 contract** — emit a full `PageContract` and a partial `Fragment` as JSON, exactly as a React client would consume them.
2. **Exercise the framework plumbing** — CQRS command bus, 3-tier signal dispatch, persistent signal outbox + drain, form engine, routing, DI composition root.
3. **Showcase Symfony components** as demo-local dev-dependencies — `console`, `lock`, `finder`, `filesystem` — proving they integrate cleanly before the framework itself adopts any of them.

> **License**: Apache-2.0.

## Stack

- PHP `^8.2`
- `middag-io/framework` — concern-first kernel (Bus / Database / Form / Http / Kernel / Logging / Persistence), Symfony-backed behind its own host-agnostic contracts
- `middag-io/ui` `^0.5` — page/fragment contract builders (zero-dep, transport-agnostic)
- SQLite via `ext-pdo_sqlite`
- Symfony `dotenv` (runtime) + `console` / `lock` / `finder` / `filesystem` / `event-dispatcher` (dev — the showcase)

No JS bundle. HTML pages are server-rendered; the ui contract is emitted as JSON for a SPA client to render.

## Layout

```
bin/console                     Symfony Console entrypoint (install:db, debug:request, outbox:drain, migrate:discover)
public/index.php                HTTP front controller
db/migrations/0001_create_demo.php   sample migration (discovered by migrate:discover)
src/
  Bootstrap/DemoBootstrap.php        Composition root — wires framework contracts to standalone defaults
  Command/CreateTaskCommand.php      CQRS command + handler dispatched through the CommandBus
  Command/CreateTaskCommandHandler.php
  Domain/Task.php
  Domain/TaskRepository.php          Raw-SQL repository over the framework ConnectionInterface
  Form/TaskForm.php                  Framework Form engine (Field factory + AbstractForm)
  Http/TaskController.php            HTML index + create (create dispatches via the bus)
  Http/UiController.php              ui 0.5.0 contract endpoints (PageContract + Fragment)
  Outbox/OutboxDrainer.php           Reads the signal outbox and delivers to async consumers
  Signal/TaskCreated.php             AsyncSignalInterface payload
  Signal/TaskCreatedListener.php     SYNC listener (Layer 1)
  Signal/TaskCreatedAsyncConsumer.php  ASYNC consumer (Layer 3, via outbox)
  Console/*Command.php               Symfony Console commands (the showcase)
tests/                          Hermetic harness tests (in-memory SQLite)
var/                            SQLite db + runtime artifacts (gitignored)
```

## Run

```bash
composer install
composer console            # list available commands
composer install:db         # create tasks + middag_signal_outbox tables
composer serve              # http://localhost:8080
```

### Routes

| Route | What it returns |
|-------|-----------------|
| `GET /` | HTML list of tasks |
| `GET\|POST /tasks/new` | HTML form; POST creates a task via the CommandBus |
| `GET /ui/page` | **Full `PageContract`** JSON (shell + region + metric/table blocks + notification) |
| `GET /ui/fragment` | **Partial `Fragment`** JSON (kind=table + notification) |

Inspect the ui contract without a browser:

```bash
php bin/console debug:request /ui/page
php bin/console debug:request /ui/fragment
```

### Console commands (Symfony showcase)

```bash
php bin/console install:db          # schema DDL via the framework SchemaBuilder
php bin/console migrate:discover    # symfony/finder lists db/migrations/*, symfony/filesystem writes var/migrations.json atomically
php bin/console outbox:drain        # symfony/lock guards the drain so concurrent runs never double-process
php bin/console debug:request <path> <method>
```

## What this validates

**ui 0.5.0 contract** — `UiController` builds a `PageContract` via `PageBuilder`/`RegionBuilder` and a `Fragment` via `Fragment::table(...)`, then `json_encode`s them. Both envelopes are self-describing (`version` + `shell`/`kind` discriminator). This is the dual full/partial consumption model the contract must serve.

**Framework plumbing** — a POST flows: `TaskController::create` → `CommandBus->handle(CreateTaskCommand)` → `CreateTaskCommandHandler` → repository `save()` → `SignalDispatcher->dispatch(TaskCreated)`. The 3-tier dispatcher fires the sync listener (Layer 1), the hook bridge (Layer 2), and — because an async consumer is registered — writes the signal to the outbox (Layer 3). `outbox:drain` then rehydrates the row via `TaskCreated::fromPayload()` and delivers it to `TaskCreatedAsyncConsumer`, marking it processed (at-least-once, idempotent).

**Symfony components** — `console` hosts every command; `lock` (FlockStore) wraps the outbox drain; `finder` + `filesystem` power `migrate:discover`. None are framework dependencies — they live in the demo's `require-dev` as a forward-looking integration spike.

## Notes

- The signal outbox ships **write-only** in the framework (`AnsiOutboxStore` exposes only `write()`/`install()`); the drain (`OutboxDrainer`) is the consumer's responsibility and lives here, in the demo.
- `TaskRepository` uses the `ConnectionInterface` directly with raw SQL. The framework `Persistence\AbstractRepository`/`AbstractMapper` are intentionally thin markers, so wrapping them buys little for a single-table demo; the real reuse is the connection's record API.
- Tests boot the real composition root against in-memory SQLite (`tests/Support/DemoTestCase`) — no mocks — so they fail loudly if the framework or ui contract drifts.

## Next demos (planned)

- `middag-php-demo-moodle` — Moodle plugin consuming the framework via `middag-io/moodle`.
- `middag-php-demo-wordpress` — WP plugin consuming the framework via `middag-io/wordpress`.
