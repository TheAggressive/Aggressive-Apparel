<?php
/**
 * Product Facets Controller
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Rate_Limiter;

defined( 'ABSPATH' ) || exit;

/**
 * `GET aggressive-apparel/v1/catalog/facets`: which attribute options still
 * have matching products for a selection.
 *
 * The filter drawer asks while the shopper is still choosing, before the
 * filtered page is fetched. It returns data only — no product markup or
 * prices — so it cannot drift from the page the way rendered cards could.
 */
final class Product_Facets_Controller {

	private const NAMESPACE = 'aggressive-apparel/v1';
	private const ROUTE     = '/catalog/facets';

	/**
	 * Facet computation.
	 *
	 * @var Product_Facet_Service
	 */
	private Product_Facet_Service $facets;

	/**
	 * Version key of the cached facet counts.
	 *
	 * @var Catalog_Cache_Version
	 */
	private Catalog_Cache_Version $version;

	/**
	 * Constructor.
	 *
	 * @param ?Product_Facet_Service $facets  Facet computation.
	 * @param ?Catalog_Cache_Version $version Facet cache version.
	 */
	public function __construct( ?Product_Facet_Service $facets = null, ?Catalog_Cache_Version $version = null ) {
		$this->version = $version ?? new Catalog_Cache_Version();
		$this->facets  = $facets ?? new Product_Facet_Service( new Product_Collection_Query(), $this->version );
	}

	/** Register hooks. */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );

		// Cached counts change with products, stock and terms.
		foreach ( array( 'woocommerce_update_product', 'woocommerce_new_product', 'woocommerce_update_product_variation', 'woocommerce_new_product_variation', 'woocommerce_product_set_stock_status', 'aggressive_apparel_sale_category_updated', 'created_term', 'edited_term', 'delete_term' ) as $hook ) {
			add_action( $hook, array( $this, 'invalidate' ) );
		}
	}

	/** Invalidate every cached facet count in constant time. */
	public function invalidate(): void {
		$this->version->bump();
	}

	/** Register the public route. */
	public function register_route(): void {
		$slug_list = array(
			'default'           => '',
			'type'              => 'string',
			'maxLength'         => 300,
			'validate_callback' => 'rest_validate_request_arg',
			'sanitize_callback' => 'sanitize_text_field',
		);

		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'taxonomy'   => array(
						'default'           => '',
						'type'              => 'string',
						'maxLength'         => 64,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_key',
					),
					'term'       => array(
						'default'           => '',
						'type'              => 'string',
						'maxLength'         => 200,
						'validate_callback' => 'rest_validate_request_arg',
						// REST sanitizers receive ($value, $request, $key); wrap so
						// sanitize_title() does not treat the request as fallback.
						'sanitize_callback' => static fn( $value ): string => sanitize_title( (string) $value ),
					),
					'category'   => $slug_list,
					// Taxonomy → comma-separated slugs, or a list of slugs.
					'attributes' => array(
						'default'              => array(),
						'type'                 => 'object',
						'additionalProperties' => array(
							'type'  => array( 'string', 'array' ),
							'items' => array( 'type' => 'string' ),
						),
					),
					'min_price'  => array(
						'default'           => 0,
						'type'              => 'number',
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => static fn( $value ): float => (float) $value,
					),
					'max_price'  => array(
						'default'           => 0,
						'type'              => 'number',
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => static fn( $value ): float => (float) $value,
					),
					'stock'      => array(
						'default'           => '',
						'type'              => 'string',
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_key',
					),
					'on_sale'    => array(
						'default' => false,
						'type'    => 'boolean',
					),
				),
			)
		);
	}

	/**
	 * Return the available options per filterable attribute.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! Product_Context::products_are_public() ) {
			return new \WP_REST_Response( array( 'facets' => (object) array() ) );
		}

		$max    = (int) apply_filters( 'aggressive_apparel_pf_facets_rate_limit_max', 600 );
		$window = (int) apply_filters( 'aggressive_apparel_pf_facets_rate_limit_window', MINUTE_IN_SECONDS );
		if ( ! Rate_Limiter::allow( 'pf_facets', $max, $window ) ) {
			$response = new \WP_REST_Response( array( 'error' => 'rate_limited' ), 429 );
			$response->header( 'Retry-After', '60' );
			return $response;
		}

		$filters = array(
			'category'   => (string) $request->get_param( 'category' ),
			'attributes' => (array) $request->get_param( 'attributes' ),
			'min_price'  => (float) $request->get_param( 'min_price' ),
			'max_price'  => (float) $request->get_param( 'max_price' ),
			'stock'      => (string) $request->get_param( 'stock' ),
			'on_sale'    => (bool) $request->get_param( 'on_sale' ),
		);

		return new \WP_REST_Response(
			array(
				'facets' => $this->facets->compute( $filters, (string) $request->get_param( 'taxonomy' ), (string) $request->get_param( 'term' ) ),
			)
		);
	}
}
