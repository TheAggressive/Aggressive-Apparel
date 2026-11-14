<?php
/**
 * Store Currency
 *
 * The store's currency format, shaped for client-side price formatting.
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
 * Store Currency
 *
 * Mirrors the Store API currency fields (`currency_prefix`, `currency_suffix`,
 * …) so prices a script formats from seeded config match the ones it formats
 * from Store API responses, and both match `wc_price()`. The symbol is
 * decoded: WooCommerce returns it as an HTML entity (`&#36;`), which a
 * `data-wp-text` binding would print verbatim.
 *
 * Site-wide configuration, so it is safe to seed into cached HTML.
 */
final class Store_Currency {

	/**
	 * Currency format in the camelCase shape the theme's scripts read
	 * (`fromCurrencyContext()` in src/utils/store-api-money.ts).
	 *
	 * @return array{
	 *     currencyPrefix: string,
	 *     currencySuffix: string,
	 *     currencyMinorUnit: int,
	 *     currencyDecimalSeparator: string,
	 *     currencyThousandSeparator: string
	 * }
	 */
	public static function context(): array {
		$symbol   = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
		$position = (string) get_option( 'woocommerce_currency_pos', 'left' );
		$prefix   = '';
		$suffix   = '';

		switch ( $position ) {
			case 'left_space':
				$prefix = $symbol . ' ';
				break;
			case 'right':
				$suffix = $symbol;
				break;
			case 'right_space':
				$suffix = ' ' . $symbol;
				break;
			default:
				$prefix = $symbol;
		}

		return array(
			'currencyPrefix'            => $prefix,
			'currencySuffix'            => $suffix,
			'currencyMinorUnit'         => wc_get_price_decimals(),
			'currencyDecimalSeparator'  => wc_get_price_decimal_separator(),
			'currencyThousandSeparator' => wc_get_price_thousand_separator(),
		);
	}
}
