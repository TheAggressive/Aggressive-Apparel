<?php
/**
 * WooCommerce mini-cart drawer accessibility.
 *
 * Adds inert to the closed mini-cart drawer so aria-hidden subtrees cannot
 * receive focus (PageSpeed Agent Accessibility / WCAG).
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
		add_filter( 'render_block', array( $this, 'inject_drawer_inert' ), 10, 2 );
	}

	/**
	 * Add inert to the drawer in SSR HTML before Interactivity API hydrates.
	 *
	 * @param string               $content Rendered block HTML.
	 * @param array<string, mixed> $block   Block data.
	 * @return string
	 */
	public function inject_drawer_inert( string $content, array $block ): string {
		if ( 'woocommerce/mini-cart' !== ( $block['blockName'] ?? '' ) ) {
			return $content;
		}

		if ( false === strpos( $content, 'wc-block-mini-cart__drawer' ) ) {
			return $content;
		}

		// The bundle is footer-based, so it can be enqueued when the block is
		// actually rendered instead of loading on every WooCommerce page.
		$this->enqueue_script();

		if ( preg_match( '/\bwc-block-mini-cart__drawer\b[^>]*\binert\b/', $content ) ) {
			return $content;
		}

		$updated = preg_replace(
			'/(<div\b(?=[^>]*\bwc-block-mini-cart__drawer\b)(?![^>]*\binert\b)[^>]*)(>)/',
			'$1 inert$2',
			$content,
			1
		);

		return is_string( $updated ) ? $updated : $content;
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
