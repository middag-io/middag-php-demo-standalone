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
2. Create a feature branch off `develop`.
3. Run the full suite locally before pushing: `composer check && composer test`.
4. Open a pull request against `develop`.

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
  multi-scope subjects. Mark a breaking change with `!` or a `BREAKING CHANGE:` footer.
- **Never** add `Co-Authored-By` trailers.
- The branch base is **`develop`**.

## Code of conduct

This project follows the [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md). By
participating you agree to uphold it.

## Security

Found a security issue? Follow [`SECURITY.md`](SECURITY.md). Please do not open a
public issue for vulnerabilities.

## License

By contributing you agree your contribution is released under the Apache License
2.0, the same license as the project.
