---
name: Bug report
about: Report a defect in the demo harness so it can be reproduced and fixed
title: "[Bug] "
labels: bug
assignees: ''
---

## Description

A clear and concise description of what the bug is.

> Note: behaviour bugs in the kernel, bus, forms, validation, or contracts usually
> belong upstream in `middag-io/framework` or `middag-io/ui`. File here if the demo
> wiring itself is wrong, or if you are unsure and we will route it.

## Steps to reproduce

1.
2.
3.

## Expected behavior

A clear and concise description of what you expected to happen.

## Actual behavior

A clear and concise description of what actually happened, including any error
messages, HTTP status codes, or stack traces.

## Environment

- PHP version (`^8.2`):
- `middag-io/framework` version:
- `middag-io/ui` version:
- How you ran it: `composer serve` / `composer test` / `bin/console`

## Relevant `composer check` / `composer test` output

```text
Paste the relevant output here.
```

## Additional context

Add any other context about the problem here (the emitted contract/JSON, the route,
related issues, screenshots, etc.).
