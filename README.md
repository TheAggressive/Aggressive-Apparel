# Aggressive Apparel Theme

Official WooCommerce Block Theme for Aggressive Apparel

## Description

A modern, high-performance WordPress block theme (Full Site Editing) built specifically for WooCommerce stores. This theme follows WordPress and PHP best practices with a professional object-oriented architecture.

## Features

- ✅ Full Site Editing (FSE) / Block Theme
- ✅ WooCommerce Integration
- ✅ GitHub-Based Theme Updates
- ✅ Security Headers Implementation
- ✅ Object-Oriented PHP Architecture
- ✅ PSR-4 Autoloading
- ✅ Bootstrap Orchestration Pattern
- ✅ Service Container Pattern
- ✅ Professional Class Structure
- ✅ theme.json Configuration
- ✅ Responsive Design
- ✅ Performance Optimized
- ✅ Extensible with Hooks and Filters

## Theme Update System

This theme includes a robust update system that automatically checks for and installs updates directly from GitHub releases:

### GitHub Integration
- **Automatic updates**: WordPress automatically detects new theme versions
- **Release assets**: Downloads official theme ZIP files from GitHub releases
- **Version comparison**: Compares current theme version with latest GitHub release
- **Secure downloads**: Uses GitHub's official release asset URLs

### Update Process
- **Background checking**: Automatically checks for updates in WordPress admin
- **One-click updates**: Standard WordPress update process
- **Package validation**: Ensures downloaded packages are valid theme ZIPs
- **Rollback safety**: WordPress maintains backup during updates

### Configuration
The update system is automatically configured and requires no setup:
- **Repository**: `TheAggressive/Aggressive-Apparel`
- **Update frequency**: Follows WordPress update schedule
- **ETag caching**: Reduces API calls for better performance
- **Error handling**: Graceful failure with clear error messages

## Security Features

This theme implements comprehensive security measures to protect your WooCommerce store:

### Security Headers
- **X-Content-Type-Options**: Prevents MIME type sniffing attacks
- **X-Frame-Options**: Protects against clickjacking attacks
- **X-XSS-Protection**: Enables browser XSS filtering
- **Referrer-Policy**: Controls referrer information sharing
- **Permissions-Policy**: Restricts access to sensitive browser features

### WordPress Security Best Practices
- **Nonces verification**: All forms include proper nonce validation
- **Input sanitization**: All user inputs are sanitized and validated
- **Secure coding**: Follows WordPress security coding standards
- **Dependency checks**: Safe function existence checks for WooCommerce

## Requirements

- WordPress 6.0 or higher
- PHP 8.1 or higher
- WooCommerce 7.0 or higher (recommended)

## Theme Structure

```
aggressive-apparel/
├── includes/
│   ├── class-autoloader.php      # PSR-4 autoloader
│   ├── class-bootstrap.php       # Main initialization orchestrator
│   ├── class-service-container.php # Service container for dependency injection
│   ├── stubs.php                 # Development stubs for IDE support
│   ├── Core/                     # Core functionality
│   │   ├── class-theme-support.php     # Theme support registration
│   │   ├── class-image-sizes.php       # Image size management
│   │   ├── class-content-width.php     # Content width setup
│   │   ├── class-theme-updates.php     # GitHub-based theme updates
│   │   └── class-block-categories.php  # Block category registration
│   ├── Assets/                   # Asset management
│   │   ├── class-styles.php            # Frontend styles
│   │   ├── class-scripts.php           # Frontend scripts
│   │   └── class-editor-assets.php     # Editor assets
│   ├── Blocks/                   # Custom block registration
│   │   └── class-blocks.php            # Block initialization
│   ├── WooCommerce/              # WooCommerce integration
│   │   ├── class-woocommerce-support.php  # WC support registration
│   │   ├── class-product-loop.php        # Product loop modifications
│   │   ├── class-cart.php               # Cart functionality
│   │   └── class-templates.php          # WC template overrides
│   ├── Exceptions/               # Custom exception classes
│   └── helpers.php               # Helper functions
├── parts/                        # Template parts
├── patterns/                     # Block patterns
├── templates/                    # Block templates
├── src/                          # Source files (build process)
├── build/                        # Compiled assets
├── tests/                        # Test suite
├── functions.php                 # Theme bootstrap
├── theme.json                    # Theme configuration
├── style.css                     # Theme header
└── README.md
```

## Class Architecture

### Bootstrap (Main Orchestrator)
**File:** `includes/class-bootstrap.php`

The central initialization class that orchestrates all theme components:
- Singleton pattern implementation
- Component initialization in correct order
- Development stubs loading (WP_DEBUG mode)
- Service container integration

```php
// Initialize theme
Aggressive_Apparel\Bootstrap::get_instance();
```

### Service Container
**File:** `includes/class-service-container.php`

Dependency injection container for managing theme services:
- Service registration and resolution
- Singleton and shared service support
- Lazy loading of components
- Clean dependency management

### Autoloader
**File:** `includes/class-autoloader.php`

PSR-4 compliant autoloader supporting:
- Flat structure: `includes/class-{name}.php`
- Namespaced structure: `includes/{Namespace}/class-{name}.php`
- Automatic class loading with fallback

### Core Components

#### Theme Support (`includes/Core/class-theme-support.php`)
Handles WordPress theme support registration:
- Post thumbnails, title tag, HTML5 markup
- Custom logo and header support
- Editor color palette and font sizes
- Responsive embeds and custom background

#### Image Sizes (`includes/Core/class-image-sizes.php`)
Modern image size management:
- Responsive image sizes (1200x1200 products, 1600x900 blog)
- Performance-optimized dimensions
- WooCommerce product image integration

#### Content Width (`includes/Core/class-content-width.php`)
Responsive content width management:
- Dynamic content width based on layout
- Sidebar-aware width calculations
- Mobile-responsive adjustments

#### Theme Updates (`includes/Core/class-theme-updates.php`)
GitHub-based automatic theme update system:
- Automatic update checking from GitHub releases
- Release asset downloading and installation
- ETag caching for API efficiency
- WordPress update integration

#### Block Categories (`includes/Core/class-block-categories.php`)
Custom block category registration:
- Organized block categorization
- Theme-specific block grouping
- Block editor organization

### Asset Management

#### Styles (`includes/Assets/class-styles.php`)
Frontend stylesheet management:
- Theme stylesheet enqueuing
- WooCommerce style integration
- Conditional loading based on page type
- Performance optimizations

#### Scripts (`includes/Assets/class-scripts.php`)
JavaScript asset management:
- Theme script enqueuing
- Product page specific scripts
- WooCommerce integration scripts
- Dependency management

#### Editor Assets (`includes/Assets/class-editor-assets.php`)
Block editor asset management:
- Editor stylesheet enqueuing
- Block editor JavaScript
- Custom block registration support

### WooCommerce Integration

#### WooCommerce Support (`includes/WooCommerce/class-woocommerce-support.php`)
Core WooCommerce theme support:
- WooCommerce theme support declaration
- Product gallery and zoom features
- Cart and checkout optimizations

#### Product Loop (`includes/WooCommerce/class-product-loop.php`)
Product archive customizations:
- Product grid layouts
- Loop hooks and filters
- Performance optimizations

#### Cart (`includes/WooCommerce/class-cart.php`)
Shopping cart enhancements:
- AJAX cart updates
- Cart fragment optimization
- Mini cart functionality

#### Templates (`includes/WooCommerce/class-templates.php`)
WooCommerce template overrides:
- Custom template locations
- Template hook modifications
- Layout customizations

### Block System

#### Blocks (`includes/Blocks/class-blocks.php`)
Custom block registration and management:
- Block initialization and setup
- Block category integration
- Block editor support

### Exception Handling

#### Exceptions (`includes/Exceptions/`)
Custom exception classes for error handling:
- API-related exceptions
- Cache operation exceptions
- Database operation exceptions
- Validation and input exceptions

### Development Tools

#### Stubs (`includes/stubs.php`)
IDE support for conditional functions:
- WooCommerce function definitions for development
- WordPress core function stubs
- Loaded only in WP_DEBUG mode
- Prevents undefined function warnings

## Usage

### Initialization

The theme uses a centralized Bootstrap orchestrator for initialization:

```php
// Single initialization point - handles all components
Aggressive_Apparel\Bootstrap::get_instance();
```

The Bootstrap class automatically:
- Loads development stubs (in WP_DEBUG mode)
- Initializes core components (theme support, theme updates, etc.)
- Sets up asset management (styles, scripts, editor assets)
- Configures WooCommerce integration (if active)
- Registers all hooks and filters in correct order

### Manual Component Access

If you need direct access to specific components:

```php
// Get Bootstrap instance
$bootstrap = Aggressive_Apparel\Bootstrap::get_instance();

// Access specific components (available after initialization)
$theme_support = new Aggressive_Apparel\Core\Theme_Support();
$theme_updates = new Aggressive_Apparel\Core\Theme_Updates();
```

### Helper Functions

```php
// Get theme instance
$theme = aggressive_apparel_theme();

// Get asset URI
$asset_url = aggressive_apparel_asset_uri('build/scripts/main.js');

// Check WooCommerce
if (aggressive_apparel_is_woocommerce_active()) {
    // WooCommerce specific code
}

// Display cart count
aggressive_apparel_cart_count();
```

### Hooks and Filters

#### Theme Hooks

```php
// After theme support setup
do_action('aggressive_apparel_after_theme_support');

// After WooCommerce support
do_action('aggressive_apparel_after_woocommerce_support');

// After asset enqueuing
do_action('aggressive_apparel_after_enqueue_assets');
```

#### Theme Filters

```php
// Modify body classes
apply_filters('aggressive_apparel_body_classes', $classes);

// Modify loop columns
apply_filters('aggressive_apparel_loop_columns', 3);

// Modify products per page
apply_filters('aggressive_apparel_products_per_page', 12);
```

## Customization

### Using theme.json

All theme settings are configured in `theme.json`:

- Colors
- Typography
- Spacing
- Layout
- Block settings
- Custom settings

### Creating Custom Classes

Add new classes to the `/includes` directory following the naming convention:

```php
class-{class-name}.php
```

The autoloader will automatically load them.

### Example Custom Class

```php
<?php
namespace Aggressive_Apparel;

class Custom_Feature {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('init', array($this, 'register_feature'));
    }

    public function register_feature() {
        // Your feature code
    }
}
```

Then initialize in `functions.php`:

```php
Aggressive_Apparel\Custom_Feature::get_instance();
```

## Development

### Build Process

The theme uses:
- **@wordpress/scripts** - Build tooling
- **Tailwind CSS v4.1** - CSS-first configuration
- **PostCSS** - CSS processing with native nesting
- **Modern CSS** - Native nesting, custom properties, @theme

See `TAILWIND_V4_GUIDE.md` for complete documentation.

#### Quick Start

```bash
# Install dependencies
npm install

# Start development (watch mode)
npm run watch

# Build for production
npm run build

# Start wp-env AND watch (recommended)
npm run dev
```

#### File Structure

- `/src/` - Source files (you edit these)
  - `/src/scripts/` - JavaScript files
  - `/src/styles/` - CSS files (with Tailwind + PostCSS)
  - `/src/blocks/` - Custom blocks (future)
- `/build/` - Compiled assets (auto-generated)
  - `/build/scripts/` - Compiled JavaScript
  - `/build/styles/` - Compiled CSS

### Styling Approach

This theme combines:
1. **Tailwind utilities** - For rapid prototyping and common patterns
2. **BEM methodology** - For complex components
3. **CSS nesting** - Modern, clean syntax
4. **@apply directive** - Extract utilities into components
5. **CSS custom properties** - From theme.json

```css
/* Example: BEM + Tailwind + Nesting */
.product-card {
  @apply bg-base border border-border p-6 rounded-lg;
  @apply transition-all duration-300;

  &:hover {
    @apply -translate-y-1 shadow-xl;
  }

  &__title {
    @apply text-xl font-bold text-primary mb-2;
  }

  &__price {
    @apply text-2xl font-bold text-accent;
  }
}
```

### Testing Suite

The theme includes a comprehensive testing suite:

#### Test Categories
- **Unit Tests**: Individual class and function testing
- **Integration Tests**: Component interaction testing, including WooCommerce integration
- **Performance Tests**: Load time and resource usage benchmarking
- **Security Tests**: Vulnerability and sanitization testing
- **Accessibility Tests**: WCAG compliance validation

#### Running Tests
```bash
# Run all tests
composer test

# Run specific test suites
composer test -- --testsuite=unit
composer test -- --testsuite=integration
composer test -- --testsuite=security
composer test -- --testsuite=accessibility
composer test -- --testsuite=performance

# Run with code coverage reporting
composer test:coverage
# View coverage report in: tests/coverage/index.html
```

#### Code Quality Tools
- **PHPStan**: Static analysis with level 8 configuration
- **PHPCS**: WordPress coding standards enforcement
- **PHPUnit**: Unit and integration testing framework

### Coding Standards

- Follow WordPress Coding Standards (WPCS)
- PSR-4 autoloading for class organization
- Use meaningful variable and function names
- Document all functions with PHPDoc
- Use type hinting and return types (PHP 8.1+)
- Implement proper error handling and logging
- Use defensive programming for WooCommerce functions

## Troubleshooting

### Common Issues

#### Theme Updates Not Working
- **GitHub access**: Ensure server can access `api.github.com`
- **Rate limits**: GitHub API has rate limits (60 requests/hour for unauthenticated)
- **Version format**: Ensure theme version in `style.css` matches GitHub release tag format
- **Debug logs**: Check WordPress debug.log for update-related errors

#### WooCommerce Functions Not Found
- **Plugin active**: Ensure WooCommerce plugin is installed and activated
- **Function checks**: The theme safely checks for WooCommerce functions before use
- **Version compatibility**: Ensure WooCommerce 7.0+ is installed

#### Theme Not Loading Styles/Scripts
- **Build process**: Run `npm run build` to compile assets
- **File permissions**: Ensure `build/` directory is writable
- **Cache issues**: Clear browser cache and WordPress object cache

#### PHPStan Errors in IDE
- **Stubs loading**: Development stubs are automatically loaded in `WP_DEBUG` mode
- **PHPStan config**: The theme includes comprehensive PHPStan configuration
- **Version compatibility**: Ensure PHPStan is configured for PHP 8.1+

### Development Tips

- **Use `WP_DEBUG`**: Enable for development to load function stubs
- **Check logs**: Debug.log contains detailed error information
- **Test builds**: Always test after modifying source files
- **Version control**: Commit frequently with descriptive messages

## Support

For issues and questions:
- GitHub: https://github.com/TheAggressive/Aggressive-Apparel/issues

## License

GNU General Public License v2 or later
http://www.gnu.org/licenses/gpl-2.0.html

## Credits

Developed by The Aggressive Network, LLC

