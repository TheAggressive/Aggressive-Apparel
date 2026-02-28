<?php
/**
 * Enhancements Coordinator Class
 *
 * Registers and initializes WooCommerce enhancement services based on
 * the feature flags managed by Feature_Settings.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enhancements Coordinator
 *
 * Acts as a single entry point that checks feature flags and only
 * instantiates / initializes services the admin has enabled.
 *
 * @since 1.17.0
 */
class Enhancements {

	/**
	 * Initialize all enabled enhancements.
	 *
	 * Called from Bootstrap::init_woocommerce_components().
	 *
	 * @return void
	 */
	public function init(): void {
		// Register shared utility modules that multiple features depend on.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_shared_modules' ) );

		// Provide defensive defaults for WooCommerce shared interactivity
		// state. The product-button block's quantity getter crashes during
		// hydrateRegions when the cart store data is missing, which aborts
		// hydration for every interactive region that follows in the DOM
		// (including Quick View, Wishlist, etc.). Registering fallback
		// values here ensures the data structure always exists; WooCommerce's
		// own register_cart_interactivity() will overwrite with real data
		// when it runs during block rendering.
		add_action( 'wp_enqueue_scripts', array( $this, 'ensure_wc_interactivity_defaults' ) );

		// Server-side features (PHP only, no JS).
		if ( Feature_Settings::is_enabled( 'product_badges' ) ) {
			( new Product_Badges() )->init();
		}

		if ( Feature_Settings::is_enabled( 'price_display' ) ) {
			( new Price_Display() )->init();
		}

		if ( Feature_Settings::is_enabled( 'product_tabs' ) ) {
			( new Product_Tabs() )->init();
		}

		if ( Feature_Settings::is_enabled( 'advanced_sorting' ) ) {
			( new Advanced_Sorting() )->init();
		}

		if ( Feature_Settings::is_enabled( 'free_shipping_bar' ) ) {
			( new Free_Shipping_Bar() )->init();
		}

		// CSS-only enhancements.
		if ( Feature_Settings::is_enabled( 'swatch_tooltips' ) ) {
			( new Swatch_Tooltips() )->init();
		}

		if ( Feature_Settings::is_enabled( 'mini_cart_styling' ) ) {
			( new Mini_Cart_Enhancements() )->init();
		}

		if ( Feature_Settings::is_enabled( 'grid_list_toggle' ) ) {
			( new Grid_List_Toggle() )->init();
		}

		if ( Feature_Settings::is_enabled( 'product_filters' ) ) {
			( new Product_Filters() )->init();
		}

		if ( Feature_Settings::is_enabled( 'page_transitions' ) ) {
			( new Page_Transitions() )->init();
		}

		// Interactive features (PHP + Interactivity API).
		if ( Feature_Settings::is_enabled( 'load_more' ) ) {
			( new Load_More() )->init();
		}

		if ( Feature_Settings::is_enabled( 'size_guide' ) ) {
			( new Size_Guide_Post_Type() )->init();
			( new Size_Guide() )->init();
		}

		if ( Feature_Settings::is_enabled( 'countdown_timer' ) ) {
			( new Countdown_Timer() )->init();
		}

		if ( Feature_Settings::is_enabled( 'recently_viewed' ) ) {
			( new Recently_Viewed() )->init();
		}

		if ( Feature_Settings::is_enabled( 'predictive_search' ) ) {
			( new Predictive_Search() )->init();
		}

		if ( Feature_Settings::is_enabled( 'sticky_add_to_cart' ) ) {
			( new Sticky_Add_To_Cart() )->init();
		}

		if ( Feature_Settings::is_enabled( 'mobile_bottom_nav' ) ) {
			( new Mobile_Bottom_Nav() )->init();
		}

		if ( Feature_Settings::is_enabled( 'exit_intent' ) ) {
			( new Exit_Intent() )->init();
		}

		// Rich interactivity features.
		if ( Feature_Settings::is_enabled( 'quick_view' ) ) {
			( new Quick_View() )->init();
		}

		if ( Feature_Settings::is_enabled( 'wishlist' ) ) {
			( new Wishlist() )->init();
		}

		if ( Feature_Settings::is_enabled( 'social_proof' ) ) {
			( new Social_Proof() )->init();
		}

		if ( Feature_Settings::is_enabled( 'frequently_bought_together' ) ) {
			( new Frequently_Bought_Together() )->init();
		}

		if ( Feature_Settings::is_enabled( 'back_in_stock' ) ) {
			( new Back_In_Stock_Installer() )->maybe_install();
			( new Back_In_Stock() )->init();
			if ( is_admin() ) {
				( new Back_In_Stock_Admin() )->init();
			}
		}
	}

	/**
	 * Register shared script modules used by multiple enhancement features.
	 *
	 * Modules registered here are loaded on-demand when a feature that
	 * declares them as a dependency is enqueued.
	 *
	 * @return void
	 */
	public function register_shared_modules(): void {
		if ( ! function_exists( 'wp_register_script_module' ) ) {
			return;
		}

		wp_register_script_module(
			'@aggressive-apparel/scroll-lock',
			AGGRESSIVE_APPAREL_URI . '/assets/interactivity/scroll-lock.js',
			array(),
			AGGRESSIVE_APPAREL_VERSION,
		);

		wp_register_script_module(
			'@aggressive-apparel/helpers',
			AGGRESSIVE_APPAREL_URI . '/assets/interactivity/helpers.js',
			array(),
			AGGRESSIVE_APPAREL_VERSION,
		);
	}

	/**
	 * Register fallback WooCommerce interactivity state defaults.
	 *
	 * WooCommerce's BlocksSharedState::register_cart_interactivity() sets
	 * the cart data during block rendering. If that call fails or runs
	 * after the product-button block attempts hydration, the quantity
	 * getter throws because cart.items is undefined.
	 *
	 * This runs during wp_enqueue_scripts (before block rendering) so the
	 * fallback is always present. WooCommerce's own call uses
	 * array_replace_recursive, so real data overwrites these defaults.
	 *
	 * @return void
	 */
	public function ensure_wc_interactivity_defaults(): void {
		if ( ! function_exists( 'wp_interactivity_state' ) ) {
			return;
		}

		wp_interactivity_state(
			'woocommerce',
			array(
				'restUrl' => esc_url_raw( get_rest_url() ),
				'nonce'   => wp_create_nonce( 'wc_store_api' ),
				'cart'    => array(
					'items' => array(),
				),
			)
		);
	}
}
