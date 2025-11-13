# Aggressive Apparel Theme

Official WooCommerce Block Theme for Aggressive Apparel

## Description

A modern, high-performance WordPress block theme (Full Site Editing) built specifically for WooCommerce stores. This theme follows WordPress and PHP best practices with a professional object-oriented architecture.

## Features

- ✅ Full Site Editing (FSE) / Block Theme
- ✅ WooCommerce Integration
- ✅ Advanced WebP Image Optimization
- ✅ Security Headers Implementation
- ✅ Object-Oriented PHP Architecture
- ✅ PSR-4 Autoloading
- ✅ Bootstrap Orchestration Pattern
- ✅ Professional Class Structure
- ✅ theme.json Configuration
- ✅ Responsive Design
- ✅ Performance Optimized
- ✅ Extensible with Hooks and Filters

## WebP Image Optimization System

This theme includes a comprehensive WebP optimization system for maximum performance:

### Automatic WebP Conversion
- **Upload-time conversion**: Images are automatically converted to WebP when uploaded
- **On-demand conversion**: Images convert to WebP when first viewed (lazy loading)
- **Browser detection**: Only serves WebP to supporting browsers
- **Fallback support**: Original images serve as fallbacks

### Coverage Areas
- **WordPress images**: All media library images
- **WooCommerce products**: Product images, galleries, and thumbnails
- **Content images**: Images within post/page content
- **Featured images**: Blog post and page featured images

### Performance Features
- **Memory-safe processing**: Prevents server overload during conversion
- **Dimension limits**: Skips extremely large images for safety
- **Caching system**: Converted images are cached for performance
- **Error handling**: Graceful degradation if conversion fails

### Configuration
The WebP system is automatically enabled and requires no configuration. Images are converted in the background without impacting page load times.

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

### WebP Security
- **Upload validation**: Only processes legitimate image uploads
- **Path sanitization**: Prevents directory traversal attacks
- **Memory limits**: Prevents resource exhaustion during conversion
- **Error handling**: Graceful failure without exposing sensitive information

## Requirements

- WordPress 6.0 or higher
- PHP 8.2 or higher
- WooCommerce 7.0 or higher (recommended)

## Theme Structure

```
aggressive-apparel/
├── includes/
│   ├── class-autoloader.php      # PSR-4 autoloader
│   ├── class-bootstrap.php       # Main initialization orchestrator
│   ├── stubs.php                 # Development stubs for IDE support
│   ├── Core/                     # Core functionality
│   │   ├── class-theme-support.php     # Theme support registration
│   │   ├── class-image-sizes.php       # Image size management & WebP
│   │   ├── class-content-width.php     # Content width setup
│   │   ├── class-webp-support.php      # WebP image serving
│   │   ├── class-webp-utils.php        # WebP utility functions
│   │   └── class-webp-on-demand.php    # On-demand WebP conversion
│   ├── Assets/                   # Asset management
│   │   ├── class-styles.php            # Frontend styles
│   │   ├── class-scripts.php           # Frontend scripts
│   │   └── class-editor-assets.php     # Editor assets
│   ├── WooCommerce/              # WooCommerce integration
│   │   ├── class-woocommerce-support.php  # WC support registration
│   │   ├── class-product-loop.php        # Product loop modifications
│   │   ├── class-cart.php               # Cart functionality
│   │   └── class-templates.php          # WC template overrides
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
- Dependency management between components

```php
// Initialize theme
Aggressive_Apparel\Bootstrap::get_instance();
```

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
Modern image size management with WebP support:
- Responsive image sizes (1200x1200 products, 1600x900 blog)
- Automatic WebP conversion on upload
- Performance-optimized dimensions
- WooCommerce product image integration

#### Content Width (`includes/Core/class-content-width.php`)
Responsive content width management:
- Dynamic content width based on layout
- Sidebar-aware width calculations
- Mobile-responsive adjustments

#### WebP Support (`includes/Core/class-webp-support.php`)
Advanced WebP image optimization:
- Browser detection for WebP support
- Automatic WebP serving for all images
- WooCommerce product image integration
- Content image replacement in posts/pages

#### WebP Utils (`includes/Core/class-webp-utils.php`)
Utility functions for WebP operations:
- Upload directory path resolution
- Browser capability detection
- WebP URL generation and validation
- Caching and performance optimizations

#### WebP On-Demand (`includes/Core/class-webp-on-demand.php`)
Background WebP conversion system:
- Lazy conversion when images are first viewed
- Memory-safe processing with limits
- Dimension restrictions for performance
- Error handling and logging

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
- Initializes core components (theme support, WebP, etc.)
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
$webp_utils = new Aggressive_Apparel\Core\WebP_Utils();
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
- Use type hinting and return types (PHP 8.2+)
- Implement proper error handling and logging
- Use defensive programming for WooCommerce functions

## Troubleshooting

### Common Issues

#### WebP Images Not Converting
- **Check GD extension**: Ensure PHP GD extension is installed and WebP support is enabled
- **Check file permissions**: Upload directory must be writable
- **Check memory limits**: Large images may need increased PHP memory limit
- **Debug mode**: Enable `WP_DEBUG` to see conversion errors in logs

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
- **Version compatibility**: Ensure PHPStan is configured for PHP 8.2+

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

