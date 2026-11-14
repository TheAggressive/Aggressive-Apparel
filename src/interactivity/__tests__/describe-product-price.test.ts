import { describeProductPrice } from '../helpers';
import {
	productBadgesHtml,
	productSavingsText,
} from '../../utils/product-extensions';

const usd = {
	currency_minor_unit: 2,
	currency_prefix: '$',
	currency_suffix: '',
};

describe( 'describeProductPrice', () => {
	it( 'prices a reduced product like the card: was, now, and the savings note', () => {
		expect(
			describeProductPrice( {
				type: 'simple',
				prices: {
					...usd,
					price: '2000',
					regular_price: '2500',
					sale_price: '2000',
				},
				extensions: {
					'aggressive-apparel/pricing': { savings: 'Save 20%' },
				},
			} )
		).toEqual( {
			current: '$20.00',
			regular: '$25.00',
			savings: 'Save 20%',
		} );
	} );

	it( 'shows no struck price when the product is not reduced', () => {
		expect(
			describeProductPrice( {
				prices: { ...usd, price: '2500', regular_price: '2500' },
			} )
		).toEqual( { current: '$25.00', regular: '', savings: '' } );
	} );

	const variable = {
		type: 'variable',
		prices: {
			...usd,
			price: '1200',
			regular_price: '1500',
			sale_price: '1200',
			price_range: { min_amount: '1200', max_amount: '1500' },
		},
	};

	it( 'uses Smart Price Display\'s "From $X" for a variable product', () => {
		expect(
			describeProductPrice( variable, {
				collapseVariablePrice: true,
				priceStartingPrefix: 'Starting at',
			} )
		).toEqual( {
			current: 'Starting at $12.00',
			regular: '',
			savings: '',
		} );
	} );

	it( "shows the card's native range when Smart Price Display is off", () => {
		expect( describeProductPrice( variable ) ).toEqual( {
			current: '$12.00 – $15.00',
			regular: '',
			savings: '',
		} );
	} );

	it( 'is empty without prices', () => {
		expect( describeProductPrice( { prices: null } ) ).toEqual( {
			current: '',
			regular: '',
			savings: '',
		} );
	} );
} );

describe( 'product extensions', () => {
	it( 'reads only string fields from the theme namespaces', () => {
		const product = {
			extensions: {
				'aggressive-apparel/badges': { html: '<div></div>' },
				'aggressive-apparel/pricing': { savings: 42 },
			},
		};
		expect( productBadgesHtml( product ) ).toBe( '<div></div>' );
		expect( productSavingsText( product ) ).toBe( '' );
		expect( productBadgesHtml( {} ) ).toBe( '' );
		expect( productSavingsText( { extensions: null } ) ).toBe( '' );
	} );
} );
