<?php
/**
 * Product Collection Query
 *
 * @package Aggressive_Apparel
 * @since 1.66.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Translates catalog filters and sorts into WP_Query arguments.
 *
 * Shared by the archive pages (filters applied to the real query, see
 * Catalog_Archive_Filters) and the facet counts, so both agree on every
 * constraint. Price, stock and sale constraints and the price/popularity/
 * rating sorts use WooCommerce's indexed product lookup table through
 * `apply_lookup_clauses()`, which acts only on queries carrying its vars.
 *
 * @since 1.66.0
 */
final class Product_Collection_Query {

	/**
	 * Query arguments for the filters: taxonomy clauses plus lookup-table vars.
	 *
	 * @param array<string, mixed> $filters Catalog_Filter_Set input.
	 * @return array{tax_query?: array<int|string, mixed>, aa_catalog_lookup_filters?: array<string, mixed>}
	 */
	public function filter_args( array $filters ): array {
		$filter_set = new Catalog_Filter_Set( $filters );
		$args       = array();

		if ( $filter_set->min_price() > 0 || $filter_set->max_price() > 0 || '' !== $filter_set->stock() ) {
			$args['aa_catalog_lookup_filters'] = array(
				'min_price' => $filter_set->min_price(),
				'max_price' => $filter_set->max_price(),
				'stock'     => $filter_set->stock(),
			);
		}

		$tax_query  = array();
		$categories = $filter_set->categories();
		if ( ! empty( $categories ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array_values( $categories ),
			);
		}

		if ( $filter_set->on_sale() ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => Sale_Category::TERM_SLUG,
			);
		}

		foreach ( $filter_set->attributes( $this->allowed_taxonomies() ) as $attribute_taxonomy => $slugs ) {
			$tax_query[] = array(
				'taxonomy' => $attribute_taxonomy,
				'field'    => 'slug',
				'terms'    => array_values( $slugs ),
			);
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed product taxonomies; inputs are validated.
		}

		return $args;
	}

	/**
	 * Query arguments for a catalog sort (WooCommerce `orderby` values).
	 *
	 * Featured and Savings are custom orders applied by Advanced_Sorting; they
	 * keep a deterministic date fallback here.
	 *
	 * @param string $orderby Sort value.
	 * @return array<string, mixed>
	 */
	public function ordering_args( string $orderby ): array {
		switch ( $orderby ) {
			case 'menu_order':
				return array(
					'orderby' => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
						'ID'         => 'ASC',
					),
				);
			case 'popularity':
			case 'rating':
				return array(
					'aa_catalog_lookup_orderby' => $orderby,
					'order'                     => 'DESC',
				);
			case 'price':
				return array(
					'aa_catalog_lookup_orderby' => 'price',
					'order'                     => 'ASC',
				);
			case 'price-desc':
				return array(
					'aa_catalog_lookup_orderby' => 'price',
					'order'                     => 'DESC',
				);
			case 'title-asc':
				return array(
					'orderby' => array(
						'title' => 'ASC',
						'ID'    => 'ASC',
					),
				);
			case 'title-desc':
				return array(
					'orderby' => array(
						'title' => 'DESC',
						'ID'    => 'DESC',
					),
				);
			default:
				return array(
					'orderby' => array(
						'date' => 'DESC',
						'ID'   => 'DESC',
					),
				);
		}
	}

	/**
	 * Merge additional `tax_query` clauses into existing query arguments.
	 *
	 * @param array<string, mixed>     $args    Query arguments.
	 * @param array<int|string, mixed> $clauses Clauses to add.
	 * @return array<string, mixed>
	 */
	public static function merge_tax_query( array $args, array $clauses ): array {
		$existing = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : array();
		unset( $clauses['relation'] );

		$merged = array_merge( $existing, array_values( $clauses ) );
		if ( count( array_filter( array_keys( $merged ), 'is_int' ) ) > 1 ) {
			$merged['relation'] = 'AND';
		}

		$args['tax_query'] = $merged; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed product taxonomies; inputs are validated.
		return $args;
	}

	/**
	 * Apply indexed price, stock and sale constraints and lookup-table sorts.
	 *
	 * Registered globally by Catalog_Archive_Filters; it changes only queries
	 * that carry the `aa_catalog_*` vars.
	 *
	 * @param array<string, string> $clauses Query SQL clauses.
	 * @param \WP_Query             $query   Query object.
	 * @return array<string, string>
	 */
	public function apply_lookup_clauses( array $clauses, \WP_Query $query ): array {
		$filters = $query->get( 'aa_catalog_lookup_filters' );
		$orderby = sanitize_key( (string) $query->get( 'aa_catalog_lookup_orderby' ) );

		if ( ! is_array( $filters ) && '' === $orderby ) {
			return $clauses;
		}

		global $wpdb;

		$alias = 'aa_product_lookup';
		if ( ! str_contains( $clauses['join'], " {$alias} " ) ) {
			$clauses['join'] .= " INNER JOIN {$wpdb->wc_product_meta_lookup} {$alias} ON {$wpdb->posts}.ID = {$alias}.product_id";
		}

		if ( is_array( $filters ) ) {
			$filter_set = new Catalog_Filter_Set( $filters );
			if ( $filter_set->min_price() > 0 ) {
				$clauses['where'] .= $wpdb->prepare( ' AND %i.max_price >= %f', $alias, $filter_set->min_price() );
			}
			if ( $filter_set->max_price() > 0 ) {
				$clauses['where'] .= $wpdb->prepare( ' AND %i.min_price <= %f', $alias, $filter_set->max_price() );
			}
			if ( '' !== $filter_set->stock() ) {
				$clauses['where'] .= $wpdb->prepare( ' AND %i.stock_status = %s', $alias, $filter_set->stock() );
			}
		}

		$order = 'ASC' === strtoupper( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';
		switch ( $orderby ) {
			case 'price':
				$clauses['orderby'] = "{$alias}.min_price {$order}, {$wpdb->posts}.ID {$order}";
				break;
			case 'popularity':
				$clauses['orderby'] = "{$alias}.total_sales {$order}, {$wpdb->posts}.ID DESC";
				break;
			case 'rating':
				$clauses['orderby'] = "{$alias}.average_rating {$order}, {$wpdb->posts}.ID DESC";
				break;
		}

		return $clauses;
	}

	/**
	 * Return product taxonomies allowed in public query constraints.
	 *
	 * @return array<int, string>
	 */
	public function allowed_taxonomies(): array {
		$attributes = function_exists( 'wc_get_attribute_taxonomy_names' )
			? \wc_get_attribute_taxonomy_names()
			: array();

		return array_merge( array( 'product_cat', 'product_tag', 'product_brand' ), $attributes );
	}
}
