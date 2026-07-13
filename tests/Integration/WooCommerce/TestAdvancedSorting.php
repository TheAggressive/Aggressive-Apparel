<?php
/**
 * Advanced sorting REST integration tests.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Advanced_Sorting;
use Aggressive_Apparel\WooCommerce\Sale_Category;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Covers product filters applied to custom-sorted product IDs.
 */
class TestAdvancedSorting extends WP_UnitTestCase {

	/** REST route under test. */
	private const ROUTE = '/aggressive-apparel/v1/sorted-products';

	/** Sorting service under test. */
	private Advanced_Sorting $sorting;

	/** Previous REMOTE_ADDR value. */
	private string $previous_remote_addr = '';

	/**
	 * Register the route on a fresh REST server.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( '\WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$this->sorting = new Advanced_Sorting();
		$this->sorting->init();
		delete_transient( 'wc_products_onsale' );
		$this->previous_remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );
	}

	/**
	 * Reset the REST server.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		if ( '' === $this->previous_remote_addr ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->previous_remote_addr;
		}
		remove_all_filters( 'aggressive_apparel_advanced_sorting_rate_limit_max' );
		remove_all_filters( 'aggressive_apparel_advanced_sorting_rate_limit_window' );

		parent::tearDown();
	}

	/**
	 * Create a featured product for the custom-sort endpoint.
	 *
	 * @param float  $price        Regular price.
	 * @param float  $sale_price   Optional active sale price.
	 * @param string $stock_status WooCommerce stock status.
	 * @return int Product ID.
	 */
	private function create_product( float $price, float $sale_price = 0, string $stock_status = 'instock' ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Sorted product ' . wp_generate_password( 6, false ) );
		$product->set_regular_price( (string) $price );
		if ( $sale_price > 0 ) {
			$product->set_sale_price( (string) $sale_price );
		}
		$product->set_stock_status( $stock_status );
		$product->set_featured( true );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product_id = $product->save();

		return (int) $product_id;
	}

	/**
	 * Dispatch the sorted-products route through REST validation.
	 *
	 * @param array<string, mixed> $params Request query params.
	 * @return WP_REST_Response
	 */
	private function dispatch( array $params ): WP_REST_Response {
		$request = new WP_REST_Request( 'GET', self::ROUTE );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_do_request( $request );
	}

	/**
	 * Coming-soon mode must not leak sorted product IDs to the public.
	 *
	 * @return void
	 */
	public function test_coming_soon_hides_sorted_ids_from_anonymous_users(): void {
		$this->create_product( 10 );
		update_option( 'woocommerce_coming_soon', 'yes' );
		wp_set_current_user( 0 );

		$response = $this->dispatch( array( 'sort' => 'featured' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $data['ids'] );
		$this->assertSame( 0, (int) $data['total'] );
		$this->assertSame( 0, (int) $data['totalPages'] );

		update_option( 'woocommerce_coming_soon', 'no' );
	}

	/**
	 * Shop managers can still preview sorted IDs while coming soon is on.
	 *
	 * @return void
	 */
	public function test_coming_soon_allows_shop_managers_to_preview(): void {
		$product_id = $this->create_product( 10 );
		update_option( 'woocommerce_coming_soon', 'yes' );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$response = $this->dispatch( array( 'sort' => 'featured' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertContains( $product_id, array_map( 'intval', $data['ids'] ) );

		update_option( 'woocommerce_coming_soon', 'no' );
		wp_set_current_user( 0 );
	}

	/**
	 * The On Sale toggle is honored before custom-sort pagination.
	 *
	 * @return void
	 */
	public function test_on_sale_filter_applies_to_featured_sort(): void {
		$sale_id = $this->create_product( 100, 75 );
		$this->create_product( 50 );
		( new Sale_Category() )->sync_product( $sale_id );

		$response = $this->dispatch(
			array(
				'sort'    => 'featured',
				'on_sale' => true,
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $sale_id ), array_map( 'intval', $data['ids'] ) );
		$this->assertSame( 1, (int) $data['total'] );
	}

	/**
	 * Price and stock filters remain active when a custom sort is selected.
	 *
	 * @return void
	 */
	public function test_price_and_stock_filters_apply_to_featured_sort(): void {
		$expected_id = $this->create_product( 50 );
		$this->create_product( 200 );
		$this->create_product( 40, 0, 'outofstock' );

		$response = $this->dispatch(
			array(
				'sort'      => 'featured',
				'max_price' => 75,
				'stock'     => 'instock',
			)
		);
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( $expected_id ), array_map( 'intval', $data['ids'] ) );
		$this->assertSame( 1, (int) $data['total'] );
	}

	/**
	 * Custom sorting is paginated by SQL rather than slicing a full ID array.
	 *
	 * @return void
	 */
	public function test_featured_sort_returns_one_bounded_page_and_total(): void {
		$this->create_product( 10 );
		$this->create_product( 20 );
		$this->create_product( 30 );

		$response = $this->dispatch(
			array(
				'sort'     => 'featured',
				'per_page' => 2,
				'page'     => 2,
			)
		);
		$data     = $response->get_data();

		$this->assertCount( 1, $data['ids'] );
		$this->assertSame( 3, (int) $data['total'] );
		$this->assertSame( 2, (int) $data['totalPages'] );
	}

	/**
	 * Savings ordering ranks discounts in SQL and leaves regular products last.
	 *
	 * @return void
	 */
	public function test_savings_sort_orders_discount_percentage(): void {
		$half_price    = $this->create_product( 100, 50 );
		$quarter_saved = $this->create_product( 100, 75 );
		$regular       = $this->create_product( 100 );

		$data = $this->dispatch( array( 'sort' => 'savings' ) )->get_data();
		$ids  = array_map( 'intval', $data['ids'] );

		$this->assertSame( $half_price, $ids[0] );
		$this->assertSame( $quarter_saved, $ids[1] );
		$this->assertSame( $regular, $ids[2] );
	}

	/**
	 * Anonymous callers are throttled through the shared rate limiter.
	 *
	 * @return void
	 */
	public function test_anonymous_sorted_products_requests_are_rate_limited(): void {
		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.47';

		add_filter( 'aggressive_apparel_advanced_sorting_rate_limit_max', static fn(): int => 1 );
		add_filter( 'aggressive_apparel_advanced_sorting_rate_limit_window', static fn(): int => 60 );

		$this->create_product( 100 );

		$first  = $this->dispatch( array( 'sort' => 'featured' ) );
		$second = $this->dispatch( array( 'sort' => 'featured' ) );

		$this->assertSame( 200, $first->get_status() );
		$this->assertSame( 429, $second->get_status() );
		$this->assertSame( '60', $second->get_headers()['Retry-After'] ?? '' );
		$this->assertSame( array( 'error' => 'rate_limited' ), $second->get_data() );
	}
}
