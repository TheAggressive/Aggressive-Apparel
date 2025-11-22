# Aggressive Apparel Theme

Official WooCommerce Block Theme for Aggressive Apparel

## Description

A modern, high-performance WordPress block theme (Full Site Editing) built specifically for WooCommerce stores. This theme follows WordPress and PHP best practices with a professional object-oriented architecture.

## Features

- ✅ Full Site Editing (FSE) / Block Theme
- ✅ WooCommerce Integration with Advanced Color Swatches
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
- ✅ WCAG 2.1 AA Accessibility Compliant
- ✅ Translation Ready
- ✅ Extensible with Hooks and Filters

## Advanced Color Swatch System

This theme includes a comprehensive color swatch system for WooCommerce products, enabling visual color and pattern selection:

### Color Management Features
- **Visual Color Selection**: Interactive color swatches in product listings and detail pages
- **Pattern Support**: Both solid colors and image patterns for fabric/texture representation
- **Admin Interface**: Easy-to-use color and pattern management in WordPress admin
- **WooCommerce Integration**: Seamless integration with WooCommerce product attributes
- **Accessibility**: WCAG 2.1 AA compliant with keyboard navigation and screen reader support

### Technical Implementation
- **PHP Classes**: Object-oriented color management with secure input validation
- **TypeScript**: Modern JavaScript with secure DOM manipulation (no innerHTML)
- **WordPress APIs**: Integration with media library and term management
- **Security**: Comprehensive input sanitization, capability checks, and XSS prevention
- **Performance**: Optimized database queries and efficient asset loading

### Color Types Supported
- **Solid Colors**: Hex color codes with visual swatch display
- **Image Patterns**: Upload patterns from WordPress media library
- **Admin Management**: Toggle between color types with validation
- **Responsive Design**: Mobile-friendly swatch layouts

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

This theme implements enterprise-level security measures to protect your WooCommerce store:

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

### Advanced Security Implementation

#### Input Validation & Sanitization
- **Color values**: Hex color validation with `sanitize_hex_color()`
- **Text inputs**: `sanitize_text_field()` and `wp_unslash()` for all inputs
- **File uploads**: Media library integration (no direct file handling)
- **Array validation**: Strict type checking for color types and attributes

#### XSS Prevention
- **Output escaping**: All dynamic HTML uses `esc_attr()`, `esc_url()`, `esc_html()`
- **DOM manipulation**: Secure JavaScript with `document.createElement()` instead of `innerHTML`
- **Template literals**: Safe string handling without concatenation vulnerabilities

#### SQL Injection Prevention
- **WordPress APIs only**: No direct SQL queries or concatenation
- **Prepared statements**: All database operations use WordPress functions
- **Meta operations**: `get_term_meta()`, `update_term_meta()`, `delete_term_meta()`

#### Access Control
- **Capability checks**: `current_user_can('manage_categories')` for all admin operations
- **Nonce verification**: All AJAX requests and form submissions validated
- **User validation**: Proper user permission checking before sensitive operations

#### File Security
- **Media library only**: Pattern images stored in WordPress media library
- **Attachment validation**: `wp_attachment_is_image()` checks for valid images
- **No file system access**: All file operations through WordPress APIs

#### JavaScript Security
- **No innerHTML**: Replaced with secure DOM creation methods
- **Server trust**: AJAX responses from trusted WordPress endpoints
- **Input validation**: Client-side validation with server-side verification

## Requirements

- WordPress 6.0 or higher
- PHP 8.0 or higher
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
│   │   ├── class-templates.php          # WC template overrides
│   │   ├── class-color-block-swatch-manager.php  # Color swatch rendering
│   │   ├── class-color-admin-ui.php     # Color admin interface
│   │   ├── class-color-pattern-admin.php # Pattern upload management
│   │   ├── class-color-attribute-manager.php     # Color attribute setup
│   │   └── class-color-data-manager.php # Color data operations
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

#### Color Swatch System
Advanced visual color selection for WooCommerce products:

**Color Block Swatch Manager** (`includes/WooCommerce/class-color-block-swatch-manager.php`)
- WooCommerce block integration for color swatches
- Dynamic HTML injection into product blocks
- Accessibility-compliant swatch rendering
- Pattern and solid color support

**Color Admin UI** (`includes/WooCommerce/class-color-admin-ui.php`)
- WordPress admin interface for color management
- Color type selection (solid/pattern)
- Secure form handling with nonces
- Media library integration for patterns

**Color Pattern Admin** (`includes/WooCommerce/class-color-pattern-admin.php`)
- Pattern image upload and management
- AJAX-powered admin interface
- WordPress media library integration
- Secure file handling and validation

**Color Attribute Manager** (`includes/WooCommerce/class-color-attribute-manager.php`)
- WooCommerce product attribute setup
- Automatic color taxonomy creation
- Attribute registration and management

**Color Data Manager** (`includes/WooCommerce/class-color-data-manager.php`)
- Color data operations and queries
- Term meta management for colors
- Optimized database interactions

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

The theme uses modern development tools:
- **@wordpress/scripts** - WordPress build tooling with webpack
- **TypeScript** - Type-safe JavaScript for admin interfaces
- **Tailwind CSS v4.1** - CSS-first configuration with native nesting
- **PostCSS** - CSS processing and optimization
- **Modern CSS** - Native nesting, custom properties, @theme
- **DOM Security** - XSS-safe JavaScript without innerHTML

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
  - `/src/scripts/` - JavaScript files (ES6+ modules)
  - `/src/scripts/admin/` - Admin interface TypeScript files
  - `/src/styles/` - CSS files (with Tailwind + PostCSS)
  - `/src/styles/admin/` - Admin-specific styles
  - `/src/styles/woocommerce/` - WooCommerce-specific styles
  - `/src/blocks/` - Custom blocks (future)
- `/build/` - Compiled assets (auto-generated)
  - `/build/scripts/` - Compiled JavaScript and TypeScript
  - `/build/styles/` - Compiled CSS with optimizations

### Styling Approach

This theme combines modern CSS methodologies:
1. **Tailwind utilities** - For rapid prototyping and common patterns
2. **BEM methodology** - For complex components (color swatches, admin interfaces)
3. **CSS nesting** - Modern, clean syntax with native support
4. **@apply directive** - Extract utilities into reusable components
5. **CSS custom properties** - From theme.json for consistent theming
6. **Security-first CSS** - XSS-safe class naming and structure

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

The theme includes a comprehensive testing suite with 59 tests covering core functionality:

#### Test Categories
- **Unit Tests**: Individual class and function testing (Color swatch system, admin UI, data management)
- **Integration Tests**: Component interaction testing, including WooCommerce integration
- **Security Tests**: Input validation, XSS prevention, and sanitization testing
- **Accessibility Tests**: WCAG 2.1 AA compliance validation (ARIA labels, keyboard navigation)
- **Performance Tests**: Load time and resource usage benchmarking

#### Color System Test Coverage
- **Swatch Rendering**: Solid colors and pattern display validation
- **Admin Interface**: Form validation, AJAX operations, and UI interactions
- **Security**: Input sanitization, capability checks, and XSS prevention
- **Accessibility**: ARIA attributes, keyboard navigation, and screen reader support
- **Data Management**: Term meta operations and color type validation

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
- **Security-first development**: Input validation, XSS prevention, secure DOM manipulation
- **Modern JavaScript**: TypeScript for type safety, ES6+ modules, no innerHTML
- **Accessibility compliance**: WCAG 2.1 AA standards with ARIA support

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

