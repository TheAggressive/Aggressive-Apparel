/**
 * Store API money formatting — the one client-side price formatter.
 *
 * Every Store API price/totals payload carries the store's currency format
 * (symbol placement as prefix/suffix, separators, minor unit). Formatting from
 * those fields keeps client-rendered prices identical to WooCommerce's
 * server-rendered `wc_price()` output, so nothing is locale-hardcoded.
 *
 * Dependency-free on purpose: block view modules bundle it directly, and the
 * `@aggressive-apparel/helpers` script module re-exports it for stores.
 *
 * @package Aggressive_Apparel
 */

/** Currency format fields as the Store API names them. */
export interface StoreApiCurrency {
	currency_minor_unit?: number;
	currency_prefix?: string;
	currency_suffix?: string;
	currency_decimal_separator?: string;
	currency_thousand_separator?: string;
}

/**
 * The same format as seeded from PHP (`Store_Currency::context()`), for
 * amounts that do not come from a Store API response.
 */
export interface CurrencyContext {
	currencyMinorUnit?: number;
	currencyPrefix?: string;
	currencySuffix?: string;
	currencyDecimalSeparator?: string;
	currencyThousandSeparator?: string;
}

/** Map a seeded currency context onto the Store API field names. */
export function fromCurrencyContext(
	context: CurrencyContext | null | undefined
): StoreApiCurrency {
	return {
		currency_minor_unit: context?.currencyMinorUnit,
		currency_prefix: context?.currencyPrefix,
		currency_suffix: context?.currencySuffix,
		currency_decimal_separator: context?.currencyDecimalSeparator,
		currency_thousand_separator: context?.currencyThousandSeparator,
	};
}

export interface MoneyFormatOptions {
	/** Drop an all-zero fraction ($104, not $104.00). */
	trimZeroDecimals?: boolean;
}

/** Minor unit from the payload, clamped to what toFixed() and stores use. */
function minorUnitOf( currency: StoreApiCurrency ): number {
	const unit = currency.currency_minor_unit;
	return Number.isInteger( unit ) && unit! >= 0 && unit! <= 6 ? unit! : 2;
}

/**
 * Format an amount in major units (e.g. 12.5 dollars).
 *
 * @param amount   Amount in the currency's major unit.
 * @param currency Store API currency fields.
 * @param options  Formatting options.
 * @return Formatted price, or '' when the amount is not a finite number.
 */
export function formatMoneyAmount(
	amount: number,
	currency: StoreApiCurrency,
	{ trimZeroDecimals = false }: MoneyFormatOptions = {}
): string {
	if ( ! Number.isFinite( amount ) ) {
		return '';
	}

	const negative = amount < 0;
	const [ integer, fraction = '' ] = Math.abs( amount )
		.toFixed( minorUnitOf( currency ) )
		.split( '.' );
	const grouped = integer.replace(
		/\B(?=(\d{3})+(?!\d))/g,
		currency.currency_thousand_separator ?? ','
	);
	const keepFraction =
		fraction !== '' && ! ( trimZeroDecimals && ! /[1-9]/.test( fraction ) );
	const number = keepFraction
		? `${ grouped }${ currency.currency_decimal_separator ?? '.' }${ fraction }`
		: grouped;

	return `${ negative ? '-' : '' }${ currency.currency_prefix ?? '' }${ number }${
		currency.currency_suffix ?? ''
	}`;
}

/**
 * Format a Store API amount string in minor units (e.g. "1250" → "$12.50").
 *
 * @param amount   Amount in the currency's minor unit, as the Store API sends it.
 * @param currency Store API currency fields.
 * @param options  Formatting options.
 * @return Formatted price, or '' when the amount is missing or not numeric.
 */
export function formatMinorAmount(
	amount: string | number | null | undefined,
	currency: StoreApiCurrency,
	options?: MoneyFormatOptions
): string {
	if ( amount === null || amount === undefined || amount === '' ) {
		return '';
	}

	const minor =
		typeof amount === 'number' ? amount : Number.parseInt( amount, 10 );
	if ( ! Number.isFinite( minor ) ) {
		return '';
	}

	return formatMoneyAmount(
		minor / Math.pow( 10, minorUnitOf( currency ) ),
		currency,
		options
	);
}
