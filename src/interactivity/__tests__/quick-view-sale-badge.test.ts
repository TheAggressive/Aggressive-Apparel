import { formatSaleText } from '../quick-view/product-data';

type MockState = Record< string, unknown > & {
	productOnSale: boolean;
	salePercentage: number;
	i18n: Record< string, string >;
	readonly saleBadgeText: string;
};

let mockState: MockState | undefined;

jest.mock(
	'@wordpress/interactivity',
	() => ( {
		store: ( _name: string, config: { state?: MockState } ) => {
			if ( config.state && ! mockState ) {
				mockState = config.state;
			}
			return { state: mockState, actions: {}, callbacks: {} };
		},
		getContext: () => ( {} ),
		getElement: () => ( { ref: null } ),
		withScope: ( fn: unknown ) => fn,
	} ),
	{ virtual: true }
);
jest.mock( '@aggressive-apparel/helpers', () => ( {} ), { virtual: true } );
jest.mock( '@aggressive-apparel/use-overlay', () => ( {} ), {
	virtual: true,
} );

describe( 'formatSaleText', () => {
	it( 'substitutes every {percent} token', () => {
		expect( formatSaleText( '{percent}% Off', 'On Sale', 13 ) ).toBe(
			'13% Off'
		);
		expect( formatSaleText( 'Save {percent}% ({percent}%)', '', 5 ) ).toBe(
			'Save 5% (5%)'
		);
	} );

	it( 'uses the fallback only when the wording needs a missing number', () => {
		expect( formatSaleText( '{percent}% Off', 'On Sale', 0 ) ).toBe(
			'On Sale'
		);
		expect( formatSaleText( 'Now on Sale', 'On Sale', 0 ) ).toBe(
			'Now on Sale'
		);
		expect( formatSaleText( 'Now on Sale', 'On Sale', 20 ) ).toBe(
			'Now on Sale'
		);
	} );
} );

describe( 'Quick View sale badge text', () => {
	let saleBadgeText: () => string;

	beforeAll( () => {
		( { saleBadgeText } = require( '../quick-view' ) );
	} );

	beforeEach( () => {
		Object.assign( mockState!, {
			productOnSale: true,
			salePercentage: 13,
			i18n: {
				saleBadgeText: '{percent}% Off',
				saleBadgeNoDiscountText: 'On Sale',
			},
		} );
	} );

	it( "uses the store's sale badge wording, not a hardcoded format", () => {
		expect( saleBadgeText() ).toBe( '13% Off' );
		expect( mockState!.saleBadgeText ).toBe( '13% Off' );
	} );

	it( 'falls back to the no-discount wording without a single figure', () => {
		mockState!.salePercentage = 0;
		expect( saleBadgeText() ).toBe( 'On Sale' );
	} );

	it( 'is empty when the product is not on sale', () => {
		mockState!.productOnSale = false;
		expect( saleBadgeText() ).toBe( '' );
	} );

	it( 'keeps the previous format when no wording was seeded', () => {
		mockState!.i18n = {};
		expect( saleBadgeText() ).toBe( '-13%' );
	} );
} );
