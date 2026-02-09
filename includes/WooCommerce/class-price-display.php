<?php
/**
 * Price Display Class
 *
 * Replaces the default variable-product price range with smarter formatting:
 * "From $X" on archives, "Save X%" on sale items.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Price Display
 *
 * @since 1.17.0
 */
class Price_Display {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_get_price_html', array( $this, 'format_price_html' ), 20, 2 );
	}

	/**
	 * Format the price HTML for variable and on-sale products.
	 *
	 * @param string      $price   Default price HTML.
	 * @param \WC_Product $product Product object.
	 * @return string Modified price HTML.
	 */
	public function format_price_html( string $price, \WC_Product $product ): string {
		// "From $X" for variable products on archive pages.
		if ( $product instanceof \WC_Product_Variable && ! is_product() ) {
			$min_price = $product->get_variation_price( 'min', true );
			if ( '' !== $min_price ) {
				$price = sprintf(
					/* translators: %s: formatted minimum price. */
					esc_html__( 'From %s', 'aggressive-apparel' ),
					wc_price( (float) $min_price ),
				);
			}
		}

		// Append "Save X%" on sale items.
		if ( $product->is_on_sale() ) {
			$percentage = $this->get_sale_percentage( $product );
			if ( $percentage > 0 ) {
				$price .= sprintf(
					' <span class="aggressive-apparel-price-save">%s</span>',
					sprintf(
						/* translators: %d: discount percentage. */
						esc_html__( 'Save %d%%', 'aggressive-apparel' ),
						$percentage,
					),
				);
			}
		}

		return $price;
	}

	/**
	 * Calculate the sale discount percentage.
	 *
	 * @param \WC_Product $product Product object.
	 * @return int Percentage (0-100).
	 */
	private function get_sale_percentage( \WC_Product $product ): int {
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();

		if ( $regular <= 0 || $sale <= 0 ) {
			if ( $product instanceof \WC_Product_Variable ) {
				$regular = (float) $product->get_variation_regular_price( 'min' );
				$sale    = (float) $product->get_variation_sale_price( 'min' );
			}
		}

		if ( $regular <= 0 || $sale >= $regular ) {
			return 0;
		}

		return (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
	}
}
