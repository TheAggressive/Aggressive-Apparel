<?php
/**
 * Wishlist account integration contracts.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Wishlist;
use Aggressive_Apparel\WooCommerce\Wishlist_Integration;
use WP_UnitTestCase;

/** Exercise real WooCommerce routes instead of a second theme data store. */
class TestWishlistIntegration extends WP_UnitTestCase {
	/** Integration hooks under test. */
	private Wishlist_Integration $integration;

	/** Prepare an enabled theme and registered Store API. */
	public function setUp(): void {
		parent::setUp();
		update_option( Feature_Settings::OPTION_KEY, array( 'wishlist' => true ) );
		$this->integration = new Wishlist_Integration();
		$this->integration->init();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
	}

	/** Restore request hooks and icon defaults. */
	public function tearDown(): void {
		remove_filter( 'pre_option_woocommerce_product_wishlist_enabled', array( $this->integration, 'enable_backend' ) );
		remove_filter( 'render_block_woocommerce/add-to-wishlist-button', array( $this->integration, 'render_button' ) );
		remove_filter( 'render_block_woocommerce/wishlist', array( $this->integration, 'render_list' ) );
		remove_action( 'admin_init', array( $this->integration, 'register_icon_settings' ), 20 );
		delete_option( Wishlist_Integration::ICON_OPTION );
		delete_option( Wishlist_Integration::SAVED_ICON_OPTION );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/** Feature toggles enable WooCommerce without persisting its option. */
	public function test_theme_toggle_controls_backend(): void {
		$this->assertSame( 'yes', $this->integration->enable_backend( false ) );
		update_option( Feature_Settings::OPTION_KEY, array( 'wishlist' => false ) );
		$this->assertFalse( $this->integration->enable_backend( false ) );
	}

	/** Guests never receive authentication tokens or account mode. */
	public function test_guest_config_keeps_browser_storage(): void {
		wp_set_current_user( 0 );
		$config = Wishlist_Integration::get_client_config();
		$this->assertFalse( $config['account'] );
		$this->assertSame( '', $config['restNonce'] );
		$this->assertSame( '', $config['nonce'] );
	}

	/** Signed-in clients receive the two distinct nonce types. */
	public function test_account_config_authenticates_store_api(): void {
		$config = Wishlist_Integration::get_client_config();
		$this->assertTrue( $config['account'] );
		$this->assertNotFalse( wp_verify_nonce( $config['restNonce'], 'wp_rest' ) );
		$this->assertNotFalse( wp_verify_nonce( $config['nonce'], 'wc_store_api' ) );
	}

	/** Custom silhouettes retain separate idle and saved states. */
	public function test_button_can_override_both_icons(): void {
		$html = Wishlist::get_toggle_icon_html( 'search', 'heart' );
		$this->assertStringContainsString( 'aa-wishlist-icon-idle', $html );
		$this->assertStringContainsString( 'aa-wishlist-icon-saved', $html );
		$this->assertStringNotContainsString( '<script', Wishlist::get_toggle_icon_html( '<script>', 'heart' ) );
	}

	/** The native button is restyled while retaining WooCommerce actions. */
	public function test_native_button_preserves_its_variation_action(): void {
		$block = new \WP_Block( array( 'blockName' => 'woocommerce/add-to-wishlist-button', 'attrs' => array() ) );
		$block->context['postId'] = 42;
		$original = '<button class="wc-block-add-to-wishlist-button__toggle" data-wp-on--click="actions.onClickToggle"><svg><path d="spinner"/></svg><span class="wc-block-add-to-wishlist-button__icon--empty"><svg><path d="star"/></svg></span><span class="wc-block-add-to-wishlist-button__icon--filled"><svg><path d="saved-star"/></svg></span></button>';
		$html = $this->integration->render_button( $original, array(), $block );
		$this->assertStringContainsString( 'actions.onClickToggle', $html );
		$this->assertStringContainsString( 'd="spinner"', $html );
		$this->assertStringContainsString( 'data-aa-wishlist-native', $html );
		$this->assertStringNotContainsString( 'd="star"', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
	}

	/** Guest saving remains available where WooCommerce hides its button. */
	public function test_native_button_is_available_to_guests(): void {
		wp_set_current_user( 0 );
		$block = new \WP_Block( array( 'blockName' => 'woocommerce/add-to-wishlist-button', 'attrs' => array() ) );
		$block->context['postId'] = 42;
		$html = $this->integration->render_button( '', array(), $block );
		$this->assertStringContainsString( 'data-aa-product-id="42"', $html );
	}

	/** Parent products wait for choices; specific variations retain those choices. */
	public function test_variation_saves_require_selected_options(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Wishlist shirt' );
		$parent->set_status( 'publish' );
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'M', 'L' ) );
		$attribute->set_variation( true );
		$parent->set_attributes( array( $attribute ) );
		$parent->save();
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_attributes( array( 'size' => 'M' ) );
		$variation->set_regular_price( '25' );
		$variation->set_status( 'publish' );
		$variation->save();
		\WC_Product_Variable::sync( $parent->get_id() );

		$html = Wishlist::get_heart_button_html( $parent->get_id() );
		$this->assertStringContainsString( 'data-aa-product-type="variable"', $html );
		$this->assertStringNotContainsString( 'data-aa-product-url', $html );

		$request = new \WP_REST_Request( 'POST', '/wc/store/v1/shopper-lists/wishlist/items' );
		$request->set_header( 'Nonce', wp_create_nonce( 'wc_store_api' ) );
		$request->set_param( 'product_id', $parent->get_id() );
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
		$request->set_param( 'product_id', $variation->get_id() );
		$saved = rest_do_request( $request );
		$this->assertSame( 201, $saved->get_status() );
		$this->assertSame( $variation->get_id(), $saved->get_data()['variation_id'] );
		$this->assertSame( 'M', $saved->get_data()['variation'][0]['value'] );
	}

	/** Wishlist saves are independent of whether a published product is in stock. */
	public function test_out_of_stock_product_can_be_saved_without_being_purchasable(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Sold out wishlist fixture' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '25' );
		$product->set_stock_status( 'outofstock' );
		$product->save();
		$request = new \WP_REST_Request( 'POST', '/wc/store/v1/shopper-lists/wishlist/items' );
		$request->set_header( 'Nonce', wp_create_nonce( 'wc_store_api' ) );
		$request->set_param( 'product_id', $product->get_id() );
		$response = rest_do_request( $request );
		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( $product->get_id(), $response->get_data()['id'] );
		$this->assertFalse( $response->get_data()['is_purchasable'] );
	}

	/** The native product-page button prevents a second theme button. */
	public function test_native_template_owns_only_the_queried_product_button(): void {
		global $_wp_current_template_content;
		$previous = $_wp_current_template_content;
		$product_id = self::factory()->post->create( array( 'post_type' => 'product' ) );
		$this->go_to( get_permalink( $product_id ) );
		$_wp_current_template_content = '<!-- wp:woocommerce/add-to-cart-with-options /-->';
		try {
			$this->assertTrue( Wishlist_Integration::uses_native_button( $product_id ) );
			$this->assertFalse( Wishlist_Integration::uses_native_button( $product_id + 1 ) );
		} finally {
			$_wp_current_template_content = $previous;
		}
	}

	/** Account saves appear in WooCommerce and stay isolated between customers. */
	public function test_account_wishlist_uses_woocommerce_storage_and_permissions(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Wishlist API fixture' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '25' );
		$product->save();

		$request = new \WP_REST_Request( 'POST', '/wc/store/v1/shopper-lists/wishlist/items' );
		$request->set_header( 'Nonce', wp_create_nonce( 'wc_store_api' ) );
		$request->set_param( 'product_id', $product->get_id() );
		$response = rest_do_request( $request );
		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$key = $response->get_data()['key'];

		$list = rest_do_request( new \WP_REST_Request( 'GET', '/wc/store/v1/shopper-lists/wishlist/items' ) );
		$this->assertSame( $product->get_id(), $list->get_data()[0]['id'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$other = rest_do_request( new \WP_REST_Request( 'GET', '/wc/store/v1/shopper-lists/wishlist/items' ) );
		$this->assertSame( array(), $other->get_data() );

		wp_set_current_user( 0 );
		$guest = rest_do_request( new \WP_REST_Request( 'GET', '/wc/store/v1/shopper-lists/wishlist/items' ) );
		$this->assertSame( 401, $guest->get_status() );
		$this->assertNotEmpty( $key );
	}
}
