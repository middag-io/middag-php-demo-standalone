# middag-php-demo-standalone

Reference scaffold proving `middag-io/framework` + `middag-io/ui` boot without Moodle/WordPress adapters. Single domain (Task), one form, one signal listener, SQLite persistence.

Used to validate framework standalone defaults (`Middag\Framework\Infrastructure\Standalone\*`).

## Stack

- PHP `^8.2`
- `middag-io/framework` (host-agnostic kernel + bus + form engine + persistence + routing)
- `middag-io/ui` (form / page contracts + DTOs)
- SQLite via `ext-pdo_sqlite`
- Symfony Dotenv for env loading

No JS. No Inertia. Server-rendered HTML.

## Layout

```
public/index.php          HTTP entrypoint
bin/install.php           DDL bootstrap (tasks + middag_outbox tables)
src/
  Bootstrap/DemoBootstrap.php   Composition root — wires standalone defaults
  Domain/Task.php
  Domain/TaskRepository.php
  Form/TaskForm.php             Uses framework Form engine
  Http/TaskController.php       Extends AbstractController, returns HTML
  Signal/TaskCreated.php        AsyncSignalInterface payload
  Signal/TaskCreatedListener.php
var/                            SQLite + runtime artifacts (gitignored)
```

## Run

```bash
cp .env.example .env             # adjust DB_DSN if needed
composer install
composer install:db              # creates tasks + outbox tables
composer serve                   # http://localhost:8080
```

Hit `/tasks/new`, submit form. App persists Task, dispatches `TaskCreated` signal, listener logs to stderr.

## Standalone defaults consumed

From `middag-io/framework` `Infrastructure/Standalone/`:

| Contract | Default impl |
|----------|--------------|
| `ConfigResolverInterface` | `EnvConfigResolver` |
| `TranslatorInterface` | `IdentityTranslator` |
| `ActorResolverInterface` | `NullActorResolver` |
| `OriginResolverInterface` | `NullOriginResolver` |
| `UserContextResolverInterface` | `NullUserContextResolver` |
| `AsyncCommandDispatcherInterface` | `SyncAsyncDispatcher` |
| `OutboxStoreInterface` | `AnsiOutboxStore` |

Plus framework's own ANSI suite: `AnsiConnection` (PDO), `AnsiSchemaBuilderAdapter`, `AnsiMigrationRunner`, `AnsiVersionTracker`.

## What this validates

- Framework boots without a platform adapter.
- DI composition root pattern works for arbitrary consumers.
- Form engine usable standalone (`Field::*()` factory + `AbstractForm`).
- Signal dispatch goes through 3-tier pipeline (Symfony EventDispatcher → HookManager → outbox).
- `StandaloneKernel` bridges Symfony Request/Response to framework PSR-15 kernel.

## Next demos (planned)

- `middag-php-demo-moodle` — Moodle plugin consuming framework via `middag-io/moodle`.
- `middag-php-demo-wordpress` — WP plugin consuming framework via `middag-io/wordpress`.
