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
 * Builds and executes deterministic, indexed Product Collection queries.
 *
 * @since 1.66.0
 */
final class Product_Collection_Query {

	/**
	 * Cursor codec.
	 *
	 * @var Catalog_Cursor
	 */
	private Catalog_Cursor $cursors;

	/**
	 * Keyset SQL builder.
	 *
	 * @var Catalog_Keyset_Clause
	 */
	private Catalog_Keyset_Clause $keyset;

	/**
	 * Constructor.
	 *
	 * @param ?Catalog_Cursor        $cursors Cursor codec.
	 * @param ?Catalog_Keyset_Clause $keyset  Keyset clause builder.
	 */
	public function __construct( ?Catalog_Cursor $cursors = null, ?Catalog_Keyset_Clause $keyset = null ) {
		$this->cursors = $cursors ?? new Catalog_Cursor();
		$this->keyset  = $keyset ?? new Catalog_Keyset_Clause();
	}

	/**
	 * Build base WP_Query arguments from the validated endpoint request.
	 *
	 * Page numbers are client UI state only. Result windows are positioned with
	 * keyset cursors (or the collection's start offset on the first page).
	 *
	 * @param int                  $per_page Products per page.
	 * @param string               $orderby  WooCommerce sort value.
	 * @param string               $taxonomy Product archive taxonomy.
	 * @param string               $term     Archive term slug.
	 * @param array<string, mixed> $filters  Product-filter values.
	 * @return array<string, mixed>
	 */
	public function build_args( int $per_page, string $orderby, string $taxonomy, string $term, array $filters = array() ): array {
		$filter_set = new Catalog_Filter_Set( $filters );
		$args       = array(
			'post_type'                 => 'product',
			'posts_per_page'            => $per_page,
			'paged'                     => 1,
			'post_status'               => 'publish',
			'aa_catalog_lookup_filters' => array(
				'min_price' => $filter_set->min_price(),
				'max_price' => $filter_set->max_price(),
				'stock'     => $filter_set->stock(),
			),
		);

		// Custom sorts resolve a bounded, ordered page of IDs separately. Render
		// exactly that page while preventing forged, unbounded render requests.
		$include = array_values(
			array_filter(
				array_map( 'absint', explode( ',', (string) ( $filters['include'] ?? '' ) ) )
			)
		);
		if ( ! empty( $include ) ) {
			$max_include                 = min( 100, max( 1, $per_page ) );
			$include                     = array_slice( $include, 0, $max_include );
			$args['post__in']            = $include;
			$args['orderby']             = 'post__in';
			$args['posts_per_page']      = count( $include );
			$args['paged']               = 1;
			$args['ignore_sticky_posts'] = true;
			return $args;
		}

		switch ( $orderby ) {
			case 'menu_order':
				$args['orderby'] = array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
					'ID'         => 'ASC',
				);
				break;

			case 'popularity':
				$args['aa_catalog_lookup_orderby'] = 'popularity';
				$args['order']                     = 'DESC';
				break;

			case 'rating':
				$args['aa_catalog_lookup_orderby'] = 'rating';
				$args['order']                     = 'DESC';
				break;

			case 'price':
				$args['aa_catalog_lookup_orderby'] = 'price';
				$args['order']                     = 'ASC';
				break;

			case 'price-desc':
				$args['aa_catalog_lookup_orderby'] = 'price';
				$args['order']                     = 'DESC';
				break;

			case 'title-asc':
				$args['orderby'] = array(
					'title' => 'ASC',
					'ID'    => 'ASC',
				);
				break;

			case 'title-desc':
				$args['orderby'] = array(
					'title' => 'DESC',
					'ID'    => 'DESC',
				);
				break;

			default:
				$args['orderby'] = array(
					'date' => 'DESC',
					'ID'   => 'DESC',
				);
		}

		$allowed   = $this->allowed_taxonomies();
		$tax_query = array();

		if ( '' !== $taxonomy && '' !== $term && in_array( $taxonomy, $allowed, true ) ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $term,
			);
		}

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

		foreach ( $filter_set->attributes( $allowed ) as $attribute_taxonomy => $slugs ) {
			$tax_query[] = array(
				'taxonomy' => $attribute_taxonomy,
				'field'    => 'slug',
				'terms'    => array_values( $slugs ),
			);
		}

		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Product taxonomy tables are indexed; this avoids unbounded ID lists and postmeta scans.
			$args['tax_query'] = $tax_query;
		}

		return $args;
	}

	/**
	 * Apply constraints owned by the saved Product Collection block.
	 *
	 * Sorting and page size remain request-controlled because the existing grid
	 * and catalog sorting UI are authoritative for those values.
	 *
	 * @param array<string, mixed>      $args             Base query arguments.
	 * @param array<string, mixed>      $collection_block Parsed Product Collection block.
	 * @param int                       $per_page         Requested page size.
	 * @param array<string, mixed>|null $cursor           Validated keyset cursor, if any.
	 * @param int                       $page             UI page (offset seek when no cursor).
	 * @return array<string, mixed>
	 */
	public function apply_collection_constraints( array $args, array $collection_block, int $per_page, ?array $cursor = null, int $page = 1 ): array {
		$attrs = isset( $collection_block['attrs'] ) && is_array( $collection_block['attrs'] )
			? $collection_block['attrs']
			: array();
		$query = isset( $attrs['query'] ) && is_array( $attrs['query'] )
			? $attrs['query']
			: array();

		$exclude = $this->bounded_product_ids( $query['exclude'] ?? array() );
		if ( ! empty( $exclude ) ) {
			$args['aa_catalog_excluded_ids'] = $exclude;
		}

		$handpicked = $this->bounded_product_ids( $query['woocommerceHandPickedProducts'] ?? array() );
		if ( ! empty( $handpicked ) ) {
			$handpicked       = array_values( array_diff( $handpicked, $exclude ) );
			$current_in       = array_values( array_filter( array_map( 'absint', (array) ( $args['post__in'] ?? array() ) ) ) );
			$args['post__in'] = empty( $current_in ) ? $handpicked : array_values( array_intersect( $current_in, $handpicked ) );
			if ( empty( $args['post__in'] ) ) {
				$args['post__in'] = array( 0 );
			}
		}

		$search = sanitize_text_field( (string) ( $query['search'] ?? '' ) );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		// Keyset cursors are the Load More / infinite-scroll path. Offset seek is
		// only for filter UI page-number jumps that lack a cursor.
		$base_offset       = min( 1000, max( 0, (int) ( $query['offset'] ?? 0 ) ) );
		$args['aa_offset'] = $base_offset;
		$args['paged']     = 1;
		$page              = max( 1, $page );
		if ( null !== $cursor ) {
			$args['aa_catalog_cursor']         = $cursor;
			$args['aa_catalog_cursor_orderby'] = sanitize_key( (string) ( $cursor['v'] ?? '' ) );
			$args['offset']                    = 0;
		} else {
			$args['offset'] = $base_offset + ( $per_page * ( $page - 1 ) );
		}

		$page_limit = min( 10000, max( 0, (int) ( $query['pages'] ?? 0 ) ) );
		if ( $page_limit > 0 ) {
			$args['aa_page_limit'] = $page_limit;
		}

		$valid_stock          = function_exists( 'wc_get_product_stock_status_options' )
			? array_keys( \wc_get_product_stock_status_options() )
			: array( 'instock', 'outofstock', 'onbackorder' );
		$has_stock_constraint = array_key_exists( 'woocommerceStockStatus', $query );
		$stock                = array_values(
			array_intersect(
				$valid_stock,
				array_map( 'sanitize_key', (array) ( $query['woocommerceStockStatus'] ?? $valid_stock ) )
			)
		);
		if ( $has_stock_constraint && count( $stock ) < count( $valid_stock ) ) {
			$args['aa_catalog_stock_statuses'] = ! empty( $stock ) ? $stock : array( 'aa-none' );
		}

		$collection = sanitize_text_field( (string) ( $attrs['collection'] ?? '' ) );
		$on_sale    = ! empty( $query['woocommerceOnSale'] ) || str_ends_with( $collection, '/on-sale' );
		if ( $on_sale ) {
			$args['aa_catalog_on_sale'] = true;
		}

		$tax_query = isset( $args['tax_query'] ) && is_array( $args['tax_query'] )
			? $args['tax_query']
			: array();
		if ( ! empty( $query['featured'] ) || str_ends_with( $collection, '/featured' ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => 'featured',
				'operator' => 'IN',
			);
		}

		$allowed = $this->allowed_taxonomies();
		foreach ( (array) ( $query['taxQuery'] ?? $query['tax_query'] ?? array() ) as $constraint ) {
			if ( ! is_array( $constraint ) ) {
				continue;
			}
			$constraint_taxonomy = sanitize_key( (string) ( $constraint['taxonomy'] ?? '' ) );
			$terms               = $this->bounded_term_ids( $constraint['terms'] ?? array() );
			if ( ! in_array( $constraint_taxonomy, $allowed, true ) || empty( $terms ) ) {
				continue;
			}
			$tax_query[] = array(
				'taxonomy' => $constraint_taxonomy,
				'field'    => 'term_id',
				'terms'    => $terms,
				'operator' => 'IN',
			);
		}

		$attributes = array();
		foreach ( (array) ( $query['woocommerceAttributes'] ?? array() ) as $attribute ) {
			if ( ! is_array( $attribute ) ) {
				continue;
			}
			$attribute_taxonomy = sanitize_key( (string) ( $attribute['taxonomy'] ?? '' ) );
			$term_id            = absint( $attribute['termId'] ?? 0 );
			if ( ! in_array( $attribute_taxonomy, $allowed, true ) || 0 === $term_id ) {
				continue;
			}
			$attributes[ $attribute_taxonomy ][] = $term_id;
		}
		foreach ( $attributes as $attribute_taxonomy => $term_ids ) {
			$tax_query[] = array(
				'taxonomy' => $attribute_taxonomy,
				'field'    => 'term_id',
				'terms'    => array_values( array_unique( $term_ids ) ),
				'operator' => 'IN',
			);
		}

		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Product taxonomies are indexed and all inputs are validated.
			$args['tax_query'] = $tax_query;
		}

		return $args;
	}

	/**
	 * Execute a query with scoped lookup-table and keyset clause handling.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return \WP_Query
	 */
	public function run( array $args ): \WP_Query {
		add_filter( 'posts_clauses', array( $this, 'apply_lookup_clauses' ), 10, 2 );
		add_filter( 'posts_clauses', array( $this, 'apply_keyset_clauses' ), 20, 2 );
		try {
			return new \WP_Query( $args );
		} finally {
			remove_filter( 'posts_clauses', array( $this, 'apply_keyset_clauses' ), 20 );
			remove_filter( 'posts_clauses', array( $this, 'apply_lookup_clauses' ), 10 );
		}
	}

	/**
	 * Build arguments for a totals count that ignores the active keyset cursor.
	 *
	 * @param array<string, mixed> $args Final render query arguments.
	 * @return array<string, mixed>
	 */
	public function count_args( array $args ): array {
		$count = $args;
		unset( $count['aa_catalog_cursor'], $count['aa_catalog_cursor_orderby'] );
		$count['posts_per_page']         = 1;
		$count['paged']                  = 1;
		$count['offset']                 = max( 0, (int) ( $args['aa_offset'] ?? 0 ) );
		$count['fields']                 = 'ids';
		$count['no_found_rows']          = false;
		$count['update_post_meta_cache'] = false;
		$count['update_post_term_cache'] = false;

		return $count;
	}

	/**
	 * Calculate totals after a collection offset and optional page cap.
	 *
	 * @param \WP_Query            $query    Executed count query (no cursor).
	 * @param array<string, mixed> $args     Final query arguments.
	 * @param int                  $per_page Page size.
	 * @return array{products:int,pages:int}
	 */
	public function totals( \WP_Query $query, array $args, int $per_page ): array {
		$offset   = max( 0, (int) ( $args['aa_offset'] ?? 0 ) );
		$products = max( 0, (int) $query->found_posts - $offset );
		$limit    = max( 0, (int) ( $args['aa_page_limit'] ?? 0 ) );
		if ( $limit > 0 ) {
			$products = min( $products, $limit * $per_page );
		}

		return array(
			'products' => $products,
			'pages'    => $products > 0 ? (int) ceil( $products / $per_page ) : 0,
		);
	}

	/**
	 * Trim a per_page+1 probe query and return whether another page exists.
	 *
	 * @param \WP_Query $query    Executed query.
	 * @param int       $per_page Requested page size.
	 * @return bool True when more products remain after this page.
	 */
	public function trim_overflow( \WP_Query $query, int $per_page ): bool {
		$posts = $query->posts;
		if ( count( $posts ) <= $per_page ) {
			return false;
		}

		$query->posts       = array_slice( $posts, 0, $per_page );
		$query->post_count  = count( $query->posts );
		$query->found_posts = max( (int) $query->found_posts, $per_page + 1 );

		return true;
	}

	/**
	 * Encode a next-page cursor from the last post in the current page.
	 *
	 * @param \WP_Query $query    Trimmed result query.
	 * @param string    $orderby  Active orderby value.
	 * @param bool      $has_more Whether another page exists.
	 * @return string
	 */
	public function next_cursor( \WP_Query $query, string $orderby, bool $has_more ): string {
		if ( ! $has_more || empty( $query->posts ) ) {
			return '';
		}

		$last = $query->posts[ array_key_last( $query->posts ) ];
		if ( ! $last instanceof \WP_Post ) {
			return '';
		}

		return $this->cursors->from_post( $last, $orderby );
	}

	/**
	 * Decode a public cursor token for the active sort.
	 *
	 * @param string $token   Opaque cursor.
	 * @param string $orderby Active orderby value.
	 * @return array<string, mixed>|null
	 */
	public function decode_cursor( string $token, string $orderby ): ?array {
		return $this->cursors->decode( $token, $orderby );
	}

	/**
	 * Apply keyset predicates when a cursor is present on the query.
	 *
	 * @param array<string, string> $clauses Query SQL clauses.
	 * @param \WP_Query             $query   Query object.
	 * @return array<string, string>
	 */
	public function apply_keyset_clauses( array $clauses, \WP_Query $query ): array {
		$cursor = $query->get( 'aa_catalog_cursor' );
		if ( ! is_array( $cursor ) ) {
			return $clauses;
		}

		$orderby = sanitize_key( (string) $query->get( 'aa_catalog_cursor_orderby' ) );
		if ( '' === $orderby ) {
			$orderby = sanitize_key( (string) ( $cursor['v'] ?? '' ) );
		}

		return $this->keyset->apply( $clauses, $cursor, $orderby );
	}

	/**
	 * Apply indexed price, stock, sale, exclusion, and sorting clauses.
	 *
	 * @param array<string, string> $clauses Query SQL clauses.
	 * @param \WP_Query             $query   Query object.
	 * @return array<string, string>
	 */
	public function apply_lookup_clauses( array $clauses, \WP_Query $query ): array {
		$filters        = $query->get( 'aa_catalog_lookup_filters' );
		$orderby        = sanitize_key( (string) $query->get( 'aa_catalog_lookup_orderby' ) );
		$stock_statuses = array_values( array_filter( array_map( 'sanitize_key', (array) $query->get( 'aa_catalog_stock_statuses' ) ) ) );
		$excluded_ids   = array_values( array_filter( array_map( 'absint', (array) $query->get( 'aa_catalog_excluded_ids' ) ) ) );
		$on_sale        = (bool) $query->get( 'aa_catalog_on_sale' );

		if ( ! is_array( $filters ) && '' === $orderby && empty( $stock_statuses ) && empty( $excluded_ids ) && ! $on_sale ) {
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
		if ( ! empty( $stock_statuses ) ) {
			$stock_predicates = array();
			foreach ( $stock_statuses as $stock_status ) {
				$stock_predicates[] = $wpdb->prepare( '%i.stock_status = %s', $alias, $stock_status );
			}
			$clauses['where'] .= ' AND (' . implode( ' OR ', $stock_predicates ) . ')';
		}
		if ( ! empty( $excluded_ids ) ) {
			foreach ( $excluded_ids as $excluded_id ) {
				$clauses['where'] .= $wpdb->prepare( ' AND %i.ID != %d', $wpdb->posts, $excluded_id );
			}
		}
		if ( $on_sale ) {
			$clauses['where'] .= $wpdb->prepare( ' AND %i.onsale = %d', $alias, 1 );
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

	/**
	 * Normalize a bounded product-ID list from saved block attributes.
	 *
	 * @param mixed $ids Raw IDs.
	 * @return int[]
	 */
	private function bounded_product_ids( $ids ): array {
		return array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) ), 0, 100 );
	}

	/**
	 * Normalize a bounded taxonomy-term ID list.
	 *
	 * @param mixed $ids Raw IDs.
	 * @return int[]
	 */
	private function bounded_term_ids( $ids ): array {
		return array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) ), 0, 1000 );
	}
}
