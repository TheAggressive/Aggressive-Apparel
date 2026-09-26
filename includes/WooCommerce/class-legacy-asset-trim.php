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
 * Commerce routes rendered entirely by blocks (shop, product archives, block
 * cart and checkout) drop the classic stylesheets too, but keep the classic
 * scripts: third-party payment gateways may still rely on them.
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
	 * Blocks that print classic WooCommerce markup styled by woocommerce.css.
	 *
	 * @var array<int, string>
	 */
	private const CLASSIC_BLOCK_MARKERS = array(
		'wp:woocommerce/legacy-template',
		'wp:woocommerce/classic-shortcode',
		'wp:woocommerce/product-details',
		'wp:woocommerce/add-to-cart-form',
		'wp:shortcode',
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
		$trim_scripts = $this->should_trim();

		if ( ! $trim_scripts && ! $this->should_trim_block_route_styles() ) {
			return;
		}

		if ( $trim_scripts ) {
			foreach ( self::SCRIPT_HANDLES as $handle ) {
				wp_dequeue_script( $handle );
			}
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

		return $this->filter_trim();
	}

	/**
	 * Whether a block-only commerce route can drop the classic stylesheets.
	 *
	 * Single products keep them: the reviews tab prints WooCommerce's classic
	 * reviews template, whose star ratings only woocommerce.css styles. Account
	 * pages and endpoints (order-received, order-pay) render classic templates.
	 *
	 * @return bool
	 */
	private function should_trim_block_route_styles(): bool {
		if ( ! function_exists( 'is_woocommerce' ) || ! function_exists( 'is_wc_endpoint_url' ) ) {
			return false;
		}

		if ( is_product() || is_account_page() || is_wc_endpoint_url() ) {
			return false;
		}

		if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() ) {
			return false;
		}

		if ( ! $this->current_template_is_block_only() || $this->queried_content_has_wc_shortcode() ) {
			return false;
		}

		return $this->filter_trim();
	}

	/**
	 * Whether the resolved block template contains no classic-markup blocks.
	 *
	 * Fails closed when no block template resolved (classic PHP template).
	 *
	 * @return bool
	 */
	private function current_template_is_block_only(): bool {
		global $_wp_current_template_content;

		if ( ! wp_is_block_theme() || ! is_string( $_wp_current_template_content ) || '' === $_wp_current_template_content ) {
			return false;
		}

		return ! $this->contains_any( $_wp_current_template_content, self::CLASSIC_BLOCK_MARKERS );
	}

	/**
	 * Apply the public trim filter.
	 *
	 * @return bool
	 */
	private function filter_trim(): bool {
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
	 * Whether the queried post content contains a WooCommerce shortcode or a
	 * block that prints classic markup.
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

		return $this->contains_any( $content, array_merge( self::WC_SHORTCODE_MARKERS, self::CLASSIC_BLOCK_MARKERS ) );
	}

	/**
	 * Whether the haystack contains any of the markers.
	 *
	 * @param string             $haystack Content to search.
	 * @param array<int, string> $markers  Substrings to look for.
	 * @return bool
	 */
	private function contains_any( string $haystack, array $markers ): bool {
		foreach ( $markers as $marker ) {
			if ( str_contains( $haystack, $marker ) ) {
				return true;
			}
		}

		return false;
	}
}
