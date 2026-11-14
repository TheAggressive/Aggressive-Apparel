import { formatSaleText } from '../quick-view/product-data';

type MockState = Record< string, unknown > & {
	productOnSale: boolean;
	salePercentage: number;
	i18n: Record< string, string >;
	readonly saleBadgeText: string;
	readonly hasNoSaleBadge: boolean;
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
		expect( mockState!.hasNoSaleBadge ).toBe( false );
	} );

	it( 'falls back to the no-discount wording without a single figure', () => {
		mockState!.salePercentage = 0;
		expect( saleBadgeText() ).toBe( 'On Sale' );
	} );

	it( 'is empty when the product is not on sale', () => {
		mockState!.productOnSale = false;
		expect( saleBadgeText() ).toBe( '' );
		expect( mockState!.hasNoSaleBadge ).toBe( true );
	} );

	it( 'keeps the previous format when no wording was seeded', () => {
		mockState!.i18n = {};
		expect( saleBadgeText() ).toBe( '-13%' );
	} );
} );

describe( "Quick View's Badge Studio badges", () => {
	let badges: typeof import( '../quick-view/badges' );
	let view: typeof import( '../quick-view/view' );

	const cardHtml = ( saleInner: string, role = '' ) =>
		'<div class="aggressive-apparel-product-badge__wrapper aggressive-apparel-product-badge__wrapper--top-left">' +
		`<span class="aggressive-apparel-product-badge aggressive-apparel-product-badge--type-sale"${ role }>${ saleInner }</span>` +
		'<span class="aggressive-apparel-product-badge aggressive-apparel-product-badge--type-new">New</span>' +
		'</div>';
	const product = ( html: string ) => ( {
		extensions: { 'aggressive-apparel/badges': { html } },
	} );

	let root: HTMLElement;
	const sale = () =>
		root.querySelector< HTMLElement >(
			'.aggressive-apparel-product-badge--type-sale'
		)!;

	beforeAll( () => {
		require( '../quick-view' );
		badges = require( '../quick-view/badges' );
		view = require( '../quick-view/view' );
	} );

	beforeEach( () => {
		document.body.innerHTML =
			'<div id="qv"><div class="aggressive-apparel-quick-view__badges"></div></div>';
		root = document.getElementById( 'qv' )!;
		Object.assign( mockState!, {
			productOnSale: false,
			salePercentage: 0,
			matchedVariationId: 0,
			themeSaleBadge: false,
			i18n: {
				saleBadgeText: '{percent}% Off',
				saleBadgeNoDiscountText: 'On Sale',
			},
		} );
	} );

	it( "shows the card's whole badge set and takes over the sale chip", () => {
		badges.renderProductBadges(
			root,
			product(
				cardHtml(
					'<span class="aggressive-apparel-product-badge__label">20% Off</span>'
				)
			)
		);

		expect(
			root.querySelectorAll( '.aggressive-apparel-product-badge' )
		).toHaveLength( 2 );
		expect( mockState!.themeSaleBadge ).toBe( true );
		mockState!.productOnSale = true;
		mockState!.salePercentage = 20;
		expect( mockState!.hasNoSaleBadge ).toBe( true );
	} );

	it( "follows the chosen variation's discount and restores the card's", () => {
		badges.renderProductBadges(
			root,
			product(
				cardHtml(
					'<span class="aggressive-apparel-product-badge__label">20% Off</span>'
				)
			)
		);
		const label = () =>
			sale().querySelector( '.aggressive-apparel-product-badge__label' )!
				.textContent;

		Object.assign( mockState!, {
			matchedVariationId: 7,
			productOnSale: true,
			salePercentage: 10,
		} );
		badges.syncThemeSaleBadge( root );
		expect( label() ).toBe( '10% Off' );

		Object.assign( mockState!, {
			productOnSale: false,
			salePercentage: 0,
		} );
		badges.syncThemeSaleBadge( root );
		expect( sale().hidden ).toBe( true );

		mockState!.matchedVariationId = 0;
		badges.syncThemeSaleBadge( root );
		expect( sale().hidden ).toBe( false );
		expect( label() ).toBe( '20% Off' );
	} );

	it( "keeps an icon-only sale badge's accessible name in step", () => {
		badges.renderProductBadges(
			root,
			product(
				cardHtml( '<svg></svg>', ' role="img" aria-label="20% Off"' )
			)
		);
		Object.assign( mockState!, {
			matchedVariationId: 7,
			productOnSale: true,
			salePercentage: 15,
		} );
		badges.syncThemeSaleBadge( root );
		expect( sale().getAttribute( 'aria-label' ) ).toBe( '15% Off' );
	} );

	it( 'falls back to the default chip when the theme draws no sale badge', () => {
		badges.renderProductBadges( root, {} );
		expect( root.innerHTML ).not.toContain( 'product-badge' );
		expect( mockState!.themeSaleBadge ).toBe( false );
		Object.assign( mockState!, {
			productOnSale: true,
			salePercentage: 20,
		} );
		expect( mockState!.hasNoSaleBadge ).toBe( false );
	} );

	it( 'uses the Badge Studio low-stock threshold for the stock message', () => {
		mockState!.lowStockThreshold = 5;
		const stock = ( quantity: number ) =>
			view.getStockInfo( {
				is_in_stock: true,
				stock_quantity: quantity,
			} as Parameters< typeof view.getStockInfo >[ 0 ] ).status;

		expect( stock( 5 ) ).toBe( 'lowstock' );
		expect( stock( 6 ) ).toBe( 'instock' );
	} );
} );
