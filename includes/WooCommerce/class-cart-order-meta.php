<?php
/**
 * Cart and checkout order-meta lines.
 *
 * Loads the script that adds free-shipping progress (cart) and a "You save"
 * total (cart and checkout) to the order summaries, only when one of those
 * blocks renders.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart and checkout order-meta lines.
 */
class Cart_Order_Meta {

	/**
	 * Script handle.
	 */
	public const SCRIPT_HANDLE = 'aggressive-apparel-cart-order-meta';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'render_block_woocommerce/cart', array( $this, 'enqueue_script_for_block' ) );
		add_filter( 'render_block_woocommerce/checkout', array( $this, 'enqueue_script_for_block' ) );
	}

	/**
	 * Enqueue the order-meta script for a rendered cart or checkout block.
	 *
	 * @param string $content Rendered block HTML.
	 * @return string Unmodified block HTML.
	 */
	public function enqueue_script_for_block( string $content ): string {
		$this->enqueue_script();

		return $content;
	}

	/**
	 * Enqueue the order-meta script and the data it renders from.
	 *
	 * WooCommerce's checkout slot API and price formatter are globals, not
	 * extracted imports, so their handles are added as dependencies here. The
	 * free-shipping wording and currency format match the free-shipping blocks
	 * (default block attributes); amounts come from the live cart. No
	 * per-shopper value is printed, so a cached page stays correct.
	 *
	 * @return void
	 */
	public function enqueue_script(): void {
		if ( is_admin() || ! function_exists( 'WC' ) || wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			return;
		}

		Asset_Loader::enqueue_script(
			self::SCRIPT_HANDLE,
			'build/scripts/cart-order-meta',
			array( 'wc-blocks-checkout', 'wc-price-format' )
		);

		// Labels travel as a PHP bag, as for the theme's script modules: the
		// POT is extracted from PHP and block.json, not from TypeScript.
		$data = array(
			'i18n'     => array(
				'youSave'          => __( 'You save', 'aggressive-apparel' ),
				'shippingProgress' => __( 'Free shipping progress', 'aggressive-apparel' ),
			),
			'shipping' => array_merge(
				Free_Shipping_Message::block_context( array() ),
				Free_Shipping::get_currency_context()
			),
		);

		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.aaCartOrderMeta = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}
}
