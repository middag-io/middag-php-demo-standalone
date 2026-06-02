# VERIFY-FINDINGS.md — demo contracts vs `@middag-io/react` 0.26 + framework 0.7.0

> Verify-first pass before building the `ui/` layer. Captured real contracts live
> (`php bin/console debug:request`) + read lib `dist-lib`/`src` + framework/ui PHP source.
> **Supersedes stale assumptions in `FRONTEND-PLAN.md`** (written against lib 0.24).
>
> Lib installed: `@middag-io/react` **0.26.0**. Inertia client `@inertiajs/react` **^3.0.0**.
> Generated 2026-06-02. All claims file:line-cited.

---

## Implementation status (2026-06-02)

- **D-1 — RESOLVED (framework + demo).** Framework `InertiaFieldMapper`/`InertiaRenderer` now emit the canonical `FormFieldNode` (node `key`; string `label`; `[{value,label}]` options; discrete `visible_when`/`required_when` FormCondition props with `eq→equals`; type-split numeric/length bounds; `helpText`/`placeholder`/`readOnly`; field defaults seeded into `values`). Demo login `form_panel` fixed to lowercase `text`/`password` + node `key`. Framework 383 tests green; demo 68 green (`FormTest` now asserts the full canonical task-form shape). Login contract re-captured canonical.
- **D-2 entity_picker — PARTIAL.** Field-node shape fixed; `entityDisplayField` lifted. Still TODO: `autocompleteHref` (source→URL host bridge) + response-envelope alignment (`{data:{options}}` vs lib unwrap). Picker renders with static options only until then.
- **D-3 — OPEN (minor, type-only).** `navigation.footer`, `theme`/`locale` deltas not yet applied (runtime-safe). `product` shell still falls back to ImmersiveShell in free.

## Verdict summary

| Dim | Topic | Verdict | One line |
|---|---|---|---|
| **D-1** | form_panel field schema | **NEEDS_BACKEND_CHANGE (framework gap)** | Lib free **already ships `form_panel`**; the framework form renderer emits a field-node shape the lib's `FormField` cannot read (4 mismatches), and the login schema uses wrong component names. |
| **D-2** | entity_picker + rowActions | **NEEDS_NORMALIZATION** | Action/rowActions = byte-for-byte COMPAT and **B-2 already done** on the real list. `entity_picker` is broken two ways (no `autocompleteHref`, response envelope outside lib's unwrap chain). |
| **D-3** | SharedProps + shell | **NEEDS_BACKEND_CHANGE (type-only, runtime-safe)** | `auth` (authed) MATCH. `navigation.footer` missing, `theme`/`locale` type-required but provider-defaulted. `product` shell is **PRO-only** → free falls back to `ImmersiveShell`. |

**Two stale plan assumptions overturned:**
1. ~~A-1/C-1: "form_panel is PRO, write a custom free block"~~ → **FALSE.** Lib free registers `form_panel` (lazy heavy deps): `register-defaults.ts:73`. No custom block needed; the demo even re-registers it (`ui/src/app/register.ts:76`). The work is **not** writing a block — it's making the backend emit a schema the lib's block understands.
2. ~~B-2: "add id + rowActions to `TaskController::index`"~~ → **ALREADY DONE.** `TaskController::index` emits per-row `id` (`:67`) + edit/delete Actions + `rowHref` (`:80-118`). The captured "gap" was `UiController::page()` — the public *sample* endpoint (`:35-54`), not the real list.

---

## D-1 — form_panel field schema (the real blocker)

The lib's `FormPanelBlock` → `FormField` (`src/base/form/FormField.tsx`) consumes a **`FormFieldNode`**:
`{ kind, key, component, props:{ label:string, required?, placeholder?, options?:[{value,label}], visible_when?:FormCondition, required_when?:FormCondition, ... } }`
- node-level **`key`** (`FormField.tsx:137`), `props.label` as **string** (`:173`), `props.options` as **list** (`:298`), conditions as **`props.visible_when`/`required_when` objects** with operator ∈ `equals|not_equals|in|not_in`.
- field `component` resolved against a **lowercase** registry: `text,email,url,password,otp,textarea,int,float,slider,select,native_select,multiselect,radio,…` (`register-default-fields.ts:46-81`). Unknown → `"Unknown field component: {component}"` (`FormField.tsx:551`).

### What the framework actually emits (task form)
`InertiaRenderer` node = `{kind, component, props}` (`framework/src/Form/Renderer/InertiaRenderer.php:94-98`) with **NO node-level `key`**. `InertiaFieldMapper::buildProps` (`InertiaFieldMapper.php:90-107`) emits:
`{ name, label, help, required, default, attributes:{…}, options, conditions:[{field,op,value,kind}] }`.
- `component` = `FieldType->value` → `text,textarea,select,radio,date,int,switch,entity_picker` (`InertiaFieldMapper.php:57`). **These lowercase strings DO line up with the lib registry.** ✅
- **Mismatch 1 — no node `key`:** lib reads `field.key`; framework puts identity in `props.name`.
- **Mismatch 2 — conditions:** framework `props.conditions: [{field, op:'eq', value, kind:'visible_when'}]` (array); lib wants `props.visible_when: {field, operator:'equals', value}` (object). Key name **and** operator vocab differ (`eq` vs `equals`).
- **Mismatch 3 — label:** framework serializes a **Translatable object** `{key, domain}` (`Translatable.php:50-62`); lib renders `props.label` as a **raw string**.
- **Mismatch 4 — options:** framework emits the assoc **map** `{low:'Low',…}` verbatim (`SelectField.php:30-34` → `InertiaFieldMapper.php:96`); lib wants **`[{value,label}]`** (`form.d.ts:27-30`, `FormField.tsx:298`).

### Login schema (hand-built, captured live)
`AuthController::loginForm` emits `component:"TextField"`/`"PasswordField"` (capitalized) + `props:{name,label,required}`, `values:[]`. Lib registry keys are lowercase `text`/`password` → capitalized names hit the **Unknown-component** arm. **Demo-local bug**, independent of the framework gap.

### Note
`ui/src/pages/task-form.ts` (dev mock fixture) is **already lib-canonical** (`{kind,key,component:'text',props:{visible_when:{operator:'equals'}}}`) — but it is **not** what the live framework produces. Someone hand-wrote the right target; the backend doesn't emit it.

---

## D-2 — entity_picker + rowActions

### Actions / rowActions → COMPAT (and B-2 already shipped)
PHP `Action::jsonSerialize` (`middag-php-ui/src/Action/Action.php:44-74`) + `ActionTarget` (`:65-82`) emit `{id,label,target:{kind:link|route|request,…},intent[,icon,confirmation,…]}` — **byte-for-byte** = lib `contract-types.d.ts:15-29,88-98`. `DenseTableBlockData.rowActions: ConditionalAction[]` (`data-display.d.ts:80-95`). `RegionBuilder->denseTable(key,columns,rows,data)` passes `id`/`rowHref`/`rowActions` through verbatim (`BlockBuilder.php:32-39`). `TaskController::index` already does all of it (`:67,:80-118`).
- **Optional cosmetic delta:** `UiController::page()` sample (`:35-54`) lacks per-row `id` + `rowActions` — add to make the validation sample mirror the real list. Not required for the user flow.

### entity_picker → BROKEN (two ways)
- Demo `GET /api/entities/tasks` returns `{success:true, data:{options:[{value:int,label}]}}` (`TaskApiController.php:99-101` + `{success,data}` envelope; `TaskEntitySource.php:25-28`; `value` is an **int** PK).
- Lib `EntityPickerField.tsx`: fetches **only if `autocompleteHref` truthy** (`:78`); unwrap chain `json.items ?? json.data ?? json` (`:113`) — **`options` is not in it**; with the envelope the real array is two levels deeper.
- Framework `EntityPickerField` + `InertiaFieldMapper` **never emit `autocompleteHref`** (`framework/src/Form/Field/EntityPickerField.php:28-65`, `InertiaFieldMapper.php:90-107`) → lib `isAsync=false` → endpoint never called.
- **Fix needs backend:** emit `autocompleteHref` → `/api/entities/tasks`; return items as a top-level `data` array (drop the `options` wrapper) or align the lib unwrap. (Couples with D-1: the field-node shape is the same `{component,props}` vs lib `{kind,key,component,props}` problem.)

---

## D-3 — SharedProps + shell

Per-prop matrix (lib `shared-props.d.ts:63-76`, demo `DemoBootstrap.php:317-358`):

| Prop | Emitted | Lib expects | Result |
|---|---|---|---|
| `auth` (authed) | flat `{id,name,email,capabilities}` | `SharedPropsAuth` (req, non-null) | **MATCH** |
| `auth` (anon) | `null` | non-nullable type | type-MISMATCH; runtime-safe **iff** no `AuthProvider` on login (shells don't use it; `AuthProvider` has no null guard `auth.tsx:28`) |
| `navigation` | `{tree,activeKey}` | `{tree,activeKey,footer:[],drilldownStack?}` — **`footer` required** | type-MISMATCH; runtime-safe (`BasicShell.tsx:102` `nav.footer ?? []`) |
| `version` | `"demo-0.5"` | `string` | **MATCH** |
| `flash` | framework `ShareFlashMiddleware` | optional `{success?,error?,info?,…}` | **MATCH** |
| `theme` | not emitted | `SharedPropsTheme` (type-req, all fields optional) | type-MISMATCH; runtime-safe (`i18n.tsx:89` optional-chains) |
| `locale` | not emitted | `string` (type-req) | type-MISMATCH; runtime-safe (defaults `'en'`, `i18n.tsx:64-67`) |
| `scope` | not emitted | optional | **MATCH** (`scope.tsx:23` `?? {}`) |
| `csrf_token`,`errors` | server/Inertia-core | not SharedProps keys | N/A |

**Shell + provider facts:**
- **`product` shell is PRO-only.** Free registers `basic`+`immersive` (`register-defaults.ts:49-50`); `ContractPage.tsx:31-34` falls back to `immersive` when `product` unregistered. Dashboard (shell `product`) renders via **ImmersiveShell** in free.
- **`I18nProvider` is mandatory** — shells call `useTranslation`, which throws without it (`i18n.tsx:192-201`). `entry-custom.tsx` already wraps it. ✅
- `AuthProvider`/`ScopeProvider` are opt-in, not used by shells. **Do NOT wrap the anonymous login in `AuthProvider`** (crashes on `null.id`).

---

## Backend punch-list (ordered)

1. **[D-1, framework] form field-node shape** — make the PHP→React form bridge emit lib-canonical `FormFieldNode`. Either in `framework/src/Form/Renderer/InertiaRenderer.php` + `InertiaFieldMapper.php` (node `key`; `props.label` string; `visible_when`/`required_when` objects w/ `equals` vocab; `options` as `[{value,label}]`), or a host-side adapter in `ui/`. **← OPEN DECISION (see below).**
2. **[D-1, demo] login schema** — `AuthController::loginForm` component names → lowercase `text`/`password` (currently `TextField`/`PasswordField`). Trivial, demo-local; do regardless.
3. **[D-2, framework] entity_picker** — emit `autocompleteHref` and align the response envelope (top-level `data` array). Couples with #1 (same node-shape problem). **← part of OPEN DECISION.**
4. **[D-3, demo] navigation.footer** — emit `footer: []` in `DemoBootstrap` navigationProps (type-canonical; runtime-safe today).
5. **[D-3, demo] theme/locale** (optional) — share `theme:{}` + `locale:'en'` for type-canonical SharedProps. No runtime impact.
6. **[D-2, demo] (cosmetic)** — add `id`+`rowActions` to `UiController::page()` sample so it mirrors the real list.

**No change needed:** `auth` (authed) shape, `version`, `flash`, `scope`, Action/rowActions serialization, `TaskController::index` rowActions.

---

## Open risks

- **`product` shell**: demo intends a product shell but free renders ImmersiveShell. Decide: accept Immersive fallback for the OSS demo, change dashboard to `shell('immersive')` explicitly, or pull `@middag-io/react-pro` (contradicts the OSS-surface thesis).
- **D-1 fix location is architectural** (framework vs host) — see decision below. Framework fix = cross-repo, affects all consumers, 0.7.0 unreleased.
- D-1 verdict reconstructed from the verify lane's transcript (its structured emit looped); the 3 load-bearing claims (form_panel-in-free, lowercase registry, `field.key`) were **re-verified directly** in lib source. D-2/D-3 are validated structured outputs.

---

## Corrected build order (next phase)

1. **DECIDE D-1 fix location** (framework-canonical vs host-adapter). Gates everything else.
2. Demo-local quick wins (independent of #1): login schema lowercase (#2), `navigation.footer` (#4), optionally theme/locale (#5).
3. Implement the form-node fix per the decision → login + create + edit `form_panel` render via the lib's block.
4. entity_picker (#3) once the node shape is settled.
5. `ui/` dev app: drop the generic mock (`app.tsx`/`hello-block`/`greeting`/`DemoPage`), point dev at the live PHP (`/build` via Vite proxy → `:8080`) or real-contract fixtures; wire `FlashProvider` (toasts) — `I18nProvider` already wired, do **not** add `AuthProvider` around login.
6. Decide `product` shell (accept Immersive, or set `shell('immersive')`).
7. Visual acceptance pass (the `FRONTEND-PLAN.md` §criteria checklist).
