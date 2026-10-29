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
	 * The filter changes the default, and an empty string shows no note.
	 *
	 * @return void
	 */
	public function test_filter_changes_or_removes_note(): void {
		$service = new Checkout_Payment_Note();
		$block   = $this->block( 'woocommerce/checkout-payment-block' );

		$custom = static fn() => 'Secured by Stripe.';
		add_filter( 'aggressive_apparel_checkout_payment_note', $custom );
		$this->assertSame( 'Secured by Stripe.', $service->default_description( $block )['attrs']['description'] );
		remove_filter( 'aggressive_apparel_checkout_payment_note', $custom );

		add_filter( 'aggressive_apparel_checkout_payment_note', '__return_empty_string' );
		$this->assertSame( $block, $service->default_description( $block ) );
		remove_filter( 'aggressive_apparel_checkout_payment_note', '__return_empty_string' );
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
