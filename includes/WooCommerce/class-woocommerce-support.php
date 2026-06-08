<?php
/**
 * WooCommerce Support Class
 *
 * Handles WooCommerce theme support registration
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

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
		$this->register_woocommerce_support();
		add_filter( 'gettext', array( $this, 'filter_mini_cart_items_counter_text' ), 10, 3 );
	}

	/**
	 * Collapse the mini-cart items counter to "(N)" regardless of WooCommerce's
	 * string format. Matches any parenthesized woocommerce string containing %d,
	 * e.g. the current "(items: %d)" or the planned "(%d items)".
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 *
	 * @return string
	 */
	public function filter_mini_cart_items_counter_text( string $translation, string $text, string $domain ): string {
		if ( 'woocommerce' === $domain && 1 === preg_match( '/^\(.*%d.*\)$/', $text ) ) {
			return '(%d)';
		}
		return $translation;
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
