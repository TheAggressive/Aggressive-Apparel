<?php
/**
 * Variable product fixture shared by WooCommerce test suites.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Support;

/**
 * Builds a published variable product with one variation per price pair.
 */
trait Creates_Variable_Products {

	/**
	 * Create and sync a published, catalog-visible variable product.
	 *
	 * @param list<array{0: float, 1: float}> $prices Per variation: regular, sale (0 = none).
	 * @param string                          $name   Product name.
	 * @return int Product ID.
	 */
	private function create_variable_product( array $prices, string $name = '' ): int {
		$product = new \WC_Product_Variable();
		$product->set_name( '' !== $name ? $name : 'Variable ' . wp_generate_password( 6, false ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product_id = (int) $product->save();

		foreach ( $prices as $price ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $product_id );
			$variation->set_regular_price( (string) $price[0] );
			if ( $price[1] > 0 ) {
				$variation->set_sale_price( (string) $price[1] );
			}
			$variation->set_status( 'publish' );
			$variation->save();
		}
		\WC_Product_Variable::sync( $product_id );

		return $product_id;
	}
}
