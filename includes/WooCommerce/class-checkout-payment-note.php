<?php
/**
 * Checkout payment-step security note.
 *
 * WooCommerce's payment step has an optional description, editable on the
 * Payment options block and empty by default. Give it a security note when
 * the merchant hasn't written one, so the reassurance sits where shoppers
 * enter card details. WooCommerce renders it as the step's own description
 * (from the block's `data-description`), and any text set in the editor wins.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout payment-step security note.
 */
class Checkout_Payment_Note {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'render_block_data', array( $this, 'default_description' ) );
	}

	/**
	 * Default the Payment options block's description.
	 *
	 * @param array<string, mixed> $parsed_block The block being rendered.
	 * @return array<string, mixed>
	 */
	public function default_description( array $parsed_block ): array {
		if ( 'woocommerce/checkout-payment-block' !== ( $parsed_block['blockName'] ?? null ) ) {
			return $parsed_block;
		}

		$attrs = is_array( $parsed_block['attrs'] ?? null ) ? $parsed_block['attrs'] : array();

		if ( '' === trim( (string) ( $attrs['description'] ?? '' ) ) ) {
			$attrs['description'] = __( 'All transactions are secure and encrypted.', 'aggressive-apparel' );
		}

		$parsed_block['attrs'] = $attrs;

		return $parsed_block;
	}
}
