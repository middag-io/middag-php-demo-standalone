<!--
Thanks for contributing to middag-io/demo-standalone!
Please fill in the sections below. PRs target the `main` branch.
-->

## Summary

<!-- What does this PR change, and why? Keep it focused on a single concern. -->

## Related issues

<!-- Link issues this PR addresses. Use "Closes #123" to auto-close on merge. -->

Closes #

## Type of change

<!-- Check all that apply. These mirror the Conventional Commit type used in your commit messages. -->

- [ ] `feat` — a new demonstration / wiring
- [ ] `fix` — a bug fix
- [ ] `refactor` — a code change that neither fixes a bug nor adds a feature
- [ ] `docs` — documentation only
- [ ] `test` — adding or correcting tests
- [ ] `chore` — tooling, build, or dependency changes
- [ ] `perf` — a performance improvement
- [ ] `style` — formatting only (no behavior change)
- [ ] This PR introduces a breaking change (described in the commit body)

<!--
Version family (ADR-032): the `php-version-family-check / check` job fails a
breaking change unless a family MAJOR window is open, and from 2.0.0 it fails a
`feat` unless a family window is open. The window is the organization variable
PHP_LIBS_OPEN_RELEASE, set on the order of the product owner. Do not use `!` or a
`BREAKING CHANGE:` footer outside a family MAJOR window; on 0.x a break ships as
`feat:` described in the commit body. A PR that has to wait stays
open; keep it current by merging `main` into it (no rebase, no force-push).
See CONTRIBUTING.md#versioning.
-->

## Scope check

<!--
This repo is a demonstration of the published OSS packages, not a library. Behaviour
belongs upstream in middag-io/framework or middag-io/ui. Confirm this PR keeps the
demo a faithful, runnable example and does not work around an upstream bug silently.
-->

- [ ] This change demonstrates a real framework/ui capability (and is covered by a test)
- [ ] No host (Moodle/WordPress) or proprietary-core imports were added

## Checklist

- [ ] Targets the `main` branch
- [ ] `php-version-family-check / check` passes, or the PR waits for the family window
- [ ] `composer check` passes locally
- [ ] `composer test` passes locally
- [ ] Commits follow Conventional Commits
- [ ] No `Co-Authored-By` trailers in any commit
- [ ] New behavior is covered by a test
