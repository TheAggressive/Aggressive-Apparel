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
		'/aggressive-apparel/v1/products/rendered',
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

	/**
	 * A missing required "query" is a 400, not a 200 with garbage.
	 */
	public function test_search_requires_query_param(): void {
		$response = $this->server->dispatch( new WP_REST_Request( 'GET', self::SEARCH ) );
		$this->assertSame( 400, $response->get_status(), 'Missing required "query" should be a 400.' );
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
