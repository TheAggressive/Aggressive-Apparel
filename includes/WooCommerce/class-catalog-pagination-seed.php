<?php
/**
 * Catalog Pagination Seed
 *
 * Single source of truth for Load More / infinite-scroll SSR state and the
 * Product Collection orderby/perPage used by rendered-products continuations.
 *
 * @package Aggressive_Apparel
 * @since 1.155.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves archive pagination seed values from the active Product Collection.
 *
 * Explicit (non-inherited) collections own their own perPage/orderBy. Seeding
 * those from `$wp_query` / the catalog default caused sparse appends after
 * client-side dedupe (e.g. "Showing 14 of 22").
 *
 * @since 1.155.0
 */
final class Catalog_Pagination_Seed {

	/**
	 * Template repository.
	 *
	 * @var Product_Collection_Template_Repository
	 */
	private Product_Collection_Template_Repository $templates;

	/**
	 * Collection query service.
	 *
	 * @var Product_Collection_Query
	 */
	private Product_Collection_Query $queries;

	/**
	 * Cursor encoder.
	 *
	 * @var Catalog_Cursor
	 */
	private Catalog_Cursor $cursors;

	/**
	 * Constructor.
	 *
	 * @param ?Product_Collection_Template_Repository $templates Template repository.
	 * @param ?Product_Collection_Query               $queries   Query service.
	 * @param ?Catalog_Cursor                         $cursors   Cursor encoder.
	 */
	public function __construct(
		?Product_Collection_Template_Repository $templates = null,
		?Product_Collection_Query $queries = null,
		?Catalog_Cursor $cursors = null
	) {
		$this->templates = $templates ?? new Product_Collection_Template_Repository();
		$this->queries   = $queries ?? new Product_Collection_Query();
		$this->cursors   = $cursors ?? new Catalog_Cursor();
	}

	/**
	 * Build the interactivity / continuation seed for an archive request.
	 *
	 * @param string $template_slug        FSE template slug.
	 * @param string $taxonomy             Optional product taxonomy.
	 * @param string $term                 Optional term slug.
	 * @param ?bool  $pagination_has_more  Native pagination had next links (null = unknown).
	 * @param string $requested_orderby    Optional `?orderby=` from the request.
	 * @return array{
	 *   templateSlug:string,
	 *   collectionBlock:?array<string,mixed>,
	 *   perPage:int,
	 *   orderby:string,
	 *   nextCursor:string,
	 *   totalProducts:int,
	 *   totalPages:int,
	 *   loadedCount:int,
	 *   allLoaded:bool
	 * }
	 */
	public function for_request(
		string $template_slug,
		string $taxonomy = '',
		string $term = '',
		?bool $pagination_has_more = null,
		string $requested_orderby = ''
	): array {
		global $wp_query;

		$template_slug = '' !== $template_slug ? $template_slug : 'archive-product';
		$block         = $this->collection_block( $template_slug );
		$per_page      = $this->resolve_per_page( $block );
		$orderby       = $this->resolve_orderby( $block, $requested_orderby );

		$fallback_products = (int) ( $wp_query->found_posts ?? 0 );
		$fallback_pages    = max( 1, (int) ( $wp_query->max_num_pages ?? 1 ) );
		$fallback_loaded   = null !== $pagination_has_more ? ! $pagination_has_more : $fallback_pages <= 1;

		if ( null === $block ) {
			$next_cursor = '';
			if ( ! $fallback_loaded && ! empty( $wp_query->posts ) ) {
				$last = $wp_query->posts[ array_key_last( $wp_query->posts ) ];
				if ( $last instanceof \WP_Post ) {
					$next_cursor = $this->cursors->from_post( $last, $orderby );
				}
			}

			return array(
				'templateSlug'    => $template_slug,
				'collectionBlock' => null,
				'perPage'         => $per_page,
				'orderby'         => $orderby,
				'nextCursor'      => $next_cursor,
				'totalProducts'   => $fallback_products,
				'totalPages'      => $fallback_pages,
				'loadedCount'     => min( $per_page, $fallback_products ),
				'allLoaded'       => $fallback_loaded,
			);
		}

		$args = $this->queries->build_args( $per_page, $orderby, $taxonomy, $term, array() );
		$args = $this->queries->apply_collection_constraints( $args, $block, $per_page, null, 1 );

		$count_query = $this->queries->run( $this->queries->count_args( $args ) );
		$totals      = $this->queries->totals( $count_query, $args, $per_page );

		$fetch_args                   = $args;
		$fetch_args['posts_per_page'] = $per_page + 1;
		$query                        = $this->queries->run( $fetch_args );
		$probe_has_more               = $this->queries->trim_overflow( $query, $per_page );
		$has_more                     = null !== $pagination_has_more ? $pagination_has_more : $probe_has_more;
		$next_cursor                  = $this->queries->next_cursor( $query, $orderby, $has_more );

		return array(
			'templateSlug'    => $template_slug,
			'collectionBlock' => $block,
			'perPage'         => $per_page,
			'orderby'         => $orderby,
			'nextCursor'      => $next_cursor,
			'totalProducts'   => $totals['products'],
			'totalPages'      => max( 1, $totals['pages'] ),
			'loadedCount'     => min( $per_page, $totals['products'] ),
			'allLoaded'       => ! $has_more || '' === $next_cursor,
		);
	}

	/**
	 * Resolve the Product Collection block for a template slug.
	 *
	 * @param string $template_slug Template slug.
	 * @return array<string, mixed>|null
	 */
	public function collection_block( string $template_slug ): ?array {
		$block = $this->templates->collection_block( $template_slug );
		if ( null === $block && 'archive-product' !== $template_slug ) {
			$block = $this->templates->collection_block( 'archive-product' );
		}

		return $block;
	}

	/**
	 * Page size from the Product Collection block, else the reading setting.
	 *
	 * @param array<string, mixed>|null $block Parsed collection block.
	 * @return int
	 */
	public function resolve_per_page( ?array $block ): int {
		if ( null !== $block ) {
			$query = isset( $block['attrs']['query'] ) && is_array( $block['attrs']['query'] )
				? $block['attrs']['query']
				: array();
			$per   = (int) ( $query['perPage'] ?? 0 );
			if ( $per > 0 ) {
				return min( 100, $per );
			}
		}

		return max( 1, (int) get_option( 'posts_per_page', 12 ) );
	}

	/**
	 * Resolve the catalog orderby that matches the rendered Product Collection.
	 *
	 * @param array<string, mixed>|null $block             Parsed collection block.
	 * @param string                    $requested_orderby Optional request orderby.
	 * @return string
	 */
	public function resolve_orderby( ?array $block, string $requested_orderby = '' ): string {
		$requested = sanitize_text_field( $requested_orderby );
		if ( '' === $requested ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public catalog sort query arg.
			$requested = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['orderby'] ) ) : '';
		}
		if ( in_array( $requested, Catalog_Cursor::ORDERBY_VALUES, true ) ) {
			return $requested;
		}

		if ( null !== $block ) {
			$query   = isset( $block['attrs']['query'] ) && is_array( $block['attrs']['query'] )
				? $block['attrs']['query']
				: array();
			$inherit = ! empty( $query['inherit'] );
			if ( ! $inherit ) {
				$from_block = $this->orderby_from_collection_query( $query );
				if ( '' !== $from_block ) {
					return $from_block;
				}
			}
		}

		$from_query = $this->orderby_from_main_query();
		if ( '' !== $from_query ) {
			return $from_query;
		}

		$default = function_exists( 'get_option' )
			? (string) get_option( 'woocommerce_default_catalog_orderby', 'menu_order' )
			: 'menu_order';

		$map = array(
			'menu_order' => 'menu_order',
			'popularity' => 'popularity',
			'rating'     => 'rating',
			'date'       => 'date',
			'price'      => 'price',
			'price-desc' => 'price-desc',
		);

		return $map[ $default ] ?? 'menu_order';
	}

	/**
	 * Map Product Collection query.orderBy / order to a catalog orderby slug.
	 *
	 * @param array<string, mixed> $query Collection query attributes.
	 * @return string
	 */
	public function orderby_from_collection_query( array $query ): string {
		$order_by = sanitize_key( (string) ( $query['orderBy'] ?? '' ) );
		$order    = strtolower( (string) ( $query['order'] ?? 'asc' ) );

		switch ( $order_by ) {
			case 'date':
			case 'popularity':
			case 'rating':
			case 'menu_order':
				return $order_by;

			case 'title':
				return 'desc' === $order ? 'title-desc' : 'title-asc';

			case 'price':
				return 'desc' === $order ? 'price-desc' : 'price';

			default:
				return '';
		}
	}

	/**
	 * Infer a catalog orderby slug from the main archive query.
	 *
	 * @return string Empty when the query does not expose a known sort.
	 */
	public function orderby_from_main_query(): string {
		global $wp_query;

		if ( ! $wp_query instanceof \WP_Query ) {
			return '';
		}

		$orderby = $wp_query->get( 'orderby' );
		$order   = strtoupper( (string) $wp_query->get( 'order' ) );

		if ( is_array( $orderby ) ) {
			if ( isset( $orderby['menu_order'] ) ) {
				return 'menu_order';
			}
			if ( isset( $orderby['date'] ) ) {
				return 'date';
			}
			if ( isset( $orderby['title'] ) ) {
				return 'DESC' === strtoupper( (string) $orderby['title'] ) ? 'title-desc' : 'title-asc';
			}
			return '';
		}

		$orderby = sanitize_key( (string) $orderby );
		if ( 'date' === $orderby || 'post_date' === $orderby ) {
			return 'date';
		}
		if ( 'menu_order' === $orderby || 'menu_order title' === $orderby ) {
			return 'menu_order';
		}
		if ( 'title' === $orderby ) {
			return 'DESC' === $order ? 'title-desc' : 'title-asc';
		}
		if ( 'rating' === $orderby || 'popularity' === $orderby ) {
			return $orderby;
		}
		if ( 'price' === $orderby ) {
			return 'DESC' === $order ? 'price-desc' : 'price';
		}

		return '';
	}
}
