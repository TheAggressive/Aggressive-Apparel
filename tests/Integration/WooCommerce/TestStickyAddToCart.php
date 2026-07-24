<?php
/**
 * Sticky add-to-cart integration tests.
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Sticky_Add_To_Cart;
use WP_UnitTestCase;

/**
 * Verify the server-rendered accessibility and hydration contract.
 */
final class TestStickyAddToCart extends WP_UnitTestCase {

	/**
	 * Product created for the current test.
	 */
	private int $product_id = 0;

	/**
	 * Create a product page context.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for sticky-cart tests.' );
		}

		$product = new \WC_Product_Simple();
		$product->set_name( 'Sticky Cart Test Product' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '42' );
		$this->product_id = $product->save();

		$this->go_to( get_permalink( $this->product_id ) );
	}

	/**
	 * Remove the product fixture.
	 */
	public function tearDown(): void {
		if ( $this->product_id > 0 && function_exists( 'wc_delete_product' ) ) {
			wc_delete_product( $this->product_id, true );
		}

		parent::tearDown();
	}

	/**
	 * Hidden SSR markup must be non-interactive until hydration reveals it.
	 */
	public function test_hidden_bar_exposes_reactive_accessibility_contract(): void {
		$sticky_cart = new Sticky_Add_To_Cart();

		ob_start();
		$sticky_cart->render_sticky_bar();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'class="aa-sticky-cart"', $html );
		$this->assertStringContainsString( 'data-wp-init="callbacks.init"', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( 'data-wp-bind--aria-hidden="state.ariaHidden"', $html );
		$this->assertMatchesRegularExpression(
			'/<div\s+class="aa-sticky-cart"[^>]*\sinert(?:\s|>)/s',
			$html
		);
		$this->assertStringContainsString( 'data-wp-bind--inert="!state.isVisible"', $html );
	}
}
