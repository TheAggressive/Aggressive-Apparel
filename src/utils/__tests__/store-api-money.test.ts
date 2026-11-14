import {
	formatMinorAmount,
	formatMoneyAmount,
	fromCurrencyContext,
} from '../store-api-money';

const USD = {
	currency_minor_unit: 2,
	currency_prefix: '$',
	currency_suffix: '',
	currency_decimal_separator: '.',
	currency_thousand_separator: ',',
};

const EUR_DE = {
	currency_minor_unit: 2,
	currency_prefix: '',
	currency_suffix: ' €',
	currency_decimal_separator: ',',
	currency_thousand_separator: '.',
};

describe( 'formatMinorAmount', () => {
	it( 'matches wc_price() for a prefix currency, grouping thousands', () => {
		expect( formatMinorAmount( '123456', USD ) ).toBe( '$1,234.56' );
		expect( formatMinorAmount( '4900', USD ) ).toBe( '$49.00' );
	} );

	it( 'honours a suffix symbol and locale separators', () => {
		expect( formatMinorAmount( '123456', EUR_DE ) ).toBe( '1.234,56 €' );
	} );

	it( 'formats currencies without minor units', () => {
		expect(
			formatMinorAmount( '1234567', {
				currency_minor_unit: 0,
				currency_prefix: '¥',
			} )
		).toBe( '¥1,234,567' );
	} );

	it( 'never invents a currency symbol the payload did not send', () => {
		expect( formatMinorAmount( '1250', {} ) ).toBe( '12.50' );
	} );

	it( 'falls back to two decimals for an out-of-range minor unit', () => {
		expect(
			formatMinorAmount( '1250', { ...USD, currency_minor_unit: 99 } )
		).toBe( '$12.50' );
	} );

	it( 'returns an empty string for a missing or non-numeric amount', () => {
		expect( formatMinorAmount( undefined, USD ) ).toBe( '' );
		expect( formatMinorAmount( null, USD ) ).toBe( '' );
		expect( formatMinorAmount( '', USD ) ).toBe( '' );
		expect( formatMinorAmount( 'free', USD ) ).toBe( '' );
	} );

	it( 'accepts numeric minor-unit amounts', () => {
		expect( formatMinorAmount( 1999, USD ) ).toBe( '$19.99' );
	} );
} );

describe( 'formatMoneyAmount', () => {
	it( 'formats major-unit amounts', () => {
		expect( formatMoneyAmount( 1234.5, USD ) ).toBe( '$1,234.50' );
	} );

	it( 'drops only an all-zero fraction when asked', () => {
		expect(
			formatMoneyAmount( 104, USD, { trimZeroDecimals: true } )
		).toBe( '$104' );
		expect(
			formatMoneyAmount( 104.5, USD, { trimZeroDecimals: true } )
		).toBe( '$104.50' );
	} );

	it( 'puts the minus sign before the symbol, as wc_price() does', () => {
		expect( formatMoneyAmount( -5, USD ) ).toBe( '-$5.00' );
	} );

	it( 'rounds float sums to the minor unit', () => {
		expect( formatMoneyAmount( 0.1 + 0.2, USD ) ).toBe( '$0.30' );
	} );

	it( 'returns an empty string for non-finite amounts', () => {
		expect( formatMoneyAmount( Number.NaN, USD ) ).toBe( '' );
		expect( formatMoneyAmount( Infinity, USD ) ).toBe( '' );
	} );
} );

describe( 'fromCurrencyContext', () => {
	it( 'maps the PHP-seeded context onto Store API field names', () => {
		expect(
			formatMoneyAmount(
				1234.5,
				fromCurrencyContext( {
					currencyMinorUnit: 2,
					currencyPrefix: '',
					currencySuffix: ' €',
					currencyDecimalSeparator: ',',
					currencyThousandSeparator: '.',
				} )
			)
		).toBe( '1.234,50 €' );
	} );

	it( 'tolerates a missing context', () => {
		expect( formatMoneyAmount( 3, fromCurrencyContext( undefined ) ) ).toBe(
			'3.00'
		);
	} );
} );
