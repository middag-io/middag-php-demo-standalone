# Changelog

All notable changes to `middag-io/demo-standalone` will be documented in this
file. release-please regenerates this file from Conventional Commits on every
release tag — manual entries below the unreleased section will be overwritten.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.0](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.4.0...v0.5.0) (2026-06-26)


### Features

* **docker:** bake React UI bundle into the image ([0673039](https://github.com/middag-io/middag-php-demo-standalone/commit/06730390068f8ebdb417922adc796448560f0542))
* **docker:** bake React UI bundle into the image ([60da422](https://github.com/middag-io/middag-php-demo-standalone/commit/60da4225363e148415a201b9f508fa82602c246f))

## [0.4.0](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.3.1...v0.4.0) (2026-06-26)


### Features

* **ci:** publish multi-arch docker image (amd64 + arm64) ([a87f832](https://github.com/middag-io/middag-php-demo-standalone/commit/a87f832289bcdc95c21c949642fa5ed2a1b7078d))
* **ci:** publish multi-arch docker image (amd64 + arm64) ([11430f4](https://github.com/middag-io/middag-php-demo-standalone/commit/11430f4819f67891ca771738a7c906e7df385a27))

## [0.3.1](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.3.0...v0.3.1) (2026-06-26)


### Bug Fixes

* **deps:** release middag-io/framework 0.11.3 bump ([1b5f7c6](https://github.com/middag-io/middag-php-demo-standalone/commit/1b5f7c620c82b1164842832d142ddf1ebab50146))

## [0.3.0](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.2.0...v0.3.0) (2026-06-22)


### Features

* **bootstrap:** adopt host component context in standalone demo ([e2ca4d5](https://github.com/middag-io/middag-php-demo-standalone/commit/e2ca4d5fd195990e555f432f0ba2eb37171938f8))
* **deps:** adopt framework 0.11.0 validation-error i18n contract ([2e98703](https://github.com/middag-io/middag-php-demo-standalone/commit/2e98703eecf6dafab00e2c7aee2f64b2e88e0a52))


### Bug Fixes

* **deps:** bump framework to 0.10.5 (blank input coerced to null) ([52c5dfe](https://github.com/middag-io/middag-php-demo-standalone/commit/52c5dfe0e1e14883b20d0b9c1dc8f19f841e8def))
* **deps:** require framework 0.10.4 so a clean install boots ([1c68ddd](https://github.com/middag-io/middag-php-demo-standalone/commit/1c68ddd7e26e302a16fbf22e6856b7122cb8cfeb))
* **deps:** upgrade to ui 1.0 and framework 0.10.3 ([1feb26b](https://github.com/middag-io/middag-php-demo-standalone/commit/1feb26b3bfcde984a084b4f1f5b9f857183178c8))
* **qa:** track phpstan-baseline.neon so a clean checkout passes ([4260a93](https://github.com/middag-io/middag-php-demo-standalone/commit/4260a934bdbbf346aa339b2b6f34f46117c70017))

## [0.2.0](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.1.0...v0.2.0) (2026-06-03)


### Features

* absorb core and UI library updates ([9e4d04d](https://github.com/middag-io/middag-php-demo-standalone/commit/9e4d04dddb1b8166accb0539d7433d51beb24855))
* absorb framework v0.5.0 OSS fixes + contract-driven frontend ([1d5f034](https://github.com/middag-io/middag-php-demo-standalone/commit/1d5f034aa3c1da9f3e6bc90b80e3b7d39fe75812))
* absorb framework v0.5.1 residual fixes (G2/G3/M10) ([63c2981](https://github.com/middag-io/middag-php-demo-standalone/commit/63c29810da7fce6637033baf80e08155c09bddd3))
* add convenience commands for dev workflow ([0059f02](https://github.com/middag-io/middag-php-demo-standalone/commit/0059f020c85014e84e4eb9a50a0189c4a73842cf))
* add help-desk domain data layer (dual-ORM tickets + agents/customers/SLA) ([2a69d26](https://github.com/middag-io/middag-php-demo-standalone/commit/2a69d266ad94f6b92d5bf42eb346367928770f5e))
* add help-desk engine write-path (ticket commands + entity sources) ([7cb64e2](https://github.com/middag-io/middag-php-demo-standalone/commit/7cb64e23793a51606474c679bbdb93a1decc3be8))
* agents (sidebar + capability gate) and customers (card_grid) pages ([133b3cb](https://github.com/middag-io/middag-php-demo-standalone/commit/133b3cb0b1d77220e5dbcd2af3c68a0ce81a0743))
* align SharedProps + dense_table to @middag-io/react contract ([7d8a6c8](https://github.com/middag-io/middag-php-demo-standalone/commit/7d8a6c843716bc4f667b405e074e7d3a5edc038a))
* async SLA escalation for high/urgent tickets ([b550078](https://github.com/middag-io/middag-php-demo-standalone/commit/b55007899cf7f61d85bf9accc2e890c1a3e5536a))
* close 3 demo gaps — link, html + custom sparkline cells on /agents ([3092ddf](https://github.com/middag-io/middag-php-demo-standalone/commit/3092ddf4b82aacfa63467c2922fca2e7ff155cee))
* close the wizard gap — guided server-driven 2-step ticket create ([08299a7](https://github.com/middag-io/middag-php-demo-standalone/commit/08299a7ba9f96faded2dba85d2efc529bb0ea963))
* contract-driven ui/ React app rendering the live backend ([d5dd3f2](https://github.com/middag-io/middag-php-demo-standalone/commit/d5dd3f23e2ab9c0661252e7a37e236370eaec193))
* dockerize standalone demo (web + worker, optional redis/ui) ([5622476](https://github.com/middag-io/middag-php-demo-standalone/commit/56224768f658aa4e6f16ba93a4bd20eda9f1b996))
* dual-ORM parity proof + help/about pages ([846044f](https://github.com/middag-io/middag-php-demo-standalone/commit/846044fcfa5730879149adc4748def0fbe59a6d8))
* gate JSON API with session #[Auth] ([8a35f43](https://github.com/middag-io/middag-php-demo-standalone/commit/8a35f434ce87794afb344a0308f2b0a2df78b365))
* help-desk dashboard (dashboard layout + custom chart block) ([54c2fda](https://github.com/middag-io/middag-php-demo-standalone/commit/54c2fda3bd2b4d845ef1f3c77a8f94e08c4190fb))
* help-desk ticket list + detail contract pages ([6e09b2d](https://github.com/middag-io/middag-php-demo-standalone/commit/6e09b2d8fb1462821af754817b21da9939a3e787))
* help-desk ticket write path (form pipeline + entity pickers) ([2c952bf](https://github.com/middag-io/middag-php-demo-standalone/commit/2c952bf25c320ad0f45af72a51ec1a8d80c6a8ce))
* rebuild standalone demo for framework 0.4.0 OSS surface ([33bd78a](https://github.com/middag-io/middag-php-demo-standalone/commit/33bd78afa06138ae6417ab68c60644b5032d73c7))
* render login + forms through the canonical framework form pipeline ([3dbfd44](https://github.com/middag-io/middag-php-demo-standalone/commit/3dbfd4415edc3210599dbf93099cd5dbc45615c2))
* retire demo_tasks — promote dashboard to /, ticket-centric throughout ([eece7de](https://github.com/middag-io/middag-php-demo-standalone/commit/eece7ded96f54e71a218f87c2f0a635870dfec6b))
* rich ticket detail — workflow_progress + tabbed detail/activity/SLA ([89a77ff](https://github.com/middag-io/middag-php-demo-standalone/commit/89a77ff02da5a5de9b66b1126309b21705d1edc2))
* self-verifying coverage manifest + /coverage page ([6d63e80](https://github.com/middag-io/middag-php-demo-standalone/commit/6d63e80f52f9fff659aad0433278ce02b80e78a3))
* standalone harness for ui 0.5.0 + framework plumbing + Symfony showcase ([06fa3eb](https://github.com/middag-io/middag-php-demo-standalone/commit/06fa3eb86781c2313db84aa902aadf4de1336121))
* ticket list as cell-renderer showcase (rich_status/annotated/link_group) ([ba4f965](https://github.com/middag-io/middag-php-demo-standalone/commit/ba4f965fef47bec88ce8510cf4fc4f78ac4a7a3d))
* **ui:** custom chart block + tabs alias; drop generic mocks ([0664f43](https://github.com/middag-io/middag-php-demo-standalone/commit/0664f434f2334e515556071068079ee927f4cb5c))
* **ui:** restore standalone dev app — replays real captured contracts, ticket-centric ([d6223d2](https://github.com/middag-io/middag-php-demo-standalone/commit/d6223d2c42e8054e710bb641b98c7f6e534494c6))
* **ui:** wire logoutUrl shared prop for the shell account menu ([3229260](https://github.com/middag-io/middag-php-demo-standalone/commit/32292607c32c3783351c63509c98bed39a4661e3))
* update core and UI dependencies ([d11b48e](https://github.com/middag-io/middag-php-demo-standalone/commit/d11b48e0e897bb45ac62ff8c1a3ce7f648da6a8e))


### Bug Fixes

* adapt demo to relocated ui ContractEnvelopeInterface namespace ([a1bf9d8](https://github.com/middag-io/middag-php-demo-standalone/commit/a1bf9d8708b6bb0d0791bd8fa8b3b003cd59b13e))
* persist estimate_minutes/notify/parent_task + repair task edit ([2eebfe7](https://github.com/middag-io/middag-php-demo-standalone/commit/2eebfe7c0a7b47bb6483cf2d82502c72f23c0106))
* sidebar pages use the 'main' region; link_group tags get hrefs ([fb73e4e](https://github.com/middag-io/middag-php-demo-standalone/commit/fb73e4e289386bf792769362992f1c0a35db2935))
* track src/Coverage/manifest.php (un-ignore from coverage/ pattern) ([d003131](https://github.com/middag-io/middag-php-demo-standalone/commit/d003131c1b4447117fec0eccaf6a84a7cda27c82))
* **ui:** chromeless centered login — custom AuthShell, no app nav for anon ([3d6e8ad](https://github.com/middag-io/middag-php-demo-standalone/commit/3d6e8adf7a4dfb59966c573053b0e26c4cda805b))
* **ui:** Dashboard nav 404'd — link to / (the real route), not /dashboard ([8f6d5f0](https://github.com/middag-io/middag-php-demo-standalone/commit/8f6d5f08665ac6529ecdb2633194ce72485c0512))
* **ui:** de-cramp /agents (full-width), fix markdown code-block contrast, refresh fixtures ([f5500a3](https://github.com/middag-io/middag-php-demo-standalone/commit/f5500a3c339e7d71333eb9fd4690321717402918))
* **ui:** dead list search/pagination — opt tables into clientSide ([bef0798](https://github.com/middag-io/middag-php-demo-standalone/commit/bef07989eabc04325594cb4ff0688feafe2f282a))
* **ui:** edit() seeds entity_picker initialOption (customer/assignee labels) ([c8f30ec](https://github.com/middag-io/middag-php-demo-standalone/commit/c8f30ece36cf591fba003a2376c55c4174bbc697))
* **ui:** Help "Open dashboard" CTA also targeted the dead /dashboard route ([e63a111](https://github.com/middag-io/middag-php-demo-standalone/commit/e63a1115ae32ff44c016296820e192ce712271e8))
* **ui:** metric cards render in the dashboard grid, not stacked full-width ([0c0c55c](https://github.com/middag-io/middag-php-demo-standalone/commit/0c0c55c908a8d8b2e30dc509c39287b9d0e79fda))
* **ui:** New-ticket action, sortable columns, spaced detail tags ([439abd3](https://github.com/middag-io/middag-php-demo-standalone/commit/439abd3cbc97b05f011e6a7ccb017e32d76ff22a))
* **ui:** readable ticket tags — custom tag_chips cell (link_group renders icons) ([251f4cb](https://github.com/middag-io/middag-php-demo-standalone/commit/251f4cbad8637dde4e33b8a6182d8029ec551f2b))
* **ui:** wizard step 2 — fix Select crash, server-validate, real labels ([089a35c](https://github.com/middag-io/middag-php-demo-standalone/commit/089a35c60df88f9c30abe61a053c3382b4f9e2c8))

## 0.1.0 (2026-05-26)


### Features

* middag-php-demo-standalone — reference scaffold (D-053) ([0fbc2bf](https://github.com/middag-io/middag-php-demo-standalone/commit/0fbc2bfc7af56ed7ec0ec7fceb7114b6d4481038))


### Bug Fixes

* **bootstrap:** wire CommandBus + AsyncCommandDispatcher via lazy container ([794d549](https://github.com/middag-io/middag-php-demo-standalone/commit/794d54956057f6e2c00333e6286ed401f3fa4c17))


### Miscellaneous Chores

* force first release as v0.1.0 ([1fbd503](https://github.com/middag-io/middag-php-demo-standalone/commit/1fbd503d8c69856982dfa86a2b9b75e41b57ce13))

## [Unreleased]

### Notes

- Demo scaffold validating `middag-io/framework` + `middag-io/ui` standalone
  consumption (no Moodle/WP host). Surfaces packaging gaps for OSS publication.
- Pre-1.0 — no tags cut yet. release-please bootstrapped 2026-05-24 (D69);
  first release PR will replace this section with a generated entry.
