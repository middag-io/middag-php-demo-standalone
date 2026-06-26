# CLAUDE.md — middag-io/demo-standalone

> A **durable** orientation guide for the agent: what this repo is, its boundaries,
> and how to work in it. **Not a file index** (it rots on every move) — use Glob/Grep
> to locate symbols. The codebase is the source of truth; live structure comes from
> `src/`, not from here.

## What this is (30s)

The **standalone proof harness** for the MIDDAG OSS stack. A real, runnable
**help-desk** application that boots `middag-io/framework` + `middag-io/ui` with **no
host (Moodle/WordPress) and no proprietary `middag-io/core`**. Every OSS `@api` area is
exercised here and pinned by a test, so the demo fails loudly the moment the framework
or ui drifts. The full area → artifact → test matrix lives in
[`COVERAGE.md`](COVERAGE.md).

This is a **demonstration, not a library**. It is `composer type: "project"`; it is not
published to Packagist as a dependency.

## The one rule: behaviour belongs upstream

The demo **uses** the OSS packages; it does not reimplement them. A bug in the kernel,
bus, forms, validation, or contracts is a `middag-io/framework` / `middag-io/ui` issue —
fix it there. If you hit an upstream gap, **note it** (see the README's upstream notes /
`COVERAGE.md`) rather than working around it silently here. And never import a host API
or the proprietary core: that boundary is the whole point of the harness.

## How it's wired

- **Composition root:** `src/Bootstrap/` — `DemoBootstrap` builds the container/routes,
  `DemoServiceProvider` drives suffix auto-discovery, `DemoKernel::boot()` is the entry
  point used by `public/index.php`, `bin/console`, and the tests.
- **Two validation styles, kept side by side** (this is intentional, do not collapse):
  the `rules()`-array `AbstractFormRequest` (`src/Http/Request/CreateTicketRequest.php`,
  `POST /api/tickets`) **and** the typed-DTO `#[ValidatedDto]` path
  (`src/Http/Dto/TicketDto.php`, `POST /api/tickets/dto`). They demonstrate the framework's
  two equivalent request-validation approaches.
- **Two persistence paradigms on one SQLite engine:** Active Record (`src/Domain/Eloquent/`)
  and Data Mapper (`src/Domain/Doctrine/`); `/parity` shows them together.
- **Tests** (`tests/`) boot the **real** `DemoKernel` against in-memory SQLite — no mocks.
  `DemoTestCase` installs the schema, seeds the demo user, and authenticates by default.

## Conventions

- **Branch base:** `develop`. **Commits:** Conventional Commits; **NEVER** `Co-Authored-By`;
  one lowercase scope (the `.githooks/commit-msg` hook enforces it — `composer install`
  wires `core.hooksPath`).
- **Release-trigger types:** release-please cuts a release only on `feat:`/`fix:` (and `!`
  breaking). A dependency bump meant to ship a release must be `fix(deps):` or `feat(deps):` —
  **never** `build(deps):`/`chore(deps):`, which release-please treats as non-user-facing and
  skips (the image then never publishes).
- **PHP style:** `declare(strict_types=1)` in every file; PSR-12 + `@PhpCsFixer`; PSR-4
  `Middag\Demo\Standalone\` → `src/`. The Apache-2.0 header is applied by PHP-CS-Fixer.
- **Gates (green before delivery):** `composer check` (PHP-CS-Fixer + Rector dry-run +
  PHPStan L6) **&&** `composer test`. Auto-fix: `composer fix` / `composer fix:all`.
- **Docs language:** English (matches the rest of the OSS stack).

## Run

```bash
composer install        # also wires .githooks (commit-msg)
composer install:db     # SQLite schema + seed   (login: demo@middag.io / middag)
composer serve          # http://localhost:8080
composer test           # PHPUnit
composer check          # style + rector + phpstan L6
```

## Refs

- This repo's coverage matrix: [`COVERAGE.md`](COVERAGE.md).
- Framework architecture + ui contracts: published at **docs.middag.dev** (and in the
  `middag-io/framework` / `middag-io/ui` repos' `docs/`).
