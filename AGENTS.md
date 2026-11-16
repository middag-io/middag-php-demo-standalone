# CLAUDE.md — middag-io/demo-standalone

> A **durable** orientation guide for the agent: what this repo is, its boundaries,
> and how to work in it. **Not a file index** (it rots on every move) — use Glob/Grep
> to locate symbols. The codebase is the source of truth; live structure comes from
> `src/`, not from here.

## What this is (30s)

The **standalone proof harness** for the MIDDAG OSS stack. A real, runnable
**help-desk** application that boots `middag-io/framework` + `middag-io/ui` with **no
host (Moodle/WordPress) and no proprietary `middag-io/core`**. Each `@api` symbol of the
framework and ui is mapped in [`COVERAGE.md`](COVERAGE.md): covered by a demo test or E2E
spec, proved by the host adapters, or a gap with its reason. `ApiCoverageTest` fails the
moment the installed framework or ui drifts from that map.

Example application: not a library, no public API, do not require it as a dependency.
It is `composer type: "project"`; the supported libraries are documented in the hub
(https://docs.middag.dev/libraries/).

## The one rule: behaviour belongs upstream

The demo **uses** the OSS packages; it does not reimplement them. A bug in the kernel,
bus, forms, validation, or contracts is a `middag-io/framework` / `middag-io/ui` issue —
fix it there. If you hit an upstream gap, **record it** in the coverage map
(`coverage/api.php`, rendered into `COVERAGE.md`) rather than working around it
silently here. And never import a host API or the proprietary core: that boundary is the whole point of the harness.

## How it's wired

- **Composition root:** `src/Kernel/Bootstrap/` — `DemoBootstrap` builds the container/routes,
  `DemoServiceProvider` drives suffix auto-discovery, `DemoKernel::boot()` is the entry
  point used by `public/index.php`, `bin/console`, and the tests.
- **Two validation styles, kept side by side** (this is intentional, do not collapse):
  the `rules()`-array `AbstractFormRequest` (`src/Http/Request/CreateTicketRequest.php`,
  `POST /api/tickets`) **and** the typed-DTO `#[ValidatedDto]` path
  (`src/Http/Dto/TicketDto.php`, `POST /api/tickets/dto`). They demonstrate the framework's
  two equivalent request-validation approaches.
- **Two persistence paradigms on one SQLite engine:** Active Record (the models at the root of `src/Domain/`)
  and Data Mapper (`src/Domain/Doctrine/`); `/parity` shows them together.
- **Tests** (`tests/`) boot the **real** `DemoKernel` against in-memory SQLite — no mocks.
  `DemoTestCase` installs the schema, seeds the demo user, and authenticates by default.

## Conventions

- **Branch base:** `main`. **Commits:** Conventional Commits; **NEVER** `Co-Authored-By`;
  one lowercase scope (the `.githooks/commit-msg` hook enforces it — `composer install`
  wires `core.hooksPath`).
- **Release-trigger types:** release-please cuts a release only on `feat:`/`fix:`. A
  dependency bump meant to ship a release must be `fix(deps):` (`feat(deps):` passes only on
  `0.x`; from `2.0.0` it fails the version family check outside a window) —
  **never** `build(deps):`/`chore(deps):`, which release-please treats as non-user-facing and
  skips (the image then never publishes).
- **Version family (https://docs.middag.dev/libraries/reference/versioning-and-compatibility):** this repo is in
  the `middag-php-*` family. It stays on `0.x` until the family cut, then jumps to `2.0.0`
  with every other member. From `2.0.0`: shared MAJOR.MINOR, PATCH is fix-only, `feat`
  waits for a family MINOR window, a break waits for a family MAJOR window (deprecate
  first in a MINOR), and one `^2.N` range for every `middag-io/*` sibling. The product
  owner decides every family MINOR and MAJOR. The PR check
  `php-version-family-check / check` reads the window from the org variable
  `PHP_LIBS_OPEN_RELEASE`; `!`/`BREAKING CHANGE` fail now unless a MAJOR window is open, so
  on `0.x` a break ships as `feat:`. A window counts as open only
  when it is ahead of the demo's version; while one is open, merge the Release PR only at the
  coordinated `X.Y.0`.
  A waiting PR is kept current by merging `main` into it, never by rebase or force-push.
  Detail: [`CONTRIBUTING.md`](CONTRIBUTING.md#versioning). Do not change the
  release-please `versioning` or the sibling ranges before the cut.
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
- Framework architecture and ui contracts: https://docs.middag.dev/framework/.
