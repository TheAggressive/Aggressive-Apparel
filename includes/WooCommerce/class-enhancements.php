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

		if ( Feature_Settings::is_enabled( 'filter_styling' ) ) {
			( new Product_Filter_Styling() )->init();
		}

		// Interactive features (PHP + Interactivity API).
		if ( Feature_Settings::is_enabled( 'size_guide' ) ) {
			( new Size_Guide() )->init();
		}

		if ( Feature_Settings::is_enabled( 'countdown_timer' ) ) {
			( new Countdown_Timer() )->init();
		}

		if ( Feature_Settings::is_enabled( 'recently_viewed' ) ) {
			( new Recently_Viewed() )->init();
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
