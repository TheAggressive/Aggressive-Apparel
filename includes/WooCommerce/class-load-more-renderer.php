<?php
/**
 * Load More Renderer
 *
 * REST endpoint that renders product cards through the full WordPress block
 * pipeline. Every render_block filter (hover image, quick-view, badges, etc.)
 * runs automatically, so infinite-scroll cards are byte-for-byte identical to
 * the initial server-rendered output regardless of block editor changes.
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
	private const FACETS_CACHE_PREFIX = 'aa_pf_facets_';
	private const FACETS_CACHE_TTL    = 300;

	/** Template slugs that contain a woocommerce/product-template block. */
	private const PRODUCT_TEMPLATES = array(
		'archive-product',
		'taxonomy-product_cat',
		'taxonomy-product_tag',
	);

	/**
	 * Parsed inner-block cache keyed by template slug (per-request).
	 *
	 * @var array<string, array<int, array<string, mixed>>>
	 */
	private array $blocks_cache = array();

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
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'flush_facets_cache' ) );
	}

	/**
	 * Delete all cached facet-availability transients.
	 *
	 * @return void
	 */
	public function flush_facets_cache(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk transient cleanup requires a direct query.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::FACETS_CACHE_PREFIX ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::FACETS_CACHE_PREFIX ) . '%'
			)
		);
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
						'enum'              => array( 'date', 'popularity', 'rating', 'price', 'price-desc', 'title-asc', 'title-desc' ),
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
						'enum'              => self::PRODUCT_TEMPLATES,
						'sanitize_callback' => 'sanitize_text_field',
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

		if ( ! in_array( $template_slug, self::PRODUCT_TEMPLATES, true ) ) {
			$template_slug = 'archive-product';
		}

		$inner_blocks = $this->get_template_inner_blocks( $template_slug );

		// Tag / brand / attribute archives have no template of their own and
		// render via archive-product — fall back to its product grid.
		if ( empty( $inner_blocks ) && 'archive-product' !== $template_slug ) {
			$inner_blocks = $this->get_template_inner_blocks( 'archive-product' );
		}

		if ( empty( $inner_blocks ) ) {
			return new \WP_REST_Response( array( 'error' => 'Template not found' ), 404 );
		}

		$query = new \WP_Query( $this->build_query_args( $page, $per_page, $orderby, $taxonomy, $term, $filters ) );

		if ( ! $query->have_posts() ) {
			$empty = array(
				'html'           => '',
				'total_products' => 0,
				'total_pages'    => 0,
			);
			if ( $with_facets ) {
				$empty['facets'] = $this->compute_facets( $filters, $taxonomy, $term );
			}
			return new \WP_REST_Response( $empty );
		}

		// Signal to render_block guards that we are rendering product cards so
		// each class's is_listing_page() (and equivalents) returns true.
		add_filter( 'aggressive_apparel_is_listing_page', '__return_true' );

		$html = '';

		while ( $query->have_posts() ) {
			$query->the_post();
			$product_id = get_the_ID();

			if ( ! $product_id ) {
				continue;
			}

			$context = array(
				'postType' => 'product',
				'postId'   => $product_id,
			);

			$card_html = '';
			foreach ( $inner_blocks as $parsed_block ) {
				if ( empty( $parsed_block['blockName'] ) ) {
					continue;
				}
				$block_obj  = new \WP_Block( $parsed_block, $context );
				$card_html .= $block_obj->render();
			}

			$post_classes   = implode( ' ', get_post_class( 'wc-block-product' ) );
			$encoded        = wp_json_encode(
				array( 'productId' => $product_id ),
				JSON_NUMERIC_CHECK | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
			);
			$wp_context_str = false !== $encoded ? $encoded : '{"productId":' . $product_id . '}';

			$html .= sprintf(
				'<li class="%s" data-wp-interactive="woocommerce/product-collection" data-wp-context=\'%s\' data-wp-key="product-item-%d">%s</li>',
				esc_attr( $post_classes ),
				$wp_context_str,
				$product_id,
				$card_html
			);
		}

		wp_reset_postdata();
		remove_filter( 'aggressive_apparel_is_listing_page', '__return_true' );

		$data = array(
			'html'           => $html,
			'total_products' => (int) $query->found_posts,
			'total_pages'    => (int) $query->max_num_pages,
		);
		if ( $with_facets ) {
			$data['facets'] = $this->compute_facets( $filters, $taxonomy, $term );
		}

		return new \WP_REST_Response( $data );
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
		// result is safely cacheable per filter signature (flushed on product
		// change — see flush_facets_cache()).
		$cache_key = self::FACETS_CACHE_PREFIX . md5( (string) wp_json_encode( array( $filters, $taxonomy, $term ) ) );
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

			$args                           = $this->build_query_args( 1, -1, 'date', $taxonomy, $term, $facet_filters );
			$args['fields']                 = 'ids';
			$args['posts_per_page']         = -1;
			$args['no_found_rows']          = true;
			$args['update_post_meta_cache'] = false;
			$args['update_post_term_cache'] = false;

			/**
			 * Product IDs from the facet query (`fields` => `ids`).
			 *
			 * @var int[] $ids
			 */
			$ids = ( new \WP_Query( $args ) )->posts;
			if ( empty( $ids ) ) {
				$facets[ $facet ] = array();
				continue;
			}

			$slugs = wp_get_object_terms(
				array_map( 'intval', $ids ),
				$facet,
				array( 'fields' => 'slugs' )
			);

			$facets[ $facet ] = is_wp_error( $slugs ) ? array() : array_values( array_unique( $slugs ) );
		}

		set_transient( $cache_key, $facets, self::FACETS_CACHE_TTL );

		return $facets;
	}

	/**
	 * Get the parsed innerBlocks of woocommerce/product-template from a template.
	 *
	 * Prefers a DB-customised template (Site Editor changes) over the theme file.
	 *
	 * @param string $template_slug Template slug.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_template_inner_blocks( string $template_slug ): array {
		if ( isset( $this->blocks_cache[ $template_slug ] ) ) {
			return $this->blocks_cache[ $template_slug ];
		}

		// Try DB template first (respects Site Editor customisations).
		$templates = get_block_templates( array( 'slug__in' => array( $template_slug ) ), 'wp_template' );

		if ( ! empty( $templates ) ) {
			$content = $templates[0]->content;
		} else {
			$file = AGGRESSIVE_APPAREL_DIR . '/templates/' . $template_slug . '.html';
			if ( ! file_exists( $file ) ) {
				return array();
			}
			global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			WP_Filesystem();
			if ( ! $wp_filesystem ) {
				return array();
			}
			$content = $wp_filesystem->get_contents( $file );
			if ( false === $content ) {
				return array();
			}
		}

		$blocks = parse_blocks( $content );
		$inner  = $this->find_product_template_inner_blocks( $blocks );

		$this->blocks_cache[ $template_slug ] = $inner;
		return $inner;
	}

	/**
	 * Recursively find woocommerce/product-template and return its innerBlocks.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks Parsed blocks.
	 * @return array<int, array<string, mixed>>
	 */
	private function find_product_template_inner_blocks( array $blocks ): array {
		foreach ( $blocks as $block ) {
			if ( 'woocommerce/product-template' === ( $block['blockName'] ?? '' ) ) {
				return $block['innerBlocks'] ?? array();
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$result = $this->find_product_template_inner_blocks( $block['innerBlocks'] );
				if ( ! empty( $result ) ) {
					return $result;
				}
			}
		}
		return array();
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
	 *                                        stock, include).
	 * @return array<string, mixed>
	 */
	private function build_query_args( int $page, int $per_page, string $orderby, string $taxonomy, string $term, array $filters = array() ): array {
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'post_status'    => 'publish',
		);

		// Custom-sort (featured/savings) path: the filters UI resolves an ordered,
		// already-paginated list of IDs via the sorted-products endpoint and asks
		// us to render exactly those, in that order. Short-circuit everything else.
		$include = array_filter( array_map( 'absint', explode( ',', (string) ( $filters['include'] ?? '' ) ) ) );
		if ( ! empty( $include ) ) {
			$args['post__in']            = $include;
			$args['orderby']             = 'post__in';
			$args['posts_per_page']      = count( $include );
			$args['paged']               = 1;
			$args['ignore_sticky_posts'] = true;
			return $args;
		}

		switch ( $orderby ) {
			case 'popularity':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['order']    = 'DESC';
				break;

			case 'rating':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['order']    = 'DESC';
				break;

			case 'price':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['order']    = 'ASC';
				break;

			case 'price-desc':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$args['order']    = 'DESC';
				break;

			case 'title-asc':
				$args['orderby'] = 'title';
				$args['order']   = 'ASC';
				break;

			case 'title-desc':
				$args['orderby'] = 'title';
				$args['order']   = 'DESC';
				break;

			default:
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
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
		$categories = array_filter( array_map( 'sanitize_title', explode( ',', (string) ( $filters['category'] ?? '' ) ) ) );
		if ( ! empty( $categories ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => array_values( $categories ),
			);
		}

		// Attribute filters, e.g. { pa_color: "red,blue", pa_size: "l" }. Each
		// taxonomy is validated against the same allow-list.
		$attributes = isset( $filters['attributes'] ) && is_array( $filters['attributes'] ) ? $filters['attributes'] : array();
		foreach ( $attributes as $attr_tax => $attr_terms ) {
			$attr_tax = sanitize_key( (string) $attr_tax );
			if ( ! in_array( $attr_tax, $allowed, true ) ) {
				continue;
			}
			$slugs = array_filter( array_map( 'sanitize_title', explode( ',', (string) $attr_terms ) ) );
			if ( empty( $slugs ) ) {
				continue;
			}
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
			$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		// Price + stock meta filters.
		$meta_query = array();

		$min_price = (float) ( $filters['min_price'] ?? 0 );
		$max_price = (float) ( $filters['max_price'] ?? 0 );
		if ( $min_price > 0 && $max_price > 0 ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => array( $min_price, $max_price ),
				'type'    => 'DECIMAL(10,2)',
				'compare' => 'BETWEEN',
			);
		} elseif ( $min_price > 0 ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => $min_price,
				'type'    => 'DECIMAL(10,2)',
				'compare' => '>=',
			);
		} elseif ( $max_price > 0 ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => $max_price,
				'type'    => 'DECIMAL(10,2)',
				'compare' => '<=',
			);
		}

		$stock = sanitize_text_field( (string) ( $filters['stock'] ?? '' ) );
		if ( '' !== $stock ) {
			$meta_query[] = array(
				'key'     => '_stock_status',
				'value'   => $stock,
				'compare' => '=',
			);
		}

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
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
