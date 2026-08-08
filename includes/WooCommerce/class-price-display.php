<?php
/**
 * Price Display Class
 *
 * Replaces the default variable-product price range with smarter formatting:
 * collapses the "min–max" range to a single starting price with a configurable,
 * translatable prefix (e.g. "From $34.99") on both archives and product pages,
 * and appends "Save X%" on sale items. Amounts are always formatted through
 * `wc_price()` so the active WooCommerce currency, symbol placement, decimal and
 * thousand separators, and tax-display settings are respected — nothing is
 * hardcoded to a single locale.
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
		// Collapse the variable-product range to a single starting price on
		// both archives and product pages. WooCommerce still reveals the exact
		// variation price in the add-to-cart form once the shopper selects
		// their options, so the headline price stays clean and stable.
		$collapsed = self::from_price_html( $product );
		if ( null !== $collapsed ) {
			$price = $collapsed;
		}

		// Append the savings line on sale items. An empty fallback means a
		// product with no single discount figure gets no savings line at all —
		// the sale badge already says it is reduced.
		if ( $product->is_on_sale() ) {
			$savings = Sale_Pricing::format_text(
				Feature_Settings::get_price_savings_text(),
				'',
				Sale_Pricing::get_discount_percentage( $product ),
			);

			if ( '' !== $savings ) {
				$price .= sprintf(
					' <span class="aggressive-apparel-price-save">%s</span>',
					esc_html( $savings ),
				);
			}
		}

		return $price;
	}

	/**
	 * Whether Smart Price Display's range-collapse behaviour is active.
	 *
	 * Every surface that renders a variable-product price (product cards, the
	 * single product headline, Quick View, the sticky cart, Frequently Bought
	 * Together, …) consults this so the "From $X" treatment is applied — or
	 * withheld — everywhere at once.
	 *
	 * @return bool
	 */
	public static function is_range_collapse_enabled(): bool {
		return Feature_Settings::is_enabled( 'price_display' );
	}

	/**
	 * Price config for Interactivity stores that format variable prices client-side.
	 *
	 * Single source of truth for the flag + prefix seeded into `wp_interactivity_state`
	 * by the Quick View and Wishlist stores, so the "collapse ⇒ prefix, otherwise
	 * blank" rule is defined once and the feature flag is read a single time.
	 *
	 * @return array{collapseVariablePrice: bool, priceStartingPrefix: string}
	 */
	public static function interactivity_price_config(): array {
		$enabled = self::is_range_collapse_enabled();

		return array(
			'collapseVariablePrice' => $enabled,
			'priceStartingPrefix'   => $enabled ? Feature_Settings::get_price_starting_prefix() : '',
		);
	}

	/**
	 * Collapsed "starting price" markup for a variable product.
	 *
	 * Returns `null` — meaning "leave the native price untouched" — when the
	 * feature is off, the product is not variable, the minimum price is
	 * unavailable, or every variation shares the same price (no range to
	 * collapse, so no "from" prefix is added).
	 *
	 * @param \WC_Product $product Product object.
	 * @return string|null Collapsed price HTML, or null to keep the default.
	 */
	public static function from_price_html( \WC_Product $product ): ?string {
		$prices = self::resolve_from_prices( $product );

		if ( null === $prices ) {
			return null;
		}

		return self::with_prefix( wc_price( $prices['price'] ) );
	}

	/**
	 * Plain-text collapsed "starting price" for JS/state/aria contexts.
	 *
	 * Mirrors {@see self::from_price_html()} but strips markup and decodes
	 * entities so the string is safe to place in interactivity state or an
	 * `aria-label`. When `$regular` is true the regular (pre-sale) price of the
	 * same cheapest variation the "From" figure represents is used, so a
	 * struck-through "was" price never describes a different variation.
	 *
	 * Active and regular collapse together (both or neither): a caller that
	 * shows `<del>regular</del><ins>active</ins>` can never end up mixing a
	 * collapsed price with a native range.
	 *
	 * @param \WC_Product $product Product object.
	 * @param bool        $regular Use the regular (pre-sale) price.
	 * @return string|null Collapsed plain-text price, or null to keep the default.
	 */
	public static function from_price_text( \WC_Product $product, bool $regular = false ): ?string {
		$prices = self::resolve_from_prices( $product );

		if ( null === $prices ) {
			return null;
		}

		$amount = $regular ? $prices['regular'] : $prices['price'];
		$html   = self::with_prefix( wc_price( $amount ) );

		return trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Resolve the consistent active + regular "from" amounts, or null when N/A.
	 *
	 * Both amounts are derived from a single `get_variation_prices()` snapshot so
	 * they always agree: applicability is judged on the active (displayed) range,
	 * and the regular amount is the regular price of the same cheapest variation
	 * the active minimum represents (falling back to the active minimum when that
	 * variation carries no regular price, which keeps `regular >= active`).
	 *
	 * @param \WC_Product $product Product object.
	 * @return array{price: float, regular: float}|null Consistent pair, or null to keep the default.
	 */
	private static function resolve_from_prices( \WC_Product $product ): ?array {
		if ( ! self::is_range_collapse_enabled() || ! $product instanceof \WC_Product_Variable ) {
			return null;
		}

		$variation_prices = $product->get_variation_prices( true );
		$active           = isset( $variation_prices['price'] ) && is_array( $variation_prices['price'] )
			? array_map( 'floatval', $variation_prices['price'] )
			: array();

		if ( empty( $active ) ) {
			return null;
		}

		$min = min( $active );
		$max = max( $active );

		// No spread between variations: WooCommerce already renders a single,
		// exact price — prefixing it with "From" would misrepresent it.
		if ( $min === $max ) {
			return null;
		}

		// Regular price of the cheapest variation(s) — the honest "was" figure
		// for the "From" price. Among any variations tied at the active minimum,
		// take the lowest regular so the implied discount is never overstated.
		$regular_prices    = isset( $variation_prices['regular_price'] ) && is_array( $variation_prices['regular_price'] )
			? $variation_prices['regular_price']
			: array();
		$cheapest_regulars = array();
		foreach ( $active as $variation_id => $amount ) {
			if ( $amount === $min && isset( $regular_prices[ $variation_id ] ) && '' !== $regular_prices[ $variation_id ] ) {
				$cheapest_regulars[] = (float) $regular_prices[ $variation_id ];
			}
		}

		return array(
			'price'   => $min,
			'regular' => empty( $cheapest_regulars ) ? $min : min( $cheapest_regulars ),
		);
	}

	/**
	 * Prepend the configured (translatable) starting-price prefix to an amount.
	 *
	 * @param string $amount Trusted WooCommerce currency markup (from wc_price()).
	 * @return string
	 */
	private static function with_prefix( string $amount ): string {
		$prefix = Feature_Settings::get_price_starting_prefix();

		if ( '' === $prefix ) {
			return $amount;
		}

		// Prefix is admin-supplied plain text (escaped); $amount is trusted
		// WooCommerce currency markup. A space separates them regardless of the
		// active currency's own symbol placement.
		return '<span class="aggressive-apparel-price-from-prefix">' . esc_html( $prefix ) . '</span> ' . $amount;
	}
}
