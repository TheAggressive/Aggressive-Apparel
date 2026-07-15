<?php
/**
 * Product Facet Service
 *
 * @package Aggressive_Apparel
 * @since 1.66.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Computes cached, disjunctive product-attribute availability.
 *
 * @since 1.66.0
 */
final class Product_Facet_Service {

	/** Facet transient prefix. */
	private const CACHE_PREFIX = 'aa_pf_facets_';

	/** Facet transient lifetime. */
	private const CACHE_TTL = 300;

	/**
	 * Catalog query policy used for the public taxonomy allow-list.
	 *
	 * @var Product_Collection_Query
	 */
	private Product_Collection_Query $queries;

	/**
	 * Shared catalog cache version.
	 *
	 * @var Catalog_Cache_Version
	 */
	private Catalog_Cache_Version $version;

	/**
	 * Constructor.
	 *
	 * @param Product_Collection_Query $queries Catalog query policy.
	 * @param Catalog_Cache_Version    $version Shared catalog cache version.
	 */
	public function __construct( Product_Collection_Query $queries, Catalog_Cache_Version $version ) {
		$this->queries = $queries;
		$this->version = $version;
	}

	/**
	 * Compute available attribute terms for the active filters.
	 *
	 * Each attribute is evaluated with the other active filters applied but its
	 * own selection removed, allowing shoppers to switch values within a facet.
	 *
	 * @param array<string, mixed> $filters  Active filter values.
	 * @param string               $taxonomy Current archive taxonomy.
	 * @param string               $term     Current archive term slug.
	 * @return array<string, array<int, string>>
	 */
	public function compute( array $filters, string $taxonomy, string $term ): array {
		$cache_key = self::CACHE_PREFIX . $this->version->current() . '_' . md5( (string) wp_json_encode( array( $filters, $taxonomy, $term ) ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		/**
		 * Attribute taxonomies exposed by the product-filter interface.
		 *
		 * @param string[] $taxonomies Default facet taxonomies.
		 */
		$facet_taxonomies = (array) apply_filters(
			'aggressive_apparel_filter_facet_taxonomies',
			array( 'pa_color', 'pa_size', 'pa_fit' )
		);
		$allowed          = $this->queries->allowed_taxonomies();
		$facets           = array();

		foreach ( $facet_taxonomies as $facet ) {
			if ( ! in_array( $facet, $allowed, true ) ) {
				continue;
			}

			$facet_filters = $filters;
			if ( isset( $facet_filters['attributes'] ) && is_array( $facet_filters['attributes'] ) ) {
				unset( $facet_filters['attributes'][ $facet ] );
			}
			$facet_filters['include'] = '';

			$facets[ $facet ] = $this->query_slugs( $facet, $facet_filters, $taxonomy, $term );
		}

		set_transient( $cache_key, $facets, self::CACHE_TTL );

		return $facets;
	}

	/**
	 * Query available terms through WooCommerce's indexed lookup tables.
	 *
	 * @param string               $facet    Attribute taxonomy being measured.
	 * @param array<string, mixed> $filters  Other active filters.
	 * @param string               $taxonomy Current archive taxonomy.
	 * @param string               $term     Current archive term.
	 * @return string[]
	 */
	private function query_slugs( string $facet, array $filters, string $taxonomy, string $term ): array {
		global $wpdb;

		$attribute_lookup = $wpdb->prefix . 'wc_product_attributes_lookup';
		$product_lookup   = $wpdb->wc_product_meta_lookup;
		$filter_set       = new Catalog_Filter_Set( $filters );
		$constraints      = new Catalog_SQL_Constraints( 'p', 'aa_facet' );

		$allowed = $this->queries->allowed_taxonomies();
		if ( '' !== $taxonomy && '' !== $term && in_array( $taxonomy, $allowed, true ) ) {
			if ( str_starts_with( $taxonomy, 'pa_' ) ) {
				$constraints->add_attribute( $taxonomy, array( $term ), $attribute_lookup );
			} else {
				$constraints->add_taxonomy( $taxonomy, array( $term ) );
			}
		}

		$constraints->add_taxonomy( 'product_cat', $filter_set->categories() );

		if ( $filter_set->on_sale() ) {
			$constraints->add_taxonomy( 'product_cat', array( Sale_Category::TERM_SLUG ) );
		}

		foreach ( $filter_set->attributes( $allowed ) as $attribute_taxonomy => $slugs ) {
			if ( $facet === $attribute_taxonomy || ! str_starts_with( $attribute_taxonomy, 'pa_' ) ) {
				continue;
			}
			$constraints->add_attribute( $attribute_taxonomy, $slugs, $attribute_lookup );
		}

		$constraints->add_lookup_filters( $filter_set, 'product_lookup' );

		$sql = "SELECT DISTINCT facet_terms.slug
			FROM {$attribute_lookup} facet_lookup
			INNER JOIN {$wpdb->posts} p ON facet_lookup.product_or_parent_id = p.ID
			INNER JOIN {$wpdb->terms} facet_terms ON facet_lookup.term_id = facet_terms.term_id
			INNER JOIN {$product_lookup} product_lookup ON p.ID = product_lookup.product_id
			" . $constraints->joins() . "
			WHERE facet_lookup.taxonomy = %s
			AND p.post_type = 'product'
			AND p.post_status = 'publish'";

		if ( ! empty( $constraints->where() ) ) {
			$sql .= "\nAND " . implode( "\nAND ", $constraints->where() );
		}

		$params = array_merge( $constraints->join_params(), array( $facet ), $constraints->where_params() );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Structural fragments contain validated identifiers/placeholders; every value is supplied separately.
		$prepared = $wpdb->prepare( $sql, ...$params );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- compute() wraps the indexed lookup in a versioned transient cache.
		$slugs = $wpdb->get_col( $prepared );

		return array_values( array_unique( array_map( 'strval', $slugs ) ) );
	}
}
