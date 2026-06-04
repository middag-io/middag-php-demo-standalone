# E2E tests (Playwright)

Full-stack end-to-end tests for the MIDDAG help-desk demo: real PHP + Inertia +
the built React bundle, driven in a browser. Playwright seeds a fresh SQLite
database, boots `composer serve`, and exercises auth, navigation, and the core
ticket business flows on **desktop and mobile** viewports.

## Layout

```
e2e/
├── auth/
│   ├── login.spec.ts      # valid login, invalid credentials, protected-route redirect
│   └── logout.spec.ts     # logout via the account menu + session teardown
├── navigation/
│   └── navigation.spec.ts # dashboard, tickets list, ticket detail, agents
├── business/
│   └── critical-flow.spec.ts # required-field validation (i18n contract) + edit a ticket
├── helpers/
│   └── auth.ts            # login() helper + seeded credentials
├── fixtures/              # generated storage state (.auth/, gitignored)
├── auth.setup.ts          # logs in once, saves storage state for the authed projects
└── playwright.config.ts
```

**Projects:** `setup` (logs in → storage state) → `desktop` + `mobile` (authenticated
navigation/business specs, reusing the storage state) and `auth-flows` (login/logout,
which run without storage state because they test authentication itself).

## Prerequisites

- PHP 8.4 with `pdo_sqlite`, and Composer dependencies installed in the repo root
  (`composer install`).
- Node 22+.
- The React bundle built into `public/build` (gitignored). Build it once:

  ```bash
  cd ../ui && npm ci && npm run build:host
  ```

- Install the E2E dependencies + browser:

  ```bash
  npm ci
  npx playwright install chromium
  ```

The seeded login is `demo@middag.io` / `middag` (see `helpers/auth.ts`).

## Run locally

The `webServer` config boots the app automatically. Locally it **reuses** an
already-running `composer serve` on `:8080`; otherwise it seeds a fresh DB
(`composer install:db`) and starts the server itself.

```bash
npm test            # headless, all projects (desktop + mobile + auth flows)
npm run test:ui     # Playwright UI mode (watch + time-travel)
npm run test:headed # headed browser
npm run report      # open the last HTML report (also has traces on retry)
npm run codegen     # record selectors against http://localhost:8080
```

Target a subset:

```bash
npx playwright test --project=desktop
npx playwright test auth/
npx playwright test -g "validation"
```

## Reports & debugging

- **HTML report** after a run: `npm run report` (`playwright-report/`).
- On failure: a **screenshot** and **video** are captured; on the first retry a
  **trace** is recorded — open it from the report or with `npx playwright show-trace <trace.zip>`.

## CI

`.github/workflows/e2e.yml` runs the suite on push/PR: installs PHP + Composer,
builds the React bundle, installs Chromium, then runs `npm test` (CI starts a
freshly seeded server, no reuse). The HTML report is uploaded as an artifact.

## Selector conventions

Resilient, accessibility-first selectors only — `getByRole`, `getByLabel`,
`getByText` on contract-driven titles/labels. No CSS-class or DOM-structure
coupling (the `@middag-io/react` markup can change). Label regexes are anchored
(`/^password/i`) so a "Show password" toggle does not collide with the field.

## Coverage — suggested extensions

The current suite covers the critical happy/error paths. To deepen coverage:

1. **Complete the create wizard end-to-end** — the new-ticket form is a two-step
   wizard whose Customer field is an async entity-picker. Drive it: fill Subject →
   open the Customer combobox → pick a seeded customer → **Continue** → confirm on
   the Schedule step → assert the new ticket lands in the queue. (Today we cover the
   wizard's *validation* path; the full create path is the main gap.)
2. **In-app menu navigation** — click the sidebar nav links (not just `goto`),
   opening the mobile sidebar (`Toggle Sidebar`) first on the mobile project.
3. **More business flows** — ticket status transitions, adding a comment, agent
   assignment, and the SLA-escalation surface.
4. **The invalid-credentials toast** — assert the transient "Invalid credentials"
   flash (needs catching it before it auto-dismisses, e.g. via an `expect.poll` or
   intercepting the flash prop).
5. **Accessibility** — fold in `@axe-core/playwright` for per-page a11y assertions.
6. **Visual regression** — `toHaveScreenshot()` baselines for key pages.
7. **API-level checks** — assert the `/api/tickets/dto` 422 envelope shape directly
   (the structured `{message,key,domain,params}` i18n contract) alongside the UI test.
