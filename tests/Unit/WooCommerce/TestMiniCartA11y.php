<?php
/**
 * Mini-cart accessibility integration tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Mini_Cart_A11y;
use WP_UnitTestCase;

/**
 * Verify the mini-cart accessibility script follows the rendered block.
 */
class TestMiniCartA11y extends WP_UnitTestCase {

	/**
	 * Script handle registered by the integration.
	 */
	private const SCRIPT_HANDLE = 'aggressive-apparel-mini-cart-a11y';

	/**
	 * Clean up the asset queue between tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		wp_dequeue_script( self::SCRIPT_HANDLE );
		wp_deregister_script( self::SCRIPT_HANDLE );
	}

	/**
	 * Clean up the asset queue between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_dequeue_script( self::SCRIPT_HANDLE );
		wp_deregister_script( self::SCRIPT_HANDLE );

		parent::tearDown();
	}

	/**
	 * The integration should use a block-specific filter.
	 *
	 * @return void
	 */
	public function test_uses_block_specific_render_hook(): void {
		$integration = new Mini_Cart_A11y();
		$integration->init();

		$this->assertNotFalse( has_filter( 'render_block_woocommerce/mini-cart', array( $integration, 'enqueue_script_for_block' ) ) );
		$this->assertFalse( has_filter( 'render_block', array( $integration, 'enqueue_script_for_block' ) ) );
	}

	/**
	 * The header trigger must enqueue the script even when Woo portals the drawer.
	 *
	 * @return void
	 */
	public function test_mini_cart_block_enqueues_script(): void {
		$integration = new Mini_Cart_A11y();
		$content     = '<div class="wc-block-mini-cart"><button class="wc-block-mini-cart__button">Cart</button></div>';

		$result = $integration->enqueue_script_for_block( $content );

		$this->assertSame( $content, $result );
		$this->assertTrue( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) );
	}
}
