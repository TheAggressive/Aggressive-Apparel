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
	 * Variable products usually carry no price of their own, so the minimum
	 * variation prices stand in. That deliberately understates a catalog where
	 * the cheapest variation is not the discounted one: such a product returns
	 * 0 and its wording falls back to a phrase with no number.
	 *
	 * @param \WC_Product $product Product object.
	 * @return int Percentage 1-100, or 0 when no single figure applies.
	 */
	public static function get_discount_percentage( \WC_Product $product ): int {
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
