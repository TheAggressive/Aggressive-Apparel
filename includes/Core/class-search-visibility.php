<?php
/**
 * Search Visibility
 *
 * Central policy for what content may appear in public autocomplete search.
 *
 * @package Aggressive_Apparel
 */

declare( strict_types=1 );

namespace Aggressive_Apparel\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Public search visibility rules.
 */
class Search_Visibility {

	/**
	 * Post types exposed through autocomplete.
	 *
	 * @var string[]
	 */
	private const SUPPORTED_POST_TYPES = array( 'product', 'post', 'page' );

	/**
	 * Supported autocomplete post types.
	 *
	 * @return string[]
	 */
	public static function supported_post_types(): array {
		return self::SUPPORTED_POST_TYPES;
	}

	/**
	 * Whether a post meets the baseline public search criteria.
	 *
	 * Requires a supported type, publish status, and no password protection.
	 *
	 * @param \WP_Post|null $post Candidate post.
	 * @return bool
	 */
	public static function is_searchable_post( ?\WP_Post $post ): bool {
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		if ( ! in_array( $post->post_type, self::SUPPORTED_POST_TYPES, true ) ) {
			return false;
		}

		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		return '' === (string) $post->post_password;
	}

	/**
	 * Whether a post should be written to the search index.
	 *
	 * Products must also be catalogue-visible so hidden SKUs are not indexed.
	 *
	 * @param \WP_Post|null $post Candidate post.
	 * @return bool
	 */
	public static function is_indexable( ?\WP_Post $post ): bool {
		if ( ! $post instanceof \WP_Post || ! self::is_searchable_post( $post ) ) {
			return false;
		}

		if ( 'product' === $post->post_type ) {
			return self::is_catalogue_visible_product( (int) $post->ID );
		}

		return true;
	}

	/**
	 * Whether a hydrated product may appear in autocomplete results.
	 *
	 * @param int $product_id Product post ID.
	 * @return bool
	 */
	public static function is_public_product( int $product_id ): bool {
		$post = get_post( $product_id );
		if ( ! self::is_searchable_post( $post ) ) {
			return false;
		}

		return self::is_catalogue_visible_product( $product_id );
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Whether products may be returned to the current requester.
	 *
	 * Requires WooCommerce, and — when the store is in "coming soon" mode —
	 * the capability to manage the store.
	 *
	 * @return bool
	 */
	public static function products_are_public(): bool {
		if ( ! self::woocommerce_active() ) {
			return false;
		}

		if ( 'yes' === get_option( 'woocommerce_coming_soon' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether a product is visible in the WooCommerce catalogue.
	 *
	 * @param int $product_id Product post ID.
	 * @return bool
	 */
	private static function is_catalogue_visible_product( int $product_id ): bool {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product = wc_get_product( $product_id );

		return $product && $product->is_visible();
	}
}
