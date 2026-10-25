<?php
/**
 * Sale discount percentage tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Sale_Pricing;
use WP_UnitTestCase;

/**
 * A variable product's discount is its cheapest variation's own discount:
 * never the lowest sale price set against a lowest regular price that
 * belongs to another variation.
 */
class TestSalePricingDiscount extends WP_UnitTestCase {

	/**
	 * A variable product whose sizes carry the given prices.
	 *
	 * @param array<string, array{0: string, 1?: string}> $sizes Size => [regular, sale].
	 * @return \WC_Product_Variable
	 */
	private function variable( array $sizes ): \WC_Product_Variable {
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array_keys( $sizes ) );
		$attribute->set_visible( true );
		$attribute->set_variation( true );

		$product = new \WC_Product_Variable();
		$product->set_name( 'Priced Tee' );
		$product->set_attributes( array( $attribute ) );
		$parent = $product->save();

		foreach ( $sizes as $size => $prices ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent );
			$variation->set_attributes( array( 'size' => strtolower( $size ) ) );
			$variation->set_regular_price( $prices[0] );
			if ( isset( $prices[1] ) ) {
				$variation->set_sale_price( $prices[1] );
			}
			$variation->save();
		}
		\WC_Product_Variable::sync( $parent );

		$fresh = wc_get_product( $parent );
		$this->assertInstanceOf( \WC_Product_Variable::class, $fresh );

		return $fresh;
	}

	/**
	 * The cheapest variation is the discounted one: its own discount, not
	 * $24 against S's $25 (which read "Save 4%").
	 *
	 * @return void
	 */
	public function test_discount_is_the_cheapest_variations_own(): void {
		$product = $this->variable(
			array(
				'S' => array( '25' ),
				'M' => array( '30', '24' ),
			)
		);

		$this->assertSame( 20, Sale_Pricing::get_discount_percentage( $product ) );
		$this->assertSame(
			array(
				'price'   => 24.0,
				'regular' => 30.0,
				'max'     => 25.0,
			),
			Sale_Pricing::cheapest_variation_prices( $product )
		);
	}

	/**
	 * When the cheapest variation isn't discounted there is no single figure.
	 *
	 * @return void
	 */
	public function test_undiscounted_cheapest_variation_gives_no_figure(): void {
		$product = $this->variable(
			array(
				'S' => array( '25' ),
				'M' => array( '30', '28' ),
			)
		);

		$this->assertSame( 0, Sale_Pricing::get_discount_percentage( $product ) );
	}

	/**
	 * Variations tied at the lowest price never overstate the discount.
	 *
	 * @return void
	 */
	public function test_tied_cheapest_variations_never_overstate(): void {
		$product = $this->variable(
			array(
				'S' => array( '24' ),
				'M' => array( '30', '24' ),
			)
		);

		$this->assertSame( 0, Sale_Pricing::get_discount_percentage( $product ) );
	}

	/**
	 * A simple product uses its own prices.
	 *
	 * @return void
	 */
	public function test_simple_product_uses_its_own_prices(): void {
		$product = new \WC_Product_Simple();
		$product->set_regular_price( '50' );
		$product->set_sale_price( '40' );
		$product->save();

		$this->assertSame( 20, Sale_Pricing::get_discount_percentage( $product ) );
	}
}
