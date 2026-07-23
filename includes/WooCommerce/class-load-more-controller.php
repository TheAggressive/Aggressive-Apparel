<?php
/**
 * Load More Controller
 *
 * REST controller for infinite scroll, Load More, and product-filter inserts.
 *
 * @package Aggressive_Apparel
 * @since 1.65.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Rate_Limiter;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates the rendered-products endpoint and delegates domain work to
 * focused catalog services.
 *
 * @since 1.65.0
 */
final class Load_More_Controller {

	/** REST namespace. */
	private const NAMESPACE = 'aggressive-apparel/v1';

	/** REST route. */
	private const ROUTE = '/products/rendered';

	/**
	 * Shared catalog cache version.
	 *
	 * @var Catalog_Cache_Version
	 */
	private Catalog_Cache_Version $catalog_version;

	/**
	 * Anonymous rendered-response cache.
	 *
	 * @var Rendered_Product_Cache
	 */
	private Rendered_Product_Cache $response_cache;

	/**
	 * Shared archive pagination seeder (orderby / perPage / cursor).
	 *
	 * @var Catalog_Pagination_Seed
	 */
	private Catalog_Pagination_Seed $seed;

	/**
	 * Product query builder and executor.
	 *
	 * @var Product_Collection_Query
	 */
	private Product_Collection_Query $queries;

	/**
	 * Product facet calculator.
	 *
	 * @var Product_Facet_Service
	 */
	private Product_Facet_Service $facets;

	/**
	 * Native Product Collection fragment renderer.
	 *
	 * @var Product_Fragment_Renderer
	 */
	private Product_Fragment_Renderer $fragments;

	/**
	 * Constructor.
	 *
	 * Optional dependencies keep WordPress's simple service factory compatible
	 * while allowing focused service substitution in tests.
	 *
	 * @param ?Catalog_Cache_Version                  $catalog_version Shared cache version.
	 * @param ?Rendered_Product_Cache                 $response_cache Rendered response cache.
	 * @param ?Product_Collection_Template_Repository $templates      Template repository.
	 * @param ?Product_Collection_Query               $queries        Query service.
	 * @param ?Product_Facet_Service                  $facets         Facet service.
	 * @param ?Product_Fragment_Renderer              $fragments      Fragment renderer.
	 * @param ?Catalog_Pagination_Seed                $seed           Archive pagination seeder.
	 */
	public function __construct(
		?Catalog_Cache_Version $catalog_version = null,
		?Rendered_Product_Cache $response_cache = null,
		?Product_Collection_Template_Repository $templates = null,
		?Product_Collection_Query $queries = null,
		?Product_Facet_Service $facets = null,
		?Product_Fragment_Renderer $fragments = null,
		?Catalog_Pagination_Seed $seed = null
	) {
		$this->catalog_version = $catalog_version ?? new Catalog_Cache_Version();
		$this->queries         = $queries ?? new Product_Collection_Query();
		$this->response_cache  = $response_cache ?? new Rendered_Product_Cache( $this->catalog_version );
		$templates             = $templates ?? new Product_Collection_Template_Repository();
		$this->seed            = $seed ?? new Catalog_Pagination_Seed( $templates, $this->queries );
		$this->facets          = $facets ?? new Product_Facet_Service( $this->queries, $this->catalog_version );
		$this->fragments       = $fragments ?? new Product_Fragment_Renderer();
	}

	/**
	 * Register endpoint and cache-invalidation hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );

		add_action( 'woocommerce_update_product', array( $this, 'invalidate_catalog_cache' ) );
		add_action( 'woocommerce_new_product', array( $this, 'invalidate_catalog_cache' ) );
		add_action( 'woocommerce_update_product_variation', array( $this, 'invalidate_catalog_cache' ) );
		add_action( 'woocommerce_new_product_variation', array( $this, 'invalidate_catalog_cache' ) );
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'invalidate_catalog_cache' ) );
		add_action( 'aggressive_apparel_sale_category_updated', array( $this, 'invalidate_catalog_cache' ) );
		add_action( 'created_term', array( $this, 'invalidate_catalog_cache' ) );
		add_action( 'edited_term', array( $this, 'invalidate_catalog_cache' ) );
		add_action( 'delete_term', array( $this, 'invalidate_catalog_cache' ) );
	}

	/**
	 * Invalidate every catalog-derived cache in constant time.
	 *
	 * @return void
	 */
	public function invalidate_catalog_cache(): void {
		$this->catalog_version->bump();
	}

	/**
	 * Register the public rendered-products route.
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
					'cursor'      => array(
						'default'           => '',
						'type'              => 'string',
						'maxLength'         => 512,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'per_page'    => array(
						'default'           => 12,
						'type'              => 'integer',
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					),
					'orderby'     => array(
						// Match WooCommerce's default catalog orderby so omitted
						// params stay aligned with SSR-seeded menu_order cursors.
						'default'           => 'menu_order',
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
						// REST sanitizers receive ($value, $request, $key). Wrapping
						// prevents sanitize_title() treating the request as fallback text.
						'sanitize_callback' => static fn( $value ): string => sanitize_title( (string) $value ),
					),
					'template'    => array(
						'default'           => 'archive-product',
						'type'              => 'string',
						'maxLength'         => 200,
						'sanitize_callback' => static fn( $value ): string => sanitize_title( (string) $value ),
					),
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
	 * Handle a rendered-products request.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \WP_REST_Request $request Incoming request.
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! Product_Context::products_are_public() ) {
			return new \WP_REST_Response(
				array(
					'html'           => '',
					'styles'         => array(),
					'total_products' => 0,
					'total_pages'    => 0,
					'next_cursor'    => '',
				)
			);
		}

		$page          = max( 1, (int) $request->get_param( 'page' ) );
		$per_page      = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$orderby       = (string) $request->get_param( 'orderby' );
		$taxonomy      = (string) $request->get_param( 'taxonomy' );
		$term          = (string) $request->get_param( 'term' );
		$template_slug = (string) $request->get_param( 'template' );
		$cursor_token  = trim( (string) $request->get_param( 'cursor' ) );
		$filters       = array(
			'category'   => (string) $request->get_param( 'category' ),
			'attributes' => (array) $request->get_param( 'attributes' ),
			'min_price'  => (float) $request->get_param( 'min_price' ),
			'max_price'  => (float) $request->get_param( 'max_price' ),
			'stock'      => (string) $request->get_param( 'stock' ),
			'on_sale'    => (bool) $request->get_param( 'on_sale' ),
			'include'    => (string) $request->get_param( 'include' ),
		);
		$with_facets   = (bool) $request->get_param( 'with_facets' );
		$has_include   = '' !== trim( (string) $filters['include'] );
		// Schema default fills orderby when omitted; detect an explicit query arg
		// so cursor continuations can adopt the embedded sort instead.
		$orderby_was_sent = array_key_exists( 'orderby', $request->get_query_params() );

		if ( (bool) $request->get_param( 'facets_only' ) ) {
			if ( ! $this->within_rate_limit( 'pf_facets', 600 ) ) {
				return $this->rate_limited_response();
			}
			return new \WP_REST_Response(
				array( 'facets' => $this->facets->compute( $filters, $taxonomy, $term ) )
			);
		}

		if ( ! $this->within_rate_limit( 'load_more', 120 ) ) {
			return $this->rate_limited_response();
		}

		// Continuations prefer keyset cursors. Filter page-number jumps may seek
		// by offset when no cursor is supplied. Load More always sends a cursor.
		$cursor = null;
		if ( '' !== $cursor_token ) {
			$cursor_orderby = $this->queries->peek_cursor_orderby( $cursor_token );
			if ( null === $cursor_orderby ) {
				return new \WP_REST_Response(
					array(
						'code'    => 'invalid_cursor',
						'message' => __( 'Invalid catalog cursor.', 'aggressive-apparel' ),
					),
					400
				);
			}
			if ( ! $orderby_was_sent ) {
				$orderby = $cursor_orderby;
			}
			$cursor = $this->queries->decode_cursor( $cursor_token, $orderby );
			if ( null === $cursor ) {
				return new \WP_REST_Response(
					array(
						'code'    => 'invalid_cursor',
						'message' => __( 'Invalid catalog cursor.', 'aggressive-apparel' ),
					),
					400
				);
			}
		}

		$template_slug    = '' !== $template_slug ? $template_slug : 'archive-product';
		$collection_block = $this->seed->collection_block( $template_slug );
		if ( null === $collection_block ) {
			return new \WP_REST_Response( array( 'error' => 'Template not found' ), 404 );
		}

		$query_args = $this->queries->build_args( $per_page, $orderby, $taxonomy, $term, $filters );
		$query_args = $this->queries->apply_collection_constraints( $query_args, $collection_block, $per_page, $cursor, $page );

		$page_limit = max( 0, (int) ( $query_args['aa_page_limit'] ?? 0 ) );
		if ( $page_limit > 0 && $page > $page_limit ) {
			return new \WP_REST_Response(
				$this->response_data(
					'',
					array(),
					array(
						'products' => 0,
						'pages'    => $page_limit,
					),
					$with_facets,
					$filters,
					$taxonomy,
					$term,
					'' 
				)
			);
		}

		/**
		 * Filters the final bounded query used for dynamic Product Collection pages.
		 *
		 * @param array<string, mixed> $query_args       Validated WP_Query arguments.
		 * @param array<string, mixed> $collection_block Parsed Product Collection block.
		 * @param \WP_REST_Request     $request          Current REST request.
		 */
		$query_args = (array) apply_filters( 'aggressive_apparel_load_more_query_args', $query_args, $collection_block, $request );

		$cache_key    = '';
		$cache_status = 'unavailable';
		$has_lock     = false;

		try {
			$cache_key    = $this->response_cache->key( $query_args, $collection_block, $with_facets );
			$cache_status = '' === $cache_key ? 'disabled' : 'miss';
			$cached       = $this->response_cache->fresh( $cache_key );
			if ( null !== $cached ) {
				return new \WP_REST_Response( $cached );
			}

			$has_lock = $this->response_cache->acquire_lock( $cache_key );
			if ( '' !== $cache_key ) {
				$cache_status = $has_lock ? 'regenerating' : 'lock_contended';
			}
			if ( '' !== $cache_key && ! $has_lock ) {
				$stale = $this->response_cache->stale( $cache_key );
				if ( null !== $stale ) {
					return new \WP_REST_Response( $stale );
				}
			}

			$count_query = $this->queries->run( $this->queries->count_args( $query_args ) );
			$totals      = $this->queries->totals( $count_query, $query_args, $per_page );

			// Probe one extra row so next_cursor does not depend on deep offsets.
			// Totals come from the separate count query, and trim_overflow() sets
			// found_posts itself, so this render query must not also run
			// SQL_CALC_FOUND_ROWS (a second full count scan per request).
			$fetch_args                   = $query_args;
			$fetch_args['posts_per_page'] = $has_include ? $per_page : $per_page + 1;
			$fetch_args['no_found_rows']  = true;
			$query                        = $this->queries->run( $fetch_args );
			$has_more                     = $has_include ? false : $this->queries->trim_overflow( $query, $per_page );
			if ( $page_limit > 0 && $page >= $page_limit ) {
				$has_more = false;
			}
			$next_cursor = $this->queries->next_cursor( $query, $orderby, $has_more );

			if ( ! $query->have_posts() ) {
				$data = $this->response_data( '', array(), $totals, $with_facets, $filters, $taxonomy, $term, '' );
				$this->response_cache->store( $cache_key, $data, $has_lock );
				return new \WP_REST_Response( $data );
			}

			$rendered = $this->fragments->render( $collection_block, $query );
			$data     = $this->response_data( $rendered->html(), $rendered->styles(), $totals, $with_facets, $filters, $taxonomy, $term, $next_cursor );
			$this->response_cache->store( $cache_key, $data, $has_lock );

			return new \WP_REST_Response( $data );
		} catch ( \Throwable $error ) {
			$this->response_cache->release_lock( $cache_key, $has_lock );
			$this->log_catalog_error( $error, $request, $query_args, $template_slug, $page, $orderby, $filters, $cache_status );

			return new \WP_REST_Response(
				array(
					'code'    => 'catalog_render_failed',
					'message' => __( 'Unable to load product results.', 'aggressive-apparel' ),
				),
				500
			);
		}
	}

	/**
	 * Report a catalog-generation failure without exposing details to shoppers.
	 *
	 * @phpstan-param \WP_REST_Request<array<string, mixed>> $request
	 * @param \Throwable           $error         Failure being reported.
	 * @param \WP_REST_Request     $request       Current REST request.
	 * @param array<string, mixed> $query_args    Final bounded query arguments.
	 * @param string               $template_slug Resolved template slug.
	 * @param int                  $page          Requested page.
	 * @param string               $orderby       Requested sort order.
	 * @param array<string, mixed> $filters       Active product filters.
	 * @param string               $cache_status  Cache state when generation failed.
	 * @return void
	 */
	private function log_catalog_error( \Throwable $error, \WP_REST_Request $request, array $query_args, string $template_slug, int $page, string $orderby, array $filters, string $cache_status ): void {
		$filter_json = wp_json_encode( $filters );
		$diagnostics = array(
			'template'         => $template_slug,
			'page'             => $page,
			'orderby'          => $orderby,
			'filter_signature' => is_string( $filter_json ) ? hash( 'sha256', $filter_json ) : 'unavailable',
			'cache_status'     => $cache_status,
		);

		/**
		 * Fires when dynamic catalog generation fails.
		 *
		 * Logging integrations can subscribe without replacing the endpoint.
		 *
		 * @param \Throwable           $error       Failure being reported.
		 * @param \WP_REST_Request     $request     Current REST request.
		 * @param array<string, mixed> $query_args  Final bounded query arguments.
		 * @param array<string, mixed> $diagnostics Safe structured diagnostic context.
		 */
		do_action( 'aggressive_apparel_catalog_render_error', $error, $request, $query_args, $diagnostics );

		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		\wc_get_logger()->error(
			'Dynamic catalog generation failed: ' . $error->getMessage(),
			array_merge(
				array(
					'source'    => 'aggressive-apparel-catalog',
					'exception' => get_class( $error ),
				),
				$diagnostics
			)
		);
	}

	/**
	 * Assemble the stable REST response contract.
	 *
	 * @param string                          $html        Product card markup.
	 * @param array<int, array<string,mixed>> $styles      Dynamic block-support styles.
	 * @param array{products:int,pages:int}   $totals      Query totals.
	 * @param bool                            $with_facets Include facet availability.
	 * @param array<string, mixed>            $filters     Active filters.
	 * @param string                          $taxonomy    Archive taxonomy.
	 * @param string                          $term        Archive term.
	 * @param string                          $next_cursor Opaque cursor for the next page.
	 * @return array<string, mixed>
	 */
	private function response_data( string $html, array $styles, array $totals, bool $with_facets, array $filters, string $taxonomy, string $term, string $next_cursor = '' ): array {
		$data = array(
			'html'           => $html,
			'styles'         => $styles,
			'total_products' => $totals['products'],
			'total_pages'    => $totals['pages'],
			'next_cursor'    => $next_cursor,
		);
		if ( $with_facets ) {
			$data['facets'] = $this->facets->compute( $filters, $taxonomy, $term );
		}

		return $data;
	}

	/**
	 * Test the shared per-client rate limit for an endpoint scope.
	 *
	 * @param string $scope       Rate-limit scope.
	 * @param int    $default_max Default request allowance.
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
	 * Return the standard throttled response.
	 *
	 * @return \WP_REST_Response
	 */
	private function rate_limited_response(): \WP_REST_Response {
		$response = new \WP_REST_Response( array( 'error' => 'rate_limited' ), 429 );
		$response->header( 'Retry-After', '60' );
		return $response;
	}
}
