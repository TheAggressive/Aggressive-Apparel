<?php
/**
 * REST endpoint security tests.
 *
 * Guards the theme's REST surface: every route must declare a
 * permission_callback, the public (__return_true) routes must be an explicit
 * allowlist, and the public search endpoint must validate + harden its input.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Security;

use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * REST security test case.
 */
class TestRestSecurity extends WP_UnitTestCase {

	/** Theme REST namespace prefix (without leading slash). */
	private const THEME_NS = 'aggressive-apparel/';

	/** Public search route. */
	private const SEARCH = '/aggressive-apparel/v1/search';

	/**
	 * Routes intentionally left unauthenticated. A NEW public route must be
	 * added here deliberately, or test_public_routes_are_allowlisted fails —
	 * turning "accidentally shipped an open endpoint" into a red test.
	 *
	 * @var string[]
	 */
	private const EXPECTED_PUBLIC = array(
		'/aggressive-apparel/v1/search',
		'/aggressive-apparel/v1/catalog/facets',
	);

	/**
	 * REST server.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Boot a fresh REST server and register the theme's routes.
	 */
	public function setUp(): void {
		parent::setUp();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
		// Exercise optional controllers even when their storefront feature is off.
		foreach ( array(
			\Aggressive_Apparel\WooCommerce\Badge_Studio_Rest::class,
			\Aggressive_Apparel\WooCommerce\Size_Guide_Rest::class,
			\Aggressive_Apparel\WooCommerce\Product_Specs_Settings::class,
			\Aggressive_Apparel\WooCommerce\Product_Specs_Grid::class,
			\Aggressive_Apparel\WooCommerce\Printful_Rest::class,
		) as $controller ) {
			( new $controller() )->register_routes();
		}
		( new \Aggressive_Apparel\WooCommerce\Product_Facets_Controller() )->register_route();
	}

	/**
	 * Tear down the REST server.
	 */
	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		parent::tearDown();
	}

	/**
	 * Map theme routes to their per-endpoint permission callbacks.
	 *
	 * @return array<string, array<int, mixed>>
	 */
	private function theme_routes(): array {
		$out = array();
		foreach ( $this->server->get_routes() as $route => $endpoints ) {
			$trimmed = ltrim( $route, '/' );
			if ( 0 !== strpos( $trimmed, self::THEME_NS ) ) {
				continue;
			}
			// Skip the WP-generated namespace index (e.g. aggressive-apparel/v1),
			// which is a core route with no theme-owned permission_callback.
			if ( preg_match( '#^aggressive-apparel/v\d+$#', $trimmed ) ) {
				continue;
			}
			foreach ( $endpoints as $endpoint ) {
				$out[ $route ][] = $endpoint['permission_callback'] ?? null;
			}
		}
		return $out;
	}

	/**
	 * Every theme route must declare a callable permission_callback. A missing
	 * one makes the endpoint silently public.
	 */
	public function test_every_theme_route_declares_a_permission_callback(): void {
		$routes = $this->theme_routes();
		$this->assertNotEmpty( $routes, 'No theme REST routes registered — did rest_api_init run?' );

		foreach ( $routes as $route => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$this->assertNotNull( $callback, "Route {$route} has an endpoint with no permission_callback (defaults to public)." );
				$this->assertTrue( is_callable( $callback ), "Route {$route} permission_callback is not callable." );
			}
		}
	}

	/**
	 * Any route left fully public must be one we consciously allow.
	 */
	public function test_public_routes_are_allowlisted(): void {
		$public = array();
		foreach ( $this->theme_routes() as $route => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( '__return_true' === $callback ) {
					$public[ $route ] = true;
				}
			}
		}

		foreach ( array_keys( $public ) as $route ) {
			$this->assertContains(
				$route,
				self::EXPECTED_PUBLIC,
				"Unexpected public REST route '{$route}'. If intentional, add it to EXPECTED_PUBLIC after review."
			);
		}
	}

	/** Every privileged handler rejects guests and ordinary shopper accounts. */
	public function test_protected_routes_reject_unprivileged_callers(): void {
		$users = array(
			0,
			self::factory()->user->create( array( 'role' => 'subscriber' ) ),
			self::factory()->user->create( array( 'role' => 'customer' ) ),
		);
		try {
			foreach ( $users as $user_id ) {
				wp_set_current_user( $user_id );
				foreach ( $this->theme_routes() as $route => $callbacks ) {
					foreach ( $callbacks as $callback ) {
						if ( '__return_true' === $callback ) {
							continue;
						}
						$request = new WP_REST_Request( 'POST', $route );
						$request->set_url_params( array( 'id' => 123 ) );
						$allowed = call_user_func( $callback, $request );
						$this->assertTrue( false === $allowed || is_wp_error( $allowed ), "{$route} permitted user {$user_id}." );
					}
				}
			}
		} finally {
			wp_set_current_user( 0 );
		}
	}

	/** Exercise REST dispatch as well as the permission callbacks themselves. */
	public function test_store_management_routes_require_store_management_capabilities(): void {
		$route = '/aggressive-apparel/v1/size-guides';
		try {
			foreach ( array( 'subscriber', 'author', 'editor', 'customer' ) as $role ) {
				wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
				$this->assertSame( 403, $this->server->dispatch( new WP_REST_Request( 'GET', $route ) )->get_status(), $role );
			}
			wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
			$this->assertSame( 200, $this->server->dispatch( new WP_REST_Request( 'GET', $route ) )->get_status() );
		} finally {
			wp_set_current_user( 0 );
		}
	}

	/**
	 * A missing required "query" is a 400, not a 200 with garbage.
	 */
	public function test_search_requires_query_param(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', self::SEARCH ) );
		$this->assertSame( 400, $response->get_status(), 'Missing required "query" should be a 400.' );
	}

	/** Bound work before tokenization and database lookup. */
	public function test_search_rejects_oversized_queries(): void {
		$request = new WP_REST_Request( 'GET', self::SEARCH );
		$request->set_param( 'query', str_repeat( 'word ', 41 ) );
		$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
	}

	/** Scalar facet arguments must not silently coerce arrays or invalid numbers. */
	public function test_facets_reject_invalid_scalar_arguments(): void {
		foreach ( array(
			array( 'taxonomy', array( 'product_cat' ) ),
			array( 'term', array( 'tees' ) ),
			array( 'category', str_repeat( 'x', 301 ) ),
			array( 'stock', array( 'instock' ) ),
			array( 'min_price', 'invalid' ),
			array( 'max_price', array( 100 ) ),
		) as list( $key, $value ) ) {
			$request = new WP_REST_Request( 'GET', '/aggressive-apparel/v1/catalog/facets' );
			$request->set_param( $key, $value );
			$this->assertSame( 400, $this->server->dispatch( $request )->get_status(), $key );
		}
	}

	/**
	 * An out-of-enum "scope" is rejected at the REST layer (400).
	 */
	public function test_search_rejects_invalid_scope(): void {
		$request = new WP_REST_Request( 'GET', self::SEARCH );
		$request->set_param( 'query', 'shirt' );
		$request->set_param( 'scope', 'evil-scope' );

		$response = $this->server->dispatch( $request );
		$this->assertSame( 400, $response->get_status(), 'Out-of-enum "scope" must be rejected with a 400.' );
	}

	/**
	 * Valid enum scopes still pass validation (guards against an over-strict
	 * regression that would reject legitimate requests).
	 */
	public function test_search_accepts_valid_scopes(): void {
		foreach ( array( 'all', 'product', 'post', 'page' ) as $scope ) {
			$request = new WP_REST_Request( 'GET', self::SEARCH );
			$request->set_param( 'query', 'shirt' );
			$request->set_param( 'scope', $scope );

			$response = $this->server->dispatch( $request );
			$this->assertSame( 200, $response->get_status(), "Valid scope '{$scope}' should pass validation." );
		}
	}

	/**
	 * Sub-minimum queries short-circuit to an empty result (no query executed).
	 */
	public function test_search_short_query_returns_empty(): void {
		$request = new WP_REST_Request( 'GET', self::SEARCH );
		$request->set_param( 'query', 'a' );

		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 0, $data['total'] );
	}

	/**
	 * Hostile input must be handled (no fatal / 500) and must never be echoed
	 * back as an unsanitized script tag.
	 */
	public function test_search_hardens_hostile_input(): void {
		$request = new WP_REST_Request( 'GET', self::SEARCH );
		$request->set_param( 'query', '<script>alert(1)</script>' );

		$response = $this->server->dispatch( $request );
		$this->assertLessThan( 500, $response->get_status(), 'Hostile query must be handled gracefully, not fatal.' );

		$json = (string) wp_json_encode( $response->get_data() );
		$this->assertStringNotContainsString( '<script>', $json, 'Response leaked an unsanitized <script> tag.' );
	}
}
