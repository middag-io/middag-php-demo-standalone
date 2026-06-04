# Security Policy

## Scope

`middag-io/demo-standalone` is an **example application** — a runnable harness that
proves the `middag-io/framework` and `middag-io/ui` OSS surface works standalone.
It is not a published library and is not intended for production use. Only the
`main` branch is maintained; there are no release branches.

Security-relevant behaviour the demo exercises (session auth, the `#[Auth]` gate,
CSRF, validated requests) is provided by `middag-io/framework`. A vulnerability in
that behaviour usually belongs upstream — but report it here if you are unsure and
we will route it.

## Reporting a Vulnerability

If you discover a security vulnerability, please report it **privately by email**
to **michael@middag.io**.

**Do not open public GitHub issues, pull requests, or discussions for security
problems.** Public disclosure before a fix is available puts users at risk.

Please include as much detail as you can:

- A description of the vulnerability and its potential impact.
- Steps to reproduce, or a proof of concept.
- The affected component (this demo, `middag-io/framework`, or `middag-io/ui`) and
  any relevant environment details.

### What to expect

- **Acknowledgement:** We aim to acknowledge your report within a few business days.
- **Coordinated disclosure:** We follow responsible (coordinated) disclosure and ask
  that you keep the report confidential until a fix has been released.
- **Credit:** If you would like recognition, we are happy to credit you once the
  issue is resolved. Let us know your preference.

Thank you for helping keep the MIDDAG OSS stack and its users safe.
