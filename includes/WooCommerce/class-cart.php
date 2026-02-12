<?php
/**
 * Cart Class
 *
 * Handles WooCommerce cart functionality
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
 * Cart Class
 *
 * Manages cart-specific functionality and AJAX fragments.
 *
 * @since 1.0.0
 */
class Cart {

	/**
	 * Initialize cart functionality
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_fragments' ), 10, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_cart_script' ), 20 );
	}

	/**
	 * Customize cart fragments for AJAX updates
	 *
	 * @param array $fragments Cart fragments.
	 * @return array Modified fragments.
	 */
	public function cart_fragments( $fragments ) {
		// Check if WooCommerce cart is available.
		if ( ! function_exists( 'WC' ) || ! \WC()->cart ) { // @phpstan-ignore booleanNot.alwaysFalse
			return $fragments;
		}

		$cart_count = \WC()->cart->get_cart_contents_count();

		$fragments['span.aggressive-apparel-cart__count'] = '<span class="aggressive-apparel-cart aggressive-apparel-cart__count">' . esc_html( (string) $cart_count ) . '</span>';

		return $fragments;
	}

	/**
	 * Enqueue cart script
	 *
	 * @return void
	 */
	public function enqueue_cart_script() {
		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		$theme_uri = get_template_directory_uri();
		$theme     = wp_get_theme();
		$version   = ! $theme->errors() ? $theme->get( 'Version' ) : '1.0.0';

		wp_enqueue_script(
			'aggressive-apparel-cart',
			$theme_uri . '/build/scripts/cart.js',
			array( 'jquery' ),
			$version,
			true
		);
	}
}
