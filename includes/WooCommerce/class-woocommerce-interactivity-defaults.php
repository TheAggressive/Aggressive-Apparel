<?php
/**
 * WooCommerce Interactivity Defaults
 *
 * Seeds fallback WooCommerce interactivity state before blocks render so
 * product-button hydration never crashes when cart data is momentarily absent.
 *
 * @package Aggressive_Apparel
 * @since 1.79.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce Interactivity Defaults
 *
 * @since 1.79.0
 */
class WooCommerce_Interactivity_Defaults {

	/**
	 * Whether fallback interactivity state has been registered this request.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_register_defaults' ) );
	}

	/**
	 * Register fallback WooCommerce interactivity state when this request needs it.
	 *
	 * @return void
	 */
	public function maybe_register_defaults(): void {
		if ( ! WooCommerce_Block_Detector::request_needs_assets() ) {
			return;
		}

		self::register_defaults();
	}

	/**
	 * Whether fallback WooCommerce interactivity state is needed on this request.
	 *
	 * @return bool
	 */
	public static function should_register(): bool {
		if ( ! function_exists( 'wp_interactivity_state' ) ) {
			return false;
		}

		return WooCommerce_Block_Detector::request_needs_assets();
	}

	/**
	 * Register fallback WooCommerce interactivity state.
	 *
	 * @return void
	 */
	public static function register_defaults(): void {
		if ( self::$registered || ! function_exists( 'wp_interactivity_state' ) ) {
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

		self::$registered = true;
	}

	/**
	 * Register fallback interactivity state if it is not already registered.
	 *
	 * Used by the runtime bailout when enqueue-time detection misses a block.
	 *
	 * @return void
	 */
	public static function ensure_registered(): void {
		self::register_defaults();
	}
}
