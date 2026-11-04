<?php
/**
 * Size Guide Assignments
 *
 * Where size guides apply: per product (product meta), per category (term
 * meta), and store-wide (an option). Size_Guide reads these to resolve a
 * product's guide; the Size Guides editor reads and writes them here.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Size guide assignment reads and writes.
 *
 * @phpstan-type Usage array{global: bool, categories: list<string>, products: int}
 */
final class Size_Guide_Assignments {

	/**
	 * Where a size guide is assigned, for display.
	 *
	 * @param int $guide_id Size guide post ID.
	 * @return Usage
	 */
	public static function usage( int $guide_id ): array {
		return self::usage_map( array( $guide_id ) )[ $guide_id ];
	}

	/**
	 * Where each of several guides is assigned, in a fixed number of queries
	 * (the shared category read, and one for product counts) however many
	 * guides.
	 *
	 * @param array $guide_ids Size guide post IDs.
	 * @phpstan-param list<int> $guide_ids
	 * @return array<int, Usage>
	 */
	public static function usage_map( array $guide_ids ): array {
		$map = array();
		foreach ( $guide_ids as $guide_id ) {
			$map[ $guide_id ] = array(
				'global'     => self::is_default( $guide_id ),
				'categories' => array(),
				'products'   => 0,
			);
		}
		if ( array() === $map ) {
			return $map;
		}

		$terms = Product_Categories::all();
		foreach ( Product_Categories::by_guide() as $guide_id => $ids ) {
			if ( isset( $map[ $guide_id ] ) ) {
				foreach ( $ids as $id ) {
					$map[ $guide_id ]['categories'][] = html_entity_decode( $terms[ $id ]->name, ENT_QUOTES, 'UTF-8' );
				}
			}
		}

		global $wpdb;
		// One grouped count for every guide; admin-only, never on the storefront.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS guide, COUNT(DISTINCT pm.post_id) AS products
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_type = 'product'
				AND p.post_status IN ('publish', 'draft', 'pending', 'private', 'future')
				GROUP BY pm.meta_value",
				Size_Guide::CPT_META_KEY
			),
			ARRAY_A
		);
		foreach ( is_array( $counts ) ? $counts : array() as $row ) {
			$guide_id = (int) ( $row['guide'] ?? 0 );
			if ( isset( $map[ $guide_id ] ) ) {
				$map[ $guide_id ]['products'] = (int) ( $row['products'] ?? 0 );
			}
		}

		return $map;
	}

	/**
	 * Category IDs that use a size guide as their default, by name.
	 *
	 * @param int $guide_id Size guide post ID.
	 * @return list<int>
	 */
	public static function categories( int $guide_id ): array {
		return Product_Categories::by_guide()[ $guide_id ] ?? array();
	}

	/**
	 * Make a size guide the default for exactly these categories.
	 *
	 * Categories that used it but are no longer listed are cleared; listed
	 * categories switch to it from whatever they used before.
	 *
	 * @param int   $guide_id     Size guide post ID.
	 * @param array $category_ids Category IDs.
	 * @phpstan-param list<int> $category_ids
	 */
	public static function set_categories( int $guide_id, array $category_ids ): void {
		foreach ( array_diff( self::categories( $guide_id ), $category_ids ) as $term_id ) {
			delete_term_meta( $term_id, Size_Guide::CPT_META_KEY );
		}
		foreach ( $category_ids as $term_id ) {
			if ( null !== term_exists( $term_id, 'product_cat' ) ) {
				update_term_meta( $term_id, Size_Guide::CPT_META_KEY, $guide_id );
			}
		}
	}

	/**
	 * Whether a size guide is the store-wide default.
	 *
	 * @param int $guide_id Size guide post ID.
	 */
	public static function is_default( int $guide_id ): bool {
		return (int) get_option( Size_Guide::CPT_OPTION_KEY, 0 ) === $guide_id;
	}

	/**
	 * Make a size guide the store-wide default, or stop it being one.
	 *
	 * @param int  $guide_id   Size guide post ID.
	 * @param bool $is_default Whether it should be the default.
	 */
	public static function set_default( int $guide_id, bool $is_default ): void {
		if ( $is_default ) {
			update_option( Size_Guide::CPT_OPTION_KEY, $guide_id, false );
		} elseif ( self::is_default( $guide_id ) ) {
			delete_option( Size_Guide::CPT_OPTION_KEY );
		}
	}

	/**
	 * Remove every assignment of a size guide (before it is trashed).
	 *
	 * Product-level assignments are left: they fall back past a guide that is
	 * no longer published, and come back if it is restored.
	 *
	 * @param int $guide_id Size guide post ID.
	 */
	public static function release( int $guide_id ): void {
		self::set_categories( $guide_id, array() );
		self::set_default( $guide_id, false );
	}
}
