# middag-io/demo-standalone

[![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

A runnable help-desk application that boots **`middag-io/framework`** and **`middag-io/ui`**
with no Moodle or WordPress host and no proprietary code: a PSR-15 web app rendered by
`@middag-io/react`, and a console worker, over SQLite.

Example application: not a library, no public API, do not require it as a dependency; see the [hub](https://docs.middag.dev/libraries/) for the supported libraries.

What it shows and how to run it: [docs.middag.dev — Demo Application](https://docs.middag.dev/framework/reference/demo-standalone/).

## Help and status

- **Status of the example app.** It is maintained with the framework and ui: each
  release builds against their current versions. [`COVERAGE.md`](COVERAGE.md) lists every
  `@api` symbol of the installed framework and ui as covered by a demo test or E2E spec,
  proved by the host adapters, or a gap with its reason. It is generated
  (`composer coverage:md`); CI regenerates it and fails on any difference, and
  `ApiCoverageTest` fails when the map and the installed libraries drift apart.
- **Questions and bugs:** https://github.com/middag-io/middag-php-demo-standalone/issues
- **Security reports:** [`SECURITY.md`](SECURITY.md)
- **Changes:** [`CHANGELOG.md`](CHANGELOG.md)

## Supported versions

| Item | Supported |
|---|---|
| PHP | `^8.3`: 8.3, 8.4 and 8.5, with `pdo` and `pdo_sqlite`. `config.platform.php` is `8.3.0`, so `composer.lock` resolves at the floor |
| Symfony | `^7.4 \|\| ^8.1` for every Symfony component the demo requires. The lock resolves 7.4; a CI job without the platform pin proves 8.x |
| `middag-io/framework` | `^1.20` |
| `middag-io/ui` | `^1.8` |
| `@middag-io/react` (renderer, `ui/`) | `^0.45.0` |
| Host | none: no Moodle or WordPress. The adapters `middag-io/moodle` and `middag-io/wordpress` are not part of the demo |
| Database | SQLite, in a file or in memory |
| Docker image | PHP 8.4 (see [`DOCKER.md`](DOCKER.md)) |

## Quick start

```bash
composer install
composer install:db         # create and seed the SQLite schema
composer serve              # http://localhost:8080  (login: demo@middag.io / middag)
composer test               # PHPUnit against the real stack, in-memory SQLite
composer check              # PHP-CS-Fixer, Rector (dry run), PHPStan
```

The React bundle is built from `ui/` (`npm ci && npm run build:host`); the E2E suite
lives in `e2e/` ([`e2e/README.md`](e2e/README.md)).

### Routes

| Route | Returns |
|---|---|
| `GET /` | dashboard |
| `GET /tickets` · `GET /tickets/{id}` | ticket queue, filtered, sorted and paged on the server (`search`, `filter[status]`, `filter[priority]`, `sort`, `direction`, `page`, `per_page`), and ticket detail (breadcrumbs, status lifecycle, tabs) |
| `GET /tickets/new` → `POST /tickets/new` → `POST /tickets/new/confirm` | the ticket wizard |
| `POST /tickets` · `GET /tickets/{id}/edit` · `PUT /tickets/{id}` | web create, edit and update (PRG) |
| `POST /tickets/{id}/close` | the "Close ticket" action: a redirect for a browser, an `ActionResult` for JSON |
| `GET /agents` · `GET /agents/{id}` · `GET /customers` · `GET /parity` · `GET /coverage` | help-desk pages |
| `GET /help` · `GET /login` | public pages |
| `POST /login` · `POST /logout` | session auth |
| `POST /api/tickets` | JSON `201`, validated by the `rules()`-array `CreateTicketRequest` |
| `POST /api/tickets/dto` | JSON `201`, validated by the `#[ValidatedDto]` typed `TicketDto` |
| `GET /api/entities/customers` · `GET /api/entities/agents` | entity-picker sources |
| `GET /ui/page` · `GET /ui/fragment` | a full `PageContract` and a partial `Fragment` as JSON |

### Console

```bash
php bin/console install:db                          # schema, migrations and seed
php bin/console migrate:discover                    # migration manifest
php bin/console worker:consume --seed=3 --limit=10  # drain the async transport (also --time-limit, --memory-limit)
php bin/console schedule:run                        # dispatch the #[Schedule] commands due this minute
php bin/console logs:clean                          # log cleanup through the bus
```

### Environment

Read through one reader (`$_ENV`, then `$_SERVER`, then `getenv()`):

| Variable | Effect |
|---|---|
| `DB_DSN` | PDO DSN; default `sqlite:var/demo.sqlite` |
| `APP_DEBUG` | `1` renders a debug stack trace on a server error |
| `LOG_LEVEL` | minimum log level; default `debug` |
| `MAIL_CAPTURE` | `1` writes each mail as JSON under `var/mail/` instead of the null mailer |
| `DEMO_WEBHOOK_URL`, `DEMO_WEBHOOK_SECRET` | a signed POST per created ticket, retried with backoff |
| `DEMO_FAULT_MODE` | `1` boots a module that fails (isolated, shown on the dashboard), a maintenance gate driven by `var/maintenance.flag`, mail capture, and `GET /_test/crash`, which answers with the 500 error page. Used by the E2E fault server |

## Renderer extensions

`ui/src/app/register.ts` registers the free renderer's shells, layouts and blocks, plus
three host extensions: an `auth` shell that replaces the one the package ships, and the
`sparkline` and `tag_chips` cells.

## Notes

- **Local libraries.** The framework and ui resolve from their releases in
  `composer.lock`. To work against local clones, add a path repository and run
  `composer update middag-io/framework middag-io/ui`; never commit it:
  ```json
  "repositories": [
    {"type": "path", "url": "../middag-php-framework", "options": {"symlink": true}},
    {"type": "path", "url": "../middag-php-ui", "options": {"symlink": true}}
  ]
  ```
- **Transports.** The web app queues async commands on an in-memory transport, drained in
  the same process. Delivery across processes is shown with the Doctrine transport and the
  framework's runtime kernel (`RuntimeKernelTest`).

## Versioning

The demo is a member of the `middag-php-*` version family:
[Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility).
The release version is also the Docker image tag.

## Contributing

See [`CONTRIBUTING.md`](CONTRIBUTING.md). Pull requests target `main`; commits follow
[Conventional Commits](https://www.conventionalcommits.org/); `composer check && composer test`
must be green. License: **Apache-2.0**.
