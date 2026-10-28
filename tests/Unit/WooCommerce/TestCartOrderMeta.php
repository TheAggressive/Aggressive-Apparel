<?php
/**
 * Cart order-meta integration tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Cart_Order_Meta;
use WP_UnitTestCase;

/**
 * Verify the order-meta script follows the rendered cart and checkout blocks.
 */
class TestCartOrderMeta extends WP_UnitTestCase {

	/**
	 * Clean up the asset queue between tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		wp_dequeue_script( Cart_Order_Meta::SCRIPT_HANDLE );
		wp_deregister_script( Cart_Order_Meta::SCRIPT_HANDLE );
	}

	/**
	 * Clean up the asset queue between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_dequeue_script( Cart_Order_Meta::SCRIPT_HANDLE );
		wp_deregister_script( Cart_Order_Meta::SCRIPT_HANDLE );

		parent::tearDown();
	}

	/**
	 * The integration should hook only the cart and checkout blocks.
	 *
	 * @return void
	 */
	public function test_uses_block_specific_render_hooks(): void {
		$integration = new Cart_Order_Meta();
		$integration->init();

		$this->assertNotFalse( has_filter( 'render_block_woocommerce/cart', array( $integration, 'enqueue_script_for_block' ) ) );
		$this->assertNotFalse( has_filter( 'render_block_woocommerce/checkout', array( $integration, 'enqueue_script_for_block' ) ) );
		$this->assertFalse( has_filter( 'render_block', array( $integration, 'enqueue_script_for_block' ) ) );
	}

	/**
	 * Rendering a cart block enqueues the script with WooCommerce's globals.
	 *
	 * @return void
	 */
	public function test_cart_block_enqueues_script_with_woo_dependencies(): void {
		$integration = new Cart_Order_Meta();
		$content     = '<div class="wp-block-woocommerce-cart"></div>';

		$result = $integration->enqueue_script_for_block( $content );

		$this->assertSame( $content, $result );
		$this->assertTrue( wp_script_is( Cart_Order_Meta::SCRIPT_HANDLE, 'enqueued' ) );

		$script = wp_scripts()->query( Cart_Order_Meta::SCRIPT_HANDLE );
		$this->assertNotFalse( $script );
		$this->assertContains( 'wc-blocks-checkout', $script->deps );
		$this->assertContains( 'wc-price-format', $script->deps );
	}

	/**
	 * The script receives the free-shipping wording and currency format once.
	 *
	 * @return void
	 */
	public function test_prints_free_shipping_data_once(): void {
		$integration = new Cart_Order_Meta();

		$integration->enqueue_script();
		$integration->enqueue_script();

		// wp_add_inline_script() casts missing data with (array) false, so the
		// list can start with a `false` placeholder; count real scripts only.
		$before = wp_scripts()->get_data( Cart_Order_Meta::SCRIPT_HANDLE, 'before' );
		$this->assertIsArray( $before );
		$before = array_values( array_filter( $before ) );
		$this->assertCount( 1, $before );
		$this->assertStringContainsString( 'window.aaCartOrderMeta', $before[0] );
		$this->assertStringContainsString( '"i18n"', $before[0] );
		$this->assertStringContainsString( '"currencyMinorUnit"', $before[0] );
		$this->assertStringContainsString( '"youSave":"You save"', $before[0] );
	}
}
