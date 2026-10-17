<?php
/**
 * Load More / Infinite Scroll Class
 *
 * Enhances native pagination with a Load More button or infinite scroll.
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load More
 *
 * Progressively enhances the query-pagination block on shop archives with
 * a Load More button or IntersectionObserver-based infinite scroll. The
 * native pagination links stay in the server HTML (crawlers, no-JS); CSS
 * shows them only without scripting.
 *
 * Continuations fetch the next native page (`nextUrl`, the "next" link) and
 * append its cards, so appended cards are always the archive's own render.
 *
 * @since 1.51.0
 *
 * @phpstan-type Seed array{mode: string, perPage: int, currentPage: int, totalProducts: int, loadedCount: int, nextUrl: string, allLoaded: bool}
 */
class Load_More {

	/**
	 * Request-scoped pagination state shared by global state and local context.
	 *
	 * @var Seed|null
	 */
	private ?array $runtime_seed = null;

	/**
	 * `found_posts` of each Product Collection query this request ran, by
	 * queryId: an explicit collection's total without a second count query.
	 *
	 * @var array<int, int>
	 */
	private array $collection_totals = array();

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block_core/query-pagination', array( $this, 'replace_pagination' ), 20, 3 );
		add_filter( 'query_loop_block_query_vars', array( $this, 'tag_collection_query' ), 30, 2 );
		add_filter( 'found_posts', array( $this, 'record_collection_total' ), 10, 2 );
		add_action( 'wp_footer', array( $this, 'output_interactivity_state' ) );
	}

	/**
	 * Enqueue CSS and register JS module.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->request_needs_assets() ) {
			return;
		}

		$this->ensure_assets();
	}

	/**
	 * Enqueue the Load More assets once the feature is known to render.
	 *
	 * Kept separate from request detection so a dynamically rendered archive
	 * template can recover even when it was not detectable during
	 * `wp_enqueue_scripts`.
	 *
	 * @return void
	 */
	private function ensure_assets(): void {
		Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-load-more',
			'build/styles/woocommerce/load-more'
		);

		Asset_Loader::enqueue_interactivity_module(
			'@aggressive-apparel/load-more',
			'build/interactivity/load-more',
			array( '@aggressive-apparel/helpers' )
		);
	}

	/**
	 * Enhance the pagination block with the Load More UI on shop archives.
	 *
	 * The native pagination markup is kept (crawlable links for search engines
	 * and shoppers without JavaScript) and hidden once the store hydrates.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Block attributes.
	 * @param \WP_Block|null       $instance      Block instance (query context).
	 * @return string Modified block HTML.
	 */
	public function replace_pagination( string $block_content, array $block, ?\WP_Block $instance = null ): string {
		if ( 'core/query-pagination' !== ( $block['blockName'] ?? '' ) ) {
			return $block_content;
		}

		if ( ! $this->is_shop_page() ) {
			return $block_content;
		}

		$this->ensure_assets();
		$this->runtime_seed = $this->build_seed( $block_content, $instance );

		$mode            = Feature_Settings::get_load_more_mode();
		$seed            = $this->runtime_seed;
		$context         = wp_json_encode( $seed );
		$all_loaded      = (bool) $seed['allLoaded'];
		$total_products  = (int) $seed['totalProducts'];
		$load_more_html  = '<div class="aa-load-more aggressive-apparel-stack aggressive-apparel-stack--lg aggressive-apparel-stack--center"';
		$load_more_html .= ' data-wp-interactive="aggressive-apparel/load-more"';
		$load_more_html .= ' data-wp-context=\'' . esc_attr( (string) $context ) . '\'';
		$load_more_html .= ' data-wp-run="callbacks.useServerSeed">';

		// Seed the store's derived getters for SSR directive processing so the
		// server markup matches the hydrated state. Unseeded, they evaluate
		// falsy: the status line renders empty and the infinite-scroll button
		// visible, and both change on hydration (layout shift on the grid).
		if ( function_exists( 'wp_interactivity_state' ) ) {
			wp_interactivity_state(
				'aggressive-apparel/load-more',
				array(
					'hideButton'         => 'infinite_scroll' === $mode || $all_loaded,
					'hideSentinel'       => 'infinite_scroll' !== $mode || $all_loaded,
					'showSentinelLoader' => false,
					'statusText'         => $total_products > 0
						? sprintf(
							/* translators: 1: number loaded so far, 2: total products. */
							__( 'Showing %1$d of %2$d products', 'aggressive-apparel' ),
							(int) $seed['loadedCount'],
							$total_products
						)
						: '',
				)
			);
		}

		// Native pagination: the crawlable path to every catalog page. CSS shows
		// it only when scripting is unavailable (`@media (scripting: none)`),
		// where it also hides the controls that need the store, so neither
		// layout flashes before hydration.
		if ( '' !== trim( $block_content ) ) {
			$load_more_html .= '<div class="aa-load-more__pagination">'
				. $block_content
				. '</div>';
		}

		// Status text.
		$load_more_html .= '<div class="aa-load-more__status">';
		$load_more_html .= '<span class="aa-load-more__count" data-wp-text="state.statusText"></span>';
		$load_more_html .= '</div>';

		// Load More button (hidden in infinite scroll mode or when all loaded).
		// Label and spinner share one grid cell; .is-loading toggles visibility so
		// the control keeps its size and the spinner stays centered.
		$load_more_html .= sprintf(
			'<button class="aa-load-more__btn aggressive-apparel-button aggressive-apparel-button--outline wp-element-button" data-wp-on--click="actions.loadMore"'
			. ' data-wp-bind--hidden="state.hideButton"'
			// aria-disabled (not the disabled attribute) so the button keeps
			// keyboard focus while loading; the loadMore action already no-ops
			// when isLoading, preventing duplicate requests.
			. ' data-wp-bind--aria-disabled="state.isLoading"'
			. ' data-wp-class--is-loading="state.isLoading"'
			. ' data-wp-bind--aria-busy="state.isLoading">'
			. '<span class="aa-load-more__btn-label">%1$s</span>'
			. '<span class="aa-load-more__btn-spinner" aria-hidden="true"></span>'
			. '</button>',
			esc_html( Feature_Settings::get_load_more_button_text() )
		);

		// Loading spinner (purely visual; aria-hidden). The loading status is
		// announced to assistive tech via the live region below. In button mode
		// the button hides while a page loads and this replaces it; in
		// infinite-scroll mode it's the only on-screen "more loading" cue.
		$load_more_html .= '<div class="aa-load-more__loading" data-wp-bind--hidden="!state.showSentinelLoader" aria-hidden="true">'
			. '<span class="aa-load-more__spinner"></span>'
			. '</div>';

		// Infinite scroll sentinel.
		if ( 'infinite_scroll' === $mode ) {
			$load_more_html .= '<div class="aa-load-more__sentinel" data-wp-bind--hidden="state.hideSentinel" aria-hidden="true"></div>';
		}

		// Screen reader announcements.
		$load_more_html .= '<div class="aa-load-more__announcer screen-reader-text" role="status" aria-live="polite" data-wp-text="state.announcement"></div>';

		$load_more_html .= '</div>';

		return $load_more_html;
	}

	/**
	 * Output interactivity state in the footer.
	 *
	 * @return void
	 */
	public function output_interactivity_state(): void {
		if ( ! $this->is_shop_page() ) {
			return;
		}

		if ( ! function_exists( 'wp_interactivity_state' ) ) {
			return;
		}

		wp_interactivity_state(
			'aggressive-apparel/load-more',
			array_merge(
				$this->get_runtime_seed(),
				array(
					'isLoading'    => false,
					'announcement' => '',
					'loadingText'  => __( 'Loading more products…', 'aggressive-apparel' ),
					'errorText'    => __( 'Products could not be loaded. Try again.', 'aggressive-apparel' ),
					/* translators: 1: number loaded so far, 2: total products. */
					'statusFormat' => __( 'Showing %1$d of %2$d products', 'aggressive-apparel' ),
					/* translators: %d: number of products just loaded. */
					'loadedFormat' => __( '%d more products loaded.', 'aggressive-apparel' ),
				)
			)
		);
	}

	/**
	 * Pagination seed for this request (main query when no block rendered).
	 *
	 * @return Seed
	 */
	private function get_runtime_seed(): array {
		return $this->runtime_seed ??= $this->build_seed( '', null );
	}

	/**
	 * Build the seed for the rendered collection page.
	 *
	 * The same values are emitted as local Interactivity API context so a
	 * router navigation (filters) replaces stale module-level state.
	 *
	 * @param string         $pagination_html Rendered native pagination.
	 * @param \WP_Block|null $instance        Pagination block (query context).
	 * @return Seed
	 */
	private function build_seed( string $pagination_html, ?\WP_Block $instance ): array {
		global $wp_query;

		$page    = $this->collection_page( $instance );
		$context = null !== $instance ? $instance->context : array();
		$query   = isset( $context['query'] ) && is_array( $context['query'] ) ? $context['query'] : array();

		if ( null !== $instance && empty( $query['inherit'] ) ) {
			$per_page = max( 1, (int) ( $query['perPage'] ?? 12 ) );
			$total    = $this->explicit_total( $instance, $page, $query );
			$on_page  = max( 0, min( $per_page, $total - $per_page * ( $page - 1 ) ) );
		} else {
			$main     = $wp_query instanceof \WP_Query ? $wp_query : null;
			$per_page = max( 1, null !== $main ? (int) $main->get( 'posts_per_page' ) : 0 );
			$total    = null !== $main ? (int) $main->found_posts : 0;
			$on_page  = null !== $main ? (int) $main->post_count : 0;
		}

		$next_url = self::next_url( $pagination_html );

		return array(
			'mode'          => Feature_Settings::get_load_more_mode(),
			'perPage'       => $per_page,
			'currentPage'   => $page,
			'totalProducts' => $total,
			'loadedCount'   => $on_page,
			'nextUrl'       => $next_url,
			'allLoaded'     => '' === $next_url,
		);
	}

	/**
	 * Tag a Product Collection's query with its queryId, so its total can be
	 * recorded when it runs.
	 *
	 * @param array<string, mixed> $query_vars Query vars built from the block.
	 * @param \WP_Block            $block      Block providing the query context.
	 * @return array<string, mixed>
	 */
	public function tag_collection_query( $query_vars, $block ): array {
		$query_vars = is_array( $query_vars ) ? $query_vars : array();
		if ( $block instanceof \WP_Block && isset( $block->context['queryId'] ) && ! empty( $block->context['query']['isProductCollectionBlock'] ) ) {
			$query_vars['aa_collection_query_id'] = (int) $block->context['queryId'];
		}

		return $query_vars;
	}

	/**
	 * Record a tagged collection query's total.
	 *
	 * @param int|string $found_posts Total rows found.
	 * @param \WP_Query  $query       Query.
	 * @return int|string
	 */
	public function record_collection_total( $found_posts, $query ) {
		if ( $query instanceof \WP_Query && is_int( $query->get( 'aa_collection_query_id', null ) ) ) {
			$this->collection_totals[ (int) $query->get( 'aa_collection_query_id' ) ] = (int) $found_posts;
		}

		return $found_posts;
	}

	/**
	 * Total products in an explicit (non-inherited) collection: the total its
	 * own query found (filters included), counted again only if it was not
	 * recorded.
	 *
	 * @param \WP_Block            $instance Pagination block (query context).
	 * @param int                  $page     Current page.
	 * @param array<string, mixed> $query    Block query attributes.
	 */
	private function explicit_total( \WP_Block $instance, int $page, array $query ): int {
		$query_id = isset( $instance->context['queryId'] ) ? (int) $instance->context['queryId'] : 0;
		$found    = $this->collection_totals[ $query_id ] ?? self::count_collection( $instance, $page );

		// found_posts ignores `offset`; the block's own offset hides that many.
		$total = max( 0, $found - max( 0, (int) ( $query['offset'] ?? 0 ) ) );
		$pages = max( 0, (int) ( $query['pages'] ?? 0 ) );

		return $pages > 0 ? min( $total, $pages * max( 1, (int) ( $query['perPage'] ?? 12 ) ) ) : $total;
	}

	/**
	 * Count an explicit collection's products with the vars it renders with.
	 *
	 * @param \WP_Block $instance Pagination block (query context).
	 * @param int       $page     Current page.
	 */
	private static function count_collection( \WP_Block $instance, int $page ): int {
		$vars                   = build_query_vars_from_query_block( $instance, $page );
		$vars['fields']         = 'ids';
		$vars['posts_per_page'] = 1;
		$vars['no_found_rows']  = false;

		return (int) ( new \WP_Query( $vars ) )->found_posts;
	}

	/**
	 * URL of the native "next page" link, or '' on the last page.
	 *
	 * @param string $pagination_html Rendered native pagination.
	 */
	private static function next_url( string $pagination_html ): string {
		if ( '' === $pagination_html ) {
			return '';
		}

		$tags = new \WP_HTML_Tag_Processor( $pagination_html );
		if ( $tags->next_tag(
			array(
				'tag_name'   => 'A',
				'class_name' => 'wp-block-query-pagination-next',
			)
		) ) {
			$href = $tags->get_attribute( 'href' );
			return is_string( $href ) ? esc_url_raw( $href ) : '';
		}

		return '';
	}

	/**
	 * Resolve the page of the Product Collection that owns this pagination.
	 *
	 * Mirrors WooCommerce/core: inherited collections page through the main
	 * query (`/page/N/`); explicit ones read `?query-{queryId}-page=N`.
	 *
	 * @param \WP_Block|null $instance Pagination block instance.
	 * @return int 1-based page number.
	 */
	private function collection_page( ?\WP_Block $instance ): int {
		$context = null !== $instance ? $instance->context : array();
		$query   = isset( $context['query'] ) && is_array( $context['query'] ) ? $context['query'] : array();

		if ( empty( $query['inherit'] ) && isset( $context['queryId'] ) ) {
			$key = 'query-' . absint( $context['queryId'] ) . '-page';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public catalog page number.
			return isset( $_GET[ $key ] ) ? max( 1, absint( wp_unslash( $_GET[ $key ] ) ) ) : 1;
		}

		return max( 1, (int) get_query_var( 'paged' ) );
	}

	/**
	 * Check if the current page is a shop archive.
	 *
	 * @return bool
	 */
	private function is_shop_page(): bool {
		// Shop, plus any product taxonomy archive — category, tag, brand and
		// product-attribute (pa_*) archives all render a product grid.
		return Product_Context::is_product_archive()
			|| ( function_exists( 'is_product_taxonomy' ) && \is_product_taxonomy() );
	}

	/**
	 * Whether this request is expected to render the Load More control.
	 *
	 * @return bool
	 */
	private function request_needs_assets(): bool {
		$needed = ! is_admin() && $this->is_shop_page();

		/**
		 * Filters whether Load More frontend assets should load.
		 *
		 * Useful for custom product archive routes that intentionally reuse the
		 * theme's archive pagination replacement.
		 *
		 * @since 1.80.0
		 *
		 * @param bool $needed Whether the current request needs the assets.
		 */
		return (bool) apply_filters( 'aggressive_apparel_load_more_needs_assets', $needed );
	}
}
