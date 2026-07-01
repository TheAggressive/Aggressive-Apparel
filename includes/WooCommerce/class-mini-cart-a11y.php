<?php
/**
 * WooCommerce mini-cart drawer accessibility.
 *
 * Loads the closed-drawer inert synchronizer only when the mini-cart block is
 * rendered so aria-hidden subtrees cannot receive focus.
 *
 * @package Aggressive_Apparel
 * @since 1.16.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mini-cart drawer accessibility integration.
 *
 * @since 1.16.0
 */
class Mini_Cart_A11y {

	/**
	 * Script handle.
	 */
	private const SCRIPT_HANDLE = 'aggressive-apparel-mini-cart-a11y';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'render_block_woocommerce/mini-cart', array( $this, 'enqueue_script_for_block' ) );
	}

	/**
	 * Enqueue the accessibility script for a rendered mini-cart block.
	 *
	 * WooCommerce portals the drawer separately from the header trigger, so the
	 * client script observes that portal and synchronizes inert after it appears.
	 *
	 * @param string $content Rendered mini-cart block HTML.
	 * @return string Unmodified block HTML.
	 */
	public function enqueue_script_for_block( string $content ): string {
		$this->enqueue_script();

		return $content;
	}

	/**
	 * Enqueue the drawer inert sync script when a mini-cart is rendered.
	 *
	 * @return void
	 */
	public function enqueue_script(): void {
		if ( is_admin() || ! function_exists( 'WC' ) ) {
			return;
		}

		Asset_Loader::enqueue_script(
			self::SCRIPT_HANDLE,
			'build/scripts/mini-cart-a11y'
		);
	}
}
