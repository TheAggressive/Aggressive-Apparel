<?php
/**
 * Load More Renderer
 *
 * REST endpoint that renders product cards through the full WordPress block
 * pipeline. Every render_block filter (hover image, quick-view, badges, etc.)
 * runs automatically. Cards render through WooCommerce's product-template
 * context and reuse the product-collection wp-elements class so page-1 head CSS
 * applies to load-more / filter inserts without shipping extra scoped styles.
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

	/**
	 * Parsed template product blocks keyed by template slug (per-request).
	 *
	 * @var array<string, array{collection: ?array<string, mixed>, template: ?array<string, mixed>}>
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

		$template_config = $this->get_template_product_config( $template_slug );

		// A resolved template may not contain a product-template block (for
		// example, a broad archive fallback or malformed client input). Never
		// render arbitrary template content; use the canonical product archive.
		if ( empty( $template_config['template'] ) && 'archive-product' !== $template_slug ) {
			$template_config = $this->get_template_product_config( 'archive-product' );
		}

		if ( empty( $template_config['template'] ) ) {
			return new \WP_REST_Response( array( 'error' => 'Template not found' ), 404 );
		}

		$template_block            = $template_config['template'];
		$collection_context        = $this->build_collection_context( $template_config['collection'] );
		$collection_elements_class = $this->prime_collection_element_styles( $template_config['collection'] );

		$query = $this->run_product_query( $this->build_query_args( $page, $per_page, $orderby, $taxonomy, $term, $filters ) );

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

			$card_html = $this->render_product_card(
				$template_block,
				$collection_context,
				$collection_elements_class,
				$product_id
			);

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

		$prepared = $wpdb->prepare( $sql, ...$params );
		$slugs    = $wpdb->get_col( $prepared );

		return array_values( array_unique( array_map( 'strval', $slugs ) ) );
	}

	/**
	 * Get product-collection and product-template blocks from a template.
	 *
	 * Prefers a DB-customised template (Site Editor changes) over the theme file.
	 *
	 * @param string $template_slug Template slug.
	 * @return array{collection: ?array<string, mixed>, template: ?array<string, mixed>}
	 */
	private function get_template_product_config( string $template_slug ): array {
		if ( isset( $this->template_cache[ $template_slug ] ) ) {
			return $this->template_cache[ $template_slug ];
		}

		$content = $this->get_template_content( $template_slug );
		if ( '' === $content ) {
			$config                                 = array(
				'collection' => null,
				'template'   => null,
			);
			$this->template_cache[ $template_slug ] = $config;
			return $config;
		}

		$blocks                                 = parse_blocks( $content );
		$config                                 = array(
			'collection' => $this->find_block_by_name( $blocks, 'woocommerce/product-collection' ),
			'template'   => $this->find_block_by_name( $blocks, 'woocommerce/product-template' ),
		);
		$this->template_cache[ $template_slug ] = $config;

		return $config;
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
	 * Build WooCommerce product-collection context from parsed collection attrs.
	 *
	 * @param ?array<string, mixed> $collection_block Parsed collection block.
	 * @return array<string, mixed>
	 */
	private function build_collection_context( ?array $collection_block ): array {
		if ( empty( $collection_block['attrs'] ) || ! is_array( $collection_block['attrs'] ) ) {
			return array();
		}

		$context = array();
		$attrs   = $collection_block['attrs'];

		foreach ( array( 'queryId', 'query', 'displayLayout', 'dimensions', 'collection', 'tagName' ) as $key ) {
			if ( isset( $attrs[ $key ] ) ) {
				$context[ $key ] = $attrs[ $key ];
			}
		}

		return $context;
	}

	/**
	 * Prime product-collection element styles and return the wp-elements class.
	 *
	 * @param ?array<string, mixed> $collection_block Parsed collection block.
	 * @return string
	 */
	private function prime_collection_element_styles( ?array $collection_block ): string {
		if ( empty( $collection_block ) ) {
			return '';
		}

		/**
		 * Collection block after render_block_data filters.
		 *
		 * @var array<string, mixed> $processed
		 */
		$processed  = apply_filters( 'render_block_data', $collection_block, $collection_block, null );
		$class_name = $processed['attrs']['className'] ?? '';

		if ( ! is_string( $class_name ) || '' === $class_name ) {
			return '';
		}

		if ( preg_match( '/\b(wp-elements-\S+)\b/', $class_name, $matches ) ) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Deep-clone a parsed block tree before per-card mutation.
	 *
	 * @param array<string, mixed> $block Parsed block.
	 * @return array<string, mixed>
	 */
	private function clone_parsed_block( array $block ): array {
		$encoded = wp_json_encode( $block );
		if ( false === $encoded ) {
			return $block;
		}

		$clone = json_decode( $encoded, true );
		return is_array( $clone ) ? $clone : $block;
	}

	/**
	 * Recursively mark inner blocks as inherited (archive template parity).
	 *
	 * @param array<string, mixed> $block Parsed block (by reference).
	 * @return void
	 */
	private function inject_is_inherited( array &$block ): void {
		if ( ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
			$block['attrs'] = array();
		}

		$block['attrs']['isInherited'] = 1;

		if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return;
		}

		foreach ( $block['innerBlocks'] as &$inner_block ) {
			$this->inject_is_inherited( $inner_block );
		}
		unset( $inner_block );
	}

	/**
	 * Render a single product card through the product-template block tree.
	 *
	 * Mirrors WooCommerce ProductTemplate::render() inner-block path so collection
	 * context and inherited attrs match archive SSR.
	 *
	 * @param array<string, mixed> $template_block            Parsed product-template block.
	 * @param array<string, mixed> $collection_context        Context from product-collection.
	 * @param string               $collection_elements_class wp-elements class from collection.
	 * @param int                  $product_id                Product post ID.
	 * @return string
	 */
	private function render_product_card(
		array $template_block,
		array $collection_context,
		string $collection_elements_class,
		int $product_id
	): string {
		$block_instance              = $this->clone_parsed_block( $template_block );
		$block_instance['blockName'] = 'core/null';
		$this->inject_is_inherited( $block_instance );

		$context = array_merge(
			$collection_context,
			array(
				'postType' => 'product',
				'postId'   => $product_id,
			)
		);

		$card_html = ( new \WP_Block( $block_instance, $context ) )->render(
			array( 'dynamic' => false )
		);

		if ( '' === $card_html ) {
			return '';
		}

		if (
			'' !== $collection_elements_class
			&& ! str_contains( $card_html, $collection_elements_class )
		) {
			$card_html = sprintf(
				'<div class="%s">%s</div>',
				esc_attr( $collection_elements_class ),
				$card_html
			);
		}

		return $card_html;
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
		$filters = $query->get( 'aa_catalog_lookup_filters' );
		$orderby = sanitize_key( (string) $query->get( 'aa_catalog_lookup_orderby' ) );

		if ( ! is_array( $filters ) && '' === $orderby ) {
			return $clauses;
		}

		global $wpdb;

		$alias = 'aa_product_lookup';
		if ( ! str_contains( $clauses['join'], " {$alias} " ) ) {
			$clauses['join'] .= " INNER JOIN {$wpdb->wc_product_meta_lookup} {$alias} ON {$wpdb->posts}.ID = {$alias}.product_id";
		}

		if ( is_array( $filters ) ) {
			$constraints = new Catalog_SQL_Constraints();
			$constraints->add_lookup_filters( new Catalog_Filter_Set( $filters ), $alias );
			if ( ! empty( $constraints->where() ) ) {
				$lookup_where      = ' AND ' . implode( ' AND ', $constraints->where() );
				$clauses['where'] .= $wpdb->prepare( $lookup_where, ...$constraints->where_params() );
			}
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
