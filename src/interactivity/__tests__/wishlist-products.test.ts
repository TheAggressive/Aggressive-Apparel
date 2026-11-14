import {
	fetchWishlistProducts,
	withProductExtensions,
} from '../wishlist/products';

const product = {
	id: 12,
	name: 'Hat',
	permalink: 'https://example.com/hat',
	prices: null,
	variation: '',
};
const response = ( data: unknown ) =>
	( { ok: true, status: 200, json: async () => data } ) as Response;

it( 'normalizes the public variation string without weakening the account schema', async () => {
	window.fetch = jest.fn().mockResolvedValue( response( [ product ] ) );
	expect(
		await fetchWishlistProducts(
			[ 12 ],
			'/shop/?rest_route=/wc/store/v1/products'
		)
	).toEqual( [ { ...product, variation: [] } ] );
	const url = new URL( ( window.fetch as jest.Mock ).mock.calls[ 0 ][ 0 ] );
	expect( url.pathname ).toBe( '/shop/' );
	expect( url.searchParams.get( 'rest_route' ) ).toBe(
		'/wc/store/v1/products'
	);
	expect( url.searchParams.get( 'include' ) ).toBe( '12' );
} );

it( 'rejects invalid product details instead of breaking the rendering loop', async () => {
	window.fetch = jest
		.fn()
		.mockResolvedValue( response( [ { ...product, images: [ null ] } ] ) );
	await expect(
		fetchWishlistProducts( [ 12 ], '/products' )
	).rejects.toThrow( 'Invalid product response' );
} );

it( 'batches long lists without losing products beyond the API page size', async () => {
	const fetchMock = jest
		.fn()
		.mockResolvedValueOnce( response( [ product ] ) )
		.mockResolvedValueOnce( response( [ { ...product, id: 101 } ] ) );
	window.fetch = fetchMock;
	const ids = Array.from( { length: 101 }, ( _, i ) => i + 1 );
	expect(
		( await fetchWishlistProducts( ids, '/products' ) ).map(
			( item ) => item.id
		)
	).toEqual( [ 12, 101 ] );
	expect(
		new URL( fetchMock.mock.calls[ 0 ][ 0 ] ).searchParams
			.get( 'include' )!
			.split( ',' )
	).toHaveLength( 100 );
	expect(
		new URL( fetchMock.mock.calls[ 1 ][ 0 ] ).searchParams.get( 'include' )
	).toBe( '101' );
} );

describe( 'withProductExtensions', () => {
	// Shopper Lists items carry variation attributes as an array.
	const item = { ...product, variation: [] };
	const badges = { html: '<div>Sale</div>' };

	it( "gives account items the parent's badges and the saved variation's savings", async () => {
		window.fetch = jest.fn( async ( url: string ) =>
			new URL( url ).searchParams.get( 'type' ) === 'variation'
				? response( [
						{
							id: 31,
							extensions: {
								'aggressive-apparel/pricing': {
									savings: 'Save 10%',
								},
							},
						},
					] )
				: response( [
						{
							id: 30,
							extensions: {
								'aggressive-apparel/badges': badges,
								'aggressive-apparel/pricing': {
									savings: 'Save 20%',
								},
							},
						},
					] )
		) as unknown as typeof fetch;

		const [ variation, simple ] = await withProductExtensions(
			[
				{ ...item, id: 31, product_id: 30, variation_id: 31 },
				{ ...item, id: 30, product_id: 30 },
			],
			'https://example.com/wp-json/wc/store/v1/products'
		);

		expect( variation.extensions ).toEqual( {
			'aggressive-apparel/pricing': { savings: 'Save 10%' },
			'aggressive-apparel/badges': badges,
		} );
		expect( simple.extensions ).toEqual( {
			'aggressive-apparel/pricing': { savings: 'Save 20%' },
			'aggressive-apparel/badges': badges,
		} );
		const urls = ( window.fetch as jest.Mock ).mock.calls.map(
			( [ url ] ) => new URL( url ).searchParams
		);
		expect( urls.map( ( params ) => params.get( '_fields' ) ) ).toEqual( [
			'id,extensions',
			'id,extensions',
		] );
	} );

	it( 'leaves the items untouched when the lookup fails', async () => {
		window.fetch = jest.fn().mockRejectedValue( new Error( 'offline' ) );
		const items = [ { ...item, id: 30 } ];
		await expect(
			withProductExtensions( items, 'https://example.com/products' )
		).resolves.toBe( items );
	} );
} );
