<?php
/**
 * Store currency format tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Store_Currency;
use WP_UnitTestCase;

/**
 * Tests for Store_Currency.
 *
 * @covers \Aggressive_Apparel\WooCommerce\Store_Currency
 */
class Test_Store_Currency extends WP_UnitTestCase {

	/**
	 * Every WooCommerce symbol position maps to the Store API prefix/suffix.
	 *
	 * @dataProvider symbol_positions
	 *
	 * @param string $position WooCommerce currency position option.
	 * @param string $prefix   Expected prefix.
	 * @param string $suffix   Expected suffix.
	 */
	public function test_symbol_position_maps_to_prefix_or_suffix( string $position, string $prefix, string $suffix ): void {
		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'woocommerce_currency_pos', $position );

		$context = Store_Currency::context();

		$this->assertSame( $prefix, $context['currencyPrefix'] );
		$this->assertSame( $suffix, $context['currencySuffix'] );
	}

	/**
	 * WooCommerce symbol positions and the prefix/suffix they produce.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public static function symbol_positions(): array {
		return array(
			'left'        => array( 'left', '€', '' ),
			'left_space'  => array( 'left_space', '€ ', '' ),
			'right'       => array( 'right', '', '€' ),
			'right_space' => array( 'right_space', '', ' €' ),
		);
	}

	/**
	 * The symbol is decoded so text bindings never print `&#36;`.
	 */
	public function test_symbol_is_decoded_from_its_html_entity(): void {
		update_option( 'woocommerce_currency', 'USD' );
		update_option( 'woocommerce_currency_pos', 'left' );

		$this->assertStringContainsString( '&', get_woocommerce_currency_symbol() );
		$this->assertSame( '$', Store_Currency::context()['currencyPrefix'] );
	}

	/**
	 * Separators and decimals come from the store's price settings.
	 */
	public function test_separators_and_decimals_follow_store_settings(): void {
		update_option( 'woocommerce_price_decimal_sep', ',' );
		update_option( 'woocommerce_price_thousand_sep', '.' );
		update_option( 'woocommerce_price_num_decimals', '3' );

		$context = Store_Currency::context();

		$this->assertSame( ',', $context['currencyDecimalSeparator'] );
		$this->assertSame( '.', $context['currencyThousandSeparator'] );
		$this->assertSame( 3, $context['currencyMinorUnit'] );
	}
}
