<?php
/**
 * Catalog Archive Filters
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Applies the URL's catalog filters and sort to the archive's real query.
 *
 * Product grids are only ever rendered by the archive page itself — on first
 * load, by Load More (which fetches the next page) and by the filters (which
 * navigate to the filtered URL) — so this is the one place filters reach SQL.
 *
 * - Inherited Product Collections render the main query: filters merge into
 *   it before WooCommerce builds its product query (which also handles
 *   `?orderby=`).
 * - Explicit (non-inherited) collections build their own query: filters and
 *   `?orderby=` merge into the archive template's main collection only,
 *   leaving other collections on the page (e.g. "You may also like") alone.
 *
 * WooCommerce renders nothing for a collection without results unless its
 * template has a No Results block — not even the collection's router region.
 * A filtered request therefore gets one, so an empty selection shows a notice
 * and a filter navigation can replace the grid with it.
 */
final class Catalog_Archive_Filters {

	/** Sorts a URL may request (WooCommerce's plus the theme's). */
	public const SORTS = array( 'menu_order', 'date', 'popularity', 'rating', 'price', 'price-desc', 'title-asc', 'title-desc', 'featured', 'savings' );

	/** Sorts ordered by Advanced_Sorting rather than by query arguments. */
	private const CUSTOM_SORTS = array( 'featured', 'savings' );

	/**
	 * Query argument builder.
	 *
	 * @var Product_Collection_Query
	 */
	private Product_Collection_Query $queries;

	/**
	 * Template repository (finds the archive's main collection).
	 *
	 * @var Product_Collection_Template_Repository
	 */
	private Product_Collection_Template_Repository $templates;

	/**
	 * Constructor.
	 *
	 * @param ?Product_Collection_Query               $queries   Query argument builder.
	 * @param ?Product_Collection_Template_Repository $templates Template repository.
	 */
	public function __construct( ?Product_Collection_Query $queries = null, ?Product_Collection_Template_Repository $templates = null ) {
		$this->queries   = $queries ?? new Product_Collection_Query();
		$this->templates = $templates ?? new Product_Collection_Template_Repository();
	}

	/** Register hooks. */
	public function init(): void {
		add_filter( 'request', array( $this, 'drop_category_slugs' ) );
		// Before WooCommerce's product query (10), which keeps these clauses.
		add_action( 'pre_get_posts', array( $this, 'filter_main_query' ), 5 );
		add_filter( 'query_loop_block_query_vars', array( $this, 'filter_collection_query' ), 20, 2 );
		add_filter( 'render_block_data', array( $this, 'add_no_results_block' ) );
		add_filter( 'posts_clauses', array( $this->queries, 'apply_lookup_clauses' ), 10, 2 );
	}

	/**
	 * Keep core from reading the catalog's `?cat=` slugs as post categories.
	 *
	 * `cat` is a core query var of post category IDs; the catalog reuses the
	 * name for product category slugs, read from `$_GET` by
	 * Catalog_Url_Filters. Core strips a value to `[0-9,-]`, so `y2k` would
	 * become post category 2 and empty the archive. A value core would strip
	 * is never an ID list, so it is dropped here; ID lists pass through.
	 *
	 * @param array<string, mixed> $query_vars Request query vars.
	 * @return array<string, mixed>
	 */
	public function drop_category_slugs( $query_vars ): array {
		$query_vars = is_array( $query_vars ) ? $query_vars : array();
		$cat        = $query_vars['cat'] ?? null;
		if ( is_string( $cat ) && 1 === preg_match( '/[^0-9,-]/', $cat ) ) {
			unset( $query_vars['cat'] );
		}

		return $query_vars;
	}

	/**
	 * Filter the main product archive query.
	 *
	 * WooCommerce applies `?orderby=` to it; custom sorts are only flagged here
	 * for Advanced_Sorting, exactly as on explicit collections.
	 *
	 * @param \WP_Query $query Query being prepared.
	 */
	public function filter_main_query( $query ): void {
		if ( ! $query instanceof \WP_Query || ! $query->is_main_query() || is_admin() || ! self::is_product_archive( $query ) ) {
			return;
		}

		$orderby = self::requested_sort();
		if ( in_array( $orderby, self::CUSTOM_SORTS, true ) ) {
			$query->set( 'aa_catalog_custom_sort', $orderby );
		}

		$args = $this->filter_args();
		if ( array() === $args ) {
			return;
		}

		// WP_Query takes the queried object from the FIRST tax clause, and the
		// archive's own term is appended after `tax_query`. Resolve it now, so a
		// filtered category stays that category's archive (title, breadcrumbs,
		// canonical) rather than becoming e.g. a colour archive.
		$query->get_queried_object();

		if ( isset( $args['tax_query'] ) ) {
			$existing = $query->get( 'tax_query' );
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed product taxonomies; inputs are validated.
			$merged = Product_Collection_Query::merge_tax_query( array( 'tax_query' => is_array( $existing ) ? $existing : array() ), $args['tax_query'] );
			$query->set( 'tax_query', $merged['tax_query'] ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Indexed product taxonomies; inputs are validated.
			unset( $args['tax_query'] );
		}

		foreach ( $args as $key => $value ) {
			$query->set( $key, $value );
		}
	}

	/**
	 * Filter the archive template's explicit Product Collection query.
	 *
	 * @param array<string, mixed> $query_vars Query vars built from the block.
	 * @param \WP_Block            $block      Block providing the query context.
	 * @return array<string, mixed>
	 */
	public function filter_collection_query( $query_vars, $block ): array {
		$query_vars = is_array( $query_vars ) ? $query_vars : array();
		if ( ! $block instanceof \WP_Block ) {
			return $query_vars;
		}

		$query    = isset( $block->context['query'] ) && is_array( $block->context['query'] ) ? $block->context['query'] : array();
		$query_id = isset( $block->context['queryId'] ) ? (int) $block->context['queryId'] : null;
		if ( ! empty( $query['inherit'] ) || ! $this->is_archive_collection( $query, $query_id ) ) {
			return $query_vars;
		}

		$filters = $this->filter_args();
		if ( isset( $filters['tax_query'] ) ) {
			$query_vars = Product_Collection_Query::merge_tax_query( $query_vars, $filters['tax_query'] );
			unset( $filters['tax_query'] );
		}
		$query_vars = array_merge( $query_vars, $filters );

		$orderby = self::requested_sort();
		if ( '' !== $orderby ) {
			unset( $query_vars['orderby'], $query_vars['order'] );
			$query_vars = array_merge( $query_vars, $this->queries->ordering_args( $orderby ) );
			if ( in_array( $orderby, self::CUSTOM_SORTS, true ) ) {
				$query_vars['aa_catalog_custom_sort'] = $orderby;
			}
		}

		return self::with_id_tiebreak( $query_vars );
	}

	/**
	 * Give a filtered archive's main collection a No Results block when its
	 * template has none.
	 *
	 * @param array<string, mixed> $parsed_block Parsed block about to render.
	 * @return array<string, mixed>
	 */
	public function add_no_results_block( $parsed_block ): array {
		$parsed_block = is_array( $parsed_block ) ? $parsed_block : array();
		if ( 'woocommerce/product-collection' !== ( $parsed_block['blockName'] ?? '' ) ) {
			return $parsed_block;
		}

		$attrs    = isset( $parsed_block['attrs'] ) && is_array( $parsed_block['attrs'] ) ? $parsed_block['attrs'] : array();
		$query    = isset( $attrs['query'] ) && is_array( $attrs['query'] ) ? $attrs['query'] : array();
		$query_id = isset( $attrs['queryId'] ) ? (int) $attrs['queryId'] : null;
		$inner    = isset( $parsed_block['innerBlocks'] ) && is_array( $parsed_block['innerBlocks'] ) ? $parsed_block['innerBlocks'] : array();
		$content  = isset( $parsed_block['innerContent'] ) && is_array( $parsed_block['innerContent'] ) ? $parsed_block['innerContent'] : array();

		if (
			array() === Catalog_Url_Filters::from_request()
			|| ! $this->is_archive_collection( $query, $query_id )
			|| self::contains_block( $inner, 'woocommerce/product-collection-no-results' )
		) {
			return $parsed_block;
		}

		$notice = '<p class="aa-catalog-no-results">'
			. esc_html__( 'No products were found matching your selection.', 'aggressive-apparel' )
			. '</p>';

		$inner[] = array(
			'blockName'    => 'woocommerce/product-collection-no-results',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $notice,
			'innerContent' => array( $notice ),
		);
		// Inner blocks render at the `null` slots, in order: add the slot just
		// before the collection's closing markup.
		$closing = array() === $content ? '' : array_pop( $content );
		array_push( $content, null, $closing );

		$parsed_block['innerBlocks']  = $inner;
		$parsed_block['innerContent'] = $content;

		return $parsed_block;
	}

	/**
	 * Whether a block list contains a block, at any depth.
	 *
	 * @param array<int|string, mixed> $blocks     Parsed blocks.
	 * @param string                   $block_name Block name to find.
	 */
	private static function contains_block( array $blocks, string $block_name ): bool {
		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}
			if ( ( $block['blockName'] ?? '' ) === $block_name ) {
				return true;
			}
			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) && self::contains_block( $block['innerBlocks'], $block_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * End a core sort on the post ID.
	 *
	 * Load More continues page by page, so the order must be total: products
	 * that tie on the sort key (the same menu order, date or title) could
	 * otherwise move between pages, appearing twice or never. WooCommerce's
	 * own sorts (popularity, price, ...) are applied in SQL and left alone.
	 *
	 * @param array<string, mixed> $query_vars Query vars.
	 * @return array<string, mixed>
	 */
	private static function with_id_tiebreak( array $query_vars ): array {
		$orderby = $query_vars['orderby'] ?? '';
		if ( ! is_string( $orderby ) || ! in_array( $orderby, array( 'date', 'title', 'menu_order', 'modified' ), true ) ) {
			return $query_vars;
		}

		$order                 = 'ASC' === strtoupper( (string) ( $query_vars['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC';
		$query_vars['orderby'] = array(
			$orderby => $order,
			'ID'     => $order,
		);

		return $query_vars;
	}

	/**
	 * Query arguments for the request's filters.
	 *
	 * @return array<string, mixed>
	 */
	private function filter_args(): array {
		$filters = Catalog_Url_Filters::from_request();

		return array() === $filters ? array() : $this->queries->filter_args( $filters );
	}

	/**
	 * Sort requested by the URL, if it is a known one.
	 */
	public static function requested_sort(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public catalog sort.
		$orderby = isset( $_GET['orderby'] ) && is_string( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';

		return in_array( $orderby, self::SORTS, true ) ? $orderby : '';
	}

	/**
	 * Whether a query lists products for a shop or product-taxonomy archive.
	 *
	 * @param \WP_Query $query Query.
	 */
	private static function is_product_archive( \WP_Query $query ): bool {
		return $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) );
	}

	/**
	 * Whether a Product Collection query is this archive's main collection:
	 * an inherited one, or the archive template's own collection.
	 *
	 * @param array<string, mixed> $query    Collection query attributes.
	 * @param int|null             $query_id Collection query ID.
	 */
	private function is_archive_collection( array $query, ?int $query_id ): bool {
		if ( empty( $query['isProductCollectionBlock'] ) ) {
			return false;
		}

		global $wp_query;
		if ( ! $wp_query instanceof \WP_Query || ! self::is_product_archive( $wp_query ) ) {
			return false;
		}

		if ( ! empty( $query['inherit'] ) ) {
			return true;
		}

		$collection = $this->templates->collection_block( Product_Context::get_current_template_slug() );
		$archive_id = isset( $collection['attrs']['queryId'] ) ? (int) $collection['attrs']['queryId'] : null;

		return null !== $archive_id && $archive_id === $query_id;
	}
}
