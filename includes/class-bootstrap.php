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
	 * Detect whether the current request is running inside a test environment.
	 *
	 * Guards the emitter in add_security_headers() so no real headers are sent
	 * under PHPUnit.
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
	 *
	 * The hook is registered unconditionally so the wiring is observable in
	 * tests; add_security_headers() is what decides whether to emit. Skipping
	 * registration under PHPUnit made the theme's own header wiring untestable
	 * while changing nothing about production behaviour.
	 */
	private function init_security_headers(): void {
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
			// Disable deprecated browser XSS auditors that can create client-side vulnerabilities.
			'X-XSS-Protection'       => '0',
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
		$this->container->register( 'theme_features', fn() => new Core\Theme_Features() );
		$this->container->register( 'adaptive_colors', fn() => new Core\Adaptive_Colors() );
		$this->container->register( 'favicon', fn() => new Core\Favicon() );
		$this->container->register( 'legal_identity', fn() => new Core\Legal_Identity() );
		$this->container->register( 'color_scheme_bootstrap', fn() => new Core\Color_Scheme_Bootstrap() );
		$this->container->register( 'search', fn() => new Core\Search() );
		$this->container->register( 'security_hardening', fn() => new Core\Security_Hardening() );
		$this->container->register( 'cache_health', fn() => new Core\Cache_Health() );
		$this->container->register( 'page_cache_purge', fn() => new Core\Page_Cache_Purge() );
		$this->container->register( 'image_loading', fn() => new Core\Image_Loading() );
		$this->container->register( 'image_output_format', fn() => new Core\Image_Output_Format() );
		$this->container->register( 'font_preload', fn() => new Core\Font_Preload() );

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
			$this->container->register( 'wc_legacy_asset_trim', fn() => new WooCommerce\Legacy_Asset_Trim() );
			$this->container->register( 'wc_mini_cart_style_defer', fn() => new WooCommerce\Mini_Cart_Style_Defer() );
			$this->container->register( 'jquery_optimizer', fn() => new WooCommerce\Jquery_Optimizer() );
			$this->container->register( 'product_gallery_nav', fn() => new WooCommerce\Product_Gallery_Nav() );

			// Register color attribute services.
			$this->container->register( 'color_attributes', fn() => new WooCommerce\Color_Attribute_Manager() );
			$this->container->register( 'color_swatch_migration', fn() => new WooCommerce\Color_Swatch_Migration() );
			$this->container->register( 'color_block_swatch_manager', fn() => new WooCommerce\Color_Block_Swatch_Manager() );
			$this->container->register( 'size_option_sorter', fn() => new WooCommerce\Size_Option_Sorter() );
			$this->container->register( 'product_card_contract', fn() => new WooCommerce\Product_Card_Contract() );
			$this->container->register( 'catalog_image_loading', fn() => new WooCommerce\Catalog_Image_Loading() );

			// Register enhancement services.
			$this->container->register( 'wc_feature_settings', fn() => new WooCommerce\Feature_Settings() );
			$this->container->register( 'wc_enhancements', fn( Service_Container $container ) => new WooCommerce\Enhancements( $container ) );
			$this->container->register( 'storefront_rest_requests', fn() => new WooCommerce\Storefront_Rest_Requests() );
			$this->container->register( 'catalog_archive_filters', fn() => new WooCommerce\Catalog_Archive_Filters() );
			$this->container->register( 'wcpay_appearance', fn() => new WooCommerce\Wcpay_Appearance() );
			$this->container->register( 'mini_cart_a11y', fn() => new WooCommerce\Mini_Cart_A11y() );
			$this->container->register( 'cart_order_meta', fn() => new WooCommerce\Cart_Order_Meta() );
			$this->container->register( 'saved_for_later', fn() => new WooCommerce\Saved_For_Later() );
			$this->container->register( 'checkout_payment_note', fn() => new WooCommerce\Checkout_Payment_Note() );
			$this->container->register( 'variation_selector_a11y', fn() => new WooCommerce\Variation_Selector_A11y() );
			$this->container->register( 'add_to_cart_button_a11y', fn() => new WooCommerce\Add_To_Cart_Button_A11y() );
		}
	}

	/**
	 * Initialize all registered services
	 *
	 * @return void
	 */
	private function initialize_services(): void {
		$this->init_security_headers();
		$this->init_core_components();
		$this->init_asset_components();
		$this->init_woocommerce_components();
		$this->init_hooks();
	}

	/**
	 * Initialize core components
	 *
	 * @return void
	 */
	private function init_core_components(): void {
		$this->container->get( 'theme_support' )->init();
		$this->container->get( 'image_sizes' )->init();
		$this->container->get( 'content_width' )->init();
		$this->container->get( 'theme_updates' )->init();
		$this->container->get( 'block_categories' ); // Register block and pattern categories.
		$this->container->get( 'theme_features' )->init();
		$this->container->get( 'adaptive_colors' )->init();
		$this->container->get( 'favicon' )->init();
		$this->container->get( 'legal_identity' )->init();
		$this->container->get( 'color_scheme_bootstrap' )->init();
		$this->container->get( 'search' )->init();
		$this->container->get( 'security_hardening' )->init();
		$this->container->get( 'cache_health' )->init();
		$this->container->get( 'page_cache_purge' )->init();
		$this->container->get( 'image_loading' )->init();
		$this->container->get( 'image_output_format' )->init();
		$this->container->get( 'font_preload' )->init();
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
		$this->container->get( 'wc_legacy_asset_trim' )->init();
		$this->container->get( 'wc_mini_cart_style_defer' )->init();
		$this->container->get( 'jquery_optimizer' )->init();
		$this->container->get( 'product_gallery_nav' )->init();
		$this->container->get( 'color_attributes' )->init();
		$this->container->get( 'color_swatch_migration' )->init();
		$this->container->get( 'color_block_swatch_manager' )->init();
		$this->container->get( 'size_option_sorter' )->init();
		$this->container->get( 'product_card_contract' )->init();
		$this->container->get( 'catalog_image_loading' )->init();
		$this->container->get( 'wc_feature_settings' )->init();
		$this->container->get( 'wc_enhancements' )->init();
		$this->container->get( 'storefront_rest_requests' )->init();
		$this->container->get( 'catalog_archive_filters' )->init();
		$this->container->get( 'wcpay_appearance' )->init();
		$this->container->get( 'mini_cart_a11y' )->init();
		$this->container->get( 'cart_order_meta' )->init();
		$this->container->get( 'saved_for_later' )->init();
		$this->container->get( 'checkout_payment_note' )->init();
		$this->container->get( 'variation_selector_a11y' )->init();
		$this->container->get( 'add_to_cart_button_a11y' )->init();
		WooCommerce\Free_Shipping::init();
		WooCommerce\Free_Shipping_Rate_Hiding::init();
		WooCommerce\Free_Shipping_Delivery_Estimate::init();
		WooCommerce\Store_Notices::init();
		WooCommerce\Attribute_Breadcrumb::init();
		WooCommerce\Account_Page::init();
		WooCommerce\Select2_A11y::init();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'aggressive-apparel specs-migrate', WooCommerce\Product_Specs_Migration_Command::class );
		}
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

			if ( WooCommerce\Product_Context::is_product_archive() ) {
				$classes[] = 'woocommerce-shop-page';
			}

			if ( \function_exists( 'is_product' ) && \is_product() ) {
				$classes[] = 'woocommerce-product-page';
			}
		}

		return $classes;
	}
}
