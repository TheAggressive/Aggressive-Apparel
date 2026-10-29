<?php
/**
 * Checkout payment-step note tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Checkout_Payment_Note;
use WP_UnitTestCase;

/**
 * Verify the Payment options block gets a default security note.
 */
class TestCheckoutPaymentNote extends WP_UnitTestCase {

	/**
	 * A parsed block.
	 *
	 * @param string               $name  Block name.
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return array<string, mixed>
	 */
	private function block( string $name, array $attrs = array() ): array {
		return array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}

	/**
	 * A payment block without a description gets the note.
	 *
	 * @return void
	 */
	public function test_defaults_empty_description(): void {
		$service = new Checkout_Payment_Note();

		$this->assertSame(
			'All transactions are secure and encrypted.',
			$service->default_description( $this->block( 'woocommerce/checkout-payment-block' ) )['attrs']['description']
		);
		$this->assertSame(
			'All transactions are secure and encrypted.',
			$service->default_description( $this->block( 'woocommerce/checkout-payment-block', array( 'description' => '  ' ) ) )['attrs']['description']
		);
	}

	/**
	 * A description set in the editor wins.
	 *
	 * @return void
	 */
	public function test_keeps_merchant_description(): void {
		$block = ( new Checkout_Payment_Note() )->default_description(
			$this->block( 'woocommerce/checkout-payment-block', array( 'description' => 'Pay your way.' ) )
		);

		$this->assertSame( 'Pay your way.', $block['attrs']['description'] );
	}

	/**
	 * Other blocks pass through unchanged.
	 *
	 * @return void
	 */
	public function test_ignores_other_blocks(): void {
		$block = $this->block( 'woocommerce/checkout-shipping-methods-block' );

		$this->assertSame( $block, ( new Checkout_Payment_Note() )->default_description( $block ) );
	}

	/**
	 * The filter is registered.
	 *
	 * @return void
	 */
	public function test_registers_filter(): void {
		$service = new Checkout_Payment_Note();
		$service->init();

		$this->assertSame( 10, has_filter( 'render_block_data', array( $service, 'default_description' ) ) );
	}
}
