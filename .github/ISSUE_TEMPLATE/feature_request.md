---
name: Feature request
about: Suggest something the demo should demonstrate
title: "[Feature] "
labels: enhancement
assignees: ""
---

## Problem / motivation

<!-- What framework/ui capability is under-demonstrated today? What would a reader
of this demo learn from the change? Describe the gap, not just the desired code. -->

## Proposed demonstration

<!-- Describe the wiring, route, command, or test you would like the demo to show.
Sketch the controller/command/contract if it helps. -->

## Scope check

`middag-io/demo-standalone` is a **demonstration** of the published OSS packages,
not a library. Its job is to stay a faithful, minimal, runnable example. New surface
lands when it shows a real `middag-io/framework` / `middag-io/ui` capability and is
covered by a test. Behaviour (kernel, bus, validation, contracts) belongs **upstream**
in those packages, not here.

- [ ] This demonstrates an existing OSS capability that the demo does not yet show.
- [ ] This is really a request for new behaviour in `middag-io/framework` / `middag-io/ui` (file it there).
- [ ] Not sure — please help me decide.

## Additional context

<!-- Links, prior art, related issues, the emitted JSON shape, or anything else that
helps us understand the request. -->
