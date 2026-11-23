# Changelog

All notable changes to `middag-io/demo-standalone` will be documented in this
file. release-please regenerates this file from Conventional Commits on every
release tag — manual entries below the unreleased section will be overwritten.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.8](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.5.7...v0.5.8) (2026-11-23)


### Features

* adopt ui 1.14 contracts in the demo ([c8fa5c1](https://github.com/middag-io/middag-php-demo-standalone/commit/c8fa5c1807c162d17549a4cffb68271a212495ca))


### Documentation

* match the README ranges to framework 1.22, ui 1.13 and react 0.48 ([6ab78ee](https://github.com/middag-io/middag-php-demo-standalone/commit/6ab78ee5d1ffed1aae5cdbb16f870a537ed72176))

### Consumer impact

- Runs on `middag-io/ui` 1.14.0 and `middag-io/framework` 1.24.0 (floors `^1.14` and `^1.24`).
- The demo navigation registry follows the ui 1.14 `NavigationRegistryInterface` (Translatable labels, styling fields, external `href` on items), checked by a parameter-parity test.
- Validation errors in the 422 body and the flashed `errors` prop carry the structured `FieldMessage` shape; `params` appears only when non-empty.
- The coverage map drops the removed `PageResources`, `PageResourcesInterface`, `Branding` and `IndexIntrospectionInterface`.

## [0.5.7](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.5.6...v0.5.7) (2026-11-23)


### Features

* adopt the ui 1.13 shared props, navigation tree and envelope v2 ([cb40539](https://github.com/middag-io/middag-php-demo-standalone/commit/cb4053940a7379e1be2b9a01b344279ba4da01c2))


### Documentation

* match the README ranges and the demo support rule ([32a1da3](https://github.com/middag-io/middag-php-demo-standalone/commit/32a1da377e8542bb6a7a94c64dae001fb5dfc667))

### Consumer impact

No consumer impact: the demo is a harness. It now requires `middag-io/ui` ^1.13, `middag-io/framework` ^1.22 and `@middag-io/react` ^0.48.0.

## [0.5.6](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.5.5...v0.5.6) (2026-11-23)


### Refactoring

* feed form panels from ui form schema nodes ([c4ff5ad](https://github.com/middag-io/middag-php-demo-standalone/commit/c4ff5ad3f156c2b3aa119966c7c87f9fc5ad3a22))
* pass framework form nodes straight to formPanel ([1c1ea6f](https://github.com/middag-io/middag-php-demo-standalone/commit/1c1ea6faa4810cd41ce7c4cfaccc8fb453016ab3))


### Documentation

* align SECURITY.md with the uniform security policy ([ccbb387](https://github.com/middag-io/middag-php-demo-standalone/commit/ccbb38715d61185883552b7963a3c19823ff8189))
* apply the version-family support policy ([f1c19ad](https://github.com/middag-io/middag-php-demo-standalone/commit/f1c19ad42abe50b790f1d739405ce608f8623e2a))
* regenerate COVERAGE.md for framework 1.21 and ui 1.11 ([42884f8](https://github.com/middag-io/middag-php-demo-standalone/commit/42884f8b4dec764defb8b0cbfe349ebef0c40a72))

### Consumer impact

No consumer impact: the demo is a harness. It now requires `middag-io/framework` ^1.21 and `middag-io/ui` ^1.11 and feeds `formPanel()` with the framework form nodes directly.

## [0.5.5](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.5.4...v0.5.5) (2026-11-22)


### Documentation

* describe internal references without naming private repositories ([3e98e8b](https://github.com/middag-io/middag-php-demo-standalone/commit/3e98e8b81d06b7048922e95ac1e4be71e476399c))


### Consumer impact

No consumer impact: documentation-only release.

## [0.5.4](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.5.3...v0.5.4) (2026-11-16)


### Features

* **auth:** seed an agent login without the supervisor capability ([dc599e7](https://github.com/middag-io/middag-php-demo-standalone/commit/dc599e713d77e60d2fca832109ebfcceeaf74f6f))
* build the agents index page with the ui CrudBuilder ([f742656](https://github.com/middag-io/middag-php-demo-standalone/commit/f7426565a8e69d3a8153ddc574547376955758ab))
* **form:** lay out the ticket form in sections and groups ([7901ff5](https://github.com/middag-io/middag-php-demo-standalone/commit/7901ff555800413a06d213d6aa57df7e894068c2))
* **mail:** read the captured outbox over a fault-mode route ([4a86a85](https://github.com/middag-io/middag-php-demo-standalone/commit/4a86a85a6aa8d2f26b5cf08c77e450a76944f096))
* **tickets:** upload, attach and download ticket attachments ([39518fb](https://github.com/middag-io/middag-php-demo-standalone/commit/39518fbdcb5aee8bc9d53053274c285f431463d4))
* **ui:** edit agents in the editable side panel ([e815800](https://github.com/middag-io/middag-php-demo-standalone/commit/e81580075a1b7cf43c60380f522d74f99e0cc664))


### Bug Fixes

* **agents:** keep supervisor-only fields out of non-supervisor rows ([b987462](https://github.com/middag-io/middag-php-demo-standalone/commit/b987462a622cfa9c6b17a063ae33dcd3d44ae7e1))
* **attachments:** bind a pending upload to the user who made it ([17979d9](https://github.com/middag-io/middag-php-demo-standalone/commit/17979d9199cc6a60e944b4aff5e96575fde79a6b))
* **attachments:** cap the attached files per ticket ([f1517b2](https://github.com/middag-io/middag-php-demo-standalone/commit/f1517b28a332a2a7d0d89e5be9b8567d3127ebcb))
* **attachments:** expire pending uploads and cap them per user ([7adea80](https://github.com/middag-io/middag-php-demo-standalone/commit/7adea80bac9969b7dbe38a0fd8a3ba66c359b7c8))
* **attachments:** let the uploader download their own pending file ([b2de42d](https://github.com/middag-io/middag-php-demo-standalone/commit/b2de42d941e3b223667b01c68577761794a71aa2))
* **attachments:** serve only attached files on download ([4240254](https://github.com/middag-io/middag-php-demo-standalone/commit/4240254034e4d654f7aca4c0eb9bd841e1e83311))
* **docker:** migrate an existing database on every web boot ([cd1709d](https://github.com/middag-io/middag-php-demo-standalone/commit/cd1709d0b06b54843c80c5fc39318e4546028c4f))
* **docker:** pass the GitHub Packages token to compose builds ([0bdaa10](https://github.com/middag-io/middag-php-demo-standalone/commit/0bdaa10b8658510d673c18fb131142f87082dc23))


### Documentation

* align DOCKER.md with the image ([103f356](https://github.com/middag-io/middag-php-demo-standalone/commit/103f35618b48966da8e6ee5667e1291d2d3884b1))
* **coverage:** record CrudBuilder as covered by the agents index ([8e2ff89](https://github.com/middag-io/middag-php-demo-standalone/commit/8e2ff8975d60d625fade3db80d55b454fe05612a))
* **coverage:** record the ui 1.9.0 poll API and the new minimums ([c690ad1](https://github.com/middag-io/middag-php-demo-standalone/commit/c690ad154d6530593c19753d9211777fe776bfc6))
* **coverage:** regenerate the coverage map for this batch ([72255b1](https://github.com/middag-io/middag-php-demo-standalone/commit/72255b1cae303972a208a0aa2bafda8efecd350a))
* point security reports to security@middag.io ([cb66087](https://github.com/middag-io/middag-php-demo-standalone/commit/cb660878b269e8cc541a955f5d11464e2fd993cc))
* **readme:** state requirements, help channels and the internal surface ([96d3c6d](https://github.com/middag-io/middag-php-demo-standalone/commit/96d3c6d46ebeec1084a2cf6559216e9694f2581d))

## [0.5.3](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.5.2...v0.5.3) (2026-11-16)


### Features

* build the HTTP kernel and the forms through framework 1.20 seams ([c2fd5c0](https://github.com/middag-io/middag-php-demo-standalone/commit/c2fd5c04261ee0fb6a396b6860eaf97c0d16ff42))
* **deps:** bump middag-io/framework to ^1.15 and ui to ^1.6 ([b15ede2](https://github.com/middag-io/middag-php-demo-standalone/commit/b15ede2af1fced39e0d31c8366c1a52803b17c02))
* **deps:** raise the platform to PHP 8.3 and Symfony 7.4 or 8.1 ([e69637a](https://github.com/middag-io/middag-php-demo-standalone/commit/e69637a046471af2516842d60cd02b359e42b6dd))
* exercise the framework bus, runtime, mail, files and schema seams ([8e3c747](https://github.com/middag-io/middag-php-demo-standalone/commit/8e3c74776ce2a7c2878d26087e666b003fbc94f0))
* exercise the framework HTTP, security and error seams ([fe14efa](https://github.com/middag-io/middag-php-demo-standalone/commit/fe14efa740e8d755e3e455cea01b215dbc7c4194))
* exercise the framework kernel and logging seams ([377758a](https://github.com/middag-io/middag-php-demo-standalone/commit/377758adbc365047570fd581ff042e30cc237659))
* exercise the framework persistence relations and entity types ([c571b25](https://github.com/middag-io/middag-php-demo-standalone/commit/c571b257642834702c71ed02bc67977f4f0b262b))
* exercise the ui page, action, navigation and table seams ([248a9ca](https://github.com/middag-io/middag-php-demo-standalone/commit/248a9ca0940b8cfbe5cf439a415b1311094e5171))
* filter, sort and page the ticket queue on the server ([7fd8240](https://github.com/middag-io/middag-php-demo-standalone/commit/7fd8240b156814285acf4081635ed8c67dba72b4))
* generate COVERAGE.md per [@api](https://github.com/api) symbol with a drift test ([a3f64c0](https://github.com/middag-io/middag-php-demo-standalone/commit/a3f64c02e10c57e6ab51c4d9d69a761285b12b54))
* **ui:** render with @middag-io/react 0.44.1 ([3d7938d](https://github.com/middag-io/middag-php-demo-standalone/commit/3d7938df363898776cdae0e97a963eedfbb2b9c3))
* **ui:** render with @middag-io/react 0.45.0 ([8d3be73](https://github.com/middag-io/middag-php-demo-standalone/commit/8d3be7391f60dc3081120452e580f48c542cf3af))


### Bug Fixes

* **deps:** update symfony/http-foundation to 8.1.11 ([5dc2526](https://github.com/middag-io/middag-php-demo-standalone/commit/5dc2526d5d08cea1d92cf1dc1f26fdf9f9f92f75))
* import the ui form contracts from their Contract namespace ([7f7d73b](https://github.com/middag-io/middag-php-demo-standalone/commit/7f7d73b9482ade058750dd5e594c205e14b60011))
* track coverage/api.php ([2ac76b7](https://github.com/middag-io/middag-php-demo-standalone/commit/2ac76b78b042aa95b2471ad72d9ecd21c900fb80))


### Refactoring

* apply the Rector PHP 8.3 set ([dc3c664](https://github.com/middag-io/middag-php-demo-standalone/commit/dc3c664157f4cd37fdf82804cdc2ffd39351fefd))
* realign src/ with the 3-tier concern layout ([af00b8b](https://github.com/middag-io/middag-php-demo-standalone/commit/af00b8b2dd8eb100228a153bed64572f392a6b0c))


### Documentation

* align versioning docs with the version family (ADR-032) ([d755116](https://github.com/middag-io/middag-php-demo-standalone/commit/d755116147a2cf202a66e6186e96ad97344fcee0))
* canonical AGENTS.md, CLAUDE.md symlink (ADR-017) ([519380c](https://github.com/middag-io/middag-php-demo-standalone/commit/519380c3897b7315b60c81924b7f01f8ca26ca28))
* drop pre-v2 deadline text ([8a89c2a](https://github.com/middag-io/middag-php-demo-standalone/commit/8a89c2ab1c94bada8bb5fa9d41d50bc4f0bcc53c))
* match the breaking-change guidance to the version family check ([a347b0c](https://github.com/middag-io/middag-php-demo-standalone/commit/a347b0c2dd9ea8d9f03c01042075f507e7c6e37c))
* point the architecture docs to the hub and state the supported versions ([0e3c9ab](https://github.com/middag-io/middag-php-demo-standalone/commit/0e3c9ab543f3aac5756d40fc006488702b0e577d))
* state that the demo is an example application and add help and status ([3577601](https://github.com/middag-io/middag-php-demo-standalone/commit/357760124946542e77ef8f5f8fb6bc97a9fe4193))

## [0.5.2](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.5.1...v0.5.2) (2026-07-27)


### Miscellaneous

* **deps:** phpstan 2.2.6 + rector 2.5.8 ([#24](https://github.com/middag-io/middag-php-demo-standalone/issues/24)) ([c606d73](https://github.com/middag-io/middag-php-demo-standalone/commit/c606d732359694797b61345876dd0f08650b5b25))

## [0.5.1](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.5.0...v0.5.1) (2026-06-30)


### Documentation

* fix stale framework version and duplicate changelog entries ([68be0d6](https://github.com/middag-io/middag-php-demo-standalone/commit/68be0d654e5527733ff63141ad73784aefd275f5))


### Miscellaneous

* release 0.5.1 ([da68ca8](https://github.com/middag-io/middag-php-demo-standalone/commit/da68ca84816fb02d54ba5d4e75e51c45bdd1b3b6))

## [0.5.0](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.4.0...v0.5.0) (2026-06-26)


### Features

* **docker:** bake React UI bundle into the image ([0673039](https://github.com/middag-io/middag-php-demo-standalone/commit/06730390068f8ebdb417922adc796448560f0542))

## [0.4.0](https://github.com/middag-io/middag-php-demo-standalone/compare/v0.3.1...v0.4.0) (2026-06-26)


### Features

* **ci:** publish multi-arch docker image (amd64 + arm64) ([a87f832](https://github.com/middag-io/middag-php-demo-standalone/commit/a87f832289bcdc95c21c949642fa5ed2a1b7078d))

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
