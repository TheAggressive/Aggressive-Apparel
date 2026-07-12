# Aggressive Apparel

Official WooCommerce block theme for [Aggressive Apparel](https://theaggressive.com) — a Full Site Editing (FSE) theme with toggleable store enhancements, custom blocks, a shared design system, and WooCommerce-first patterns.

**Version:** see `style.css` / `package.json` (kept in sync by semantic-release) · **Requires:** WordPress 7.0+ / PHP 8.0+ · **License:** GPL-2.0-or-later

## Features

- **Full Site Editing** — 13 templates, 80 block patterns, complete `theme.json` configuration
- **WooCommerce integration** — product gallery, color swatches, and custom shop/cart/checkout templates
- **Design system tokens** — `theme.json` as source of truth with a compiled `--aa-*` alias layer
- **19 store enhancements** — premium features behind toggle flags; disabled features load zero hooks or assets
- **40 custom blocks** — 34 Interactivity API blocks + 6 static Gutenberg blocks
- **Interactivity API** — client-side reactivity without a separate JavaScript framework
- **Automatic updates** — GitHub release-based update system with ETag caching
- **Accessible** — WCAG 2.2 AA compliance targets, 44px touch targets, `prefers-reduced-motion` support
- **Secure** — security headers, nonce verification, output escaping, capability checks
- **Performance** — deferred scripts, conditional asset loading, Speculation Rules API prefetch
- **Test coverage** — PHPUnit suites for unit, integration, security, accessibility, and performance

## Store Enhancements

Features are managed under **Appearance → Store Enhancements** and default to **OFF**. `Feature_Settings::is_enabled()` and the `Enhancements` coordinator ensure disabled features register no hooks or assets.

A separate **Store Copy** tab controls storefront microcopy (button labels, filter text, wishlist copy, and similar strings).

| Feature                    | Section      | Description                                                                                                                  |
| -------------------------- | ------------ | ---------------------------------------------------------------------------------------------------------------------------- |
| Product Badges             | Catalog      | Sale, new, low stock, and bestseller badges on product cards                                                                 |
| Smart Price Display        | Catalog      | Enhanced archive pricing and savings display                                                                                 |
| Advanced Sorting           | Catalog      | Featured, biggest savings, and A–Z / Z–A sort options                                                                        |
| Product Filters            | Catalog      | AJAX filters (categories, swatches, sizes, price, stock). Place `filter-toggle` and `filter-active-bar` blocks in templates. |
| Load More                  | Catalog      | Load More button or infinite scroll instead of pagination                                                                    |
| Page Transitions           | Catalog      | View Transitions API + Speculation Rules for smoother navigation                                                             |
| Catalog Hover Image        | Catalog      | Show the first gallery image on product-card hover                                                                           |
| Size Guide                 | Product      | Reusable size guides assignable to products or categories                                                                    |
| Sticky Add to Cart         | Product      | Fixed bar when the main add-to-cart scrolls out of view                                                                      |
| Stock Status               | Product      | Availability indicator in Quick View                                                                                         |
| Quick View                 | Product      | Product modal with add-to-cart from archives                                                                                 |
| Frequently Bought Together | Product      | Bundling with combined add-to-cart on product pages                                                                          |
| Wishlist                   | Engagement   | Heart-icon toggle with localStorage and Store API                                                                            |
| Social Proof               | Engagement   | Recent purchase toast notifications                                                                                          |
| Back in Stock              | Engagement   | Email subscriptions for out-of-stock products                                                                                |
| Swatch Tooltips            | Mobile & UI  | Fabric name and composition on swatch hover                                                                                  |
| Mobile Bottom Navigation   | Mobile & UI  | Fixed bottom nav on mobile (Home, Search, Cart, Account)                                                                     |
| Custom Cursor              | Mobile & UI  | Branded cursor on desktop interactive areas                                                                                  |
| Adaptive Colors            | Experimental | Per-block light/dark overrides and adaptive palette via CSS `light-dark()`                                                   |

## Quick Start

```bash
# Install Node and PHP dependencies
pnpm install
composer install

# Build blocks, interactivity modules, icons, and assets
pnpm build

# Watch mode + wp-env (port 9910)
pnpm dev

# Full quality assurance (tests + lint + PHPStan)
pnpm qa
```

### Development Commands

| Command                    | Description                                                           |
| -------------------------- | --------------------------------------------------------------------- |
| `pnpm build`               | Build blocks, interactivity blocks, shared modules, assets, and icons |
| `pnpm dev`                 | Watch mode + wp-env                                                   |
| `pnpm setup`               | Install, build, and start wp-env                                      |
| `pnpm test`                | All tests (JS, tool tests, PHP)                                       |
| `pnpm test:any -- <flags>` | Targeted PHPUnit runs inside wp-env                                   |
| `pnpm test:unit`           | PHP unit tests                                                        |
| `pnpm test:integration`    | PHP integration tests                                                 |
| `pnpm test:security`       | Security tests                                                        |
| `pnpm test:accessibility`  | Accessibility tests                                                   |
| `pnpm test:performance`    | Performance benchmarks                                                |
| `pnpm lint:all`            | Prettier, ESLint, TypeScript, Stylelint, PHPCS                        |
| `pnpm lint:fix`            | Auto-fix formatting and lint issues                                   |
| `pnpm lint:css`            | Stylelint + design-system CSS checks                                  |
| `pnpm analyse:php`         | PHPStan (level 6)                                                     |
| `pnpm qa`                  | Tests + lint + PHPStan                                                |
| `pnpm perf`                | Lighthouse performance budget (build + report)                        |
| `pnpm env:start`           | Start wp-env (port 9910)                                              |
| `pnpm env:stop`            | Stop wp-env                                                           |

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
│   ├── blocks/               # Static blocks
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
├── patterns/                 # Block patterns (80)
├── src/
│   ├── blocks/               # Static Gutenberg blocks (8, incl. 2 split-story columns)
│   ├── blocks-interactivity/ # Interactivity API blocks (36, incl. 2 card-flip faces)
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
    │   ├── Core (theme support, icons, image sizes, adaptive colors, updates)
    │   ├── Assets (styles, scripts)
    │   └── Blocks (auto-discovery from build/)
    └── WooCommerce (conditional)
        ├── Core WC support (templates, cart, product loop, color swatches)
        ├── Feature_Settings (19 toggles + store copy)
        └── Enhancements → individual feature classes
```

### Custom Blocks

Blocks auto-register from `build/blocks/` and `build/blocks-interactivity/`.

**Static blocks (6 top-level):** `aggressive-apparel-logo`, `dark-mode-toggle`, `copyright`, `icon`, `product-rating`, `split-story` (with locked `split-story-media` / `split-story-content` column child blocks)

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

**Commerce & filters:** `filter-toggle`, `filter-active-bar`, `product-color-swatches`, `product-tabs`, `grid-list-toggle`, `countdown-timer`, `recently-viewed`, `search`

**Wishlist:** `wishlist`, `wishlist-button`, `wishlist-item-image`, `wishlist-item-name`, `wishlist-item-price`, `wishlist-item-actions`

**Free shipping:** `free-shipping-bar` (progress bar), `free-shipping-message` (inline copy with live cart updates). Threshold comes from WooCommerce free-shipping zones or the `aggressive_apparel_free_shipping_threshold` filter.

**Content & layout:** `parallax`, `animate-on-scroll`, `lookbook`, `ticker`, `modal`, `card-flip`, `horizontal-scroll`, `hero-carousel`

Product filter blocks are **template-placed only** — add `filter-toggle` and `filter-active-bar` on shop, category, and tag archives (or use the `shop-archive-header` pattern). Each block ships its own frontend CSS and connects to the shared `aggressive-apparel/product-filters` Interactivity store.

Blocks such as `product-tabs`, `search`, `modal` (supports exit-intent and scroll-depth triggers), and free-shipping blocks are placed in templates rather than controlled by store-enhancement toggles.

**Full placement rules (filters, wishlist, cards, nav):** [`docs/block-placement.md`](docs/block-placement.md)

### Icon System

Two icon libraries:

- **UI icons** (32) — `Icons::get()` / `Icons::render()` for navigation, actions, and status glyphs
- **Brand icons** (40) — lazy-loaded from `build/icons/` via `Brand_Icons`; use the `icon` block or `Icons::get('slug')` after build

Color swatches use `Color_Attribute_Manager`, `Color_Data_Manager`, `Color_Block_Swatch_Manager`, `Color_Admin_UI`, and `Color_Pattern_Admin` for solid colors and pattern images with keyboard and screen-reader support.

## Testing

Tests run inside wp-env (Docker). WooCommerce is installed in the test environment.

| Suite         | Coverage                                                      |
| ------------- | ------------------------------------------------------------- |
| Unit          | Bootstrap, assets, theme support, blocks, WooCommerce classes |
| Integration   | WooCommerce integration, block rendering                      |
| Security      | HTTP security headers, permission enforcement                 |
| Accessibility | ARIA attributes, keyboard navigation                          |
| Performance   | Load time and resource usage benchmarks                       |

**Tools:** PHPUnit 9.6, PHPStan level 6, PHPCS (WordPress standards), ESLint, Stylelint, Jest (via wp-scripts)

Target a single test file or method:

```bash
pnpm test:any -- tests/Unit/Some_Test.php --verbose
pnpm test:any -- --filter '^Some_Test::test_method$' --verbose
```

## CI/CD

GitHub Actions (`.github/workflows/release.yml`):

```
lint-frontend ∥ lint-php → build → test (all PHPUnit suites) → package → semantic-release (feat/fix/perf only)
```

- **Quality checks** run on every push and pull request
- **Release pipeline** (package + GitHub release ZIP) runs only for conventional `feat:`, `fix:`, or `perf:` commits
- **Pre-commit hook** (Husky): `format:fix` → `lint:js:fix` → `qa`

### Versioning (what stays in sync on release)

semantic-release (see `.releaserc.json`) automatically bumps and commits:

| File | Updated on release? |
| ---- | ------------------- |
| `style.css` (`Version:`) | Yes — WordPress theme version |
| `package.json` (`version`) | Yes |
| `CHANGELOG.md` | Yes |
| Release ZIP + `.sha256` | Yes — version stamped inside the packaged `style.css` |
| `README.md` / `CLAUDE.md` | **No** — do not hardcode the theme version here |
| Per-block `block.json` `version` | **No** — independent of theme releases |

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
- PHP 8.0+ (8.3 recommended; used in wp-env and CI)
- Node.js 24+ with pnpm 11+
- WooCommerce 7.0+ (recommended)
- Docker (for wp-env)

## Support

- Issues: https://github.com/TheAggressive/Aggressive-Apparel/issues

## License

GNU General Public License v2 or later — http://www.gnu.org/licenses/gpl-2.0.html

Developed by [The Aggressive Network, LLC](https://theaggressive.com)
