<?php
/**
 * Mini Cart Enhancements Class
 *
 * Styles the native WooCommerce mini-cart block to match the theme design.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mini Cart Enhancements
 *
 * @since 1.17.0
 */
class Mini_Cart_Enhancements {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue mini-cart override styles.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-mini-cart',
			'build/styles/woocommerce/mini-cart'
		);
	}
}
