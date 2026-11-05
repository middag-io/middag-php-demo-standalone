# Contributing

Thanks for your interest in `middag-io/demo-standalone`. This is the **standalone
proof harness** for the MIDDAG OSS stack: a real, runnable help-desk application
that boots `middag-io/framework` + `middag-io/ui` with **no Moodle/WordPress host
and no proprietary `middag-io/core`**, exercising each OSS `@api` area and proving
it with a test.

## Scope

This repository is a **demonstration**, not a library. Its job is to stay a
**faithful, minimal, runnable** example of the published OSS packages. That shapes
what belongs here:

- **Keep it representative.** New surface lands when it demonstrates a real
  framework/ui capability (a concern, an `@api`, a wiring pattern) — and comes with
  a test that proves it. No speculative app features.
- **Behaviour belongs upstream.** A bug in the kernel, bus, forms, validation, or
  contracts is a `middag-io/framework` / `middag-io/ui` issue. Fix it there; the
  demo only *uses* those packages. If you find such a gap, note it (see the
  README's upstream-gap notes) rather than working around it silently here.
- **No host, no core.** The demo never imports a host API (Moodle/WordPress) or the
  proprietary core. That boundary is the whole point.

## Workflow

1. Fork and clone.
2. Create a feature branch off `main`.
3. Run the full suite locally before pushing: `composer check && composer test`.
4. Open a pull request against `main`. To bring a long-lived branch up to date,
   merge `main` into it; never rebase or force-push a branch with an open PR.

### Running the demo locally

```bash
composer install
composer install:db    # create + seed the SQLite schema (login: demo@middag.io / middag)
composer serve         # http://localhost:8080
composer test          # PHPUnit — boots the real stack against in-memory SQLite, no mocks
```

To iterate against **local clones** of the framework/ui instead of the published
packages, add a path repository and update (this is a local-only override — do not
commit it):

```json
"repositories": [
  {"type": "path", "url": "../middag-php-framework", "options": {"symlink": true}},
  {"type": "path", "url": "../middag-php-ui", "options": {"symlink": true}}
]
```

## Coding standards

- `declare(strict_types=1);` at the top of **every** PHP file.
- **PSR-12 plus the `@PhpCsFixer` ruleset**, enforced by PHP-CS-Fixer. `camelCase`
  for methods/properties, `PascalCase` for classes. The Apache-2.0 header is applied
  automatically — run `composer fix`.
- **PSR-4**: the namespace mirrors the path — `Middag\Demo\Standalone\<...>`.
- Explicit types on every signature. Target PHP `^8.2`; prefer enums, `readonly`,
  and `final` where they fit.
- Cover new behaviour with a test. `tests/` boots the real composition root
  (`DemoKernel`) against in-memory SQLite — no mocks — so a test failing loudly when
  the framework or ui drifts is the point.

## Quality gates

Everything green before you push:

```bash
composer check    # PHP-CS-Fixer + Rector (dry-run) + PHPStan (level 6)
composer test     # PHPUnit
composer fix      # auto-fix: PHP-CS-Fixer + Rector
composer fix:all  # style → rector → style (re-settles formatting after Rector)
```

- PHPStan runs at **level 6** with zero new errors.
- PHP-CS-Fixer and Rector must be clean (the dry-run shows no diff).

## Commit messages and branch

[Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): short summary

Longer body when the "why" isn't obvious.
```

- Types: `feat`, `fix`, `refactor`, `perf`, `docs`, `style`, `test`, `build`,
  `ci`, `chore`, `revert`.
- One scope per commit, lowercase — the `commit-msg` hook rejects comma-separated
  multi-scope subjects. Do **not** mark a breaking change with `!` or a `BREAKING CHANGE:`
  footer outside a family MAJOR window: the hook accepts both, but the version family
  check refuses them. On `0.x` a break ships as `feat:` with the break and the migration
  described in the commit body. See [Versioning](#versioning) for when a `feat` or a
  breaking change can land.
- **Never** add `Co-Authored-By` trailers.
- The branch base is **`main`**.

## Versioning

The demo belongs to the `middag-php-*` version family
(ADR-032 of the internal `docs-middag-ops`, which amends ADR-003 for the PHP libraries). release-please derives
each release from the commit types on `main`.

- **Now (`0.x`).** `feat:` and `fix:` cut a release as before. A breaking change ships
  as `feat:` with the break and the migration described in the commit body; `!` or a
  `BREAKING CHANGE:` footer fails the version family check unless a family MAJOR window
  is open. The `1.x` framework
  and ui may still break inside a MINOR until 2026-11-30. From 2026-12-01 no break
  lands on `1.x` until the cut.
- **The cut.** The family ships `2.0.0` before 2026-11-30, through a coordinated
  `Release-As: 2.0.0` in every family repo. The demo jumps from `0.x` to `2.0.0`.
- **From `2.0.0`.** release-please cuts PATCH releases only. A PATCH carries fixes
  only. A `feat` waits for a family MINOR window. A breaking change waits for a family
  MAJOR window, and is deprecated first in a MINOR, with a runtime notice. Family
  MINOR and MAJOR releases go out as a coordinated `Release-As: X.Y.0` in every family
  repo. The product owner decides each family MINOR (a new Moodle version to support,
  a MIDDAG release that needs a feature, a deprecation or a new range, or a new
  Symfony MINOR to absorb) and every family MAJOR. `composer.json` requires one
  `^2.N` range for every `middag-io/*` sibling. A dependency bump meant to ship a
  release is `fix(deps):`; `feat(deps):` fails the check outside a window.

### The version family check

`php-version-family-check / check` runs on every pull request
(`.github/workflows/family-window.yml`). It reads the PR title and the header and
footers of every commit in the PR except merge commits.

- **The window** is the organization variable `PHP_LIBS_OPEN_RELEASE`, set and
  cleared on the order of the product owner. Empty means closed. `X.Y` (with Y > 0)
  is a family MINOR window. `X.0` is a family MAJOR window; the `2.0.0` cut is
  window `2.0`. A PR into any branch other than `main` sees the window closed.
- A `!` before the colon or a `BREAKING CHANGE:` footer fails unless a MAJOR window
  is open.
- A `feat` fails unless a window is open, but only once the version in
  `.release-please-manifest.json` on the base branch is `2.0.0` or higher. Below
  that, `feat` passes.
- A `Release-As:` footer fails unless a window is open and the footer names the
  `X.Y.0` of that window.

To pass it, use the type that describes the change; never relabel a feature as a
`fix`. A feature or a break that has to wait stays on its open PR. Keep the PR
current by merging `main` into it, and re-run the check when the window opens.

A window counts as open only when it is ahead of the demo's current version; a value
left set after its family release reads as closed. While a window is open, the demo's
Release PR is merged only at the coordinated `X.Y.0`. A re-run reads the base of the
original event: after the `2.0.0` cut, a PR opened before the cut must merge `main`
into it so that the check reads the new version.

## Code of conduct

This project follows the [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md). By
participating you agree to uphold it.

## Security

Found a security issue? Follow [`SECURITY.md`](SECURITY.md). Please do not open a
public issue for vulnerabilities.

## License

By contributing you agree your contribution is released under the Apache License
2.0, the same license as the project.
