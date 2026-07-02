<?php
/**
 * Advanced Sorting Class
 *
 * Adds additional product sorting options to the WooCommerce catalog.
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Advanced Sorting
 *
 * Extends the WooCommerce sort dropdown with Featured, Biggest Savings,
 * and alphabetical options. Provides a custom REST endpoint for AJAX
 * sorting modes that the Store API does not natively support.
 *
 * @since 1.51.0
 */
class Advanced_Sorting {

	/**
	 * Custom sort waiting to be applied to the main server-rendered query.
	 *
	 * @var string
	 */
	private string $ssr_sort = '';

	/**
	 * Maximum category slugs accepted by public sorting requests.
	 *
	 * @var int
	 */
	private const MAX_CATEGORY_FILTERS = 10;

	/**
	 * Maximum raw category query length accepted by public sorting requests.
	 *
	 * @var int
	 */
	private const MAX_CATEGORY_QUERY_LENGTH = 300;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const REST_NAMESPACE = 'aggressive-apparel/v1';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_catalog_orderby', array( $this, 'modify_orderby_options' ) );
		add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'handle_custom_ordering' ), 10, 3 );
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
	}

	/**
	 * Modify the catalog orderby dropdown options.
	 *
	 * @param array<string, string> $options Existing options.
	 * @return array<string, string> Modified options.
	 */
	public function modify_orderby_options( array $options ): array {
		// Relabel "popularity" to "Best Selling".
		if ( isset( $options['popularity'] ) ) {
			$options['popularity'] = __( 'Best Selling', 'aggressive-apparel' );
		}

		// Add new options.
		$options['featured']   = __( 'Featured', 'aggressive-apparel' );
		$options['savings']    = __( 'Biggest Savings', 'aggressive-apparel' );
		$options['title-asc']  = __( 'Name: A-Z', 'aggressive-apparel' );
		$options['title-desc'] = __( 'Name: Z-A', 'aggressive-apparel' );

		return $options;
	}

	/**
	 * Handle custom ordering args for SSR mode.
	 *
	 * @param array<string, mixed> $args     Current ordering args.
	 * @param string               $orderby  The orderby value.
	 * @param string               $order    The order direction.
	 * @return array<string, mixed> Modified args.
	 */
	public function handle_custom_ordering( array $args, string $orderby, string $order ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress filter signature requires $order.
		switch ( $orderby ) {
			case 'title-asc':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;

			case 'title-desc':
				$args['orderby'] = 'title';
				$args['order']   = 'DESC';
				break;

			case 'featured':
				$this->ssr_sort = 'featured';
				add_filter( 'posts_clauses', array( $this, 'apply_ssr_ordering' ), 20, 2 );
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;

			case 'savings':
				$this->ssr_sort = 'savings';
				add_filter( 'posts_clauses', array( $this, 'apply_ssr_ordering' ), 20, 2 );
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
		}

		return $args;
	}

	/**
	 * Apply a custom sort directly in SQL for the native archive query.
	 *
	 * @param array<string, string> $clauses Query clauses.
	 * @param \WP_Query             $query   Query object.
	 * @return array<string, string>
	 */
	public function apply_ssr_ordering( array $clauses, \WP_Query $query ): array {
		if ( '' === $this->ssr_sort || ! $query->is_main_query() ) {
			return $clauses;
		}

		global $wpdb;
		if ( 'featured' === $this->ssr_sort ) {
			$clauses['orderby'] = "EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} aa_featured_tr
				INNER JOIN {$wpdb->term_taxonomy} aa_featured_tt ON aa_featured_tr.term_taxonomy_id = aa_featured_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} aa_featured_t ON aa_featured_tt.term_id = aa_featured_t.term_id
				WHERE aa_featured_tr.object_id = {$wpdb->posts}.ID
				AND aa_featured_tt.taxonomy = 'product_visibility'
				AND aa_featured_t.slug = 'featured'
			) DESC, {$wpdb->posts}.post_date DESC";
		} else {
			$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} aa_regular_price ON {$wpdb->posts}.ID = aa_regular_price.post_id AND aa_regular_price.meta_key = '_regular_price'";
			$clauses['join']   .= " LEFT JOIN {$wpdb->postmeta} aa_sale_price ON {$wpdb->posts}.ID = aa_sale_price.post_id AND aa_sale_price.meta_key = '_sale_price'";
			$clauses['orderby'] = "CASE
				WHEN aa_sale_price.meta_value <> '' AND CAST(aa_regular_price.meta_value AS DECIMAL(20,6)) > 0
				THEN (CAST(aa_regular_price.meta_value AS DECIMAL(20,6)) - CAST(aa_sale_price.meta_value AS DECIMAL(20,6))) / CAST(aa_regular_price.meta_value AS DECIMAL(20,6))
				ELSE -1 END DESC, {$wpdb->posts}.post_date DESC";
		}

		$this->ssr_sort = '';
		remove_filter( 'posts_clauses', array( $this, 'apply_ssr_ordering' ), 20 );

		return $clauses;
	}

	/**
	 * Register custom REST route for AJAX sorting.
	 *
	 * @return void
	 */
	public function register_rest_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/sorted-products',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_sorted_products' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'sort'       => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'featured', 'savings' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page'   => array(
						'type'              => 'integer',
						'default'           => 12,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					),
					'page'       => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'category'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'on_sale'    => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'attributes' => array(
						'default' => array(),
						'type'    => 'object',
					),
					'min_price'  => array(
						'default'           => 0,
						'type'              => 'number',
						'sanitize_callback' => static fn( $value ): float => (float) $value,
					),
					'max_price'  => array(
						'default'           => 0,
						'type'              => 'number',
						'sanitize_callback' => static fn( $value ): float => (float) $value,
					),
					'stock'      => array(
						'default'           => '',
						'type'              => 'string',
						'enum'              => array( '', 'instock', 'outofstock', 'onbackorder' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Handle the sorted-products REST request.
	 *
	 * Returns product IDs in the requested sort order. The frontend
	 * uses these IDs with the Store API `include` param.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request Request object.
	 * @return \WP_REST_Response Response with sorted IDs.
	 */
	public function handle_sorted_products( \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
		$sort     = $request->get_param( 'sort' );
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );
		$category = self::normalize_category_filter( (string) $request->get_param( 'category' ) );
		$on_sale  = (bool) $request->get_param( 'on_sale' );
		$filters  = array(
			'attributes' => (array) $request->get_param( 'attributes' ),
			'min_price'  => (float) $request->get_param( 'min_price' ),
			'max_price'  => (float) $request->get_param( 'max_price' ),
			'stock'      => (string) $request->get_param( 'stock' ),
			'on_sale'    => $on_sale,
		);

		$result      = $this->query_sorted_page( (string) $sort, $category, $filters, (int) $page, (int) $per_page );
		$total       = $result['total'];
		$total_pages = (int) ceil( $total / max( 1, (int) $per_page ) );
		$page_ids    = $result['ids'];

		$response = new \WP_REST_Response(
			array(
				'ids'        => $page_ids,
				'total'      => $total,
				'totalPages' => $total_pages,
			)
		);

		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		return $response;
	}

	/**
	 * Query one custom-sort page without loading the catalogue into PHP.
	 *
	 * @param string               $sort     Sort mode.
	 * @param string               $category Canonical category slugs.
	 * @param array<string, mixed> $filters  Active filters.
	 * @param int                  $page     Page number.
	 * @param int                  $per_page Page size.
	 * @return array{ids: int[], total: int}
	 */
	private function query_sorted_page( string $sort, string $category, array $filters, int $page, int $per_page ): array {
		global $wpdb;

		$filter_set  = new Catalog_Filter_Set( array_merge( $filters, array( 'category' => $category ) ) );
		$constraints = new Catalog_SQL_Constraints( 'p', 'aa_sort' );
		$joins       = array( "INNER JOIN {$wpdb->wc_product_meta_lookup} product_lookup ON p.ID = product_lookup.product_id" );
		$where       = array(
			"p.post_type = 'product'",
			"p.post_status = 'publish'",
		);

		$constraints->add_taxonomy( 'product_cat', $filter_set->categories() );

		if ( $filter_set->on_sale() ) {
			$constraints->add_taxonomy( 'product_cat', array( Sale_Category::TERM_SLUG ) );
		}

		$allowed_attributes = function_exists( 'wc_get_attribute_taxonomy_names' ) ? wc_get_attribute_taxonomy_names() : array();
		foreach ( $filter_set->attributes( $allowed_attributes ) as $taxonomy => $slugs ) {
			$constraints->add_taxonomy( $taxonomy, $slugs );
		}

		$constraints->add_lookup_filters( $filter_set, 'product_lookup' );

		if ( 'savings' === $sort ) {
			$joins[] = "LEFT JOIN {$wpdb->postmeta} regular_price ON p.ID = regular_price.post_id AND regular_price.meta_key = '_regular_price'";
			$joins[] = "LEFT JOIN {$wpdb->postmeta} sale_price ON p.ID = sale_price.post_id AND sale_price.meta_key = '_sale_price'";
			$rank    = "MAX(CASE
				WHEN sale_price.meta_value <> '' AND CAST(regular_price.meta_value AS DECIMAL(20,6)) > 0
				THEN (CAST(regular_price.meta_value AS DECIMAL(20,6)) - CAST(sale_price.meta_value AS DECIMAL(20,6))) / CAST(regular_price.meta_value AS DECIMAL(20,6))
				ELSE -1 END)";
		} else {
			$rank = "MAX(EXISTS (
				SELECT 1 FROM {$wpdb->term_relationships} featured_tr
				INNER JOIN {$wpdb->term_taxonomy} featured_tt ON featured_tr.term_taxonomy_id = featured_tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} featured_t ON featured_tt.term_id = featured_t.term_id
				WHERE featured_tr.object_id = p.ID
				AND featured_tt.taxonomy = 'product_visibility'
				AND featured_t.slug = 'featured'
			))";
		}

		$constraint_joins = $constraints->joins();
		if ( '' !== $constraint_joins ) {
			$joins[] = $constraint_joins;
		}
		$where     = array_merge( $where, $constraints->where() );
		$from      = "FROM {$wpdb->posts} p\n" . implode( "\n", $joins );
		$where_sql = 'WHERE ' . implode( "\nAND ", $where );
		$params    = array_merge( $constraints->join_params(), $constraints->where_params() );

		$count_sql = "SELECT COUNT(DISTINCT p.ID) {$from} {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Generated table aliases and clauses; all public values are prepared.
		$prepared_count = empty( $params ) ? $count_sql : $wpdb->prepare( $count_sql, ...$params );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Bounded indexed catalogue count.
		$total = (int) $wpdb->get_var( $prepared_count );

		$per_page    = min( 100, max( 1, $per_page ) );
		$offset      = ( max( 1, $page ) - 1 ) * $per_page;
		$page_sql    = "SELECT p.ID, {$rank} AS sort_rank
			{$from}
			{$where_sql}
			GROUP BY p.ID
			ORDER BY sort_rank DESC, p.post_date DESC, p.ID DESC
			LIMIT %d OFFSET %d";
		$page_params = array_merge( $params, array( $per_page, $offset ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Generated table aliases and clauses; all public values are prepared.
		$prepared_page = $wpdb->prepare( $page_sql, ...$page_params );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Returns only one requested page.
		$ids = array_map( 'intval', $wpdb->get_col( $prepared_page ) );

		return array(
			'ids'   => $ids,
			'total' => $total,
		);
	}

	/**
	 * Normalize public category filters to known product category slugs.
	 *
	 * @param string $category Raw comma-separated category slugs.
	 * @return string Canonical comma-separated category slugs.
	 */
	private static function normalize_category_filter( string $category ): string {
		$slugs = self::get_category_slugs( $category );

		return implode( ',', $slugs );
	}

	/**
	 * Parse, bound, and validate category slugs.
	 *
	 * @param string $category Raw comma-separated category slugs.
	 * @return string[] Existing product category slugs.
	 */
	private static function get_category_slugs( string $category ): array {
		$category = substr( $category, 0, self::MAX_CATEGORY_QUERY_LENGTH );

		$slugs = array_filter(
			array_unique(
				array_map(
					static fn( string $slug ): string => sanitize_title( trim( $slug ) ),
					explode( ',', $category )
				)
			)
		);

		$slugs = array_slice( array_values( $slugs ), 0, self::MAX_CATEGORY_FILTERS );

		if ( empty( $slugs ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'fields'     => 'slugs',
				'slug'       => $slugs,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$valid = array_flip( array_map( 'strval', $terms ) );

		return array_values(
			array_filter(
				$slugs,
				static fn( string $slug ): bool => isset( $valid[ $slug ] )
			)
		);
	}
}
