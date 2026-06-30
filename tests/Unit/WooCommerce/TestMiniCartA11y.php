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
 * Verify mini-cart assets load only when the block is rendered.
 */
class TestMiniCartA11y extends WP_UnitTestCase {

	/**
	 * Script handle registered by the integration.
	 */
	private const SCRIPT_HANDLE = 'aggressive-apparel-mini-cart-a11y';

	/**
	 * Clean up the script queue between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		wp_dequeue_script( self::SCRIPT_HANDLE );
		wp_deregister_script( self::SCRIPT_HANDLE );

		parent::tearDown();
	}

	/**
	 * Unrelated blocks must not load the mini-cart bundle.
	 *
	 * @return void
	 */
	public function test_unrelated_blocks_do_not_enqueue_script(): void {
		$integration = new Mini_Cart_A11y();

		$integration->inject_drawer_inert(
			'<p>Content</p>',
			array( 'blockName' => 'core/paragraph' )
		);

		$this->assertFalse( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) );
	}

	/**
	 * Rendering a mini-cart should add inert and enqueue its footer bundle.
	 *
	 * @return void
	 */
	public function test_rendered_mini_cart_enqueues_script(): void {
		$integration = new Mini_Cart_A11y();
		$content     = '<div class="wc-block-mini-cart__drawer" aria-hidden="true"></div>';

		$result = $integration->inject_drawer_inert(
			$content,
			array( 'blockName' => 'woocommerce/mini-cart' )
		);

		$this->assertStringContainsString( ' inert', $result );
		$this->assertTrue( wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) );
	}
}
