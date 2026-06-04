# middag-io/demo-standalone

The **standalone proof harness** for the MIDDAG OSS stack — a runnable help-desk app
that boots [`middag-io/framework`](https://github.com/middag-io/middag-php-framework) +
[`middag-io/ui`](https://github.com/middag-io/middag-php-ui) with no host and no
proprietary core, exercising every OSS `@api` area and proving each with a test.

It is a **demonstration, not a library**: read it to see how the published packages wire
together in a real application.

## Start here

- **[README](https://github.com/middag-io/middag-php-demo-standalone/blob/main/README.md)** —
  what it proves, the stack, how to run it, and the full route map.
- **[COVERAGE.md](https://github.com/middag-io/middag-php-demo-standalone/blob/main/COVERAGE.md)** —
  the `@api` → artifact → test matrix, and the honest OSS ↔ core boundary.
- **[CONTRIBUTING](https://github.com/middag-io/middag-php-demo-standalone/blob/main/CONTRIBUTING.md)** —
  workflow, coding standards, and the quality gates.

## Two things worth seeing

- **Request validation, two ways.** The same help-desk create path is shown both with the
  declarative `rules()`-array `AbstractFormRequest` (`POST /api/tickets`) and with the
  typed-DTO `#[ValidatedDto]` attribute (`POST /api/tickets/dto`) — the framework's two
  equivalent validation styles, side by side.
- **Two persistence paradigms, one engine.** Active Record (Eloquent-style) and Data
  Mapper (Doctrine-style) over the same SQLite connection; the `/parity` page runs them
  side by side.

## The wider stack

The framework architecture, the ui PageContract system, and the OSS ↔ proprietary
boundary are documented at **[docs.middag.dev](https://docs.middag.dev)**.
