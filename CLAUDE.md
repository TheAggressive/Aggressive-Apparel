# CLAUDE.md - Aggressive Apparel Theme

WordPress FSE block theme for WooCommerce: service container, Interactivity API
blocks, toggleable store enhancements. WP 7.0+, PHP 8.2+, pnpm 11+, GPL-2.0+.
Version lives in git tags and `style.css` — never hardcode it in docs.

Human-facing overview: [`README.md`](README.md).

## Working agreement

- **Be concise.** Answer what was asked. No preamble, no summary of work the
  user just watched you do, no restating the request back. Skip the recap
  unless the result is non-obvious.
- **Route mechanical work to a Haiku sub-agent.** Renames, reformatting,
  mass find-and-replace, summarising a long file, scraping or extracting
  structured data from output — dispatch with
  `Agent(subagent_type: "general-purpose", model: "haiku")`. Reserve the
  main Opus context for work that needs judgment about this codebase.
  Do not spawn agents for tasks you can finish in one or two tool calls.
- **Never suggest `/compact` as a cost-saving measure.** It is not one — it
  spends a full-context read to produce a summary, then keeps going in the
  same session. Finish the job and `/clear` instead. Cost is governed by
  average context size per turn, not by session count.
- **Read narrowly.** Prefer `sed -n 'A,Bp'`, `grep -n`, and targeted globs over
  whole-file reads — a file read at turn 10 is re-sent on every turn after it.
- **Pick model and effort once per session.** Switching mid-session
  invalidates the prompt cache and re-bills the entire context.

## Quick Commands

```bash
pnpm build       # blocks + assets   (build:blocks|:interactivity|:assets)
pnpm dev         # Studio + watch
pnpm test        # JS + PHP          (test:unit|:integration|:js|:e2e)
pnpm lint:all    # all linters       (lint:fix to autofix)
pnpm analyse:php # PHPStan level 8
pnpm qa          # Docker-free native + Studio gate   (qa:ci = container parity)
pnpm ci:package  # package + verify the ZIP from current build/
pnpm i18n:pot    # regenerate languages/aggressive-apparel.pot
```

Full command list and task recipes: [`docs/architecture.md`](docs/architecture.md).

## Coding Standards

### No god files (800 warn / 1000 hard cap)

Non-test sources under `src/` (`.ts`/`.tsx`) and `includes/` (`.php`) have a
two-tier line budget enforced by `bin/check-file-length.sh` (wired into
`lint:all` / `qa` / CI): **> 800 warns**, **> 1000 fails the build**. There is
**no allowlist and no baseline** — split by responsibility instead of raising
the cap:

- **Pure logic** → sibling modules (e.g. `src/interactivity/<store>/product-data.ts`).
- **Interactivity stores** → keep getters in the one `state` literal (deepMerge
  flattens getters, so they can't be spread); move **actions** into extra
  `store('<namespace>', { actions })` calls in sibling files — the runtime
  merges multiple `store()` calls by namespace. Sub-files live in
  `src/interactivity/<name>/`; the module glob is `src/interactivity/*.ts`
  (direct children only), so sub-files bundle into the entry, not new modules.
- **PHP classes** → extract collaborators or traits, one per file. In
  `includes/` both classes and traits go in `class-*.php` — the theme
  autoloader loads nothing else. (`tests/` is classmap-loaded and uses
  `trait-*.php` for traits.)

### PHP

WPCS 3.4 (plus PHPCompatibility, strict class file names) for `includes/`
*and* `tests/` — tests are classes in `class-test-*.php` files, classmap-loaded.
`declare(strict_types=1);`, PHPStan level 8 with **no baseline** (zero
suppressed findings). PSR-4 under `Aggressive_Apparel\`, directory-matched;
`Theme_Support` → `class-theme-support.php`. New classes autoload unregistered.

- **No mutable static caches.** Memoise per request with `Core\Request_Cache`
  (non-persistent `wp_cache` group; invalidate with `forget()` or a generation
  key). `static` state is only for registries and hook-registration guards
  (prefer `has_action()`). No class-level singletons (the container owns
  services) and no `*_for_tests` hooks — tests use `wp_cache_flush()`.

### JavaScript / TypeScript / CSS

TypeScript for all new code; React/JSX for editor components. Native CSS (no
framework) built by PostCSS (`postcss-import`) + Lightning CSS. BEM-like custom
classes.

**Formatting is the WordPress standard, one tool per language:** `wp-prettier`
(installed as `prettier`) + `@wordpress/prettier-config` for JS/TS/CSS/JSON,
WPCS/phpcbf for PHP. ESLint and Stylelint check correctness only — never add
layout rules to them. `.editorconfig` matches; editors use the local Prettier,
so format-on-save writes what CI checks.

- **Native CSS nesting only.** Lightning CSS flattens it at build time. No
  Sass-style concatenation (`&__el`, `&--mod`) — native nesting can't express
  it; write the full BEM selector. Nesting under a selector *list* compiles to
  `:is()`, which takes the list's highest specificity — spell such rules out
  when the members' specificities differ.

- **Never use body-level `:has()`** (`body:has(...)`, `body.x:has(...)`) — it
  forces a document-wide style re-scan on EVERY childList mutation anywhere in
  the page (measured ~72ms per mutation). Mirror the state as a body
  class/attribute from the owning JS instead;
  `bin/check-design-system-css.sh` fails the build on violations.
  Component-scoped `:has()` subjects are fine.
- **Visually hidden text uses WordPress's `screen-reader-text` class** (core
  ships it on the front end and in wp-admin). Don't hand-roll a copy.
- **Per-frame text updates must mutate a `Text` node's `.data`**, never assign
  `textContent` (which replaces the node — a childList mutation).
- **Layer order is `aa-reset` first, `aa-forms` last** (declared in
  `main.css`). `aa-reset` (`base/reset.css`) zeroes padding/border/radius on
  form controls; the form baseline lives in `aa-forms` so it beats the reset
  and every other `aa-*` layer — don't move form rules into other `aa-*`
  layers. Field values come from `--aa-field-*` tokens
  ([`docs/design-tokens.md`](docs/design-tokens.md#forms)).

### Design system override principle

The design system must NOT style what the block editor controls (buttons,
element/block styling), and our CSS must stay overridable by the editor. Style
only what the editor can't reach (raw inputs, WC-encapsulated fields, select2,
autofill); keep it low priority (`@layer` + `:where()`); never use `!important`
or unlayered/high-specificity to beat editor/theme.json output. WC overrides may
be unlayered only to beat WooCommerce's own unlayered CSS, and set only
structural props (use `color: inherit` so editor colors win).

`!important` is allowed only to beat a vendor `!important` or a vendor rule no
class can outrank (an `#id`, an inline `style`), to survive a same-specificity
load-order race with vendor CSS (WooCommerce bundles print in varying order),
for the reset's `[hidden]` rule (state, not styling), for reduced-motion
kill-switches and the `.aa-hidden-*` utilities (contracts that must beat every
rule), or to override the nav panel's own `!important` critical CSS. Add a
comment naming what it beats; if specificity or a layer can win instead, use
that — including against the theme's own rules and inline styles, which are
fixed at the source rather than fought.

theme.json `settings` is the single source of truth; `src/styles/base/tokens.css`
(`--aa-*`) is a consumer/alias layer, not the source.
Details: [`docs/design-tokens.md`](docs/design-tokens.md),
[`docs/design-system.md`](docs/design-system.md).

## Testing

`pnpm test` (JS + tool + PHP), `pnpm test:e2e` (Studio). What the suites
guarantee: [`docs/architecture.md`](docs/architecture.md#what-the-suites-guarantee).

- **No skipped or assertion-free tests** — every suite fails on them. Missing
  prerequisites (WooCommerce, the build) fail the bootstrap, not the test.
- **Assert behaviour, not source text.** CSS contracts go in
  `src/styles/__tests__/css-contracts.test.ts` (compiled output: selector,
  value, `!important`, layer) — never grep a stylesheet.
- **No fixed sleeps in e2e** — wait on a condition (attribute, focus,
  `CSSTransition.finished`, `expect.poll`).
- `tests/e2e/component-styles.spec.ts` snapshots component paint: after an
  intended change, review the diff and re-record with `--update-snapshots`.

## i18n

Text domain `aggressive-apparel` (`Domain Path: /languages`). Tooling in
`bin/i18n/` (`pnpm i18n:*`).

- **Placeholders are gated, both families.** `bin/i18n/po.mjs` defines the one
  pattern covering printf (`%s`, `%2$d`) *and* brace tokens (`{percent}`,
  `{pct}`) that this theme substitutes with `str_replace`. MT protects them from
  the provider; `lint-placeholders.mjs` fails the build when a catalog drops or
  renames one. It runs from `validate-po.sh` on the **host**, not in the wp-env
  cli container (which ships neither gettext nor node). Brace tokens are
  invisible to `msgfmt -c`, so without this a translated `{pourcentage}` reaches
  a product badge verbatim.
- **The POT records source line numbers**, so any line-count change to a file
  containing a translatable string invalidates it, with no other symptom. Fix is
  always `pnpm i18n:pot`. `ci:i18n` is deliberately outside `pre-push`, so this
  surfaces in CI.
- Interactivity **script modules** use PHP `i18n` bags — not
  `wp_set_script_translations`.
- Commit `.pot` + locale `.po`; `.mo` / Jed `.json` are gitignored, built by
  `i18n:compile`. **MT draft PRs never arrive pre-validated** — a PR showing no
  completed checks means nothing ran, not that anything passed.

Full runbook: [`languages/README.md`](languages/README.md).

## Full-page-cache correctness rule

Never bake a per-user/per-session *value* into server HTML or
`wp_interactivity_state` and trust it — a page cache serves the priming
visitor's copy to everyone. Personalized fragments must rehydrate client-side
(cart count → Store API `refreshCartCount()` on load, gated on the
`woocommerce_items_in_cart` cookie; wishlist → localStorage). Seeding **config,
i18n, and default flags** into interactivity state is fine; seeding a live
count, geo, or membership value is not.

The catalog also *assumes* a persistent object cache and a full-page cache at
real traffic — see [`docs/woocommerce.md`](docs/woocommerce.md).

## Navigation gotchas

Two subsystems — `navigation` (desktop bar) and `navigation-panel` (mobile
drawer) — each with its own store; `nav-link` is the shared leaf.

- The panel is **portaled to `wp_footer`**, so **`data-wp-bind` / `data-wp-class`
  directives don't react across the portal boundary**. Drilldown open-state class
  and the trigger's `aria-expanded` are toggled imperatively in
  `callbacks.onSubmenuStateChange`.
- `focus()` on an element inside an off-screen sliding panel cancels the slide —
  always pass `{ preventScroll: true }` for in-panel focus moves.
- The blocks have **no view modules**. Each store ships once as a shared script
  module and is enqueued in `class-navigation-functions.php`. Don't add per-block
  `viewScriptModule`s back — `wp_enqueue_script_module` alone doesn't add a bare
  specifier to the import map, so a view module importing the store would fail.
- **Shared dirs compile into EACH block's bundle**, so module-level state cannot
  coordinate block types. Coordinate through the DOM.

Full subsystem map, modal/overlay contract, and debug tooling:
[`docs/navigation.md`](docs/navigation.md).

## Versioning

`style.css` is the ONLY version synced, via the machine-authored
`chore/version-sync` PR. Change the tracked version only with
`bash bin/release/sync-version.sh <version>` — never by editing the header.
`package.json` stays `0.0.0-development`; `CHANGELOG.md` is frozen; `@since`
tags are historical. Releasing is manual on purpose:

```bash
gh workflow run "CI/CD Pipeline" --ref master -f publish=true
```

Mechanics and rationale: [`docs/versioning.md`](docs/versioning.md).
Procedure: [`docs/release-runbook.md`](docs/release-runbook.md).
Day-to-day flow: [`docs/workflow.md`](docs/workflow.md).

## Git hooks & the CI contract

`pre-commit` runs `lint-staged` (staged files only); `commit-msg` runs commitlint
(Conventional Commits); `pre-push` runs `pnpm qa:fast`.

**These are the same commands Actions runs**, and `bin/ci/contracts.mjs` fails
the build if the two lists ever diverge in either direction — a workflow job may
only invoke a canonical lane, never inline shell. **Adding a step to CI without
making it runnable locally is a build failure, by design.** Detail:
[`docs/versioning.md`](docs/versioning.md).

## Reference

| Topic | Doc |
| --- | --- |
| Architecture, blocks, build, testing, tasks | [`docs/architecture.md`](docs/architecture.md) |
| Navigation, overlays, debug tooling | [`docs/navigation.md`](docs/navigation.md) |
| WooCommerce, swatches, caching prerequisites | [`docs/woocommerce.md`](docs/woocommerce.md) |
| theme.json tokens & override contract | [`docs/design-tokens.md`](docs/design-tokens.md) |
| Full design system | [`docs/design-system.md`](docs/design-system.md) |
| Versioning & release · block placement | [`docs/versioning.md`](docs/versioning.md) · [`docs/block-placement.md`](docs/block-placement.md) |

Entry points: [functions.php](functions.php) (bootstrap) ·
[theme.json](theme.json) (block config) · [style.css](style.css) (version source
of truth) · [phpstan.neon](phpstan.neon) · [bin/local/studio.mjs](bin/local/studio.mjs)
(Studio bridge).
