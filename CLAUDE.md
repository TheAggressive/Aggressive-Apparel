# CLAUDE.md - Aggressive Apparel Theme

This file provides guidance for AI assistants working with the Aggressive Apparel WordPress theme codebase.

## Overview

**Aggressive Apparel** is a modern WordPress Full Site Editing (FSE) block theme built specifically for WooCommerce. It features a sophisticated service container architecture, custom Gutenberg blocks with Interactivity API support, and comprehensive testing infrastructure.

- **Version:** 1.16.0
- **Requires:** WordPress 6.0+, PHP 8.0+
- **Tested up to:** WordPress 6.8
- **Package Manager:** pnpm
- **License:** GPL-2.0-or-later

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
│   ├── blocks/              # Static blocks
│   ├── blocks-interactivity/# Interactive blocks
│   ├── scripts/             # Theme scripts
│   └── styles/              # Theme styles
├── includes/                 # PHP classes (PSR-4 autoloaded)
│   ├── Assets/              # Script and style loaders
│   ├── Blocks/              # Block registration
│   ├── Core/                # Theme supports, image sizes, icons
│   └── WooCommerce/         # WooCommerce integration
├── parts/                    # Template parts (header, footer)
├── patterns/                 # Block patterns
├── src/                      # Source code
│   ├── blocks/              # Static Gutenberg blocks
│   ├── blocks-interactivity/# Interactive blocks (Interactivity API)
│   ├── scripts/             # Theme JavaScript/TypeScript
│   └── styles/              # Theme CSS
├── templates/                # FSE templates
└── tests/                    # Test suites
```

### PHP Architecture

The theme uses a **service container pattern** with dependency injection:

```
functions.php
    └── Autoloader (PSR-4)
    └── Bootstrap (Singleton)
        └── Service_Container
            ├── Core services (theme_support, image_sizes, etc.)
            ├── Asset services (styles, scripts)
            └── WooCommerce services (conditional)
```

**Namespace:** `Aggressive_Apparel\`

**Key Classes:**
- [class-bootstrap.php](includes/class-bootstrap.php) - Main initialization, security headers
- [class-service-container.php](includes/class-service-container.php) - DI container
- [class-blocks.php](includes/Blocks/class-blocks.php) - Auto-discovers and registers blocks
- [class-theme-support.php](includes/Core/class-theme-support.php) - Theme features
- [class-icons.php](includes/Core/class-icons.php) - SVG icon system

### Block System

Blocks are auto-discovered from `build/blocks/` and `build/blocks-interactivity/` directories.

**Static Blocks** (`src/blocks/`):
| Block | Description |
|-------|-------------|
| `aggressive-apparel-logo` | Brand logo component |
| `dark-mode-toggle` | Light/dark theme switcher |
| `copyright` | Footer copyright line |

**Interactive Blocks** (`src/blocks-interactivity/`):
| Block | Description |
|-------|-------------|
| `navigation` | Responsive navigation (toggle, mobile panel, menu bar) |
| `nav-link` | Single navigation link (child of navigation or nav-submenu) |
| `nav-submenu` | Dropdown and mega menu support |
| `parallax` | Advanced parallax effects |
| `animate-on-scroll` | Scroll-triggered animations |

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

| Component | CSS | JS | Backdrop Opacity |
|-----------|-----|-----|-----------------|
| Quick View | `quick-view.css` | `quick-view.js` | 50% |
| Size Guide | `size-guide.css` | `size-guide.js` | 80% |
| Bottom Nav Search | `mobile-bottom-nav.css` | `bottom-nav.js` | 50% |

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

| Class | Purpose |
|-------|---------|
| `Color_Attribute_Manager` | Manages WooCommerce color attributes |
| `Color_Data_Manager` | Handles color data persistence |
| `Color_Block_Swatch_Manager` | Renders swatches in blocks |
| `Color_Pattern_Admin` | Admin UI for color patterns |
| `Color_Admin_UI` | Color swatch admin interface |

## Navigation System

Navigation is split into **two independent block subsystems**, each with its own
root block and Interactivity store. `nav-link` is the shared leaf used by both.

### Desktop subsystem — `aggressive-apparel/navigation`
Horizontal menu bar. Store: `aggressive-apparel/navigation` ([navigation/store.ts](src/blocks-interactivity/navigation/store.ts)).

| Block | Role |
|-------|------|
| `navigation` | Root container. Provides submenu theming context (`navigationId`, `submenuBackgroundColor`, border/radius, etc.). |
| `navigation-trigger` | Hamburger button. Lives in the desktop nav but drives the **mobile panel** store (opens the drawer). |
| `nav-submenu-dropdown` | Click/hover dropdown (`ancestor: navigation`). |
| `nav-submenu-mega` | Full-width mega menu with arbitrary inner blocks (`ancestor: navigation`). |

### Mobile subsystem — `aggressive-apparel/navigation-panel`
Slide-in drawer, **portaled to `wp_footer`** so `position: fixed` escapes ancestor
stacking/transform contexts. Store: `aggressive-apparel/navigation-panel`
([navigation-panel/store.ts](src/blocks-interactivity/navigation-panel/store.ts)).
Mutable state lives in `state._panels[panelSlug]`, shared between the trigger and
the portaled panel.

| Block | Role |
|-------|------|
| `navigation-panel` | Root drawer. Provides `navigationId` (= panelSlug) + panel hover color context. |
| `nav-panel-header` / `nav-panel-footer` | Optional drawer chrome (`parent: navigation-panel`). |
| `nav-submenu-accordion` | Expand-in-place submenu (`ancestor: navigation-panel`). |
| `nav-submenu-drilldown` | Slide-over submenu, supports nesting + overlay/push animation (`ancestor: navigation-panel`). |

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
    'version' => '1.16.0-abc123'
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
AGGRESSIVE_APPAREL_VERSION  // Theme version from style.css
AGGRESSIVE_APPAREL_DIR      // Theme directory path
AGGRESSIVE_APPAREL_URI      // Theme directory URI
```

## Helper Functions

```php
aggressive_apparel_asset_uri($path)     // Get asset URL
aggressive_apparel_asset_path($path)    // Get asset file path
```

## Custom Image Sizes

Retina-ready image sizes defined in [class-image-sizes.php](includes/Core/class-image-sizes.php):

| Size Name | Dimensions | Use Case |
|-----------|------------|----------|
| `aggressive-apparel-product-featured` | 1200x1200 | Product hero images |
| `aggressive-apparel-product-thumbnail` | 400x400 | Product grid thumbnails |
| `aggressive-apparel-product-gallery` | 1200x1200 | Product gallery images |
| `aggressive-apparel-blog-featured` | 1600x900 | Blog hero (16:9) |
| `aggressive-apparel-blog-thumbnail` | 600x400 | Blog cards (3:2) |

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

**Available Icons:**
- **Navigation:** `hamburger`, `close`, `chevron-down`, `chevron-up`, `chevron-left`, `chevron-right`, `arrow-left`, `arrow-right`
- **Actions:** `search`, `cart`, `user`, `heart`
- **UI:** `check`, `plus`, `minus`, `info`, `warning`, `error`
- **Social:** `facebook`, `twitter`, `instagram`

## Design Tokens (theme.json)

### Layout
- **Content Width:** 1200px
- **Wide Width:** 1400px

### Color Palette

The editor swatch **name matches the slug** (so `var(--aa-color-foreground)` ↔
"Foreground"), and adaptive (light/dark) colors carry an **"(Adaptive)"** suffix
so it's clear they shift with the color scheme.

| Editor name | Slug (= CSS) | Value |
|------|------|-------|
| Primary | `primary` | Brand red (`var(--wp--preset--color--red)`); token `--aa-color-brand` |
| Red | `red` | `oklch(57.7% 0.245 27.325)`; token `--aa-color-brand-red` |
| White | `white` | `#ffffff` |
| Black | `black` | `#000000` |
| Surface (Adaptive) | `surface` | Adaptive page/section background |
| Surface Elevated (Adaptive) | `surface-elevated` | Adaptive higher-contrast surface |
| Foreground (Adaptive) | `foreground` | Adaptive primary text |
| Foreground Muted (Adaptive) | `foreground-muted` | Adaptive secondary text |
| Accent (Adaptive) | `accent` | Adaptive brand interactive color |
| Border (Adaptive) | `border` | Adaptive borders/dividers |
| Success | `success` | `#15803d` (status) |
| Warning | `warning` | `#c2410c` (status) |
| Error | `error` | `#dc2626` (status) |
| Info | `info` | `#1d4ed8` (status) |
| Neutral | `neutral` | `#6b7280` (status) |

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

Located in `patterns/`:

| Pattern | Description |
|---------|-------------|
| `navigation-simple` | Basic horizontal navigation |
| `navigation-centered` | Center-aligned navigation |
| `navigation-with-dropdowns` | Navigation with dropdown menus |
| `navigation-mega-menu` | Full mega menu implementation |
| `hero-product-launch` | Hero section for products |
| `product-showcase-grid` | Product grid display |
| `customer-testimonials` | Testimonial section |

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
Automated versioning and changelog generation via semantic-release.

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

1. Create class in `includes/WooCommerce/`
2. Register in `Bootstrap::register_services()`
3. Initialize in `Bootstrap::init_woocommerce_components()`

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

| File | Purpose |
|------|---------|
| [functions.php](functions.php) | Theme bootstrap |
| [theme.json](theme.json) | Block theme configuration |
| [style.css](style.css) | Theme metadata |
| [package.json](package.json) | Node dependencies and scripts |
| [composer.json](composer.json) | PHP dependencies |
| [phpunit.xml.dist](phpunit.xml.dist) | PHPUnit configuration |
| [phpstan.neon](phpstan.neon) | Static analysis config |
| [.wp-env.json](.wp-env.json) | Development environment |
