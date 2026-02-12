# Aggressive Apparel

Official WooCommerce Block Theme for [Aggressive Apparel](https://aggressive-apparel.com) — a modern Full Site Editing (FSE) theme with 16 toggleable store enhancements, 19 custom blocks, and 36 block patterns.

**Version:** 1.16.0 &middot; **Requires:** WordPress 6.0+ / PHP 8.0+ &middot; **License:** GPL-2.0-or-later

## Features

- **Full Site Editing** — 13 templates, 36 block patterns, complete theme.json configuration
- **WooCommerce Integration** — product gallery, color swatches, custom templates for shop/cart/checkout
- **16 Store Enhancements** — premium features behind toggle flags, zero overhead when disabled
- **19 Custom Blocks** — 15 interactive (Interactivity API) + 4 static Gutenberg blocks
- **Interactivity API** — client-side reactivity without a JavaScript framework
- **Automatic Updates** — GitHub release-based update system with ETag caching
- **Accessible** — WCAG 2.1 AA compliant, 44px touch targets, `prefers-reduced-motion` support
- **Secure** — security headers, nonce verification, output escaping, capability checks
- **106 PHPUnit Tests** — unit, integration, security, accessibility, and performance suites

## Store Enhancements

All features are managed via **Appearance > Store Enhancements** and default to OFF. The `Feature_Settings::is_enabled()` / `Enhancements` coordinator pattern ensures zero hooks or assets load for disabled features.

| Feature | Type | Description |
|---------|------|-------------|
| Product Badges | Server-side | Sale, new, out-of-stock badges on product cards |
| Smart Price Display | Server-side | Enhanced price formatting with savings display |
| Product Tabs Manager | Server-side | Custom product detail tabs |
| Free Shipping Progress Bar | Server-side | Cart progress bar toward free shipping threshold |
| Swatch Tooltips | CSS | Hover tooltips on color/size swatches |
| Mini Cart Styling | CSS | Enhanced mini cart appearance |
| Product Filter Styling | CSS | Styled product archive filters |
| Page Transitions | CSS | View Transitions API for smooth cross-page navigation |
| Size Guide | Interactive | Size guide modal with custom post type |
| Sale Countdown Timer | Interactive | Urgency timer on sale products |
| Recently Viewed Products | Interactive | localStorage-based recently viewed section |
| Predictive Search | Interactive | Live product results as user types |
| Sticky Add to Cart | Interactive | Fixed bar when main CTA scrolls out of view |
| Mobile Bottom Navigation | Interactive | Fixed bottom nav bar on mobile devices |
| Quick View | Rich | Product modal with add-to-cart from archives |
| Wishlist | Rich | Heart-icon toggle with localStorage and Store API |
| Social Proof | Rich | Real-time purchase notification toasts |
| Frequently Bought Together | Rich | Product bundling with combined add-to-cart |
| Back in Stock Notifications | Rich | Email subscriptions for out-of-stock products |
| Stock Status | Rich | Enhanced stock status display |

## Quick Start

```bash
# Install dependencies
pnpm install

# Build all blocks and assets
pnpm build

# Start development (watch mode + wp-env)
pnpm dev

# Run full quality assurance
pnpm qa  # tests + linting + PHPStan
```

### Development Commands

| Command | Description |
|---------|-------------|
| `pnpm build` | Build blocks, interactivity modules, and assets |
| `pnpm dev` | Watch mode + wp-env |
| `pnpm test` | All tests (JS + PHP) |
| `pnpm test:unit` | PHP unit tests |
| `pnpm test:integration` | PHP integration tests |
| `pnpm test:security` | Security header tests |
| `pnpm test:accessibility` | A11y compliance tests |
| `pnpm test:performance` | Performance benchmarks |
| `pnpm lint:all` | ESLint + Stylelint + PHPCS |
| `pnpm analyse:php` | PHPStan level 6 |
| `pnpm qa` | Tests + linting + PHPStan |
| `pnpm env:start` | Start wp-env (port 9910) |
| `pnpm env:stop` | Stop wp-env |

## Architecture

### Directory Structure

```
aggressive-apparel/
├── assets/interactivity/        # Interactivity API JS modules (11 files)
├── build/                       # Compiled output (gitignored)
├── includes/                    # PHP classes (PSR-4 autoloaded)
│   ├── class-bootstrap.php      # Main orchestrator (singleton)
│   ├── class-service-container.php
│   ├── class-autoloader.php
│   ├── Assets/                  # Script/style loaders (3 classes)
│   ├── Blocks/                  # Block registration
│   ├── Core/                    # Theme supports, icons, updates (6 classes)
│   └── WooCommerce/             # Store features (34 classes)
├── parts/                       # Template parts (header, footer)
├── patterns/                    # Block patterns (36 patterns)
├── src/                         # Source code
│   ├── blocks/                  # Static Gutenberg blocks (4)
│   ├── blocks-interactivity/    # Interactive blocks (15)
│   ├── scripts/                 # Theme JS/TS
│   └── styles/                  # Theme CSS (Tailwind v4 + PostCSS)
├── templates/                   # FSE templates (13)
└── tests/                       # PHPUnit test suites (17 test files)
```

### PHP Architecture

The theme uses a **service container pattern** with PSR-4 autoloading under the `Aggressive_Apparel` namespace:

```
functions.php → Bootstrap (singleton)
    ├── Autoloader (PSR-4)
    ├── Service_Container
    │   ├── Core services (theme support, icons, image sizes, updates)
    │   ├── Asset services (styles, scripts)
    │   └── Block registration
    └── WooCommerce (conditional on class_exists)
        ├── Core WC support (templates, cart, product loop, color swatches)
        ├── Feature_Settings (16 toggle definitions)
        └── Enhancements coordinator → individual feature classes
```

### Custom Blocks

**Interactive Blocks** (Interactivity API):
`navigation`, `navigation-panel`, `menu-toggle`, `nav-menu`, `nav-link`, `nav-submenu`, `mega-menu-content`, `panel-header`, `panel-body`, `panel-footer`, `panel-close-button`, `parallax`, `animate-on-scroll`, `lookbook`, `ticker`

**Static Blocks:**
`aggressive-apparel-logo`, `dark-mode-toggle`, `menu-group`, `copyright`

### Icon System

31 SVG icons available via `Icons::get('name')` and `Icons::render('name')`:

**Navigation:** hamburger, close, chevron-down/up/left/right, arrow-left/right, dots, bars
**Actions:** home, search, cart, user, heart
**UI:** check, plus, minus, info, warning, error
**Social:** facebook, twitter, instagram

### Color Swatch System

A comprehensive color attribute system for WooCommerce product variations:

| Class | Purpose |
|-------|---------|
| `Color_Attribute_Manager` | WooCommerce color attribute setup |
| `Color_Data_Manager` | Color data persistence and queries |
| `Color_Block_Swatch_Manager` | Swatch rendering in product blocks |
| `Color_Admin_UI` | Admin interface for color management |
| `Color_Pattern_Admin` | Pattern image upload via media library |

Supports solid hex colors and image patterns with full keyboard navigation and screen reader support.

## Testing

106 tests across 5 suites, run inside wp-env (Docker-based WordPress):

| Suite | Files | What it covers |
|-------|-------|----------------|
| Unit | 9 | Bootstrap, assets, theme support, blocks, WC classes |
| Integration | 3 | WooCommerce integration, block rendering |
| Security | 1 | HTTP security headers |
| Accessibility | 2 | ARIA attributes, keyboard navigation |
| Performance | 2 | Load time, resource usage |

**Tools:** PHPUnit 9.6, PHPStan level 6, PHPCS (WordPress standards), ESLint, Stylelint

## CI/CD Pipeline

GitHub Actions workflow with semantic-release:

```
setup → lint (JS, PHP, PHPCS, PHPStan) → release check → build → test suites (5 parallel) → package → release
```

- **Quality checks** run on every push (lint, PHPCS, PHPStan)
- **Full pipeline** (build, test, package, release) only for `feat:`, `fix:`, `perf:` commits
- **Semantic release** auto-versions and creates GitHub releases with theme ZIP
- **Conventional Commits** enforced via commitlint + Husky pre-commit hooks

### Pre-commit Hook

Runs automatically on every commit:
```
format:fix → lint:js:fix → qa (tests + linting + PHPStan)
```

## Theme Configuration

### Constants

```php
AGGRESSIVE_APPAREL_VERSION  // Theme version
AGGRESSIVE_APPAREL_DIR      // Theme directory path
AGGRESSIVE_APPAREL_URI      // Theme directory URI
```

### Helper Functions

```php
aggressive_apparel_theme()                 // Bootstrap instance
aggressive_apparel_asset_uri($path)        // Asset URL
aggressive_apparel_asset_path($path)       // Asset file path
aggressive_apparel_is_woocommerce_active() // WooCommerce check
```

### Security Headers

Added via `Bootstrap::add_security_headers()`:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Node.js 22+ with pnpm 9+
- WooCommerce 7.0+ (recommended)
- Docker (for wp-env test environment)

## Support

- Issues: https://github.com/TheAggressive/Aggressive-Apparel/issues

## License

GNU General Public License v2 or later — http://www.gnu.org/licenses/gpl-2.0.html

Developed by [The Aggressive Network, LLC](https://aggressive-apparel.com)
