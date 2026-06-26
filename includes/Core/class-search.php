<?php
/**
 * Site Search
 *
 * Powers the full-screen search modal (aggressive-apparel/search block):
 *
 *  - Registers a public REST endpoint that searches products, posts and pages
 *    in a single request and returns grouped, type-aware results.
 *  - Registers / enqueues the shared Interactivity modules + the search store.
 *  - Renders the modal shell once in wp_footer (portaled out of the header so
 *    position: fixed escapes any sticky-header transform/stacking context).
 *
 * Works with or without WooCommerce — the Products group is simply omitted when
 * WooCommerce is inactive.
 *
 * @package Aggressive_Apparel
 */

declare( strict_types=1 );

namespace Aggressive_Apparel\Core;

use Aggressive_Apparel\Assets\Asset_Loader;

defined( 'ABSPATH' ) || exit;

/**
 * Site search controller + asset/markup wiring.
 */
class Search {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private const REST_NAMESPACE = 'aggressive-apparel/v1';

	/**
	 * REST route.
	 *
	 * @var string
	 */
	private const REST_ROUTE = '/search';

	/**
	 * Interactivity store namespace (shared by trigger + modal).
	 *
	 * @var string
	 */
	public const STORE = 'aggressive-apparel/search';

	/**
	 * Results returned per content type.
	 *
	 * @var int
	 */
	private const PER_TYPE = 8;

	/**
	 * Maximum anonymous requests allowed per rate-limit window.
	 *
	 * @var int
	 */
	private const RATE_LIMIT_MAX = 60;

	/**
	 * Rate-limit window in seconds.
	 *
	 * @var int
	 */
	private const RATE_LIMIT_WINDOW = 60;

	/**
	 * Whether the modal shell has already been emitted this request.
	 *
	 * @var bool
	 */
	private bool $shell_rendered = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ), 5 );
		add_action( 'wp_footer', array( $this, 'render_modal_shell' ) );
	}

	/**
	 * Register the search REST route.
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'query' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'scope' => array(
						'type'              => 'string',
						'default'           => 'all',
						'enum'              => array( 'all', 'product', 'post', 'page' ),
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Handle a search request.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$query = trim( (string) $request->get_param( 'query' ) );
		$scope = (string) $request->get_param( 'scope' );

		if ( mb_strlen( $query ) < 2 ) {
			return rest_ensure_response(
				array(
					'query'  => $query,
					'groups' => array(),
					'total'  => 0,
				)
			);
		}

		// Throttle anonymous traffic so the (unindexed) LIKE searches can't be
		// flooded with cache-missing queries. Logged-in users are exempt.
		if ( $this->is_rate_limited() ) {
			$response = new \WP_REST_Response(
				array( 'message' => __( 'Too many requests. Please slow down.', 'aggressive-apparel' ) ),
				429
			);
			$response->header( 'Retry-After', (string) self::RATE_LIMIT_WINDOW );
			return $response;
		}

		// Whether Products may be exposed to this requester (respects the store's
		// coming-soon mode). Folded into the cache key so an admin-primed response
		// containing products is never served to the public.
		$show_products = $this->products_are_public();

		// Short-lived cache: identical queries within the window reuse results.
		$cache_key = 'aa_search_' . md5( $scope . '|' . ( $show_products ? '1' : '0' ) . '|' . mb_strtolower( $query ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return rest_ensure_response( $cached );
		}

		$groups = array();

		if ( ( 'all' === $scope || 'product' === $scope ) && $show_products ) {
			$products = $this->search_products( $query );
			if ( ! empty( $products ) ) {
				$groups[] = array(
					'type'  => 'product',
					'label' => __( 'Products', 'aggressive-apparel' ),
					'items' => $products,
				);
			}
		}

		if ( 'all' === $scope || 'post' === $scope ) {
			$posts = $this->search_posts( $query );
			if ( ! empty( $posts ) ) {
				$groups[] = array(
					'type'  => 'post',
					'label' => __( 'Articles', 'aggressive-apparel' ),
					'items' => $posts,
				);
			}
		}

		if ( 'all' === $scope || 'page' === $scope ) {
			$pages = $this->search_pages( $query );
			if ( ! empty( $pages ) ) {
				$groups[] = array(
					'type'  => 'page',
					'label' => __( 'Pages', 'aggressive-apparel' ),
					'items' => $pages,
				);
			}
		}

		$total    = array_sum( array_map( static fn( array $g ): int => count( $g['items'] ), $groups ) );
		$response = array(
			'query'   => $query,
			'scope'   => $scope,
			'groups'  => $groups,
			'total'   => $total,
			'viewAll' => add_query_arg( 's', rawurlencode( $query ), home_url( '/' ) ),
		);

		set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );

		return rest_ensure_response( $response );
	}

	/**
	 * Search WooCommerce products.
	 *
	 * @param string $query Search term.
	 * @return array<int, array<string, mixed>>
	 */
	private function search_products( string $query ): array {
		$ids = $this->query_ids( 'product', $query );
		if ( empty( $ids ) ) {
			return array();
		}

		$items = array();
		foreach ( $ids as $id ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}

			$thumb_id = $product->get_image_id();
			$thumb    = $thumb_id
				? wp_get_attachment_image_url( (int) $thumb_id, 'aggressive-apparel-product-thumbnail' )
				: '';

			$items[] = array(
				'id'        => $product->get_id(),
				'title'     => $this->clean_text( $product->get_name() ),
				'url'       => get_permalink( $product->get_id() ),
				'thumbnail' => $thumb ? $thumb : '',
				'price'     => $this->format_product_price( $product ),
				'onSale'    => $product->is_on_sale(),
			);
		}

		return $items;
	}

	/**
	 * Build a concise, plain-text price for a product.
	 *
	 * Computed from raw values via wc_price() rather than get_price_html() so it
	 * sidesteps the Smart Price Display filter (which appends savings copy) and
	 * keeps the search result tidy. Variable products show a "From {min}" range.
	 * HTML entities (e.g. the currency &#36;) are decoded so the value renders as
	 * plain text on the client.
	 *
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	private function format_product_price( \WC_Product $product ): string {
		if ( $product instanceof \WC_Product_Variable ) {
			$min  = (float) $product->get_variation_price( 'min', true );
			$max  = (float) $product->get_variation_price( 'max', true );
			$html = $min !== $max
				/* translators: %s: lowest price in a variable product's range. */
				? sprintf( __( 'From %s', 'aggressive-apparel' ), wc_price( $min ) )
				: wc_price( $min );
		} else {
			$html = wc_price( (float) wc_get_price_to_display( $product ) );
		}

		return $this->clean_text( $html );
	}

	/**
	 * Strip tags and decode HTML entities to plain text.
	 *
	 * Titles run through wptexturize/the_title, so an ampersand comes back as
	 * "&amp;". Decoding here means the client escapes it exactly once before
	 * injecting it as text — otherwise it double-encodes to "&amp;amp;" and the
	 * raw entity shows on screen.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private function clean_text( string $text ): string {
		return html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Search blog posts.
	 *
	 * @param string $query Search term.
	 * @return array<int, array<string, mixed>>
	 */
	private function search_posts( string $query ): array {
		$ids = $this->query_ids( 'post', $query );

		$items = array();
		foreach ( $ids as $id ) {
			$thumb = get_the_post_thumbnail_url( $id, 'aggressive-apparel-blog-thumbnail' );

			$items[] = array(
				'id'        => $id,
				'title'     => $this->clean_text( get_the_title( $id ) ),
				'url'       => get_permalink( $id ),
				'thumbnail' => $thumb ? $thumb : '',
				'excerpt'   => $this->clean_text( $this->get_excerpt( $id ) ),
				'date'      => get_the_date( '', $id ),
			);
		}

		return $items;
	}

	/**
	 * Search pages.
	 *
	 * @param string $query Search term.
	 * @return array<int, array<string, mixed>>
	 */
	private function search_pages( string $query ): array {
		$ids = $this->query_ids( 'page', $query );

		$items = array();
		foreach ( $ids as $id ) {
			$items[] = array(
				'id'    => $id,
				'title' => $this->clean_text( get_the_title( $id ) ),
				'url'   => get_permalink( $id ),
			);
		}

		return $items;
	}

	/**
	 * Run a search WP_Query for a single post type and return the IDs.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $query     Search term.
	 * @return array<int, int>
	 */
	private function query_ids( string $post_type, string $query ): array {
		$q = new \WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'publish',
				's'                      => $query,
				'posts_per_page'         => self::PER_TYPE,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			)
		);

		// fields=ids returns int[] at runtime, but WP_Query::$posts is typed
		// int[]|WP_Post[], so narrow each element explicitly for static analysis.
		$ids = array_map(
			static fn ( $post ): int => $post instanceof \WP_Post ? $post->ID : (int) $post,
			$q->posts
		);

		// Prime post + meta caches once so the per-item get_the_title() /
		// get_permalink() / thumbnail lookups below don't each fire their own
		// query (the fields=ids query above fetches no post objects).
		if ( ! empty( $ids ) ) {
			_prime_post_caches( $ids, false, true );
		}

		return $ids;
	}

	/**
	 * Build a short, plain-text excerpt for a post.
	 *
	 * @param int $id Post ID.
	 * @return string
	 */
	private function get_excerpt( int $id ): string {
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}

		$text = has_excerpt( $id )
			? get_the_excerpt( $id )
			: wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ), 24, '…' );

		return (string) $text;
	}

	/**
	 * Register shared Interactivity modules + the search store, then enqueue
	 * the store and the block style. Runs on every front-end page (the trigger
	 * lives in the global header).
	 *
	 * @return void
	 */
	public function register_assets(): void {
		if ( ! $this->should_load() || ! function_exists( 'wp_register_script_module' ) ) {
			return;
		}

		// Register the shared modules the store depends on. wp_register_script_module
		// is a no-op if a module id is already registered (e.g. by the WooCommerce
		// Enhancements coordinator), so this is safe to call unconditionally and
		// guarantees the import map is complete even when WooCommerce is inactive.
		$this->register_shared_modules();

		Asset_Loader::enqueue_interactivity_module(
			self::STORE . '-store',
			'build/interactivity/search-store',
			array(
				'@aggressive-apparel/scroll-lock',
				'@aggressive-apparel/helpers',
				'@aggressive-apparel/use-overlay',
			)
		);

		// Seed REST + i18n state for the store before hydration.
		if ( function_exists( 'wp_interactivity_state' ) ) {
			wp_interactivity_state(
				self::STORE,
				array(
					'restUrl'      => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ),
					'isOpen'       => false,
					'query'        => '',
					'scope'        => 'all',
					'isLoading'    => false,
					'hasSearched'  => false,
					'groups'       => array(),
					'total'        => 0,
					'viewAllUrl'   => '',
					'placeholders' => $this->get_placeholder_phrases(),
					'i18n'         => array(
						'noResults'      => __( 'No results found.', 'aggressive-apparel' ),
						'resultSingular' => __( '1 result found.', 'aggressive-apparel' ),
						/* translators: %d: number of search results. */
						'resultPlural'   => __( '%d results found.', 'aggressive-apparel' ),
					),
				)
			);
		}

		// The trigger block's style.css carries both the trigger and modal styles.
		wp_enqueue_style( 'aggressive-apparel-search-style' );
	}

	/**
	 * Register the shared overlay/scroll-lock/helpers modules (idempotent).
	 *
	 * @return void
	 */
	private function register_shared_modules(): void {
		$modules = array(
			'@aggressive-apparel/scroll-lock' => array( 'build/interactivity/scroll-lock.js', array() ),
			'@aggressive-apparel/helpers'     => array( 'build/interactivity/helpers.js', array() ),
			'@aggressive-apparel/use-overlay' => array(
				'build/interactivity/use-overlay.js',
				array( '@aggressive-apparel/scroll-lock', '@aggressive-apparel/helpers' ),
			),
		);

		foreach ( $modules as $id => $config ) {
			list( $path, $deps ) = $config;
			if ( ! file_exists( AGGRESSIVE_APPAREL_DIR . '/' . $path ) ) {
				continue;
			}
			wp_register_script_module(
				$id,
				AGGRESSIVE_APPAREL_URI . '/' . $path,
				$deps,
				AGGRESSIVE_APPAREL_VERSION
			);
		}
	}

	/**
	 * Render the search modal shell once in the footer (portal pattern).
	 *
	 * @return void
	 */
	public function render_modal_shell(): void {
		if ( $this->shell_rendered || ! $this->should_load() ) {
			return;
		}
		$this->shell_rendered = true;

		$scopes = array( 'all' => __( 'All', 'aggressive-apparel' ) );
		// Only offer the Products tab when products are actually returnable to
		// this requester (WooCommerce active + not hidden by coming-soon mode).
		if ( $this->products_are_public() ) {
			$scopes['product'] = __( 'Products', 'aggressive-apparel' );
		}
		$scopes['post'] = __( 'Articles', 'aggressive-apparel' );
		$scopes['page'] = __( 'Pages', 'aggressive-apparel' );

		$icon_search = Icons::get(
			'search',
			array(
				'width'       => 22,
				'height'      => 22,
				'aria-hidden' => 'true',
			)
		);
		$icon_clear  = Icons::get(
			'close',
			array(
				'width'       => 18,
				'height'      => 18,
				'aria-hidden' => 'true',
			)
		);
		$icon_close  = Icons::get(
			'close',
			array(
				'width'       => 20,
				'height'      => 20,
				'aria-hidden' => 'true',
			)
		);
		$icon_arrow  = Icons::get(
			'arrow-right',
			array(
				'width'       => 16,
				'height'      => 16,
				'aria-hidden' => 'true',
			)
		);

		$tabs_html = '';
		foreach ( $scopes as $value => $label ) {
			$tabs_html .= sprintf(
				'<button type="button" role="tab" class="aa-search__tab" data-scope="%1$s" data-wp-on--click="actions.setScope" data-wp-class--is-active="callbacks.isActiveScope" data-wp-bind--aria-selected="callbacks.isActiveScope"><span class="aa-search__tab-check" aria-hidden="true"><svg viewBox="0 0 12 12" fill="none"><polyline points="2.5 6.5 5 9 9.5 3.5"/></svg></span><span class="aa-search__tab-label">%2$s</span></button>',
				esc_attr( $value ),
				esc_html( $label )
			);
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- All interpolations escaped inline below.
		printf(
			'<div
				class="aa-search"
				id="aa-search-modal"
				hidden
				data-wp-interactive="%1$s"
				data-wp-class--is-open="state.isOpen"
				data-wp-on-document--keydown="actions.handleKeydown"
			>
				<div class="aa-search__overlay" data-wp-on--click="actions.close" aria-hidden="true"></div>
				<div class="aa-search__dialog" role="dialog" aria-modal="true" aria-label="%2$s">
					<div class="aa-search__bar">
						<div class="aa-search__field" data-wp-class--is-filled="state.hasQuery">
							<span class="aa-search__field-icon" aria-hidden="true">%3$s</span>
							<input
								type="search"
								class="aa-search__input"
								placeholder="%4$s"
								aria-label="%4$s"
								autocomplete="off"
								spellcheck="false"
								enterkeyhint="search"
								role="combobox"
								aria-controls="aa-search-results"
								aria-autocomplete="list"
								data-wp-bind--aria-expanded="state.hasResults"
								data-wp-on--input="actions.handleInput"
							/>
							<button type="button" class="aa-search__clear" aria-label="%5$s" hidden data-wp-bind--hidden="!state.hasQuery" data-wp-on--click="actions.clear">%6$s</button>
						</div>
						<button type="button" class="aa-search__close" aria-label="%7$s" data-wp-on--click="actions.close">%8$s</button>
					</div>
					<div class="aa-search__tabs" role="tablist" aria-label="%9$s">%10$s</div>
					<div class="aa-search__body">
						<p class="aa-search__hint" data-wp-bind--hidden="state.hideHint">%11$s</p>
						<p class="aa-search__loading" hidden data-wp-bind--hidden="!state.isLoading" aria-hidden="true">%12$s</p>
						<p class="aa-search__empty" hidden data-wp-bind--hidden="!state.showEmpty" role="status">%13$s</p>
						<div id="aa-search-results" class="aa-search__results" role="listbox" aria-label="%14$s" data-wp-watch="callbacks.renderResults"></div>
					</div>
					<div class="aa-search__footer">
						<a class="aa-search__view-all" hidden data-wp-bind--hidden="!state.hasResults" data-wp-bind--href="state.viewAllUrl">%15$s %16$s</a>
						<div class="aa-search__hints" aria-hidden="true">
							<span class="aa-search__hint-item"><kbd>&uarr;</kbd><kbd>&darr;</kbd> %17$s</span>
							<span class="aa-search__hint-item"><kbd>&crarr;</kbd> %18$s</span>
							<span class="aa-search__hint-item"><kbd>esc</kbd> %19$s</span>
						</div>
					</div>
				</div>
				<div class="screen-reader-text" aria-live="polite" data-wp-text="state.announcement"></div>
			</div>',
			esc_attr( self::STORE ),
			esc_attr__( 'Search', 'aggressive-apparel' ),
			$icon_search,
			esc_attr__( 'Search', 'aggressive-apparel' ),
			esc_attr__( 'Clear search', 'aggressive-apparel' ),
			$icon_clear,
			esc_attr__( 'Close search', 'aggressive-apparel' ),
			$icon_close,
			esc_attr__( 'Filter results by type', 'aggressive-apparel' ),
			$tabs_html,
			esc_html__( 'Start typing to search products, articles and pages.', 'aggressive-apparel' ),
			esc_html__( 'Searching…', 'aggressive-apparel' ),
			esc_html__( 'No results found.', 'aggressive-apparel' ),
			esc_attr__( 'Search results', 'aggressive-apparel' ),
			esc_html__( 'View all results', 'aggressive-apparel' ),
			$icon_arrow,
			esc_html__( 'navigate', 'aggressive-apparel' ),
			esc_html__( 'open', 'aggressive-apparel' ),
			esc_html__( 'close', 'aggressive-apparel' )
		);
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Whether the search assets + modal shell should load on this request.
	 *
	 * Front end only. The search trigger normally lives in the global header, so
	 * this loads site-wide by default; the filter lets a site disable it on
	 * specific requests (e.g. landing pages) where the block isn't used.
	 *
	 * @return bool
	 */
	private function should_load(): bool {
		if ( is_admin() ) {
			return false;
		}

		return (bool) apply_filters( 'aggressive_apparel_load_search_assets', true );
	}

	/**
	 * Animated placeholder phrases for the search field.
	 *
	 * Translatable and filterable. The Products phrase is dropped when products
	 * aren't returnable to this requester (coming-soon mode) so we don't tease
	 * hidden catalogue.
	 *
	 * @return array<int, string>
	 */
	private function get_placeholder_phrases(): array {
		$phrases = array();
		if ( $this->products_are_public() ) {
			$phrases[] = __( 'Search for Products…', 'aggressive-apparel' );
		}
		$phrases[] = __( 'Search for Articles…', 'aggressive-apparel' );
		$phrases[] = __( 'Find Pages…', 'aggressive-apparel' );

		/**
		 * Filter the animated search placeholder phrases.
		 *
		 * @param array<int, string> $phrases Placeholder phrases.
		 */
		return array_values( (array) apply_filters( 'aggressive_apparel_search_placeholders', $phrases ) );
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	private function woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Whether products may be returned to the current requester.
	 *
	 * Requires WooCommerce, and — when the store is in "coming soon" mode —
	 * the capability to manage the store. This keeps unreleased catalogue from
	 * leaking through the public search endpoint while the storefront is hidden.
	 *
	 * @return bool
	 */
	private function products_are_public(): bool {
		if ( ! $this->woocommerce_active() ) {
			return false;
		}

		if ( 'yes' === get_option( 'woocommerce_coming_soon' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the current (anonymous) request has exceeded the rate limit.
	 *
	 * Logged-in users are exempt. Uses a fixed-window counter keyed by a hashed
	 * client IP so the unindexed search queries can't be flooded.
	 *
	 * @return bool
	 */
	private function is_rate_limited(): bool {
		if ( is_user_logged_in() ) {
			return false;
		}

		$ip = Client_IP::get();
		if ( '' === $ip ) {
			return false;
		}

		$max    = max( 1, (int) apply_filters( 'aggressive_apparel_search_rate_limit_max', self::RATE_LIMIT_MAX ) );
		$window = max( 10, (int) apply_filters( 'aggressive_apparel_search_rate_limit_window', self::RATE_LIMIT_WINDOW ) );
		$key    = 'aa_search_rl_' . hash( 'sha256', $ip );
		$count  = (int) get_transient( $key );

		if ( $count >= $max ) {
			return true;
		}

		set_transient( $key, $count + 1, $window );

		return false;
	}
}
