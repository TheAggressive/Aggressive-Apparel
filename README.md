# Aggressive Apparel

Official WooCommerce block theme for [Aggressive Apparel](https://theaggressive.com) — a Full Site Editing (FSE) theme with toggleable store enhancements, custom blocks, a shared design system, and WooCommerce-first patterns.

**Version:** see `style.css` / `package.json` (kept in sync by semantic-release) · **Requires:** WordPress 7.0+ / PHP 8.0+ · **License:** GPL-2.0-or-later

## Features

- **Full Site Editing** — 13 templates, 83 block patterns, complete `theme.json` configuration
- **WooCommerce integration** — product gallery, color swatches, and custom shop/cart/checkout templates
- **Design system tokens** — `theme.json` as source of truth with a compiled `--aa-*` alias layer
- **17 store enhancements** — premium features behind toggle flags; disabled features load zero hooks or assets
- **46 custom blocks** — 37 Interactivity API registrations + 9 standard-build registrations (both totals include locked child blocks)
- **Interactivity API** — client-side reactivity without a separate JavaScript framework
- **Automatic updates** — GitHub release-based update system with ETag caching and SHA-256 package verification
- **Accessible** — WCAG 2.2 AA compliance targets, 44px touch targets, `prefers-reduced-motion` support
- **Secure** — security headers, nonce verification, output escaping, capability checks
- **Performance** — deferred scripts, conditional asset loading, Speculation Rules API prefetch
- **Test coverage** — PHPUnit, Jest/tooling tests, and Playwright end-to-end coverage

## Store Enhancements

Features are managed under **Appearance → Store Enhancements** and default to **OFF**. `Feature_Settings::is_enabled()` and the `Enhancements` coordinator ensure disabled features register no hooks or assets.

Appearance features that work without WooCommerce (**Adaptive Colors**, and future Core toggles) live under **Appearance → Theme Features**.

A separate **Store Copy** tab controls storefront microcopy (button labels, filter text, wishlist copy, and similar strings).

| Feature                    | Section                     | Description                                                                                                                  |
| -------------------------- | --------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| Product Badges             | Catalog                     | Sale, new, low stock, and bestseller badges on product cards                                                                 |
| Smart Price Display        | Catalog                     | Single “From” price for variable products on cards and product pages, plus savings display                                   |
| Advanced Sorting           | Catalog                     | Featured, biggest savings, and A–Z / Z–A sort options                                                                        |
| Product Filters            | Catalog                     | AJAX filters (categories, swatches, sizes, price, stock). Place `filter-toggle` and `filter-active-bar` blocks in templates. |
| Load More                  | Catalog                     | Load More button or infinite scroll instead of pagination                                                                    |
| Page Transitions           | Catalog                     | View Transitions API + Speculation Rules for smoother navigation                                                             |
| Catalog Hover Image        | Catalog                     | Show the first gallery image on product-card hover                                                                           |
| Size Guide                 | Product                     | Reusable guides assignable to products or categories and rendered through a placeable product block                          |
| Sticky Add to Cart         | Product                     | Fixed bar when the main add-to-cart scrolls out of view                                                                      |
| Stock Status               | Product                     | Availability indicator in Quick View                                                                                         |
| Quick View                 | Product                     | Product modal with add-to-cart from archives                                                                                 |
| Frequently Bought Together | Product                     | Bundling with combined add-to-cart on product pages                                                                          |
| Wishlist                   | Engagement                  | Heart-icon toggle with localStorage and Store API                                                                            |
| Social Proof               | Engagement                  | Recent purchase toast notifications                                                                                          |
| Back in Stock              | Engagement                  | Email subscriptions for out-of-stock products                                                                                |
| Swatch Tooltips            | Mobile & UI                 | Fabric name and composition on swatch hover                                                                                  |
| Mobile Bottom Navigation   | Mobile & UI                 | Fixed bottom nav on mobile (Home, Search, Cart, Account)                                                                     |
| Adaptive Colors            | Appearance → Theme Features | Per-block light/dark overrides and adaptive palette via CSS `light-dark()`                                                   |

## Quick Start

```bash
# Install Node and PHP dependencies
pnpm install
composer install

# Build blocks, interactivity modules, icons, and assets
pnpm build

# Watch mode + wp-env (port 9910)
pnpm dev

# Core quality assurance (i18n + tests + lint + PHPStan)
pnpm qa

# Browser end-to-end tests (install Chromium once first)
pnpm test:e2e:install
pnpm test:e2e
```

### Development Commands

| Command                    | Description                                                           |
| -------------------------- | --------------------------------------------------------------------- |
| `pnpm build`               | Build blocks, interactivity blocks, shared modules, assets, and icons |
| `pnpm dev`                 | Watch mode + wp-env                                                   |
| `pnpm setup`               | Install, build, and start wp-env                                      |
| `pnpm test`                | JS unit tests, tooling tests, and PHP suites                          |
| `pnpm test:any -- <flags>` | Targeted PHPUnit runs inside wp-env                                   |
| `pnpm test:unit`           | PHP unit tests                                                        |
| `pnpm test:integration`    | PHP integration tests                                                 |
| `pnpm test:security`       | Security tests                                                        |
| `pnpm test:accessibility`  | Accessibility tests                                                   |
| `pnpm test:performance`    | Performance benchmarks                                                |
| `pnpm test:e2e`            | Playwright browser tests against wp-env                               |
| `pnpm test:e2e:install`    | Install the Playwright Chromium browser and system dependencies       |
| `pnpm lint:all`            | Prettier, file lengths, ESLint, TypeScript, Stylelint, and PHPCS      |
| `pnpm lint:files`          | Enforce source-file length budgets                                    |
| `pnpm lint:fix`            | Auto-fix formatting and lint issues                                   |
| `pnpm lint:css`            | Stylelint + design-system CSS checks                                  |
| `pnpm analyse:php`         | PHPStan (level 6)                                                     |
| `pnpm qa`                  | i18n check + unit/tool/PHP tests + lint + PHPStan                     |
| `pnpm ci:verify`           | Full local rehearsal of the required GitHub Actions checks            |
| `pnpm perf`                | Lighthouse performance budget (build + report)                        |
| `pnpm env:start`           | Start wp-env and update development to the latest Beta/RC             |
| `pnpm env:stop`            | Stop wp-env                                                           |
| `pnpm env:check`           | Report versions and verify that attachment files exist                |
| `pnpm env:backup`          | Create and checksum a database + wp-content recovery point            |
| `pnpm env:backups`         | List available recovery points                                        |
| `pnpm env:restore -- <id>` | Verify and restore a recovery point after backing up current state    |
| `pnpm env:clean:all`       | Confirm, back up, and clean both wp-env databases                     |
| `pnpm env:destroy:all`     | Confirm, back up, and destroy all wp-env containers and local data    |
| `pnpm env:reset`           | Confirm, back up, clean, restart, and verify wp-env                   |

The environments intentionally use two WordPress channels:

| Lane                       | WordPress version                               | Role                                           |
| -------------------------- | ----------------------------------------------- | ---------------------------------------------- |
| Local development          | Latest Beta/RC via WordPress Beta Tester        | Early compatibility feedback                   |
| Required release CI        | Pinned stable core and WooCommerce versions     | Reproducible release gate                      |
| Scheduled compatibility CI | Latest Beta/RC, daily and manually dispatchable | Detect upcoming WordPress compatibility issues |
| PHP test site              | Pinned stable version from `.wp-env.json`       | Reproducible unit and integration tests        |

The development site uses wp-env's normal `wp-content` directory; it does not
map the complete directory into the repository. The Beta/RC updater takes a
transactional archive before replacing core so uploads, fonts, and locally
installed plugins survive the update. Set `WP_ENV_SKIP_BETA_UPDATE=1` to skip
the moving update for an offline or deterministic run.

Required CI and `pnpm ci:verify` both use `bin/ci/.wp-env.json`. That committed
configuration pins WordPress, WooCommerce, and PHP; maps the theme to the same
lowercase path; and stores its Docker state under the ignored `.wp-env-ci/`
directory on dedicated ports. Each stateful parity lane resets both isolated
databases, reapplies the pinned configuration, and stops its containers when
finished. It therefore starts from the same WordPress state as Actions without
altering the development database, uploads, fonts, or locally installed plugins.

Node and pnpm are exact release-gate inputs. `pnpm ci:verify` downloads the
official Node version from `.node-version` into the ignored local cache and
verifies its pinned SHA-256 checksum before running; it does not change the
global Node installation. `pnpm ci:doctor` then fails immediately if Node or
pnpm differs from Actions. GitHub runs `ci:frontend`, `ci:i18n`, `ci:build`,
`ci:php`, and `ci:e2e` in parallel jobs, while `pnpm ci:verify` runs those same
commands serially.

Recovery points default to the ignored `.wp-env-backups/` directory. Set
`WP_ENV_BACKUP_DIR` to an absolute directory on durable storage to keep them
outside the repository. The five newest recovery points are retained by default;
set `WP_ENV_BACKUP_RETENTION` to another positive count when storage policy
requires it. Every clean, destroy, reset, and restore requires an explicit typed
confirmation and creates a verified recovery point first. Intentional
non-interactive automation must pass `--yes` or set
`WP_ENV_CONFIRM_DESTRUCTIVE=1`; the commands otherwise fail closed. Direct
`wp-env clean` and `wp-env destroy` bypass these safeguards and should not be
used.

### Scaffolding Blocks

```bash
pnpm create-block <name>              # Static block
pnpm create-block-dynamic <name>      # Dynamic (PHP render) block
pnpm create-block-interactive <name>  # Interactivity API block
```

## Design System

`theme.json` is the single source of truth for editor-configurable design decisions. WordPress exposes preset and custom CSS variables; `src/styles/base/tokens.css` provides a thin `--aa-*` alias layer for component CSS.

| Layer                        | Owns                                                                    | Use                                                |
| ---------------------------- | ----------------------------------------------------------------------- | -------------------------------------------------- |
| `theme.json`                 | Palette, spacing, typography, motion, radius, shadows, z-index          | Source of truth and Site Editor configuration      |
| `src/styles/base/tokens.css` | `--aa-*` aliases, derived commerce/status colors, safe runtime defaults | Component-facing token API                         |
| Feature CSS                  | Layout and state composition                                            | Prefer tokens; avoid raw values unless truly local |
| `build/styles/`              | Compiled output                                                         | Generated — do not edit directly                   |

Shared interaction primitives live in `src/styles/components/buttons.css`: `.aggressive-apparel-button` for CTAs, `.aa-icon-button` for utility controls, `.aa-choice-pill` for selectable chips and tabs, and `.aa-stepper-button` for quantity controls. `theme.json` owns their visual roles; the component stylesheet owns target sizing, focus, motion, and disabled/loading behavior.

After token changes:

```bash
pnpm lint:css
pnpm build:assets
pnpm build:interactivity
```

More detail: [`docs/design-system.md`](docs/design-system.md) · [`docs/block-placement.md`](docs/block-placement.md) · [`docs/performance-testing.md`](docs/performance-testing.md)

## Architecture

### Directory Structure

```
aggressive-apparel/
├── build/                    # Compiled output (gitignored)
│   ├── blocks/               # Standard-build block registrations
│   ├── blocks-interactivity/ # Interactive blocks
│   ├── interactivity/        # Shared enhancement modules + nav stores
│   ├── icons/                # Generated brand icon definitions
│   ├── scripts/              # Theme JS/TS
│   └── styles/               # Theme CSS
├── includes/                 # PHP classes (PSR-4, Aggressive_Apparel\)
│   ├── Assets/               # Script and style loaders
│   ├── Blocks/               # Block registration
│   ├── Core/                 # Theme supports, icons, updates, adaptive colors
│   └── WooCommerce/          # Store features and WooCommerce integration
├── parts/                    # Template parts (header, footer)
├── patterns/                 # Block patterns (83)
├── src/
│   ├── blocks/               # Standard blocks (9, incl. 2 split-story columns)
│   ├── blocks-interactivity/ # Interactivity API blocks (37, incl. 2 card-flip faces)
│   ├── interactivity/        # Shared frontend modules (filters, quick view, nav stores, etc.)
│   ├── icons/                # Brand SVG sources (built to build/icons/)
│   ├── scripts/              # Admin, editor, and theme JS/TS
│   └── styles/               # Theme CSS (Tailwind v4 + PostCSS)
├── templates/                # FSE templates (13 HTML + emails/)
└── tests/                    # PHPUnit test suites
```

### PHP Architecture

```
functions.php → Bootstrap (singleton)
    ├── Autoloader (PSR-4)
    ├── Service_Container
    │   ├── Core (theme support, icons, image sizes, adaptive colors, theme features, updates)
    │   ├── Assets (styles, scripts)
    │   └── Blocks (auto-discovery from build/)
    └── WooCommerce (conditional)
        ├── Core WC support (templates, cart, product loop, color swatches)
        ├── Feature_Settings (17 toggles + store copy)
        └── Enhancements → individual feature classes
```

### Custom Blocks

Blocks auto-register from `build/blocks/` and `build/blocks-interactivity/`.

**Standard-build blocks (7 top-level):** `aggressive-apparel-logo`, `dark-mode-toggle`, `copyright`, `icon`, `product-rating`, `size-guide`, `split-story` (with locked `split-story-media` / `split-story-content` column child blocks)

**Navigation — desktop (`aggressive-apparel/navigation` store):**

| Block                  | Role                                            |
| ---------------------- | ----------------------------------------------- |
| `navigation`           | Horizontal menu bar; submenu theming context    |
| `navigation-trigger`   | Hamburger button (opens the mobile panel store) |
| `nav-link`             | Single link (shared leaf)                       |
| `nav-submenu-dropdown` | Click/hover dropdown                            |
| `nav-submenu-mega`     | Full-width mega menu                            |

**Navigation — mobile panel (`aggressive-apparel/navigation-panel` store, portaled to `wp_footer`):**

| Block                                   | Role                                 |
| --------------------------------------- | ------------------------------------ |
| `navigation-panel`                      | Slide-in drawer root                 |
| `nav-panel-header` / `nav-panel-footer` | Optional drawer chrome               |
| `nav-submenu-accordion`                 | Expand-in-place submenu              |
| `nav-submenu-drilldown`                 | Slide-over submenu (overlay or push) |

**Commerce & filters:** `filter-toggle`, `filter-active-bar`, `product-color-swatches`, `product-tabs`, `grid-list-toggle`, `countdown-timer`, `recently-viewed`, `search`, `store-notices`

**Wishlist:** `wishlist`, `wishlist-button`, `wishlist-item-image`, `wishlist-item-name`, `wishlist-item-price`, `wishlist-item-actions`

**Free shipping:** `free-shipping-bar` (progress bar), `free-shipping-message` (inline copy with live cart updates). Threshold comes from WooCommerce free-shipping zones or the `aggressive_apparel_free_shipping_threshold` filter.

**Content & layout:** `parallax`, `animate-on-scroll`, `lookbook`, `ticker`, `modal`, `card-flip`, `horizontal-scroll`, `hero-carousel`

Product filter blocks are **template-placed only** — add `filter-toggle` and `filter-active-bar` on shop, category, and tag archives (or use the `shop-archive-header` pattern). Each block ships its own frontend CSS and connects to the shared `aggressive-apparel/product-filters` Interactivity store.

The `size-guide` block renders its accessible modal only in product context when the Size Guide enhancement is enabled and a guide is assigned; the default single-product template already includes it.

The `store-notices` block replaces WooCommerce’s standard notice block in theme templates with dismissible, auto-expiring toast notifications. It supports configurable placement, visibility limits and durations, plus optional capture of Cart and Checkout block notices.

Blocks such as `product-tabs`, `search`, `store-notices`, `modal` (supports exit-intent and scroll-depth triggers), and free-shipping blocks are placed in templates rather than controlled by store-enhancement toggles. The portaled full-screen search supports scoped All, Products, Articles, and Pages tabs when those content types are available.

`product-tabs` replaces WooCommerce's native Product Details with four selectable layouts — **accordion** (independent by default, or one-open-at-a-time), **inline**, **modern tabs**, and **scrollspy** — animated with CSS (grid-rows reveal, sticky mobile rails) rather than per-frame JavaScript. The editor exposes heading font size, heading text color, and accent color (stored as portable palette references); the default layout is set globally under **Products → Product Tabs** and is overridable per placement.

**Full placement rules (filters, wishlist, cards, nav):** [`docs/block-placement.md`](docs/block-placement.md)

### Icon System

Two icon libraries:

- **UI icons** (41) — `Icons::get()` / `Icons::render()` for navigation, actions, and status glyphs
- **Brand icons** (42) — lazy-loaded from `build/icons/` via `Brand_Icons`; use the `icon` block or `Icons::get('slug')` after build

Color swatches use `Color_Attribute_Manager`, `Color_Data_Manager`, `Color_Block_Swatch_Manager`, `Color_Admin_UI`, and `Color_Pattern_Admin` for solid colors and pattern images with keyboard and screen-reader support.

## Testing

PHP and browser tests use wp-env (Docker), with WooCommerce installed in both development and test environments. JavaScript unit and tooling tests run directly through Node.

| Suite                 | Coverage                                                                 |
| --------------------- | ------------------------------------------------------------------------ |
| JavaScript/tooling    | Interactivity stores, utilities, icon generation, and sanitization       |
| PHP unit              | Bootstrap, assets, theme support, blocks, and WooCommerce classes        |
| PHP integration       | WooCommerce integration and block rendering                              |
| PHP security          | HTTP security headers and permission enforcement                         |
| PHP accessibility     | ARIA attributes and keyboard-navigation markup                           |
| PHP performance       | Load-time and resource-usage benchmarks                                  |
| Playwright end-to-end | Editor/frontend behavior, responsive commerce UI, overlays, and checkout |

**Tools:** PHPUnit 9.6, Playwright, PHPStan level 6, PHPCS (WordPress standards), ESLint, Stylelint, Jest (via wp-scripts)

Target a single test file or method:

```bash
pnpm test:any -- tests/Unit/Some_Test.php --verbose
pnpm test:any -- --filter '^Some_Test::test_method$' --verbose
```

## CI/CD

GitHub Actions (`.github/workflows/release.yml`):

```
detect changes → frontend ∥ i18n → build → PHP quality/tests ∥ browser E2E → package → semantic-release (feat/fix/perf only) → verify release assets
```

- **Code changes** run the full pipeline on every push and pull request; translation-only changes run the i18n catalog check and ship with the next code release
- **PHP and browser tests run in parallel** from the same uploaded build, so the full E2E suite does not add a serial stage to the CI critical path
- **Release pipeline** (package + GitHub release ZIP) runs only for conventional `feat:`, `fix:`, or `perf:` commits
- **Git hooks** (Husky) — split so commits stay fast:
  - `pre-commit`: `format:fix` → `lint:js:fix` (autofix only)
  - `commit-msg`: commitlint (Conventional Commits)
  - `pre-push`: `pnpm ci:verify` — exact release-CI lanes against an isolated clean wp-env

### Versioning (what stays in sync on release)

semantic-release (see `.releaserc.json`) automatically bumps and commits:

| File                             | Updated on release?                                   |
| -------------------------------- | ----------------------------------------------------- |
| `style.css` (`Version:`)         | Yes — WordPress theme version                         |
| `package.json` (`version`)       | Yes                                                   |
| `CHANGELOG.md`                   | Yes                                                   |
| Release ZIP + `.sha256`          | Yes — version stamped inside the packaged `style.css` |
| `README.md` / `CLAUDE.md`        | **No** — do not hardcode the theme version here       |
| Per-block `block.json` `version` | **No** — independent of theme releases                |

Both release assets are **required**: `Core\Theme_Updates` verifies the package
against the `.sha256` sidecar and offers no update at all when it is missing, so
a partial upload silently stops every install from seeing the release. The
release job runs `bin/release/verify-assets.sh` afterwards, which re-uploads a
missing asset and fails the build if it still is.

When docs mention inventory (block counts, pattern counts, feature lists), update those in the same PR that changes the inventory — not as part of the release job.

## Theme Configuration

### Constants

```php
AGGRESSIVE_APPAREL_VERSION  // Theme version from style.css (release-managed)
AGGRESSIVE_APPAREL_DIR      // Theme directory path
AGGRESSIVE_APPAREL_URI      // Theme directory URI
```

### Helpers

```php
aggressive_apparel_asset_uri($path)   // Asset URL
aggressive_apparel_asset_path($path)  // Asset file path
aggressive_apparel_free_shipping_threshold() // Free-shipping threshold (filterable)
```

### Security Headers

Added via `Bootstrap::add_security_headers()`:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`

## Requirements

- WordPress 7.0+
- PHP 8.0+ (8.3 recommended and used in wp-env; CI also verifies on 8.2)
- Node.js 24+ with pnpm 11+
- WooCommerce 7.0+ (recommended)
- Docker (for wp-env)

## Support

- Issues: https://github.com/TheAggressive/Aggressive-Apparel/issues

## License

GNU General Public License v2 or later — http://www.gnu.org/licenses/gpl-2.0.html

Developed by [The Aggressive Network, LLC](https://theaggressive.com)
