<?php
/**
 * Legacy Asset Trim Class
 *
 * Removes WooCommerce's classic (non-block) frontend scripts and styles on
 * requests that cannot use them. On a block theme, WooCommerce blocks ship
 * their own `wc-blocks-*` assets; the classic bundle (jQuery-based scripts
 * and the woocommerce-general/layout/smallscreen stylesheets) is only needed
 * on classic WooCommerce routes and for WooCommerce shortcodes.
 *
 * Measured impact: ~126 KB of CSS and the entire jQuery script chain
 * (jquery, blockUI, js.cookie, woocommerce.js, add-to-cart.js) removed from
 * the homepage and other non-commerce pages.
 *
 * @package Aggressive_Apparel
 * @since 1.132.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy Asset Trim
 *
 * @since 1.132.0
 */
class Legacy_Asset_Trim {

	/**
	 * Classic WooCommerce script handles safe to drop off non-WC pages.
	 *
	 * The jQuery library itself is intentionally not touched — other plugins
	 * may rely on it. Dropping these handles removes their private
	 * dependencies (jquery-blockui, js-cookie) automatically when nothing
	 * else needs them.
	 *
	 * @var array<int, string>
	 */
	private const SCRIPT_HANDLES = array(
		'wc-add-to-cart',
		'woocommerce',
		'wc-cart-fragments',
	);

	/**
	 * Classic WooCommerce style handles safe to drop off non-WC pages.
	 *
	 * WooCommerce block styles (`wc-blocks-*`) are never touched.
	 *
	 * @var array<int, string>
	 */
	private const STYLE_HANDLES = array(
		'woocommerce-general',
		'woocommerce-layout',
		'woocommerce-smallscreen',
		'woocommerce-blocktheme',
		'woocommerce-inline',
	);

	/**
	 * WooCommerce shortcode prefixes that require the classic assets.
	 *
	 * @var array<int, string>
	 */
	private const WC_SHORTCODE_MARKERS = array(
		'[product',
		'[add_to_cart',
		'[woocommerce_',
		'[shop_messages',
	);

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Priority 99: after WooCommerce has enqueued its frontend assets (10).
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_trim' ), 99 );
	}

	/**
	 * Dequeue classic WooCommerce assets when the request cannot need them.
	 *
	 * @return void
	 */
	public function maybe_trim(): void {
		if ( ! $this->should_trim() ) {
			return;
		}

		foreach ( self::SCRIPT_HANDLES as $handle ) {
			wp_dequeue_script( $handle );
		}

		foreach ( self::STYLE_HANDLES as $handle ) {
			wp_dequeue_style( $handle );
		}
	}

	/**
	 * Whether the classic WooCommerce bundle can be removed for this request.
	 *
	 * Fails open: any classic WooCommerce route, endpoint, or shortcode in the
	 * queried content keeps the full asset set.
	 *
	 * @return bool
	 */
	private function should_trim(): bool {
		// Classic WooCommerce routes (shop, archives, single product).
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			return false;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			return false;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return false;
		}

		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return false;
		}

		// WooCommerce shortcodes in the queried content need classic assets.
		if ( $this->queried_content_has_wc_shortcode() ) {
			return false;
		}

		/**
		 * Filter whether classic WooCommerce assets are trimmed on this request.
		 *
		 * @since 1.132.0
		 *
		 * @param bool $trim True to dequeue the classic bundle.
		 */
		return (bool) apply_filters( 'aggressive_apparel_trim_wc_legacy_assets', true );
	}

	/**
	 * Whether the queried post content contains a WooCommerce shortcode.
	 *
	 * @return bool
	 */
	private function queried_content_has_wc_shortcode(): bool {
		$post_id = get_queried_object_id();

		if ( $post_id <= 0 ) {
			return false;
		}

		$content = get_post_field( 'post_content', $post_id );

		if ( ! is_string( $content ) || '' === $content ) {
			return false;
		}

		foreach ( self::WC_SHORTCODE_MARKERS as $marker ) {
			if ( str_contains( $content, $marker ) ) {
				return true;
			}
		}

		return false;
	}
}
