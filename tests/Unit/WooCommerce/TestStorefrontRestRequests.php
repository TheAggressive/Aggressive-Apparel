<?php
/**
 * Tests for booting the theme's price-rendering REST routes as storefront.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Storefront_Rest_Requests;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Storefront_Rest_Requests
 */
class TestStorefrontRestRequests extends WP_UnitTestCase {

	/**
	 * Original request URI.
	 *
	 * @var string|null
	 */
	private ?string $uri = null;

	/**
	 * Original query vars.
	 *
	 * @var array<string, mixed>
	 */
	private array $get = array();

	/** Snapshot the request globals. */
	public function setUp(): void {
		parent::setUp();
		$this->uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : null;
		$this->get = $_GET;
		$_GET      = array();
	}

	/** Restore the request globals. */
	public function tearDown(): void {
		$_GET = $this->get;
		if ( null === $this->uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->uri;
		}
		parent::tearDown();
	}

	/**
	 * Is the request treated as REST by WooCommerce after the filter?
	 *
	 * @param string $uri Request URI.
	 */
	private function rest_after_filter( string $uri ): bool {
		$_SERVER['REQUEST_URI'] = $uri;
		return ( new Storefront_Rest_Requests() )->boot_as_storefront( true );
	}

	/** Theme routes that render shopper prices boot like pages. */
	public function test_price_routes_boot_as_storefront(): void {
		$this->assertFalse( $this->rest_after_filter( '/wp-json/aggressive-apparel/v1/search?q=tee' ) );
		$this->assertFalse( $this->rest_after_filter( '/subdir/wp-json/aggressive-apparel/v1/search/' ) );
	}

	/** Plain-permalink requests (`?rest_route=`) are recognised too. */
	public function test_rest_route_query_parameter(): void {
		$_GET['rest_route'] = '/aggressive-apparel/v1/search';
		$this->assertFalse( $this->rest_after_filter( '/?rest_route=/aggressive-apparel/v1/search' ) );

		$_GET['rest_route'] = '/aggressive-apparel/v1/catalog/facets';
		$this->assertTrue( $this->rest_after_filter( '/?rest_route=/aggressive-apparel/v1/catalog/facets' ) );
	}

	/** Every other REST route keeps WooCommerce's own handling. */
	public function test_other_routes_stay_rest(): void {
		$this->assertTrue( $this->rest_after_filter( '/wp-json/aggressive-apparel/v1/catalog/facets' ) );
		$this->assertTrue( $this->rest_after_filter( '/wp-json/wc/store/v1/cart' ) );
		$this->assertTrue( $this->rest_after_filter( '/wp-json/wp/v2/posts' ) );
		$this->assertTrue( $this->rest_after_filter( '/wp-json/evil/aggressive-apparel/v1/search-like' ) );
	}

	/** A page request is never turned into a REST request. */
	public function test_never_marks_pages_as_rest(): void {
		$_SERVER['REQUEST_URI'] = '/shop/';
		$this->assertFalse( ( new Storefront_Rest_Requests() )->boot_as_storefront( false ) );
	}
}
