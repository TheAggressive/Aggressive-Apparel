# Theme architecture & build

Service container, block system, build pipeline, environment, testing, and
task recipes. Rules that constrain every change stay in `CLAUDE.md`.

---

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
│   ├── blocks/               # Static Gutenberg blocks (8, incl. 2 split-story columns)
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
            ├── Core (theme support, icons, image sizes, adaptive colors, theme features, updates)
            ├── Assets (styles, scripts)
            ├── Blocks (auto-discovery from build/)
            └── WooCommerce (conditional)
                ├── Core WC support (templates, cart, product loop, color swatches)
                ├── Feature_Settings (17 toggles + store copy)
                └── Enhancements → individual feature classes
```

**Namespace:** `Aggressive_Apparel\`

**Key Classes:**

- [class-bootstrap.php](../includes/class-bootstrap.php) - Main initialization, security headers
- [class-service-container.php](../includes/class-service-container.php) - Service registry / factory
- [class-blocks.php](../includes/Blocks/class-blocks.php) - Auto-discovers and registers blocks
- [class-theme-support.php](../includes/Core/class-theme-support.php) - Theme features
- [class-icons.php](../includes/Core/class-icons.php) - SVG icon system
- [class-enhancements.php](../includes/WooCommerce/class-enhancements.php) - Feature flag coordinator

### Block System

Blocks are auto-discovered from `build/blocks/` and `build/blocks-interactivity/` directories. Full inventory and placement rules: [`README.md`](../README.md) and [`block-placement.md`](block-placement.md).

**Static Blocks** (`src/blocks/`):

| Block                     | Description               |
| ------------------------- | ------------------------- |
| `aggressive-apparel-logo` | Brand logo component      |
| `dark-mode-toggle`        | Light/dark theme switcher |
| `copyright`               | Footer copyright line     |
| `icon`                    | Brand / UI icon picker    |
| `product-rating`          | Product rating display    |
| `split-story`             | Split editorial layout    |

**Interactive Blocks** (`src/blocks-interactivity/`) — highlights:

| Block                                         | Description                                                          |
| --------------------------------------------- | -------------------------------------------------------------------- |
| `navigation` / `navigation-panel`             | Desktop bar + mobile drawer (separate stores; see Navigation System) |
| `nav-link`                                    | Shared leaf link                                                     |
| `nav-submenu-*`                               | Dropdown, mega, accordion, drilldown                                 |
| `parallax`                                    | Parallax effects                                                     |
| `animate-on-scroll`                           | Scroll-triggered animations                                          |
| `filter-toggle` / `filter-active-bar`         | Product filters UI (block-placed; ships own CSS)                     |
| `hero-carousel`                               | Hero carousel                                                        |
| `horizontal-scroll`                           | Pinned / paged / native rails (`paged` = directional snap)           |
| `wishlist` (+ item blocks)                    | Wishlist page and heart toggle                                       |
| `free-shipping-bar` / `free-shipping-message` | Free-shipping progress / copy                                        |

**Product filter blocks:** When Product Filters is enabled, place `aggressive-apparel/filter-toggle` and `aggressive-apparel/filter-active-bar` on shop, category, and tag archive templates. There is no automatic injection — both blocks wire into the shared `aggressive-apparel/product-filters` Interactivity store. CSS lives in each block's `style.css`, not the global product-filters stylesheet. Agency placement rules for all commerce/nav blocks: [`block-placement.md`](block-placement.md).

**Creating New Blocks:**

```bash
# Static block
pnpm create-block <block-name>

# Dynamic block (PHP render)
pnpm create-block-dynamic <block-name>

# Interactive block (Interactivity API)
pnpm create-block-interactive <block-name>
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

### WordPress Studio

`bin/local/studio.mjs` discovers the registered Studio site that contains this
checkout. Never hardcode the Studio URL or port. Run WP-CLI through `pnpm cli`
or `studio wp`; Studio uses SQLite. PHPUnit uses the isolated native MySQL/Core
runner under `.cache/local/` and must never target the Studio database.

The pinned `bin/ci/.wp-env.json` configuration belongs only to CI parity.

### Debug Flags

- `WP_DEBUG`: true
- `WP_DEBUG_LOG`: true
- `SCRIPT_DEBUG`: true

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

# End-to-end (Playwright, drives the real editor + front end)
pnpm test:e2e:install   # one-time: download the browser (CI: installs system deps)
pnpm test:e2e           # builds and drives the registered Studio site
pnpm test:e2e:ci        # isolated containerized release parity
```

### Test Configuration

- PHPUnit 9.6 with Yoast Polyfills
- Jest for JavaScript
- Playwright for end-to-end (`playwright.config.ts`, specs in `tests/e2e/`)
- WordPress Studio for browser tests and interactive development
- Native disposable MySQL/Core fixtures for PHPUnit
- wp-env only for CI release parity

**E2E** (`tests/e2e/`) covers browser behavior unit tests can't — the card-flip
3D flip + `inert` a11y and the split-story sticky/grid/gap layout. `global-setup.ts`
logs in once (admin/password) and saves the session; each spec builds its block
via `wp.data`, publishes, asserts on the rendered front end, and deletes the page.
CI must run `pnpm test:e2e:install` before `pnpm ci:e2e`. (On WSL without sudo,
the browser deps can't install — run with `PLAYWRIGHT_SKIP_VALIDATE_HOST_REQUIREMENTS=1`
and an `LD_LIBRARY_PATH` to extracted libnss3/libnspr4/libasound2 debs.)

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

## Theme Constants

```php
AGGRESSIVE_APPAREL_VERSION  // Theme version from style.css (artifact-stamped)
AGGRESSIVE_APPAREL_DIR      // Theme directory path
AGGRESSIVE_APPAREL_URI      // Theme directory URI
```

## Helper Functions

```php
aggressive_apparel_asset_uri($path)     // Get asset URL
aggressive_apparel_asset_path($path)    // Get asset file path
aggressive_apparel_free_shipping_threshold() // Free-shipping threshold (filterable)
```

## SVG Icon System

Centralized icon library in [class-icons.php](../includes/Core/class-icons.php):

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
- **Actions:** `search`, `cart`, `user`, `heart`, `heart-outline`, `eye`, `filter`, `grid-view`, `list-view`
- **UI:** `check`, `plus`, `minus`, `info`, `play`, `pause`, `warning`, `error`
- **Social / brand:** `facebook`, `twitter`, `instagram`, `brand-mark`, `paths`

Brand icons (42 SVGs under `src/icons/`) are built to `build/icons/` and loaded via `Brand_Icons` / the `icon` block.

## Custom Image Sizes

Retina-ready image sizes defined in [class-image-sizes.php](../includes/Core/class-image-sizes.php):

| Size Name                              | Dimensions | Use Case                |
| -------------------------------------- | ---------- | ----------------------- |
| `aggressive-apparel-product-featured`  | 1200x1200  | Product hero images     |
| `aggressive-apparel-product-thumbnail` | 400x400    | Product grid thumbnails |
| `aggressive-apparel-product-gallery`   | 1200x1200  | Product gallery images  |
| `aggressive-apparel-blog-featured`     | 1600x900   | Blog hero (16:9)        |
| `aggressive-apparel-blog-thumbnail`    | 600x400    | Blog cards (3:2)        |

## Block Patterns

Located in `patterns/` (83 files). Prefer the Site Editor / pattern inserter over maintaining a full list here. Representative categories include navigation, shop archives, PDP conversion, homepage merchandising, and cart recovery. Placement guidance: [`block-placement.md`](block-placement.md).

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

1. Run `composer install`
2. Verify the native test database and Core fixtures with `pnpm test:unit`
3. Check PHP/MySQL versions printed by the native runner

### Styles Not Loading

1. Verify `build/styles/` exists
2. Check browser dev tools for 404s
3. Run `pnpm build:assets`

## Security

The theme adds security headers via `Bootstrap::add_security_headers()`:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`
