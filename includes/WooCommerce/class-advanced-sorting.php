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
	 * Transient key for savings-sorted product IDs.
	 *
	 * @var string
	 */
	private const SAVINGS_TRANSIENT = 'aa_savings_sorted_ids';

	/**
	 * Transient TTL in seconds (15 minutes).
	 *
	 * @var int
	 */
	private const TRANSIENT_TTL = 900;

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

		// Cache invalidation.
		add_action( 'woocommerce_update_product', array( $this, 'flush_savings_cache' ) );
		add_action( 'woocommerce_new_product', array( $this, 'flush_savings_cache' ) );
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
				$featured_ids = $this->get_featured_sorted_ids();
				if ( ! empty( $featured_ids ) ) {
					$args['post__in'] = $featured_ids;
					$args['orderby']  = 'post__in';
				}
				break;

			case 'savings':
				$savings_ids = $this->get_savings_sorted_ids();
				if ( ! empty( $savings_ids ) ) {
					$args['post__in'] = $savings_ids;
					$args['orderby']  = 'post__in';
				}
				break;
		}

		return $args;
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
					'sort'     => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array( 'featured', 'savings' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 12,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					),
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'category' => array(
						'type'              => 'string',
						'default'           => '',
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
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response with sorted IDs.
	 */
	public function handle_sorted_products( \WP_REST_Request $request ): \WP_REST_Response { // phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
		$sort     = $request->get_param( 'sort' );
		$per_page = $request->get_param( 'per_page' );
		$page     = $request->get_param( 'page' );
		$category = $request->get_param( 'category' );

		$all_ids = 'featured' === $sort
			? $this->get_featured_sorted_ids( $category )
			: $this->get_savings_sorted_ids( $category );

		$total       = count( $all_ids );
		$total_pages = (int) ceil( $total / $per_page );
		$offset      = ( $page - 1 ) * $per_page;
		$page_ids    = array_slice( $all_ids, $offset, $per_page );

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
	 * Get product IDs sorted with featured products first.
	 *
	 * @param string $category Optional category slug filter.
	 * @return int[] Ordered product IDs.
	 */
	private function get_featured_sorted_ids( string $category = '' ): array {
		$featured_ids = wc_get_featured_product_ids();

		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $category ) ) {
			$slugs                   = array_map( 'trim', explode( ',', $category ) );
			$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $slugs,
				),
			);
		}

		$all_query = new \WP_Query( $query_args );
		$all_ids   = $all_query->posts;

		// Featured first, then the rest by date.
		$featured_in_set = array_intersect( $featured_ids, $all_ids );
		$non_featured    = array_diff( $all_ids, $featured_ids );

		return array_merge( array_values( $featured_in_set ), array_values( $non_featured ) );
	}

	/**
	 * Get product IDs sorted by discount percentage (highest first).
	 *
	 * @param string $category Optional category slug filter.
	 * @return int[] Ordered product IDs.
	 */
	private function get_savings_sorted_ids( string $category = '' ): array {
		$transient_key = self::SAVINGS_TRANSIENT . ( $category ? '_' . md5( $category ) : '' );
		$cached        = get_transient( $transient_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$category_join  = '';
		$category_where = '';

		if ( ! empty( $category ) ) {
			$slugs        = array_map( 'trim', explode( ',', $category ) );
			$placeholders = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );

			$category_join  = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
			$category_join .= " INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'product_cat'";
			$category_join .= " INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id";
			$category_where = $wpdb->prepare( " AND t.slug IN ({$placeholders})", ...$slugs ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		// Get sale products sorted by discount percentage.
		// Results are cached via transient above — direct query required for
		// complex discount calculation that WP_Query cannot express.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$sale_ids = $wpdb->get_col(
			"SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm_reg ON p.ID = pm_reg.post_id AND pm_reg.meta_key = '_regular_price'
			INNER JOIN {$wpdb->postmeta} pm_sale ON p.ID = pm_sale.post_id AND pm_sale.meta_key = '_sale_price'
			{$category_join}
			WHERE p.post_type = 'product'
			AND p.post_status = 'publish'
			AND pm_sale.meta_value != ''
			AND pm_sale.meta_value IS NOT NULL
			AND CAST(pm_reg.meta_value AS DECIMAL(10,2)) > 0
			{$category_where}
			ORDER BY ((CAST(pm_reg.meta_value AS DECIMAL(10,2)) - CAST(pm_sale.meta_value AS DECIMAL(10,2))) / CAST(pm_reg.meta_value AS DECIMAL(10,2))) DESC"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$sale_ids = array_map( 'intval', $sale_ids );

		// Append non-sale products sorted by date.
		$non_sale_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'post__not_in'   => $sale_ids, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_post__not_in
		);

		if ( ! empty( $category ) ) {
			$slugs                      = array_map( 'trim', explode( ',', $category ) );
			$non_sale_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $slugs,
				),
			);
		}

		$non_sale_query = new \WP_Query( $non_sale_args );
		$non_sale_ids   = array_map( 'intval', array_map( 'strval', $non_sale_query->posts ) );

		$result = array_merge( $sale_ids, $non_sale_ids );

		set_transient( $transient_key, $result, self::TRANSIENT_TTL );

		return $result;
	}

	/**
	 * Flush the savings sort cache.
	 *
	 * @return void
	 */
	public function flush_savings_cache(): void {
		global $wpdb;

		// Delete all savings transients (base + per-category).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient cleanup requires direct query.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::SAVINGS_TRANSIENT ) . '%'
			)
		);
	}
}
