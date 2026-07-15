<?php
/**
 * Load More Renderer
 *
 * REST endpoint for infinite scroll, "Load more", and product-filter inserts.
 *
 * The catalogue query is built here against WooCommerce's indexed lookup tables
 * (price/stock/sort) with disjunctive facet availability. Rendering, however, is
 * delegated to WooCommerce itself: the template's real `product-collection`
 * block is rendered with the computed page-N query installed as the global
 * query (which an inherited collection clones). Card markup is never rebuilt
 * manually. Any context-dependent block-support classes are returned with
 * deterministic style assets so the fragment remains self-contained even when
 * WordPress legitimately produces a different hash from the initial document.
 *
 * @package Aggressive_Apparel
 * @since 1.65.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Rate_Limiter;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load More Renderer
 *
 * @since 1.65.0
 */
class Load_More_Renderer {

	/** REST namespace / route. */
	private const NAMESPACE = 'aggressive-apparel/v1';
	private const ROUTE     = '/products/rendered';

	/** Transient prefix + TTL (5 minutes) for cached facet availability. */
	private const FACETS_CACHE_PREFIX   = 'aa_pf_facets_';
	private const FACETS_CACHE_TTL      = 300;
	private const FACETS_VERSION_OPTION = 'aa_pf_facets_version';
	private const RESPONSE_CACHE_GROUP  = 'aggressive-apparel-rendered-products';

	/**
	 * Parsed product-collection block keyed by template slug (per-request).
	 * Null means the template contains no product-collection block.
	 *
	 * @var array<string, ?array<string, mixed>>
	 */
	private array $template_cache = array();

	/**
	 * Whether facet transients were already cleared during this request.
	 *
	 * @var bool
	 */
	private bool $facets_cache_flushed = false;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );

		// Invalidate cached facet availability when the catalogue changes.
		add_action( 'woocommerce_update_product', array( $this, 'flush_facets_cache' ) );
		add_action( 'woocommerce_new_product', array( $this, 'flush_facets_cache' ) );
		add_action( 'woocommerce_update_product_variation', array( $this, 'flush_facets_cache' ) );
		add_action( 'woocommerce_new_product_variation', array( $this, 'flush_facets_cache' ) );
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'flush_facets_cache' ) );
		add_action( 'aggressive_apparel_sale_category_updated', array( $this, 'flush_facets_cache' ) );
		add_action( 'created_term', array( $this, 'flush_facets_cache' ) );
		add_action( 'edited_term', array( $this, 'flush_facets_cache' ) );
		add_action( 'delete_term', array( $this, 'flush_facets_cache' ) );
	}

	/**
	 * Invalidate cached facet availability in constant time.
	 *
	 * @return void
	 */
	public function flush_facets_cache(): void {
		if ( $this->facets_cache_flushed ) {
			return;
		}
		$this->facets_cache_flushed = true;

		// Versioning is constant-time even when imports have created many cached
		// filter signatures. Old transients simply age out after five minutes.
		$version = (int) get_option( self::FACETS_VERSION_OPTION, 1 );
		update_option( self::FACETS_VERSION_OPTION, $version + 1, false );
	}

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public function register_route(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'page'        => array(
						'default'           => 1,
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => 1000,
						'validate_callback' => static fn( $value ): bool => is_numeric( $value ) && (int) $value >= 1 && (int) $value <= 1000,
						'sanitize_callback' => 'absint',
					),
					'per_page'    => array(
						'default'           => 12,
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					),
					'orderby'     => array(
						'default'           => 'date',
						'type'              => 'string',
						'enum'              => array( 'menu_order', 'date', 'popularity', 'rating', 'price', 'price-desc', 'title-asc', 'title-desc' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'taxonomy'    => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'term'        => array(
						'default'           => '',
						'type'              => 'string',
						// Wrapped: the REST API calls sanitize callbacks as
						// ( $value, $request, $key ), and sanitize_title()'s 2nd
						// param is $fallback_title — passing $request there makes
						// it return the request object for an empty term.
						'sanitize_callback' => static fn( $value ): string => sanitize_title( (string) $value ),
					),
					'template'    => array(
						'default'           => 'archive-product',
						'type'              => 'string',
						'maxLength'         => 200,
						// The client forwards WordPress's resolved wp_template post-name.
						// sanitize_title prevents paths while retaining valid hierarchy slugs.
						'sanitize_callback' => static fn( $value ): string => sanitize_title( (string) $value ),
					),
					// Filter params (used by the product-filters UI). All optional.
					'category'    => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'attributes'  => array(
						'default' => array(),
						'type'    => 'object',
					),
					'min_price'   => array(
						'default'           => 0,
						'type'              => 'number',
						'sanitize_callback' => static fn( $value ): float => (float) $value,
					),
					'max_price'   => array(
						'default'           => 0,
						'type'              => 'number',
						'sanitize_callback' => static fn( $value ): float => (float) $value,
					),
					'stock'       => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'on_sale'     => array(
						'default' => false,
						'type'    => 'boolean',
					),
					'include'     => array(
						'default'           => '',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					// Faceted availability: which attribute terms still have
					// matching products given the current filters.
					'with_facets' => array(
						'default' => false,
						'type'    => 'boolean',
					),
					'facets_only' => array(
						'default' => false,
						'type'    => 'boolean',
					),
				),
			)
		);
	}

	/**
	 * Handle the REST request.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		// Don't expose unreleased catalogue to the public while the store is in
		// coming-soon mode (return an empty page rather than products).
		if ( ! Product_Context::products_are_public() ) {
			return new \WP_REST_Response(
				array(
					'html'           => '',
					'styles'         => array(),
					'total_products' => 0,
					'total_pages'    => 0,
				)
			);
		}

		$page          = max( 1, (int) $request->get_param( 'page' ) );
		$per_page      = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$orderby       = (string) $request->get_param( 'orderby' );
		$taxonomy      = (string) $request->get_param( 'taxonomy' );
		$term          = (string) $request->get_param( 'term' );
		$template_slug = (string) $request->get_param( 'template' );

		$filters = array(
			'category'   => (string) $request->get_param( 'category' ),
			'attributes' => (array) $request->get_param( 'attributes' ),
			'min_price'  => (float) $request->get_param( 'min_price' ),
			'max_price'  => (float) $request->get_param( 'max_price' ),
			'stock'      => (string) $request->get_param( 'stock' ),
			'on_sale'    => (bool) $request->get_param( 'on_sale' ),
			'include'    => (string) $request->get_param( 'include' ),
		);

		$with_facets = (bool) $request->get_param( 'with_facets' );

		// Facets-only request (filter UI updating which swatches/chips are
		// available). These fire once per selection, so they get their own, more
		// generous throttle and skip the template lookup + card rendering.
		if ( (bool) $request->get_param( 'facets_only' ) ) {
			if ( ! $this->within_rate_limit( 'pf_facets', 600 ) ) {
				return $this->rate_limited_response();
			}
			return new \WP_REST_Response(
				array( 'facets' => $this->compute_facets( $filters, $taxonomy, $term ) )
			);
		}

		// Throttle the heavier card-rendering path. Logged-in users are exempt;
		// the limit is generous so infinite-scroll + prefetch never trips it.
		if ( ! $this->within_rate_limit( 'load_more', 120 ) ) {
			return $this->rate_limited_response();
		}

		if ( '' === $template_slug ) {
			$template_slug = 'archive-product';
		}

		$collection_block = $this->get_template_collection_block( $template_slug );

		// A resolved template may not contain a product-collection block (a broad
		// archive fallback or malformed client input). Never render arbitrary
		// template content; fall back to the canonical product archive.
		if ( null === $collection_block && 'archive-product' !== $template_slug ) {
			$collection_block = $this->get_template_collection_block( 'archive-product' );
		}

		if ( null === $collection_block ) {
			return new \WP_REST_Response( array( 'error' => 'Template not found' ), 404 );
		}

		$query_args = $this->build_query_args( $page, $per_page, $orderby, $taxonomy, $term, $filters );
		$query_args = $this->apply_template_query_constraints( $query_args, $collection_block, $page, $per_page );

		/**
		 * Filters the final bounded query used for dynamic Product Collection pages.
		 *
		 * Custom collection handlers can reproduce their own registered collection
		 * constraints here without replacing the renderer or its security controls.
		 *
		 * @param array<string, mixed> $query_args      Validated WP_Query arguments.
		 * @param array<string, mixed> $collection_block Parsed Product Collection block.
		 * @param \WP_REST_Request     $request         Current REST request.
		 */
		$query_args = (array) apply_filters( 'aggressive_apparel_load_more_query_args', $query_args, $collection_block, $request );
		$cache_key  = $this->response_cache_key( $query_args, $collection_block, $with_facets );
		$has_lock   = false;
		if ( '' !== $cache_key ) {
			$cached = wp_cache_get( $cache_key, self::RESPONSE_CACHE_GROUP );
			if ( is_array( $cached ) ) {
				return new \WP_REST_Response( $cached );
			}

			$has_lock = wp_cache_add( $cache_key . ':lock', 1, self::RESPONSE_CACHE_GROUP, 300 );
			if ( ! $has_lock ) {
				$stale = wp_cache_get( $cache_key . ':stale', self::RESPONSE_CACHE_GROUP );
				if ( is_array( $stale ) ) {
					return new \WP_REST_Response( $stale );
				}
			}
		}
		$query = $this->run_product_query( $query_args );
		if ( ! $query->have_posts() && $page > 1 ) {
			// WP_Query skips FOUND_ROWS when an offset page has no rows, which
			// would incorrectly collapse a known collection to zero products.
			// Run a one-row count query only for this exceptional out-of-range
			// case; normal pages never pay for an additional query.
			$count_args                   = $query_args;
			$count_args['posts_per_page'] = 1;
			$count_args['paged']          = 1;
			$count_args['offset']         = 0;
			$count_args['fields']         = 'ids';
			$count_query                  = $this->run_product_query( $count_args );
			$query->found_posts           = $count_query->found_posts;
		}
		$totals = $this->query_totals( $query, $query_args, $per_page );

		if ( ! $query->have_posts() ) {
			$empty = array(
				'html'           => '',
				'styles'         => array(),
				'total_products' => $totals['products'],
				'total_pages'    => $totals['pages'],
			);
			if ( $with_facets ) {
				$empty['facets'] = $this->compute_facets( $filters, $taxonomy, $term );
			}
			$this->cache_response( $cache_key, $empty, $has_lock );
			return new \WP_REST_Response( $empty );
		}

		// Signal to render_block guards that we are rendering product cards so
		// each feature's is_listing_page() (quick view, wishlist, badges) applies.
		add_filter( 'aggressive_apparel_is_listing_page', '__return_true' );
		try {
			$rendered = $this->render_products_html( $collection_block, $query );
		} finally {
			remove_filter( 'aggressive_apparel_is_listing_page', '__return_true' );
		}

		$data = array(
			'html'           => $rendered->html(),
			'styles'         => $rendered->styles(),
			'total_products' => $totals['products'],
			'total_pages'    => $totals['pages'],
		);
		if ( $with_facets ) {
			$data['facets'] = $this->compute_facets( $filters, $taxonomy, $term );
		}
		$this->cache_response( $cache_key, $data, $has_lock );

		return new \WP_REST_Response( $data );
	}

	/**
	 * Build a privacy-safe cache key for public, anonymous rendered fragments.
	 *
	 * Persistent object caching is required by default so local development does
	 * not create database transients for every filter permutation. Sites with a
	 * custom cache backend can override eligibility or add pricing/locale context.
	 *
	 * @param array<string, mixed> $query_args       Final query arguments.
	 * @param array<string, mixed> $collection_block Parsed Product Collection block.
	 * @param bool                 $with_facets      Whether facets are included.
	 * @return string Cache key, or an empty string when caching is unsafe.
	 */
	private function response_cache_key( array $query_args, array $collection_block, bool $with_facets ): string {
		$enabled = ! is_user_logged_in()
			&& function_exists( 'wp_using_ext_object_cache' )
			&& wp_using_ext_object_cache()
			&& '' === (string) apply_filters( 'aggressive_apparel_dynamic_style_nonce', '' );

		/**
		 * Filters whether anonymous rendered Product Collection fragments are cached.
		 *
		 * @param bool                 $enabled          Default eligibility.
		 * @param array<string, mixed> $query_args       Final query arguments.
		 * @param array<string, mixed> $collection_block Parsed collection block.
		 */
		$enabled = (bool) apply_filters( 'aggressive_apparel_rendered_products_cache_enabled', $enabled, $query_args, $collection_block );
		if ( ! $enabled ) {
			return '';
		}

		$customer = \WC()->customer;
		$context  = array(
			'locale'       => determine_locale(),
			'currency'     => function_exists( 'get_woocommerce_currency' ) ? \get_woocommerce_currency() : '',
			'country'      => $customer->get_billing_country(),
			'state'        => $customer->get_billing_state(),
			'prices_taxed' => function_exists( 'wc_prices_include_tax' ) ? \wc_prices_include_tax() : false,
		);

		/**
		 * Filters request context that can change anonymous product-card markup.
		 *
		 * Multi-currency, membership, or geo-pricing integrations should add their
		 * public pricing discriminator here, or disable fragment caching above.
		 *
		 * @param array<string, mixed> $context Pricing/locale cache context.
		 */
		$context = (array) apply_filters( 'aggressive_apparel_rendered_products_cache_context', $context );
		$version = (int) get_option( self::FACETS_VERSION_OPTION, 1 );
		$payload = array(
			'v'           => $version,
			'theme'       => AGGRESSIVE_APPAREL_VERSION,
			'wp'          => get_bloginfo( 'version' ),
			'wc'          => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'query'       => $query_args,
			'collection'  => $collection_block,
			'with_facets' => $with_facets,
			'context'     => $context,
		);

		return hash( 'sha256', (string) wp_json_encode( $payload ) );
	}

	/**
	 * Store a fresh and stale copy, then release the render lock.
	 *
	 * @param string               $cache_key Cache key (empty when disabled).
	 * @param array<string, mixed> $data      REST response data.
	 * @param bool                 $has_lock  Whether this request owns the lock.
	 * @return void
	 */
	private function cache_response( string $cache_key, array $data, bool $has_lock ): void {
		if ( '' === $cache_key ) {
			return;
		}

		wp_cache_set( $cache_key, $data, self::RESPONSE_CACHE_GROUP, 300 );
		wp_cache_set( $cache_key . ':stale', $data, self::RESPONSE_CACHE_GROUP, 3600 );
		if ( $has_lock ) {
			wp_cache_delete( $cache_key . ':lock', self::RESPONSE_CACHE_GROUP );
		}
	}

	/**
	 * Render the product cards for a page through WooCommerce's own pipeline.
	 *
	 * Rather than reconstructing each card, this renders the template's real
	 * `woocommerce/product-collection` block. Context-dependent block-support
	 * hashes may differ from the initial document, so the returned fragment owns
	 * deterministic style assets for the exact classes in its markup.
	 *
	 * The saved collection may use either inherited or explicit query settings.
	 * Dynamic requests must always render the already-computed endpoint query, so
	 * this scoped copy is forced to inherit the global query installed below.
	 * Only the product-template's `<li>` cards are returned; the client appends
	 * them into the existing grid `<ul>`.
	 *
	 * @param array<string, mixed> $collection_block Parsed product-collection block.
	 * @param \WP_Query            $query            Query with this page's products.
	 * @return Rendered_Product_Fragment
	 */
	private function render_products_html( array $collection_block, \WP_Query $query ): Rendered_Product_Fragment {
		global $wp_query;

		if ( ! isset( $collection_block['attrs'] ) || ! is_array( $collection_block['attrs'] ) ) {
			$collection_block['attrs'] = array();
		}
		if ( ! isset( $collection_block['attrs']['query'] ) || ! is_array( $collection_block['attrs']['query'] ) ) {
			$collection_block['attrs']['query'] = array();
		}
		$collection_block['attrs']['query']['inherit'] = true;

		$saved_query = $wp_query;
		$fingerprint = hash(
			'sha256',
			(string) wp_json_encode( array( get_stylesheet(), AGGRESSIVE_APPAREL_VERSION, get_bloginfo( 'version' ), $collection_block ) )
		);
		/**
		 * CSP nonce applied to dynamic style elements installed by the client.
		 * Sites enforcing a nonce-based style-src policy should return the same
		 * request nonce their security layer includes in the response header.
		 *
		 * @param string $nonce Current nonce (empty by default).
		 */
		$nonce     = (string) apply_filters( 'aggressive_apparel_dynamic_style_nonce', '' );
		$collector = new Block_Support_Style_Collector( $fingerprint, $nonce );
		$collector->start();

		// Install this page's query as the global so the inherited collection
		// clones it (WooCommerce's documented mechanism); always restored below.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Scoped swap, restored in finally.
		$wp_query = $query;

		try {
			$rendered = ( new \WP_Block( $collection_block ) )->render();
		} finally {
			$collector->stop();
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the real global query.
			$wp_query = $saved_query;
			wp_reset_postdata();
		}

		return new Rendered_Product_Fragment(
			$this->extract_template_items( $rendered ),
			$collector->assets()
		);
	}

	/**
	 * Return the inner `<li>` cards of the product-template `<ul>` from a rendered
	 * collection, matching nested `<ul>` depth so list markup inside a card is not
	 * mistaken for the closing tag. Pure string work — never DOM parsing — so SVG
	 * attribute casing (viewBox, etc.) in the cards is preserved.
	 *
	 * @param string $html Rendered product-collection HTML.
	 * @return string The `<li>` items, or '' if the template list is not found.
	 */
	private function extract_template_items( string $html ): string {
		if ( ! preg_match( '/<ul\b[^>]*\bwc-block-product-template\b[^>]*>/', $html, $match, PREG_OFFSET_CAPTURE ) ) {
			return '';
		}

		$inner_start = $match[0][1] + strlen( $match[0][0] );
		$offset      = $inner_start;
		$length      = strlen( $html );
		$depth       = 1;

		while ( $offset < $length && $depth > 0 ) {
			$open  = preg_match( '/<ul\b/', $html, $om, PREG_OFFSET_CAPTURE, $offset ) ? (int) $om[0][1] : -1;
			$close = strpos( $html, '</ul>', $offset );

			if ( false === $close ) {
				break;
			}

			if ( $open > -1 && $open < $close ) {
				++$depth;
				$offset = $open + 3;
			} else {
				--$depth;
				if ( 0 === $depth ) {
					return substr( $html, $inner_start, $close - $inner_start );
				}
				$offset = $close + 5;
			}
		}

		return '';
	}

	/**
	 * Whether the request is within the per-IP rate limit for a scope.
	 *
	 * The max/window are overridable via `aggressive_apparel_{scope}_rate_limit_max`
	 * and `..._rate_limit_window` filters.
	 *
	 * @param string $scope       Rate-limit bucket (e.g. 'load_more', 'pf_facets').
	 * @param int    $default_max Default requests allowed per window.
	 * @return bool
	 */
	private function within_rate_limit( string $scope, int $default_max ): bool {
		return Rate_Limiter::allow(
			$scope,
			(int) apply_filters( "aggressive_apparel_{$scope}_rate_limit_max", $default_max ),
			(int) apply_filters( "aggressive_apparel_{$scope}_rate_limit_window", MINUTE_IN_SECONDS )
		);
	}

	/**
	 * Standard 429 response for a throttled request.
	 *
	 * @return \WP_REST_Response
	 */
	private function rate_limited_response(): \WP_REST_Response {
		$response = new \WP_REST_Response( array( 'error' => 'rate_limited' ), 429 );
		$response->header( 'Retry-After', '60' );
		return $response;
	}

	/**
	 * Compute which attribute terms still have matching products.
	 *
	 * Disjunctive faceting: each attribute is evaluated with the *other* active
	 * filters applied but ignoring its own selection, so a shopper can keep
	 * switching values within a facet. The result is the set of term slugs that
	 * the filter UI should leave enabled (everything else is dimmed/disabled).
	 *
	 * @param array<string, mixed> $filters  Active filter params.
	 * @param string               $taxonomy Archive taxonomy (or '').
	 * @param string               $term     Archive term slug (or '').
	 * @return array<string, array<int, string>> Available slugs keyed by taxonomy.
	 */
	private function compute_facets( array $filters, string $taxonomy, string $term ): array {
		// Availability depends only on the catalogue, not the requester, so the
		// result is safely cacheable per filter signature (versioned on product
		// change — see flush_facets_cache()).
		$version   = (int) get_option( self::FACETS_VERSION_OPTION, 1 );
		$cache_key = self::FACETS_CACHE_PREFIX . $version . '_' . md5( (string) wp_json_encode( array( $filters, $taxonomy, $term ) ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		/**
		 * Attribute taxonomies the filter UI offers as facets.
		 *
		 * Mirror the client-side `ATTRIBUTE_FILTERS` config — add a taxonomy here
		 * (and a swatch/chip UI for it) to expose a new attribute filter.
		 *
		 * @param string[] $taxonomies Default facet taxonomies.
		 */
		$facet_taxonomies = (array) apply_filters(
			'aggressive_apparel_filter_facet_taxonomies',
			array( 'pa_color', 'pa_size', 'pa_fit' )
		);
		$allowed          = $this->allowed_taxonomies();
		$facets           = array();

		foreach ( $facet_taxonomies as $facet ) {
			// Omit (don't key) taxonomies we can't evaluate so the client leaves
			// those options untouched rather than disabling them all. An allowed
			// taxonomy with genuinely no matches is still keyed (to an empty array).
			if ( ! in_array( $facet, $allowed, true ) ) {
				continue;
			}

			// Drop this facet's own selection (disjunctive) and never restrict to
			// a custom-sort id slice.
			$facet_filters = $filters;
			if ( isset( $facet_filters['attributes'] ) && is_array( $facet_filters['attributes'] ) ) {
				unset( $facet_filters['attributes'][ $facet ] );
			}
			$facet_filters['include'] = '';

			$facets[ $facet ] = $this->query_facet_slugs( $facet, $facet_filters, $taxonomy, $term );
		}

		set_transient( $cache_key, $facets, self::FACETS_CACHE_TTL );

		return $facets;
	}

	/**
	 * Find available attribute terms entirely in SQL.
	 *
	 * WooCommerce's attribute and product lookup tables are indexed for this
	 * exact workload. Returning only distinct slugs avoids materializing every
	 * matching product ID in PHP, which is the difference between a bounded
	 * facet request and one whose memory use grows with the catalogue.
	 *
	 * @param string               $facet    Attribute taxonomy being measured.
	 * @param array<string, mixed> $filters  Other active filters.
	 * @param string               $taxonomy Current archive taxonomy.
	 * @param string               $term     Current archive term.
	 * @return string[] Available term slugs.
	 */
	private function query_facet_slugs( string $facet, array $filters, string $taxonomy, string $term ): array {
		global $wpdb;

		$attribute_lookup = $wpdb->prefix . 'wc_product_attributes_lookup';
		$product_lookup   = $wpdb->wc_product_meta_lookup;
		$filter_set       = new Catalog_Filter_Set( $filters );
		$constraints      = new Catalog_SQL_Constraints( 'p', 'aa_facet' );

		$allowed = $this->allowed_taxonomies();
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

		foreach ( $filter_set->attributes( $allowed ) as $attr_tax => $slugs ) {
			if ( $facet === $attr_tax || ! str_starts_with( $attr_tax, 'pa_' ) ) {
				continue;
			}
			$constraints->add_attribute( $attr_tax, $slugs, $attribute_lookup );
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

		// The constraint builder emits placeholders only; all values are supplied
		// here. This indexed lookup runs only on a versioned facet-cache miss.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Structural fragments come from validated Catalog_SQL_Constraints; every value remains a placeholder.
		$prepared = $wpdb->prepare( $sql, ...$params );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- compute_facets() wraps this indexed lookup in a versioned transient cache.
		$slugs = $wpdb->get_col( $prepared );

		return array_values( array_unique( array_map( 'strval', $slugs ) ) );
	}

	/**
	 * Resolve a template's `woocommerce/product-collection` block.
	 *
	 * Prefers a DB-customised template (Site Editor changes) over the theme file,
	 * so the load-more render uses exactly the collection the visitor is seeing.
	 *
	 * @param string $template_slug Template slug.
	 * @return ?array<string, mixed> The parsed collection block, or null if absent.
	 */
	private function get_template_collection_block( string $template_slug ): ?array {
		if ( array_key_exists( $template_slug, $this->template_cache ) ) {
			return $this->template_cache[ $template_slug ];
		}

		$content = $this->get_template_content( $template_slug );
		$block   = '' === $content
			? null
			: $this->find_block_by_name( parse_blocks( $content ), 'woocommerce/product-collection' );

		$this->template_cache[ $template_slug ] = $block;

		return $block;
	}

	/**
	 * Load raw template content for a slug.
	 *
	 * @param string $template_slug Template slug.
	 * @return string
	 */
	private function get_template_content( string $template_slug ): string {
		$templates = get_block_templates( array( 'slug__in' => array( $template_slug ) ), 'wp_template' );

		if ( ! empty( $templates ) ) {
			return (string) $templates[0]->content;
		}

		$file = AGGRESSIVE_APPAREL_DIR . '/templates/' . $template_slug . '.html';
		if ( ! file_exists( $file ) ) {
			return '';
		}

		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		if ( ! $wp_filesystem ) {
			return '';
		}

		$content = $wp_filesystem->get_contents( $file );
		return false !== $content ? (string) $content : '';
	}

	/**
	 * Recursively find the first block matching a block name.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks    Parsed blocks.
	 * @param string                                  $block_name Block name to match.
	 * @return ?array<string, mixed>
	 */
	private function find_block_by_name( array $blocks, string $block_name ): ?array {
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) === $block_name ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = $this->find_block_by_name( $block['innerBlocks'], $block_name );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Apply the saved Product Collection's explicit query constraints.
	 *
	 * Sort and page size remain request-controlled because the catalog sorting
	 * UI and the already-rendered grid are authoritative for those values. The
	 * collection still owns its result set: offsets, exclusions, hand-picked
	 * IDs, search, stock, sale, featured, taxonomy, and attribute constraints.
	 *
	 * @param array<string, mixed> $args             Base endpoint query arguments.
	 * @param array<string, mixed> $collection_block Parsed Product Collection block.
	 * @param int                  $page             Requested page.
	 * @param int                  $per_page         Requested page size.
	 * @return array<string, mixed>
	 */
	private function apply_template_query_constraints( array $args, array $collection_block, int $page, int $per_page ): array {
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
			// WP_Query does not support combining post__in and post__not_in, so
			// compose the exclusion into the bounded inclusion set first.
			$handpicked       = array_values( array_diff( $handpicked, $exclude ) );
			$current_in       = array_values( array_filter( array_map( 'absint', (array) ( $args['post__in'] ?? array() ) ) ) );
			$args['post__in'] = empty( $current_in ) ? $handpicked : array_values( array_intersect( $current_in, $handpicked ) );
			// WP_Query treats an empty post__in as "all posts". An impossible ID
			// preserves the correct empty intersection without broadening results.
			if ( empty( $args['post__in'] ) ) {
				$args['post__in'] = array( 0 );
			}
		}

		$search = sanitize_text_field( (string) ( $query['search'] ?? '' ) );
		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$base_offset       = min( 1000, max( 0, (int) ( $query['offset'] ?? 0 ) ) );
		$args['aa_offset'] = $base_offset;
		$args['offset']    = $base_offset + ( $per_page * ( $page - 1 ) );
		$args['paged']     = 1;

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
			// An explicitly empty selection means no eligible products, not all
			// products. The sentinel can never match a real WooCommerce status.
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
			$taxonomy = sanitize_key( (string) ( $constraint['taxonomy'] ?? '' ) );
			$terms    = $this->bounded_term_ids( $constraint['terms'] ?? array() );
			if ( ! in_array( $taxonomy, $allowed, true ) || empty( $terms ) ) {
				continue;
			}
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
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
			$taxonomy = sanitize_key( (string) ( $attribute['taxonomy'] ?? '' ) );
			$term_id  = absint( $attribute['termId'] ?? 0 );
			if ( ! in_array( $taxonomy, $allowed, true ) || 0 === $term_id ) {
				continue;
			}
			$attributes[ $taxonomy ][] = $term_id;
		}
		foreach ( $attributes as $taxonomy => $term_ids ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
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
	 * Normalize a bounded product-ID list from saved block attributes.
	 *
	 * @param mixed $ids Raw list.
	 * @return int[]
	 */
	private function bounded_product_ids( $ids ): array {
		return array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) ), 0, 100 );
	}

	/**
	 * Normalize a bounded taxonomy-term ID list.
	 *
	 * @param mixed $ids Raw list.
	 * @return int[]
	 */
	private function bounded_term_ids( $ids ): array {
		return array_slice( array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) ), 0, 1000 );
	}

	/**
	 * Calculate totals after a saved collection offset/page cap is applied.
	 *
	 * @param \WP_Query            $query    Executed query.
	 * @param array<string, mixed> $args     Final query arguments.
	 * @param int                  $per_page Page size.
	 * @return array{products:int,pages:int}
	 */
	private function query_totals( \WP_Query $query, array $args, int $per_page ): array {
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
	 * Run a catalogue query with WooCommerce's indexed product lookup table.
	 *
	 * @param array<string, mixed> $args WP_Query arguments.
	 * @return \WP_Query
	 */
	private function run_product_query( array $args ): \WP_Query {
		add_filter( 'posts_clauses', array( $this, 'apply_product_lookup_clauses' ), 10, 2 );
		try {
			return new \WP_Query( $args );
		} finally {
			remove_filter( 'posts_clauses', array( $this, 'apply_product_lookup_clauses' ), 10 );
		}
	}

	/**
	 * Apply price, stock, and common catalogue ordering through
	 * wc_product_meta_lookup instead of unindexed postmeta scans.
	 *
	 * @param array<string, string> $clauses Query SQL clauses.
	 * @param \WP_Query             $query   Query object.
	 * @return array<string, string>
	 */
	public function apply_product_lookup_clauses( array $clauses, \WP_Query $query ): array {
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
	 * Build WP_Query args for the given parameters.
	 *
	 * Maps WooCommerce catalogue sort values to WP_Query orderby args.
	 *
	 * @param int                  $page     Page number.
	 * @param int                  $per_page Posts per page.
	 * @param string               $orderby  WooCommerce sort value.
	 * @param string               $taxonomy Product taxonomy to filter by (or '').
	 * @param string               $term     Term slug within that taxonomy (or '').
	 * @param array<string, mixed> $filters  Optional product-filters params
	 *                                        (category, attributes, min/max price,
	 *                                        stock, on_sale, include).
	 * @return array<string, mixed>
	 */
	private function build_query_args( int $page, int $per_page, string $orderby, string $taxonomy, string $term, array $filters = array() ): array {
		$filter_set = new Catalog_Filter_Set( $filters );
		$args       = array(
			'post_type'                 => 'product',
			'posts_per_page'            => $per_page,
			'paged'                     => $page,
			'post_status'               => 'publish',
			'aa_catalog_lookup_filters' => array(
				'min_price' => $filter_set->min_price(),
				'max_price' => $filter_set->max_price(),
				'stock'     => $filter_set->stock(),
			),
		);

		// Custom-sort (featured/savings) path: the filters UI resolves an ordered,
		// already-paginated list of IDs via the sorted-products endpoint and asks
		// us to render exactly those, in that order. Cap the list so a forged
		// request cannot force unbounded block rendering.
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
				// Match WooCommerce's "Default sorting": explicit catalogue
				// positions first, then titles for stable pagination. ID is the
				// final tie-breaker when products share both values.
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

		// Filter by the current taxonomy term archive (category, tag, brand or a
		// product attribute), validated against an allow-list so an arbitrary or
		// internal taxonomy can't be queried.
		if ( '' !== $taxonomy && '' !== $term && in_array( $taxonomy, $allowed, true ) ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $term,
			);
		}

		// Category filter (one or more slugs from the filters UI).
		$categories = $filter_set->categories();
		if ( ! empty( $categories ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array_values( $categories ),
			);
		}

		// The managed Sales category is an indexed taxonomy constraint. Keeping it
		// separate from shopper-selected categories makes the two clauses combine
		// with AND instead of materializing every sale product ID in post__in.
		if ( $filter_set->on_sale() ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => Sale_Category::TERM_SLUG,
			);
		}

		// Attribute filters, e.g. { pa_color: "red,blue", pa_size: "l" }. Each
		// taxonomy is validated against the same allow-list.
		foreach ( $filter_set->attributes( $allowed ) as $attr_tax => $slugs ) {
			$tax_query[] = array(
				'taxonomy' => $attr_tax,
				'field'    => 'slug',
				'terms'    => array_values( $slugs ),
			);
		}

		if ( ! empty( $tax_query ) ) {
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Taxonomy tables are indexed; this avoids unindexed postmeta and unbounded post__in lists.
			$args['tax_query'] = $tax_query;
		}

		return $args;
	}

	/**
	 * Product taxonomies that may be used to filter the load-more query.
	 *
	 * @return array<int, string>
	 */
	private function allowed_taxonomies(): array {
		$attributes = function_exists( 'wc_get_attribute_taxonomy_names' )
			? \wc_get_attribute_taxonomy_names()
			: array();

		return array_merge( array( 'product_cat', 'product_tag', 'product_brand' ), $attributes );
	}
}
