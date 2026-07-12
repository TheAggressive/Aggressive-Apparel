# CLAUDE.md - Aggressive Apparel Theme

This file provides guidance for AI assistants working with the Aggressive Apparel WordPress theme codebase.

## Overview

**Aggressive Apparel** is a modern WordPress Full Site Editing (FSE) block theme built specifically for WooCommerce. It features a service container architecture, custom Gutenberg blocks with Interactivity API support, toggleable store enhancements, and comprehensive testing infrastructure.

- **Version:** see `style.css` / `package.json` (semantic-release; do not hardcode here)
- **Requires:** WordPress 7.0+, PHP 8.0+
- **Tested up to:** WordPress 7.0
- **Package Manager:** pnpm 11+
- **License:** GPL-2.0-or-later

Canonical human-facing overview: [`README.md`](README.md). Keep architecture notes here; keep inventory counts and feature tables in the README when they change.

## Quick Commands

```bash
# Development
pnpm install          # Install dependencies
pnpm build            # Build all blocks and assets
pnpm dev              # Watch mode + start wp-env
pnpm start            # Watch mode only

# Testing
pnpm test             # Run all tests (JS + PHP)
pnpm test:unit        # PHP unit tests only
pnpm test:integration # PHP integration tests
pnpm test:js          # JavaScript tests

# Linting
pnpm lint:all         # Run all linters
pnpm lint:fix         # Fix all lint issues
pnpm analyse:php      # PHPStan static analysis

# Environment
pnpm env:start        # Start wp-env
pnpm env:stop         # Stop wp-env
pnpm setup            # Full setup: install → build → start
```

## Architecture

### Directory Structure

```
aggressive-apparel/
├── build/                    # Compiled output (git-ignored)
│   ├── blocks/               # Static blocks
│   ├── blocks-interactivity/ # Interactive blocks
│   ├── interactivity/        # Shared enhancement modules + nav stores
│   ├── icons/                # Generated brand icon definitions
│   ├── scripts/              # Theme scripts
│   └── styles/               # Theme styles
├── includes/                 # PHP classes (PSR-4 autoloaded)
│   ├── Assets/               # Script and style loaders
│   ├── Blocks/               # Block registration
│   ├── Core/                 # Theme supports, image sizes, icons, updates, adaptive colors
│   └── WooCommerce/          # WooCommerce integration + store enhancements
├── parts/                    # Template parts (header, footer)
├── patterns/                 # Block patterns
├── src/                      # Source code
│   ├── blocks/               # Static Gutenberg blocks (6)
│   ├── blocks-interactivity/ # Interactive blocks (Interactivity API, 36 incl. 2 card-flip faces)
│   ├── interactivity/        # Shared frontend modules (filters, quick view, nav stores)
│   ├── icons/                # Brand SVG sources
│   ├── scripts/              # Theme JavaScript/TypeScript
│   └── styles/               # Theme CSS
├── templates/                # FSE templates (13 HTML + emails/)
└── tests/                    # Test suites
```

### PHP Architecture

The theme uses a **service container** for registration and lazy resolution (most services are constructed with `new`; a few receive injected deps):

```
functions.php
    └── Autoloader (PSR-4)
    └── Bootstrap (Singleton)
        └── Service_Container
            ├── Core (theme support, icons, image sizes, adaptive colors, updates)
            ├── Assets (styles, scripts)
            ├── Blocks (auto-discovery from build/)
            └── WooCommerce (conditional)
                ├── Core WC support (templates, cart, product loop, color swatches)
                ├── Feature_Settings (19 toggles + store copy)
                └── Enhancements → individual feature classes
```

**Namespace:** `Aggressive_Apparel\`

**Key Classes:**

- [class-bootstrap.php](includes/class-bootstrap.php) - Main initialization, security headers
- [class-service-container.php](includes/class-service-container.php) - Service registry / factory
- [class-blocks.php](includes/Blocks/class-blocks.php) - Auto-discovers and registers blocks
- [class-theme-support.php](includes/Core/class-theme-support.php) - Theme features
- [class-icons.php](includes/Core/class-icons.php) - SVG icon system
- [class-enhancements.php](includes/WooCommerce/class-enhancements.php) - Feature flag coordinator

### Block System

Blocks are auto-discovered from `build/blocks/` and `build/blocks-interactivity/` directories. Full inventory and placement rules: [`README.md`](README.md) and [`docs/block-placement.md`](docs/block-placement.md).

**Static Blocks** (`src/blocks/`):
| Block | Description |
|-------|-------------|
| `aggressive-apparel-logo` | Brand logo component |
| `dark-mode-toggle` | Light/dark theme switcher |
| `copyright` | Footer copyright line |
| `icon` | Brand / UI icon picker |
| `product-rating` | Product rating display |
| `split-story` | Split editorial layout |

**Interactive Blocks** (`src/blocks-interactivity/`) — highlights:

| Block | Description |
|-------|-------------|
| `navigation` / `navigation-panel` | Desktop bar + mobile drawer (separate stores; see Navigation System) |
| `nav-link` | Shared leaf link |
| `nav-submenu-*` | Dropdown, mega, accordion, drilldown |
| `parallax` | Parallax effects |
| `animate-on-scroll` | Scroll-triggered animations |
| `filter-toggle` / `filter-active-bar` | Product filters UI (block-placed; ships own CSS) |
| `hero-carousel` | Hero carousel |
| `wishlist` (+ item blocks) | Wishlist page and heart toggle |
| `free-shipping-bar` / `free-shipping-message` | Free-shipping progress / copy |

**Product filter blocks:** When Product Filters is enabled, place `aggressive-apparel/filter-toggle` and `aggressive-apparel/filter-active-bar` on shop, category, and tag archive templates. There is no automatic injection — both blocks wire into the shared `aggressive-apparel/product-filters` Interactivity store. CSS lives in each block's `style.css`, not the global product-filters stylesheet. Agency placement rules for all commerce/nav blocks: [`docs/block-placement.md`](docs/block-placement.md).

**Creating New Blocks:**

```bash
# Static block
pnpm create-block <block-name>

# Dynamic block (PHP render)
pnpm create-block-dynamic <block-name>

# Interactive block (Interactivity API)
pnpm create-block-interactive <block-name>
```

## Coding Standards

### PHP

- Follow WordPress Coding Standards (WPCS 3.1)
- Use strict types: `declare(strict_types=1);`
- PHPStan level 6 analysis
- PSR-4 autoloading with WordPress naming conventions

**File Naming Convention:**

```
Class Name:              File Name:
Theme_Support     →      class-theme-support.php
Color_Admin_UI    →      class-color-admin-ui.php
WooCommerce_Support →    class-woocommerce-support.php
```

**Adding a New PHP Class:**

1. Create file in appropriate directory under `includes/`
2. Use namespace matching directory: `Aggressive_Apparel\Core\My_Class`
3. File name: `class-my-class.php`
4. Autoloader handles loading automatically

### JavaScript/TypeScript

- TypeScript for all new code
- React/JSX for block editor components
- ESLint with WordPress plugin
- Prettier for formatting

### CSS

- Tailwind CSS 4.x with PostCSS
- Stylelint for linting
- BEM-like naming for custom classes
- **Never use body-level `:has()`** (`body:has(...)`, `body.x:has(...)`) —
  it forces a document-wide style re-scan on EVERY childList mutation
  anywhere in the page (measured ~72ms per mutation). Mirror the state as
  a body class/attribute from the owning JS instead;
  `bin/check-design-system-css.sh` fails the build on violations.
  Component-scoped `:has()` subjects are fine.
- **Per-frame text updates must mutate a `Text` node's `.data`**, never
  assign `textContent` (which replaces the node — a childList mutation).

## Modal & Overlay Pattern

All full-screen modals and overlays **must** follow this consistent pattern:

### CSS

1. **Animated backdrop blur**: Base state `backdrop-filter: blur(0)`, transitions to `blur(4px)` on open. Both `background-color` and `backdrop-filter` are in the `transition` list so they animate in and out smoothly.
2. **`@starting-style`** for entry animation: Wrapped in `@supports selector(@starting-style)`. Defines the initial state (opacity 0, transform, blur 0) so the browser has a "before" state to transition from.
3. **`prefers-reduced-motion: reduce`**: Disables all transitions, animations, and `backdrop-filter`.
4. **`[hidden]` override**: `display: none` to ensure the hidden attribute works with flex/grid containers.

### JavaScript

1. **`lockScroll()` on open** (from `@aggressive-apparel/scroll-lock`): Called immediately when opening.
2. **`unlockScroll()` deferred to `transitionend`** on close: Listen for `transitionend` with `propertyName === 'opacity'` on the modal/panel element. Include a safety `setTimeout` fallback (~50ms after expected duration) for reduced motion or edge cases. Use a `done` flag to prevent double execution.
3. **`hidden` attribute managed manually**: Remove `hidden` + force reflow (`void el.offsetHeight`) before setting open state. Set `hidden = true` inside the same `finish()` callback as `unlockScroll()`.

### Current Implementations

| Component         | CSS                     | JS              | Backdrop Opacity |
| ----------------- | ----------------------- | --------------- | ---------------- |
| Quick View        | `quick-view.css`        | `quick-view.js` | 50%              |
| Size Guide        | `size-guide.css`        | `size-guide.js` | 80%              |
| Bottom Nav Search | `mobile-bottom-nav.css` | `bottom-nav.js` | 50%              |

## Testing

### Test Suites

```
tests/
├── Unit/                  # Fast, isolated tests
├── Integration/           # WordPress integration tests
├── Security/              # Security header tests
├── Accessibility/         # A11y compliance tests
└── Performance/           # Performance benchmarks
```

### Running Tests

```bash
# All PHP tests
pnpm test:php

# Specific suite
pnpm test:unit
pnpm test:integration
pnpm test:security
pnpm test:accessibility
pnpm test:performance

# With coverage
pnpm test:coverage

# JavaScript tests
pnpm test:js
pnpm test:js:watch
```

### Test Configuration

- PHPUnit 9.6 with Yoast Polyfills
- Jest for JavaScript
- wp-env for WordPress test environment

## WooCommerce Integration

WooCommerce features are **conditionally loaded** only when WooCommerce is active:

- Product gallery support (zoom, lightbox, slider)
- Custom product loop (3 columns, 12 products default)
- Color attribute/swatch management
- Cart and checkout templates

**WooCommerce Templates:**

- [archive-product.html](templates/archive-product.html)
- [single-product.html](templates/single-product.html)
- [page-cart.html](templates/page-cart.html)
- [page-checkout.html](templates/page-checkout.html)
- [taxonomy-product_cat.html](templates/taxonomy-product_cat.html)

### Color Swatch System

The theme includes a comprehensive color attribute system for product variations:

| Class                        | Purpose                              |
| ---------------------------- | ------------------------------------ |
| `Color_Attribute_Manager`    | Manages WooCommerce color attributes |
| `Color_Data_Manager`         | Handles color data persistence       |
| `Color_Block_Swatch_Manager` | Renders swatches in blocks           |
| `Color_Pattern_Admin`        | Admin UI for color patterns          |
| `Color_Admin_UI`             | Color swatch admin interface         |

## Block Debug Tooling (parallax / animate-on-scroll)

Both blocks share one debug implementation in `src/blocks-interactivity/debug-shared/`
(controller, panel, overlays, probe, perf monitor, i18n); the per-block files
(`parallax/debug/controller.ts`, `animate-on-scroll/debug.ts`) are thin adapters.
Inspector preset UI is likewise shared via `src/blocks-interactivity/editor-shared/`.

- **Visitors get zero debug bytes.** `debugMode` is gated in each `render.php`
  by `aggressive_apparel_can_view_block_debug()` (`edit_posts`, filterable) —
  gating the context prevents the code-split debug chunk from loading, and the
  debug CSS ships standalone (`src/styles/components/debug-overlays.css` stub →
  enqueued only when debug renders via `aggressive_apparel_enqueue_block_debug_assets()`,
  which also prints the translated `#aa-dbg-i18n` strings blob — keys mirror
  `debug-shared/i18n.ts`).
- **Cross-bundle rule:** shared dirs compile into EACH block's bundle, so
  module-level state cannot coordinate block types. Coordinate through the DOM
  (dataset counter on `<html>`, data-attribute refcounts, DOM element counts).
- **Activation buffer:** parallax expands its observer boundary by the
  `activationBuffer` attribute (% of viewport height, default 20, 0 disables)
  so the frame engine warms up before layers become visible — that's why the
  debug view shows both a "Detection boundary" and an "Observer boundary" for
  parallax but a single box for animate-on-scroll (which needs no warm-up).
- **Effective threshold:** production observers use `getEffectiveThreshold()`
  (`debug-shared/utils`, pure — safe to import from view code): elements taller
  than the root box auto-cap the trigger at 90% of the reachable ratio. The
  debug UI displays the same effective value (parallax stashes it on
  `ctx.effectiveThreshold`).
- The debug probe runs its own dense-threshold IntersectionObserver with the
  production rootMargin — never widen production observer thresholds for
  debugging.

## Navigation System

Navigation is split into **two independent block subsystems**, each with its own
root block and Interactivity store. `nav-link` is the shared leaf used by both.

### Desktop subsystem — `aggressive-apparel/navigation`

Horizontal menu bar. Store: `aggressive-apparel/navigation` ([navigation/store.ts](src/blocks-interactivity/navigation/store.ts)).

| Block                  | Role                                                                                                              |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------- |
| `navigation`           | Root container. Provides submenu theming context (`navigationId`, `submenuBackgroundColor`, border/radius, etc.). |
| `navigation-trigger`   | Hamburger button. Lives in the desktop nav but drives the **mobile panel** store (opens the drawer).              |
| `nav-submenu-dropdown` | Click/hover dropdown (`ancestor: navigation`).                                                                    |
| `nav-submenu-mega`     | Full-width mega menu with arbitrary inner blocks (`ancestor: navigation`).                                        |

### Mobile subsystem — `aggressive-apparel/navigation-panel`

Slide-in drawer, **portaled to `wp_footer`** so `position: fixed` escapes ancestor
stacking/transform contexts. Store: `aggressive-apparel/navigation-panel`
([navigation-panel/store.ts](src/blocks-interactivity/navigation-panel/store.ts)).
Mutable state lives in `state._panels[panelSlug]`, shared between the trigger and
the portaled panel.

| Block                                   | Role                                                                                          |
| --------------------------------------- | --------------------------------------------------------------------------------------------- |
| `navigation-panel`                      | Root drawer. Provides `navigationId` (= panelSlug) + panel hover color context.               |
| `nav-panel-header` / `nav-panel-footer` | Optional drawer chrome (`parent: navigation-panel`).                                          |
| `nav-submenu-accordion`                 | Expand-in-place submenu (`ancestor: navigation-panel`).                                       |
| `nav-submenu-drilldown`                 | Slide-over submenu, supports nesting + overlay/push animation (`ancestor: navigation-panel`). |

### Shared leaf

- `nav-link` — single link. `parent` of every container above. Consumes `navigationId` context.

### Shared code

- `nav-shared/dom.ts` + `nav-shared/keys.ts` hold the helpers/constants that are
  byte-identical across both subsystems (`logError`/`logWarning`,
  `safeQuerySelector*`, `safeGetElementById`, `prefersReducedMotion`, `KEYS`,
  `ARROW_KEYS`, `FOCUSABLE_SELECTOR`, `TRANSITION_DURATION_MS`). Each subsystem's
  `utils.ts`/`constants.ts` re-exports them, so internal `from './utils'` /
  `from './constants'` imports are unchanged. Subsystem-specific things
  (`SELECTORS`, `announce`, `focusMenuItem`, state classes, timing, ID helpers)
  intentionally stay per-subsystem because they differ.

### Cross-subsystem gotchas

- Because the panel is portaled, **`data-wp-bind` / `data-wp-class` directives don't
  react across the portal boundary**. Drilldown open-state class and the trigger's
  `aria-expanded` are toggled imperatively in `callbacks.onSubmenuStateChange`.
- `focus()` on an element inside an off-screen sliding panel cancels the slide —
  always pass `{ preventScroll: true }` for in-panel focus moves.
- The blocks have **no view modules**. Each subsystem's store is shipped once as a
  shared script module (`@aggressive-apparel/navigation-store` /
  `-panel-store`, built from `src/interactivity/`) and enqueued directly in
  `class-navigation-functions.php`; the store self-registers via `store()` before
  hydration. `supports.interactivity` loads the runtime; `render.php` emits the
  directives. Don't add per-block `viewScriptModule`s back — `wp_enqueue_script_module`
  alone doesn't add a bare specifier to the import map (only declared deps of
  enqueued modules get mapped), so a view module importing the store would fail.

**Key Attributes:**

- `breakpoint`: Mobile breakpoint (default: 1024px)
- `openOn`: "hover" or "click" for desktop submenus
- `position`: "left" or "right" for the mobile panel
- drilldown `animationStyle`: "overlay" or "push" (iOS-style parallax)

## WordPress Hooks

### Actions

```php
// After theme initialization
do_action('aggressive_apparel_init');

// After image sizes registered
do_action('aggressive_apparel_after_image_sizes');
```

### Filters

```php
// Modify body classes
add_filter('body_class', ...);

// Custom image size names in media library
add_filter('image_size_names_choose', ...);
```

## Build System

### Webpack Configurations

1. **webpack.config.mjs** - Block compilation (via @wordpress/scripts)
2. **webpack.assets.config.mjs** - Theme scripts/styles

### Build Commands

```bash
pnpm build               # Build all
pnpm build:blocks        # Static blocks only
pnpm build:interactivity # Interactive blocks only
pnpm build:assets        # Scripts and styles only
```

### Asset Loading

Assets use `.asset.php` files for dependency management:

```php
// Automatically generated
return array(
    'dependencies' => array('wp-blocks', 'wp-element'),
    'version' => '1.0.0-abc123' // content hash from the build, not the theme version
);
```

## Development Environment

### wp-env Configuration

```json
{
  "phpVersion": "8.3",
  "port": 9910,
  "testsPort": 9920
}
```

### Debug Flags

- `WP_DEBUG`: true
- `WP_DEBUG_LOG`: true
- `SCRIPT_DEBUG`: true

## Security

The theme adds security headers via `Bootstrap::add_security_headers()`:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`

## Theme Constants

```php
AGGRESSIVE_APPAREL_VERSION  // Theme version from style.css (release-managed)
AGGRESSIVE_APPAREL_DIR      // Theme directory path
AGGRESSIVE_APPAREL_URI      // Theme directory URI
```

## Helper Functions

```php
aggressive_apparel_asset_uri($path)     // Get asset URL
aggressive_apparel_asset_path($path)    // Get asset file path
aggressive_apparel_free_shipping_threshold() // Free-shipping threshold (filterable)
```

## Custom Image Sizes

Retina-ready image sizes defined in [class-image-sizes.php](includes/Core/class-image-sizes.php):

| Size Name                              | Dimensions | Use Case                |
| -------------------------------------- | ---------- | ----------------------- |
| `aggressive-apparel-product-featured`  | 1200x1200  | Product hero images     |
| `aggressive-apparel-product-thumbnail` | 400x400    | Product grid thumbnails |
| `aggressive-apparel-product-gallery`   | 1200x1200  | Product gallery images  |
| `aggressive-apparel-blog-featured`     | 1600x900   | Blog hero (16:9)        |
| `aggressive-apparel-blog-thumbnail`    | 600x400    | Blog cards (3:2)        |

## SVG Icon System

Centralized icon library in [class-icons.php](includes/Core/class-icons.php):

```php
// Get icon SVG markup
Icons::get('cart', ['width' => 32, 'height' => 32]);

// Render icon directly
Icons::render('search', ['class' => 'icon-search']);

// Check if icon exists
Icons::exists('hamburger'); // true

// List all available icons
Icons::list();
```

**Available Icons (UI library highlights):**

- **Navigation:** `hamburger`, `dots`, `bars`, `close`, `chevron-*`, `arrow-*`, `home`
- **Actions:** `search`, `cart`, `user`, `heart`, `eye`, `filter`, `grid-view`, `list-view`
- **UI:** `check`, `plus`, `minus`, `info`, `play`, `pause`, `warning`, `error`
- **Social / brand:** `facebook`, `twitter`, `instagram`, `brand-mark`, `paths`

Brand icons (40 SVGs under `src/icons/`) are built to `build/icons/` and loaded via `Brand_Icons` / the `icon` block.

## Design Tokens (theme.json)

### Layout

- **Content Width:** 1200px
- **Wide Width:** 1400px

### Color Palette

The editor swatch **name matches the slug** (so `var(--aa-color-foreground)` ↔
"Foreground"), and adaptive (light/dark) colors carry an **"(Adaptive)"** suffix
so it's clear they shift with the color scheme.

| Editor name                     | Slug (= CSS)           | Value                                  |
| ------------------------------- | ---------------------- | -------------------------------------- |
| Primary                         | `primary`              | Legacy alias to adaptive Accent        |
| Red                             | `red`                  | Legacy alias to adaptive Accent        |
| White                           | `white`                | `#ffffff`                              |
| Black                           | `black`                | `#000000`                              |
| Transparent                     | `transparent`          | `transparent` (explicit "no fill")     |
| Surface (Adaptive)              | `surface`              | Adaptive page/section background       |
| Surface Elevated (Adaptive)     | `surface-elevated`     | Adaptive higher-contrast surface       |
| Foreground (Adaptive)           | `foreground`           | Adaptive primary text                  |
| Foreground Muted (Adaptive)     | `foreground-muted`     | Adaptive secondary text                |
| Accent (Adaptive)               | `accent`               | Adaptive brand interactive color       |
| Accent on Foreground (Adaptive) | `accent-on-foreground` | Inverse accent for foreground surfaces |
| Border (Adaptive)               | `border`               | Adaptive borders/dividers              |
| Success                         | `success`              | `oklch(52.7% 0.137 150.1)` (status)    |
| Warning                         | `warning`              | `oklch(55.3% 0.174 38.4)` (status)     |
| Error                           | `error`                | `oklch(57.7% 0.215 27.3)` (status)     |
| Info                            | `info`                 | `oklch(48.8% 0.217 264.4)` (status)    |
| Neutral                         | `neutral`              | `oklch(55.1% 0.023 264.4)` (status)    |

Adaptive colors are injected as `light-dark()` palette entries by
`Core/Adaptive_Colors` from `settings.custom.adaptiveColors` (edit their `light`/
`dark` values there). Status colors are plain palette swatches.

### Source of Truth & Token Layer

**theme.json `settings` is the single source of truth.** `src/styles/base/tokens.css`
(`--aa-*`) is a thin **consumer/alias + derivation + `@property`** layer on top of
the WordPress-generated `--wp--preset--*` / `--wp--custom--*` variables — not the
source. Don't move values out of theme.json into tokens.css.

Slot rule: values an editor should be able to pick live in **UI-backed preset
slots** (`color.palette`, `shadow.presets`, `spacing.spacingSizes`,
`typography.fontSizes`); CSS-only internals (radius, motion, z-index) stay in
`settings.custom`. Status colors → palette; shadows → `shadow.presets`; the
matching `--aa-*` tokens repoint at those presets.

**Three editor surfaces:** front end; the editor **canvas iframe** (gets theme.json
presets + tokens via `add_theme_support('editor-styles')` + `add_editor_style('build/styles/base/tokens.css')` in `Core/Theme_Support`); and the editor **chrome**
(sidebar/popovers — no presets, so `var(--aa-*)` can't resolve there — use the JS
map in `src/utils/editor-style-tokens.ts`).

**Override principle:** the design system must NOT style what the block editor
controls (buttons, element/block styling), and our CSS must stay overridable by
the editor. Style only what the editor can't reach (raw inputs, WC-encapsulated
fields, select2, autofill); keep it low priority (`@layer` + `:where()`); never use
`!important` or unlayered/high-specificity to beat editor/theme.json output. WC
overrides may be unlayered only to beat WooCommerce's own unlayered CSS, and set
only structural props (use `color: inherit` so editor colors win).

### Forms

`src/styles/components/forms.css` is the single token-driven form layer: native
`input`/`select`/`textarea` baseline (layered + `:where()`), plus unlayered
WooCommerce (block + classic), select2, file input, autofill, and `:user-invalid`
coverage. Buttons are intentionally NOT styled here (editor-controlled).

### Style Variations (FSE)

Theme style variations live in `/styles/*.json` (e.g. `styles/noir.json`). Block
style variations are defined in theme.json `styles.blocks.*.variations` (e.g. the
`core/group` "Card") or via `register_block_style` in `Core/Theme_Support`.

### Spacing Scale

47 spacing presets from `0.5` (0.125rem) to `96` (24rem), including fluid variants with `clamp()` for responsive spacing.

### Typography

System font stack with 20+ fluid font sizes using `clamp()` for responsive scaling.

## Block Patterns

Located in `patterns/` (80 files). Prefer the Site Editor / pattern inserter over maintaining a full list here. Representative categories include navigation, shop archives, PDP conversion, homepage merchandising, and cart recovery. Placement guidance: [`docs/block-placement.md`](docs/block-placement.md).

## Git Workflow

### Commit Convention

Uses [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` New feature
- `fix:` Bug fix
- `refactor:` Code refactoring
- `docs:` Documentation
- `test:` Tests
- `chore:` Maintenance

### Pre-commit Hooks

Husky runs `pnpm precommit` which executes:

- Format fixes
- Lint fixes
- Commitlint validation

### Semantic Release

Automated versioning and changelog generation via semantic-release (`.releaserc.json`).

**Auto-updated on release:** `style.css`, `package.json`, `CHANGELOG.md`, and the packaged theme ZIP.

**Not auto-updated:** `README.md`, `CLAUDE.md`, docs, or per-block `block.json` versions. Do not hardcode the theme version in assistant/docs files — point at `style.css` / `package.json`. Update inventory counts (blocks, patterns, features) in the same PR that changes them.

## Common Tasks

### Adding a New Block

1. Create block: `pnpm create-block-interactive my-block`
2. Edit files in `src/blocks-interactivity/my-block/`
3. Build: `pnpm build:interactivity`
4. Block auto-registers on next page load

### Adding a New Pattern

1. Create `patterns/my-pattern.php`
2. Add header comment with Title, Slug, Categories
3. Add block markup

### Adding WooCommerce Feature

1. Create class(es) in `includes/WooCommerce/`
2. Add a feature definition in `Feature_Settings::get_feature_definitions()`
3. Map the feature key → class(es) in `Enhancements` (feature map)
4. Gate hooks/assets with `Feature_Settings::is_enabled()` so disabled features load nothing

### Running Static Analysis

```bash
pnpm analyse:php           # Run PHPStan
pnpm analyse:php:baseline  # Generate baseline for existing issues
```

## Troubleshooting

### Blocks Not Appearing

1. Run `pnpm build`
2. Check `build/` directory exists
3. Clear browser cache
4. Check for PHP errors in debug.log

### Tests Failing

1. Ensure wp-env is running: `pnpm env:start`
2. Run `composer install` in wp-env: `pnpm cli composer install`
3. Check PHP version compatibility

### Styles Not Loading

1. Verify `build/styles/` exists
2. Check browser dev tools for 404s
3. Run `pnpm build:assets`

## Important Files

| File                                 | Purpose                       |
| ------------------------------------ | ----------------------------- |
| [functions.php](functions.php)       | Theme bootstrap               |
| [theme.json](theme.json)             | Block theme configuration     |
| [style.css](style.css)               | Theme metadata                |
| [package.json](package.json)         | Node dependencies and scripts |
| [composer.json](composer.json)       | PHP dependencies              |
| [phpunit.xml.dist](phpunit.xml.dist) | PHPUnit configuration         |
| [phpstan.neon](phpstan.neon)         | Static analysis config        |
| [.wp-env.json](.wp-env.json)         | Development environment       |
