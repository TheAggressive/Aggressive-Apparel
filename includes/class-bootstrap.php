<?php
/**
 * Bootstrap Class
 *
 * Initializes all theme components
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

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
	 * @var Bootstrap
	 */
	private static $instance = null;

	/**
	 * Service container for dependency management
	 *
	 * @var Service_Container
	 */
	private $container;

	/**
	 * Get the singleton instance
	 *
	 * @return Bootstrap
	 */
	public static function get_instance() {
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
	 * Initialize security headers
	 */
	private function init_security_headers(): void {
		// Don't add security headers hook in testing environments.
		if ( defined( 'WP_TESTS_DIR' ) || defined( 'WP_PHPUNIT__TEST' ) ||
			( defined( 'WP_DEBUG' ) && WP_DEBUG && strpos( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ?? '' ) ), 'phpunit' ) !== false ) ) {
			return;
		}

		add_action( 'send_headers', array( $this, 'add_security_headers' ) );
	}

	/**
	 * Add security headers to HTTP responses
	 *
	 * @return void
	 */
	public function add_security_headers(): void {
		// Don't send headers in testing environments, CLI, or if headers already sent.
		if ( headers_sent() ||
			defined( 'WP_TESTS_DIR' ) ||
			defined( 'WP_PHPUNIT__TEST' ) ||
			( defined( 'WP_DEBUG' ) && WP_DEBUG && strpos( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ?? '' ) ), 'phpunit' ) !== false ) ||
			( php_sapi_name() === 'cli' ) ||
			! isset( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		// Prevent MIME type sniffing.
		header( 'X-Content-Type-Options: nosniff' );

		// Prevent clickjacking attacks.
		header( 'X-Frame-Options: SAMEORIGIN' );

		// Enable XSS filtering.
		header( 'X-XSS-Protection: 1; mode=block' );

		// Referrer Policy.
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );

		// Feature Policy / Permissions Policy (restrict features).
		header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
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

			// Register color attribute services.
			$this->container->register( 'color_attributes', fn() => new WooCommerce\Color_Attribute_Manager() );
			$this->container->register( 'color_block_swatch_manager', fn() => new WooCommerce\Color_Block_Swatch_Manager() );
			$this->container->register( 'color_pattern_admin', fn() => new WooCommerce\Color_Pattern_Admin() );
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
		$this->container->get( 'theme_updates' )->init(); // Initialize theme updates
		// Custom blocks.
		Blocks\Blocks::init();
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

		// Only initialize other WooCommerce services if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Initialize WooCommerce-specific services.
		if ( $this->container->has( 'wc_support' ) ) {
			$this->container->get( 'wc_support' )->init();
		}
		if ( $this->container->has( 'cart' ) ) {
			$this->container->get( 'cart' )->init();
		}
		if ( $this->container->has( 'wc_templates' ) ) {
			$this->container->get( 'wc_templates' )->init();
		}
		if ( $this->container->has( 'color_attributes' ) ) {
			$this->container->get( 'color_attributes' )->init();
		}
		if ( $this->container->has( 'color_block_swatch_manager' ) ) {
			$this->container->get( 'color_block_swatch_manager' )->init();
		}
		if ( $this->container->has( 'color_pattern_admin' ) ) {
			$this->container->get( 'color_pattern_admin' )->register_hooks();
		}
	}

	/**
	 * Initialize additional hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( 'body_class', array( $this, 'add_body_classes' ), 10, 1 );
	}

	/**
	 * Add custom body classes
	 *
	 * @param array $classes Existing body classes.
	 * @return array Modified body classes.
	 */
	public function add_body_classes( $classes ) {
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
