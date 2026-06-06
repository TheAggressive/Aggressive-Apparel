<?php
/**
 * Product Context Helper
 *
 * Centralizes WooCommerce page-context detection (archive / listing / single
 * product) and current-product resolution so feature classes don't each
 * reimplement the same conditional-tag guards.
 *
 * @package Aggressive_Apparel
 * @since 1.78.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Context
 *
 * Stateless helpers; all methods are static.
 *
 * @since 1.78.0
 */
class Product_Context {

	/**
	 * Whether the current request is a product archive (shop, category, or tag).
	 *
	 * @return bool
	 */
	public static function is_product_archive(): bool {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}

		return is_shop() || is_product_category() || is_product_tag();
	}

	/**
	 * Whether the current request is a single product page.
	 *
	 * @return bool
	 */
	public static function is_single_product(): bool {
		return function_exists( 'is_product' ) && is_product();
	}

	/**
	 * Whether the current request is a product listing.
	 *
	 * Honors the `aggressive_apparel_is_listing_page` filter (set by the
	 * Load More AJAX renderer) so server-rendered card markup stays in sync
	 * with the original archive request. Otherwise matches product archives
	 * plus search results.
	 *
	 * @return bool
	 */
	public static function is_product_listing(): bool {
		if ( (bool) apply_filters( 'aggressive_apparel_is_listing_page', false ) ) {
			return true;
		}

		return self::is_product_archive() || is_search();
	}

	/**
	 * Whether the current request displays products in any context.
	 *
	 * Covers archives, single product, cart (cross-sells), and search —
	 * the broadest "does this page show product cards" check.
	 *
	 * @return bool
	 */
	public static function is_product_display_page(): bool {
		if ( (bool) apply_filters( 'aggressive_apparel_is_listing_page', false ) ) {
			return true;
		}

		$is_cart = function_exists( 'is_cart' ) && is_cart();

		return self::is_product_archive()
			|| self::is_single_product()
			|| $is_cart
			|| is_search();
	}

	/**
	 * Get the WC_Product for the current post in the loop.
	 *
	 * @return \WC_Product|null
	 */
	public static function get_current_product(): ?\WC_Product {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( get_the_ID() );

		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Resolve the current product ID across WooCommerce and block contexts.
	 *
	 * Resolution order: block instance context → block array context → global
	 * `$product` → queried object → global `$post` → loop post.
	 *
	 * @param array $block          Optional block data from render_block.
	 * @param mixed $block_instance Optional rendered WP_Block instance.
	 * @return int Product ID, or 0 when no product is available.
	 */
	public static function resolve_product_id( array $block = array(), $block_instance = null ): int {
		if ( $block_instance instanceof \WP_Block && isset( $block_instance->context['postId'] ) ) {
			$context_product_id = absint( $block_instance->context['postId'] );

			if ( $context_product_id > 0 && 'product' === get_post_type( $context_product_id ) ) {
				return $context_product_id;
			}
		}

		if ( isset( $block['context']['postId'] ) ) {
			$block_product_id = absint( $block['context']['postId'] );

			if ( $block_product_id > 0 && 'product' === get_post_type( $block_product_id ) ) {
				return $block_product_id;
			}
		}

		global $product;

		if ( $product instanceof \WC_Product ) {
			return (int) $product->get_id();
		}

		$queried_product_id = get_queried_object_id();
		if ( $queried_product_id > 0 && 'product' === get_post_type( $queried_product_id ) ) {
			return (int) $queried_product_id;
		}

		global $post;

		if ( $post instanceof \WP_Post && 'product' === $post->post_type ) {
			return (int) $post->ID;
		}

		$loop_product_id = get_the_ID();
		if ( $loop_product_id > 0 && 'product' === get_post_type( $loop_product_id ) ) {
			return (int) $loop_product_id;
		}

		return 0;
	}

	/**
	 * Get the slug of the current product category archive, if any.
	 *
	 * @return string Category slug, or an empty string when not on a category.
	 */
	public static function get_current_category_slug(): string {
		if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
			return '';
		}

		$term = get_queried_object();

		return $term instanceof \WP_Term ? $term->slug : '';
	}
}
