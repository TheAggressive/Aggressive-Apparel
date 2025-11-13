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
	 * Theme version
	 *
	 * @var string
	 */
	private $version;

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
		add_action( 'send_headers', array( $this, 'add_security_headers' ) );
	}

	/**
	 * Add security headers to HTTP responses
	 *
	 * @return void
	 */
	public function add_security_headers(): void {
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
		$this->load_development_stubs();
		$this->init_security_headers();
		$this->version = wp_get_theme()->get( 'Version' );
		$this->init_core();
		$this->init_assets();
		$this->init_woocommerce();
		$this->init_hooks();
	}

	/**
	 * Initialize core components
	 *
	 * @return void
	 */
	private function init_core() {
		// Theme support.
		$theme_support = new Core\Theme_Support();
		$theme_support->init();

		// Image sizes.
		$image_sizes = new Core\Image_Sizes();
		$image_sizes->init();

		// Content width.
		$content_width = new Core\Content_Width( 1200 );
		$content_width->init();

		// WebP support (serves WebP to browsers).
		$webp_support = new Core\WebP_Support();
		$webp_support->init();

		// WebP on-demand conversion (converts when images are viewed).
		$webp_on_demand = new Core\WebP_On_Demand();
		$webp_on_demand->init();
	}

	/**
	 * Initialize asset components
	 *
	 * @return void
	 */
	private function init_assets() {
		// Styles.
		$styles = new Assets\Styles( $this->version );
		$styles->init();

		// Scripts.
		$scripts = new Assets\Scripts( $this->version );
		$scripts->init();

		// Editor assets.
		$editor_assets = new Assets\Editor_Assets( $this->version );
		$editor_assets->init();
	}

	/**
	 * Initialize WooCommerce components
	 *
	 * @return void
	 */
	private function init_woocommerce() {
		// Only initialize if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// WooCommerce support.
		$wc_support = new WooCommerce\WooCommerce_Support();
		$wc_support->init();

		// Product loop.
		$product_loop = new WooCommerce\Product_Loop( 3, 12 );
		$product_loop->init();

		// Cart.
		$cart = new WooCommerce\Cart();
		$cart->init();

		// Templates.
		$templates = new WooCommerce\Templates();
		$templates->init();
	}

	/**
	 * Initialize additional hooks
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_filter( 'body_class', array( $this, 'add_body_classes' ), 10, 1 );

		// Initialize admin components.
		if ( is_admin() ) {
			$this->init_admin();
		}
	}

	/**
	 * Initialize admin components
	 *
	 * @return void
	 */
	private function init_admin() {
		// Admin initialization complete.
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
