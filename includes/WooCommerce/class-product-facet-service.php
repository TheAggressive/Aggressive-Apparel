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
	private const CACHE_PREFIX = 'aa_pf_facets_v2_'; // v2: categories include their children.

	/** Facet transient lifetime. */
	private const CACHE_TTL = 300;

	/** Most selected terms a facet response is cached for. */
	private const MAX_CACHED_TERMS = 4;

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
		/**
		 * Attribute taxonomies exposed by the product-filter interface.
		 *
		 * @param string[] $taxonomies Default facet taxonomies.
		 */
		$facet_taxonomies = (array) apply_filters(
			'aggressive_apparel_filter_facet_taxonomies',
			array( 'pa_color', 'pa_size', 'pa_collection' )
		);

		$allowed = $this->queries->allowed_taxonomies();
		$filters = self::canonical_filters( new Catalog_Filter_Set( $filters ), $allowed );
		if ( ! in_array( $taxonomy, $allowed, true ) ) {
			$taxonomy = '';
			$term     = '';
		}

		// Keyed on the normalized request, so spelling variants of one selection
		// (case, order, duplicates, unknown parameters) share one entry rather
		// than each writing a transient. The taxonomy list is part of the key so
		// a changed list (a deploy or a filter) never serves an old shape.
		$cache_key = self::CACHE_PREFIX . $this->version->current() . '_' . md5( (string) wp_json_encode( array( $filters, $taxonomy, $term, $facet_taxonomies ) ) );
		$cacheable = self::is_cacheable( $filters );
		$cached    = $cacheable ? get_transient( $cache_key ) : false;
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$facets = array();

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

		if ( $cacheable ) {
			set_transient( $cache_key, $facets, self::CACHE_TTL );
		}

		return $facets;
	}

	/**
	 * Whether a canonical selection is worth a transient.
	 *
	 * This is a public endpoint, so the key space is caller-controlled: free-form
	 * prices and made-up slugs would each write a new row (to wp_options without
	 * a persistent object cache). Only cache selections the filter UI actually
	 * repeats — a few real terms, no price bounds; the rest compute uncached
	 * under the endpoint's rate limit.
	 *
	 * @param array<string, mixed> $filters Canonical filters.
	 * @return bool
	 */
	private static function is_cacheable( array $filters ): bool {
		if ( $filters['min_price'] > 0 || $filters['max_price'] > 0 ) {
			return false;
		}

		$selected = array( 'product_cat' => (string) $filters['category'] );
		foreach ( (array) $filters['attributes'] as $taxonomy => $slugs ) {
			$selected[ (string) $taxonomy ] = (string) $slugs;
		}

		$total = 0;
		foreach ( $selected as $taxonomy => $list ) {
			$slugs = array_filter( explode( ',', $list ) );
			if ( empty( $slugs ) ) {
				continue;
			}

			$total += count( $slugs );
			if ( $total > self::MAX_CACHED_TERMS ) {
				return false;
			}

			$known = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'slug'       => $slugs,
					'fields'     => 'slugs',
					'hide_empty' => false,
				)
			);
			if ( ! is_array( $known ) || count( $known ) !== count( $slugs ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * A filter selection in canonical form: validated, allow-listed and sorted.
	 *
	 * @param Catalog_Filter_Set $filter_set Parsed filters.
	 * @param array<int, string> $allowed    Allowed taxonomies.
	 * @return array<string, mixed>
	 */
	private static function canonical_filters( Catalog_Filter_Set $filter_set, array $allowed ): array {
		$categories = $filter_set->categories();
		sort( $categories );

		$attributes = array();
		foreach ( $filter_set->attributes( $allowed ) as $attribute_taxonomy => $slugs ) {
			sort( $slugs );
			$attributes[ $attribute_taxonomy ] = implode( ',', $slugs );
		}
		ksort( $attributes );

		return array(
			'category'   => implode( ',', $categories ),
			'attributes' => $attributes,
			'min_price'  => $filter_set->min_price(),
			'max_price'  => $filter_set->max_price(),
			'stock'      => $filter_set->stock(),
			'on_sale'    => $filter_set->on_sale(),
		);
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
