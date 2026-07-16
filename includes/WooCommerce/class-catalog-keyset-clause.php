<?php
/**
 * Catalog Keyset Clause
 *
 * Builds WP_Query posts_clauses predicates for cursor pagination.
 *
 * @package Aggressive_Apparel
 * @since 1.90.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Applies keyset (seek) WHERE clauses for catalog cursors.
 *
 * @since 1.90.0
 */
final class Catalog_Keyset_Clause {

	/** Lookup-table alias shared with Product_Collection_Query. */
	private const LOOKUP_ALIAS = 'aa_product_lookup';

	/**
	 * Append keyset predicates for a validated cursor payload.
	 *
	 * @param array<string, string> $clauses Query SQL clauses.
	 * @param array<string, mixed>  $cursor  Validated cursor payload.
	 * @param string                $orderby Active orderby value.
	 * @return array<string, string>
	 */
	public function apply( array $clauses, array $cursor, string $orderby ): array {
		global $wpdb;

		$id = absint( $cursor['id'] ?? 0 );
		if ( $id < 1 || sanitize_key( (string) ( $cursor['v'] ?? '' ) ) !== $orderby ) {
			return $clauses;
		}

		switch ( $orderby ) {
			case 'date':
				$date              = (string) $cursor['d'];
				$clauses['where'] .= $wpdb->prepare(
					" AND ( {$wpdb->posts}.post_date < %s OR ( {$wpdb->posts}.post_date = %s AND {$wpdb->posts}.ID < %d ) )",
					$date,
					$date,
					$id
				);
				break;

			case 'price':
				$clauses           = $this->ensure_lookup_join( $clauses );
				$price             = (float) $cursor['p'];
				$clauses['where'] .= $wpdb->prepare(
					' AND ( %i.min_price > %f OR ( %i.min_price = %f AND %i.ID > %d ) )',
					self::LOOKUP_ALIAS,
					$price,
					self::LOOKUP_ALIAS,
					$price,
					$wpdb->posts,
					$id
				);
				break;

			case 'price-desc':
				$clauses           = $this->ensure_lookup_join( $clauses );
				$price             = (float) $cursor['p'];
				$clauses['where'] .= $wpdb->prepare(
					' AND ( %i.min_price < %f OR ( %i.min_price = %f AND %i.ID < %d ) )',
					self::LOOKUP_ALIAS,
					$price,
					self::LOOKUP_ALIAS,
					$price,
					$wpdb->posts,
					$id
				);
				break;

			case 'popularity':
				$clauses           = $this->ensure_lookup_join( $clauses );
				$sales             = absint( $cursor['s'] );
				$clauses['where'] .= $wpdb->prepare(
					' AND ( %i.total_sales < %d OR ( %i.total_sales = %d AND %i.ID < %d ) )',
					self::LOOKUP_ALIAS,
					$sales,
					self::LOOKUP_ALIAS,
					$sales,
					$wpdb->posts,
					$id
				);
				break;

			case 'rating':
				$clauses           = $this->ensure_lookup_join( $clauses );
				$rating            = (float) $cursor['r'];
				$clauses['where'] .= $wpdb->prepare(
					' AND ( %i.average_rating < %f OR ( %i.average_rating = %f AND %i.ID < %d ) )',
					self::LOOKUP_ALIAS,
					$rating,
					self::LOOKUP_ALIAS,
					$rating,
					$wpdb->posts,
					$id
				);
				break;

			case 'title-asc':
				$title             = (string) $cursor['t'];
				$clauses['where'] .= $wpdb->prepare(
					" AND ( {$wpdb->posts}.post_title > %s OR ( {$wpdb->posts}.post_title = %s AND {$wpdb->posts}.ID > %d ) )",
					$title,
					$title,
					$id
				);
				break;

			case 'title-desc':
				$title             = (string) $cursor['t'];
				$clauses['where'] .= $wpdb->prepare(
					" AND ( {$wpdb->posts}.post_title < %s OR ( {$wpdb->posts}.post_title = %s AND {$wpdb->posts}.ID < %d ) )",
					$title,
					$title,
					$id
				);
				break;

			case 'menu_order':
				$menu              = (int) $cursor['m'];
				$title             = (string) $cursor['t'];
				$clauses['where'] .= $wpdb->prepare(
					" AND (
						{$wpdb->posts}.menu_order > %d
						OR ( {$wpdb->posts}.menu_order = %d AND {$wpdb->posts}.post_title > %s )
						OR ( {$wpdb->posts}.menu_order = %d AND {$wpdb->posts}.post_title = %s AND {$wpdb->posts}.ID > %d )
					)",
					$menu,
					$menu,
					$title,
					$menu,
					$title,
					$id
				);
				break;
		}

		return $clauses;
	}

	/**
	 * Ensure the product meta lookup table is joined.
	 *
	 * @param array<string, string> $clauses Query SQL clauses.
	 * @return array<string, string>
	 */
	private function ensure_lookup_join( array $clauses ): array {
		global $wpdb;

		$alias = self::LOOKUP_ALIAS;
		if ( ! str_contains( $clauses['join'], " {$alias} " ) ) {
			$clauses['join'] .= " INNER JOIN {$wpdb->wc_product_meta_lookup} {$alias} ON {$wpdb->posts}.ID = {$alias}.product_id";
		}

		return $clauses;
	}
}
