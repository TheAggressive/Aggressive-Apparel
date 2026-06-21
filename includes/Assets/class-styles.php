<?php
/**
 * Styles Class
 *
 * Handles frontend stylesheet enqueuing
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Assets;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Styles Class
 *
 * Responsible for enqueuing all frontend stylesheets.
 *
 * @since 1.0.0
 */
class Styles {

	/**
	 * Initialize styles
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( Asset_Loader::class, 'enqueue_tokens' ), 0 );
		add_action( 'enqueue_block_assets', array( Asset_Loader::class, 'enqueue_tokens' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_assets' ), 10 );
	}

	/**
	 * Enqueue the core stylesheets shared between frontend and editor contexts.
	 *
	 * @return void
	 */
	private function enqueue_core_styles(): void {
		Asset_Loader::enqueue_tokens();
		Asset_Loader::enqueue_style( 'aggressive-apparel-main', 'build/styles/main' );
	}

	/**
	 * Enqueue frontend styles
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		$this->enqueue_core_styles();

		if ( function_exists( 'WC' ) ) {
			Asset_Loader::enqueue_style( 'aggressive-apparel-mini-cart', 'build/styles/woocommerce/mini-cart' );

			// Checkout/cart readability fixes (floating labels, order-summary
			// badges) for the block checkout & cart.
			if (
				( function_exists( 'is_checkout' ) && is_checkout() ) ||
				( function_exists( 'is_cart' ) && is_cart() )
			) {
				Asset_Loader::enqueue_style( 'aggressive-apparel-checkout', 'build/styles/woocommerce/checkout' );
			}
		}

		if ( is_singular( 'product' ) ) {
			Asset_Loader::enqueue_style( 'aggressive-apparel-product', 'build/styles/woocommerce/color-swatches' );
			Asset_Loader::enqueue_style( 'aggressive-apparel-variation-pills', 'build/styles/woocommerce/variation-pills' );
		}

		/**
		 * Hook: After styles enqueued
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_enqueue_styles' );
	}

	/**
	 * Enqueue block assets (available in both frontend and editor)
	 *
	 * @return void
	 */
	public function enqueue_block_assets() {
		$this->enqueue_core_styles();

		/**
		 * Hook: After block assets enqueued
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_enqueue_block_assets' );
	}
}
