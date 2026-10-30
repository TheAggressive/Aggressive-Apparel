<?php
/**
 * Search REST Handler
 *
 * Public autocomplete endpoint: validation, rate limiting, and grouping.
 *
 * @package Aggressive_Apparel
 */

declare( strict_types=1 );

namespace Aggressive_Apparel\Core;

defined( 'ABSPATH' ) || exit;

/**
 * REST API handler for live search.
 */
class Search_Rest {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	public const REST_NAMESPACE = 'aggressive-apparel/v1';

	/**
	 * REST route.
	 *
	 * @var string
	 */
	public const REST_ROUTE = '/search';

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
	 * Result builder.
	 *
	 * @var Search_Results
	 */
	private Search_Results $results;

	/**
	 * Construct the REST handler.
	 *
	 * @param Search_Results $results Result builder.
	 */
	public function __construct( Search_Results $results ) {
		$this->results = $results;
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
						'maxLength'         => 200,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'scope' => array(
						'type'              => 'string',
						'default'           => 'all',
						'enum'              => array( 'all', 'product', 'post', 'page' ),
						// Explicit validate_callback: WP_REST_Request::has_valid_params()
						// only enforces `enum` when one is set, so without this an
						// out-of-enum scope would pass validation.
						'validate_callback' => 'rest_validate_request_arg',
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
			return $this->response(
				array(
					'query'  => $query,
					'groups' => array(),
					'total'  => 0,
				)
			);
		}

		if ( $this->is_rate_limited() ) {
			return $this->rate_limited_response( $query );
		}

		$show_products = Search_Visibility::products_are_public();
		// Cache candidate IDs in Search_Index, not rendered responses: prices,
		// taxes, visibility filters, and translated copy can vary per shopper.
		$groups = array();

		if ( ( 'all' === $scope || 'product' === $scope ) && $show_products ) {
			$products = $this->results->products( $query );
			if ( ! empty( $products ) ) {
				$groups[] = array(
					'type'  => 'product',
					'label' => __( 'Products', 'aggressive-apparel' ),
					'items' => $products,
				);
			}
		}

		if ( 'all' === $scope || 'post' === $scope ) {
			$posts = $this->results->posts( $query );
			if ( ! empty( $posts ) ) {
				$groups[] = array(
					'type'  => 'post',
					'label' => __( 'Articles', 'aggressive-apparel' ),
					'items' => $posts,
				);
			}
		}

		if ( 'all' === $scope || 'page' === $scope ) {
			$pages = $this->results->pages( $query );
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

		return $this->response( $response );
	}

	/**
	 * Prevent browsers and shared proxies from caching shopper-specific data.
	 *
	 * @param array<string, mixed> $data Response data.
	 */
	private function response( array $data ): \WP_REST_Response {
		return new \WP_REST_Response( $data, 200, array( 'Cache-Control' => 'private, no-store' ) );
	}

	/**
	 * Whether the current (anonymous) request has exceeded the rate limit.
	 *
	 * @return bool
	 */
	private function is_rate_limited(): bool {
		$max = max( 1, (int) apply_filters( 'aggressive_apparel_search_rate_limit_max', self::RATE_LIMIT_MAX ) );

		return ! Rate_Limiter::allow( 'search', $max, $this->effective_window() );
	}

	/**
	 * Resolve the effective rate-limit window, honoring the filter.
	 *
	 * Shared by the limiter and the `Retry-After` header so a filtered window
	 * doesn't tell clients to retry sooner than the limit actually resets.
	 *
	 * @return int
	 */
	private function effective_window(): int {
		return max( 10, (int) apply_filters( 'aggressive_apparel_search_rate_limit_window', self::RATE_LIMIT_WINDOW ) );
	}

	/**
	 * Standard 429 response for a throttled request.
	 *
	 * @param string $query Normalized query string.
	 * @return \WP_REST_Response
	 */
	private function rate_limited_response( string $query ): \WP_REST_Response {
		$response = new \WP_REST_Response(
			array(
				'error'   => 'rate_limited',
				'message' => __( 'Too many requests. Please slow down.', 'aggressive-apparel' ),
				'query'   => $query,
			),
			429
		);
		$response->header( 'Retry-After', (string) $this->effective_window() );
		$response->header( 'Cache-Control', 'private, no-store' );

		return $response;
	}
}
