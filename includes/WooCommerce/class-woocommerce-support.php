<?php
/**
 * WooCommerce Support Class
 *
 * Handles WooCommerce theme support registration
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Support Class
 *
 * Registers WooCommerce theme support features.
 *
 * @since 1.0.0
 */
class WooCommerce_Support {

	/**
	 * Initialize WooCommerce support
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'after_setup_theme', array( $this, 'register_woocommerce_support' ), 20 );
	}

	/**
	 * Register WooCommerce support
	 *
	 * @return void
	 */
	public function register_woocommerce_support() {
		// Declare WooCommerce support.
		add_theme_support( 'woocommerce' );

		// Add support for WooCommerce product gallery features.
		add_theme_support(
			'wc-product-gallery-zoom'
		);

		add_theme_support(
			'wc-product-gallery-lightbox'
		);

		add_theme_support(
			'wc-product-gallery-slider'
		);

		/**
		 * Hook: After WooCommerce support registration
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_woocommerce_support' );
	}
}
