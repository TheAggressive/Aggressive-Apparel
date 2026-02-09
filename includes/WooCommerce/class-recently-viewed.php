<?php
/**
 * Recently Viewed Products Class
 *
 * Renders a "Recently Viewed" section on product pages using localStorage
 * for tracking and the REST API for fetching product data.
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
 * Recently Viewed Products
 *
 * @since 1.17.0
 */
class Recently_Viewed {

	/**
	 * Maximum products to store and display.
	 *
	 * @var int
	 */
	private int $max_display = 4;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_placeholder' ), 25 );
	}

	/**
	 * Register and enqueue the Interactivity API script module on single product pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		if ( function_exists( 'wp_register_script_module' ) ) {
			wp_register_script_module(
				'@aggressive-apparel/recently-viewed',
				AGGRESSIVE_APPAREL_URI . '/assets/interactivity/recently-viewed.js',
				array( '@wordpress/interactivity' ),
				AGGRESSIVE_APPAREL_VERSION,
			);
			wp_enqueue_script_module( '@aggressive-apparel/recently-viewed' );
		}
	}

	/**
	 * Render a placeholder section that the Interactivity API will populate.
	 *
	 * On single product pages, we also record the current product ID for the
	 * client-side script to persist into localStorage.
	 *
	 * @return void
	 */
	public function render_placeholder(): void {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		$product_id = (int) get_the_ID();

		$context = wp_json_encode(
			array(
				'currentProductId' => $product_id,
				'maxDisplay'       => $this->max_display,
				'products'         => array(),
				'loaded'           => false,
				'restBase'         => esc_url_raw( rest_url( 'wc/store/v1/products' ) ),
			),
		);

		echo '<section class="aggressive-apparel-recently-viewed" data-wp-interactive="aggressive-apparel/recently-viewed" data-wp-context=\'' . esc_attr( $context ) . '\' data-wp-init="callbacks.init">';
		echo '<div data-wp-bind--hidden="!context.loaded">';
		echo '<h2 class="aggressive-apparel-recently-viewed__title">' . esc_html__( 'Recently Viewed', 'aggressive-apparel' ) . '</h2>';
		echo '<div class="aggressive-apparel-recently-viewed__grid" data-wp-html="state.productsHtml"></div>';
		echo '</div>';
		echo '</section>';
	}
}
