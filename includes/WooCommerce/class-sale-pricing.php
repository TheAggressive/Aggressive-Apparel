<?php
/**
 * Shared sale discount maths and merchant-authored sale wording.
 *
 * Product badges and the price line both describe the same discount, so both
 * derive it here. Keeping one implementation stops the badge and the "Save X%"
 * line disagreeing when only one of them is changed.
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
 * Sale discount helpers.
 */
final class Sale_Pricing {
	/**
	 * Token merchants write where the discount number should appear.
	 *
	 * @var string
	 */
	public const PERCENT_TOKEN = '{percent}';

	/**
	 * Calculate the whole-number sale discount for a product.
	 *
	 * A variable product describes the discount on its cheapest variation —
	 * the one its "From $X" price shows — never a minimum sale price set
	 * against a minimum regular price taken from another variation. When that
	 * cheapest variation isn't discounted the result is 0, and the wording
	 * falls back to a phrase with no number.
	 *
	 * @param \WC_Product $product Product object.
	 * @return int Percentage 1-100, or 0 when no single figure applies.
	 */
	public static function get_discount_percentage( \WC_Product $product ): int {
		if ( $product instanceof \WC_Product_Variable ) {
			$cheapest = self::cheapest_variation_prices( $product );
			$regular  = null !== $cheapest ? $cheapest['regular'] : 0.0;
			$sale     = null !== $cheapest ? $cheapest['price'] : 0.0;
		} else {
			$regular = (float) $product->get_regular_price();
			$sale    = (float) $product->get_sale_price();
		}

		if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
			return 0;
		}

		return (int) round( ( ( $regular - $sale ) / $regular ) * 100 );
	}

	/**
	 * The cheapest variation's displayed price and its own regular price,
	 * plus the highest displayed price, all from one `get_variation_prices()`
	 * snapshot so they always agree.
	 *
	 * Among variations tied at the lowest price, the lowest regular price is
	 * taken, so the implied discount is never overstated. A variation with no
	 * regular price falls back to its active price, which keeps
	 * `regular >= price`.
	 *
	 * @param \WC_Product_Variable $product Variable product.
	 * @return array{price: float, regular: float, max: float}|null Null when no variation has a price.
	 */
	public static function cheapest_variation_prices( \WC_Product_Variable $product ): ?array {
		$variation_prices = $product->get_variation_prices( true );
		$active           = isset( $variation_prices['price'] ) && is_array( $variation_prices['price'] )
			? array_map( 'floatval', $variation_prices['price'] )
			: array();

		if ( empty( $active ) ) {
			return null;
		}

		$min            = min( $active );
		$regular_prices = isset( $variation_prices['regular_price'] ) && is_array( $variation_prices['regular_price'] )
			? $variation_prices['regular_price']
			: array();
		$regulars       = array();
		foreach ( $active as $variation_id => $amount ) {
			if ( $amount === $min && isset( $regular_prices[ $variation_id ] ) && '' !== $regular_prices[ $variation_id ] ) {
				$regulars[] = (float) $regular_prices[ $variation_id ];
			}
		}

		return array(
			'price'   => $min,
			'regular' => empty( $regulars ) ? $min : min( $regulars ),
			'max'     => max( $active ),
		);
	}

	/**
	 * Resolve merchant-authored sale wording against a discount.
	 *
	 * A template without the token is static wording ("Now on Sale") and is
	 * returned verbatim, so `$fallback` is consulted only when the template
	 * asks for a number the product cannot supply. Pass an empty `$fallback`
	 * to mean "render nothing in that case".
	 *
	 * @param string $template Wording, optionally containing `{percent}`.
	 * @param string $fallback Wording used when the token cannot be resolved.
	 * @param int    $percent  Whole-number discount, or 0 when uncomputable.
	 * @return string
	 */
	public static function format_text( string $template, string $fallback, int $percent ): string {
		if ( ! str_contains( $template, self::PERCENT_TOKEN ) ) {
			return $template;
		}

		if ( $percent <= 0 ) {
			return $fallback;
		}

		return str_replace( self::PERCENT_TOKEN, (string) $percent, $template );
	}
}
