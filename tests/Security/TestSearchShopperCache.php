<?php
/**
 * Search responses must not share shopper-specific prices.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Security;

use Aggressive_Apparel\Core\Search_Index;
use Aggressive_Apparel\Core\Search_Rest;
use Aggressive_Apparel\Core\Search_Results;
use WP_REST_Request;
use WP_UnitTestCase;

/** Public autocomplete pricing isolation. */
class TestSearchShopperCache extends WP_UnitTestCase {

	/** A member's filtered price must not be reused for an anonymous shopper. */
	public function test_product_prices_are_resolved_for_each_shopper(): void {
		update_option( 'woocommerce_coming_soon', 'no' );
		$product = new \WC_Product_Simple();
		$product->set_name( 'Zzshopperprice Tee' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '100' );
		$product->save();
		$index = new Search_Index();
		$index->maybe_install();
		$index->sync_ids( array( $product->get_id() ) );
		$search  = new Search_Rest( new Search_Results( $index ) );
		$request = new WP_REST_Request( 'GET', '/aggressive-apparel/v1/search' );
		$request->set_param( 'query', 'Zzshopperprice' );
		$request->set_param( 'scope', 'product' );
		$member = self::factory()->user->create( array( 'role' => 'customer' ) );
		$price  = static fn( $value ): string => get_current_user_id() === $member ? '10' : (string) $value;
		add_filter( 'woocommerce_product_get_price', $price );
		try {
			wp_set_current_user( $member );
			$member_data = $search->handle( $request )->get_data();
			wp_set_current_user( 0 );
			$guest_response = $search->handle( $request );
			$guest_data     = $guest_response->get_data();
			$this->assertSame( 'private, no-store', $guest_response->get_headers()['Cache-Control'] );
			$this->assertNotEmpty( $member_data['groups'][0]['items'] );
			$this->assertNotEmpty( $guest_data['groups'][0]['items'] );
			$this->assertNotSame( $member_data['groups'][0]['items'][0]['price'], $guest_data['groups'][0]['items'][0]['price'] );
		} finally {
			remove_filter( 'woocommerce_product_get_price', $price );
			wp_set_current_user( 0 );
		}
	}
}
