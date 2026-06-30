<?php
/**
 * Bootstrap Class
 *
 * Initializes all theme components
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstrap Class
 *
 * Responsible for initializing all theme components in the correct order.
 * Each component follows Single Responsibility Principle.
 *
 * @since 1.0.0
 */
class Bootstrap {

	/**
	 * The single instance of the class
	 *
	 * @var Bootstrap|null
	 */
	private static ?Bootstrap $instance = null;

	/**
	 * Service container for dependency management
	 *
	 * @var Service_Container
	 */
	private Service_Container $container;

	/**
	 * Get the singleton instance
	 *
	 * @return Bootstrap
	 */
	public static function get_instance(): Bootstrap {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Load development stubs for IDE support
	 *
	 * @return void
	 */
	private function load_development_stubs(): void {
		// Only load stubs in development environment.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( __DIR__ . '/stubs.php' ) ) {
			require_once __DIR__ . '/stubs.php';
		}
	}

	/**
	 * Detect whether the current request is running inside a test environment.
	 *
	 * Used by both init_security_headers() and add_security_headers() to avoid
	 * duplicating the condition in two places.
	 *
	 * @return bool
	 */
	private function is_testing_environment(): bool {
		return defined( 'WP_TESTS_DIR' ) ||
			defined( 'WP_PHPUNIT__TEST' ) ||
			( defined( 'WP_DEBUG' ) && WP_DEBUG &&
				strpos( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ?? '' ) ), 'phpunit' ) !== false );
	}

	/**
	 * Initialize security headers
	 */
	private function init_security_headers(): void {
		if ( $this->is_testing_environment() ) {
			return;
		}
		add_action( 'send_headers', array( $this, 'add_security_headers' ) );
	}

	/**
	 * Security headers applied to front-end HTTP responses.
	 *
	 * Exposed as a static map so the values can be asserted in tests without
	 * emitting real headers (which is not possible under PHPUnit).
	 *
	 * @return array<string, string> Map of header name => header value.
	 */
	public static function get_security_headers(): array {
		return array(
			// Prevent MIME type sniffing.
			'X-Content-Type-Options' => 'nosniff',
			// Prevent clickjacking attacks.
			'X-Frame-Options'        => 'SAMEORIGIN',
			// Enable legacy XSS filtering.
			'X-XSS-Protection'       => '1; mode=block',
			// Referrer Policy.
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			// Permissions Policy (restrict powerful features).
			'Permissions-Policy'     => 'geolocation=(), microphone=(), camera=()',
		);
	}

	/**
	 * Add security headers to HTTP responses
	 *
	 * @return void
	 */
	public function add_security_headers(): void {
		if ( headers_sent() || $this->is_testing_environment() || php_sapi_name() === 'cli' || ! isset( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		foreach ( self::get_security_headers() as $name => $value ) {
			header( $name . ': ' . $value );
		}
	}


	/**
	 * Constructor
	 */
	private function __construct() {
		$this->container = new Service_Container();
		$this->register_services();
		$this->initialize_services();
	}

	/**
	 * Register all services in the container
	 *
	 * @return void
	 */
	private function register_services(): void {
		// Register core services.
		$this->container->register( 'theme_support', fn() => new Core\Theme_Support() );
		$this->container->register( 'image_sizes', fn() => new Core\Image_Sizes() );
		$this->container->register( 'content_width', fn() => new Core\Content_Width( 1200 ) );
		$this->container->register( 'theme_updates', fn() => Core\Theme_Updates::get_instance() );
		$this->container->register( 'block_categories', fn() => new Core\Block_Categories() );
		$this->container->register( 'adaptive_colors', fn() => new Core\Adaptive_Colors() );
		$this->container->register( 'favicon', fn() => new Core\Favicon() );
		$this->container->register( 'search', fn() => new Core\Search() );
		$this->container->register( 'security_hardening', fn() => new Core\Security_Hardening() );

		// Register asset services.
		$this->container->register( 'styles', fn() => new Assets\Styles() );
		$this->container->register( 'scripts', fn() => new Assets\Scripts() );

		// Register product loop (always available for theme flexibility).
		$this->container->register( 'product_loop', fn() => new WooCommerce\Product_Loop( 3, 12 ) );

		// Register WooCommerce services (conditionally).
		if ( class_exists( 'WooCommerce' ) ) {
			$this->container->register( 'wc_support', fn() => new WooCommerce\WooCommerce_Support() );
			$this->container->register( 'cart', fn() => new WooCommerce\Cart() );
			$this->container->register( 'wc_templates', fn() => new WooCommerce\Templates() );
			$this->container->register( 'wc_block_styles', fn() => new WooCommerce\WooCommerce_Block_Styles() );
			$this->container->register( 'wc_interactivity_defaults', fn() => new WooCommerce\WooCommerce_Interactivity_Defaults() );
			$this->container->register( 'wc_block_asset_bailout', fn() => new WooCommerce\WooCommerce_Block_Asset_Bailout() );
			$this->container->register( 'product_gallery_nav', fn() => new WooCommerce\Product_Gallery_Nav() );

			// Register color attribute services.
			$this->container->register( 'color_attributes', fn( Service_Container $container ) => new WooCommerce\Color_Attribute_Manager( $container->get( 'color_pattern_admin' ) ) );
			$this->container->register( 'color_block_swatch_manager', fn() => new WooCommerce\Color_Block_Swatch_Manager() );
			$this->container->register( 'color_pattern_admin', fn() => new WooCommerce\Color_Pattern_Admin() );
			$this->container->register( 'size_option_sorter', fn() => new WooCommerce\Size_Option_Sorter() );

			// Register enhancement services.
			$this->container->register( 'wc_feature_settings', fn() => new WooCommerce\Feature_Settings() );
			$this->container->register( 'wc_enhancements', fn( Service_Container $container ) => new WooCommerce\Enhancements( $container ) );
			$this->container->register( 'wcpay_appearance', fn() => new WooCommerce\Wcpay_Appearance() );
			$this->container->register( 'mini_cart_a11y', fn() => new WooCommerce\Mini_Cart_A11y() );
		}
	}

	/**
	 * Initialize all registered services
	 *
	 * @return void
	 */
	private function initialize_services(): void {
		$this->load_development_stubs();
		$this->init_security_headers();

		// Initialize core components.
		$this->init_core_components();

		// Initialize assets.
		$this->init_asset_components();

		// Initialize WooCommerce if available.
		$this->init_woocommerce_components();

		// Initialize hooks.
		$this->init_hooks();
	}

	/**
	 * Initialize core components
	 *
	 * @return void
	 */
	private function init_core_components(): void {
		// Initialize core services using the container.
		$this->container->get( 'theme_support' )->init();
		$this->container->get( 'image_sizes' )->init();
		$this->container->get( 'content_width' )->init();
		$this->container->get( 'theme_updates' )->init(); // Initialize theme updates.
		$this->container->get( 'block_categories' ); // Register block and pattern categories.
		$this->container->get( 'adaptive_colors' )->init();
		$this->container->get( 'favicon' )->init();
		$this->container->get( 'search' )->init();
		$this->container->get( 'security_hardening' )->init();
		Core\Brand_Icons::init();
		// Custom blocks.
		Blocks\Blocks::init();
		Blocks\Icon_Block::init();
	}

	/**
	 * Initialize asset components
	 *
	 * @return void
	 */
	private function init_asset_components(): void {
		// Initialize asset services using the container.
		$this->container->get( 'styles' )->init();
		$this->container->get( 'scripts' )->init();
	}

	/**
	 * Initialize WooCommerce components
	 *
	 * @return void
	 */
	private function init_woocommerce_components(): void {
		// Always initialize product loop for theme flexibility.
		$this->container->get( 'product_loop' )->init();

		// WooCommerce services are only registered when WooCommerce is active
		// (see register_services()), so no has() checks are needed here.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->container->get( 'wc_support' )->init();
		WooCommerce\Rating::init();
		$this->container->get( 'cart' )->init();
		$this->container->get( 'wc_templates' )->init();
		$this->container->get( 'wc_block_styles' )->init();
		$this->container->get( 'wc_interactivity_defaults' )->init();
		$this->container->get( 'wc_block_asset_bailout' )->init();
		$this->container->get( 'product_gallery_nav' )->init();
		$this->container->get( 'color_attributes' )->init();
		$this->container->get( 'color_block_swatch_manager' )->init();
		$this->container->get( 'size_option_sorter' )->init();
		$this->container->get( 'wc_feature_settings' )->init();
		$this->container->get( 'wc_enhancements' )->init();
		$this->container->get( 'wcpay_appearance' )->init();
		$this->container->get( 'mini_cart_a11y' )->init();
	}

	/**
	 * Initialize additional hooks
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_filter( 'body_class', array( $this, 'add_body_classes' ), 10, 1 );
	}

	/**
	 * Add custom body classes
	 *
	 * @param array<int, string> $classes Existing body classes.
	 * @return array<int, string> Modified body classes.
	 */
	public function add_body_classes( array $classes ): array {
		// Add has-sidebar class if applicable.
		if ( \is_active_sidebar( 'sidebar-1' ) ) {
			$classes[] = 'has-sidebar';
		}

		// Add WooCommerce specific classes.
		if ( class_exists( 'WooCommerce' ) ) {
			$classes[] = 'woocommerce-active';

			if ( \function_exists( 'is_shop' ) && \function_exists( 'is_product_category' ) && \function_exists( 'is_product_tag' ) &&
				( \is_shop() || \is_product_category() || \is_product_tag() ) ) {
				$classes[] = 'woocommerce-shop-page';
			}

			if ( \function_exists( 'is_product' ) && \is_product() ) {
				$classes[] = 'woocommerce-product-page';
			}
		}

		return $classes;
	}
}
