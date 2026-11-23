# Security Policy

## Scope

`middag-io/demo-standalone` is an **example application** — a runnable harness that
proves the `middag-io/framework` and `middag-io/ui` OSS surface works standalone.
It is not a published library and is not intended for production use.

Security-relevant behaviour the demo exercises (session auth, the `#[Auth]` gate,
CSRF, validated requests) is provided by `middag-io/framework`. A vulnerability in
that behaviour usually belongs upstream — but report it here if you are unsure and
we will route it.

## Supported versions

The `0.x` line is pre-stable and unsupported: it gets no backports or security fixes, and the move to `2.0.0` is a clean cut.

From `2.0.0`, only the latest release is supported.

## Reporting a vulnerability

Do not open public issues, pull requests or discussions for security problems.

Report privately through either:

- GitHub private vulnerability reporting: **Report a vulnerability** under the repository's **Security** tab, or
- email to **security@middag.io**.

Include a description and its impact, steps to reproduce or a proof of concept, and the affected versions.

## What to expect

- **Acknowledgement** within 5 business days.
- **Triage:** we confirm whether the report is accepted and share our plan for a fix. There is no fixed deadline for the fix.
- **Coordinated disclosure:** please keep the report confidential until a fix is released.
- **Credit:** we credit reporters who want it once the issue is resolved.
