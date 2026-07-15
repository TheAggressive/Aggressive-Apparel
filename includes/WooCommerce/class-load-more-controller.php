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
	 * Product Collection template repository.
	 *
	 * @var Product_Collection_Template_Repository
	 */
	private Product_Collection_Template_Repository $templates;

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
	 */
	public function __construct(
		?Catalog_Cache_Version $catalog_version = null,
		?Rendered_Product_Cache $response_cache = null,
		?Product_Collection_Template_Repository $templates = null,
		?Product_Collection_Query $queries = null,
		?Product_Facet_Service $facets = null,
		?Product_Fragment_Renderer $fragments = null
	) {
		$this->catalog_version = $catalog_version ?? new Catalog_Cache_Version();
		$this->queries         = $queries ?? new Product_Collection_Query();
		$this->response_cache  = $response_cache ?? new Rendered_Product_Cache( $this->catalog_version );
		$this->templates       = $templates ?? new Product_Collection_Template_Repository();
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
				)
			);
		}

		$page          = max( 1, (int) $request->get_param( 'page' ) );
		$per_page      = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$orderby       = (string) $request->get_param( 'orderby' );
		$taxonomy      = (string) $request->get_param( 'taxonomy' );
		$term          = (string) $request->get_param( 'term' );
		$template_slug = (string) $request->get_param( 'template' );
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

		$template_slug    = '' !== $template_slug ? $template_slug : 'archive-product';
		$collection_block = $this->templates->collection_block( $template_slug );
		if ( null === $collection_block && 'archive-product' !== $template_slug ) {
			$collection_block = $this->templates->collection_block( 'archive-product' );
		}
		if ( null === $collection_block ) {
			return new \WP_REST_Response( array( 'error' => 'Template not found' ), 404 );
		}

		$query_args = $this->queries->build_args( $page, $per_page, $orderby, $taxonomy, $term, $filters );
		$query_args = $this->queries->apply_collection_constraints( $query_args, $collection_block, $page, $per_page );

		/**
		 * Filters the final bounded query used for dynamic Product Collection pages.
		 *
		 * @param array<string, mixed> $query_args       Validated WP_Query arguments.
		 * @param array<string, mixed> $collection_block Parsed Product Collection block.
		 * @param \WP_REST_Request     $request          Current REST request.
		 */
		$query_args = (array) apply_filters( 'aggressive_apparel_load_more_query_args', $query_args, $collection_block, $request );

		$cache_key = $this->response_cache->key( $query_args, $collection_block, $with_facets );
		$cached    = $this->response_cache->fresh( $cache_key );
		if ( null !== $cached ) {
			return new \WP_REST_Response( $cached );
		}

		$has_lock = $this->response_cache->acquire_lock( $cache_key );
		if ( '' !== $cache_key && ! $has_lock ) {
			$stale = $this->response_cache->stale( $cache_key );
			if ( null !== $stale ) {
				return new \WP_REST_Response( $stale );
			}
		}

		$query = $this->queries->run( $query_args );
		if ( ! $query->have_posts() && $page > 1 ) {
			// Empty offset pages do not populate FOUND_ROWS. Count once with a
			// bounded one-row query so totals remain accurate out of range.
			$count_args                   = $query_args;
			$count_args['posts_per_page'] = 1;
			$count_args['paged']          = 1;
			$count_args['offset']         = 0;
			$count_args['fields']         = 'ids';
			$count_query                  = $this->queries->run( $count_args );
			$query->found_posts           = $count_query->found_posts;
		}
		$totals = $this->queries->totals( $query, $query_args, $per_page );

		if ( ! $query->have_posts() ) {
			$data = $this->response_data( '', array(), $totals, $with_facets, $filters, $taxonomy, $term );
			$this->response_cache->store( $cache_key, $data, $has_lock );
			return new \WP_REST_Response( $data );
		}

		$rendered = $this->fragments->render( $collection_block, $query );
		$data     = $this->response_data( $rendered->html(), $rendered->styles(), $totals, $with_facets, $filters, $taxonomy, $term );
		$this->response_cache->store( $cache_key, $data, $has_lock );

		return new \WP_REST_Response( $data );
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
	 * @return array<string, mixed>
	 */
	private function response_data( string $html, array $styles, array $totals, bool $with_facets, array $filters, string $taxonomy, string $term ): array {
		$data = array(
			'html'           => $html,
			'styles'         => $styles,
			'total_products' => $totals['products'],
			'total_pages'    => $totals['pages'],
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
