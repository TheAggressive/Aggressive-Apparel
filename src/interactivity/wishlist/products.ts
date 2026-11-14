/** Public product lookup, separate from the authenticated Shopper Lists schema. */
import { isWishlistProduct, type WishlistProduct } from './account';
import { requestJson } from './request';
import { BADGES_EXTENSION } from '../../utils/product-extensions';

/** Fetch in bounded batches so lists larger than the Store API page size survive. */
export async function fetchWishlistProducts(
	ids: number[],
	endpoint?: string
): Promise< WishlistProduct[] > {
	const products: WishlistProduct[] = [];
	for ( let start = 0; start < ids.length; start += 100 ) {
		if ( ! endpoint )
			throw new Error( 'Wishlist products API unavailable' );
		const url = new URL( endpoint, location.href );
		url.searchParams.set(
			'include',
			ids.slice( start, start + 100 ).join( ',' )
		);
		url.searchParams.set( 'per_page', '100' );
		const { response, data } = await requestJson( url.href );
		if ( ! response.ok ) throw new Error( 'Product request failed' );
		if ( ! Array.isArray( data ) )
			throw new Error( 'Invalid product response' );
		// Public products expose a human-readable variation string; Shopper Lists
		// exposes an attribute array. Normalize only the public response here.
		const batch: unknown[] = data.map( ( value ) =>
			value &&
			typeof value === 'object' &&
			typeof value.variation === 'string'
				? { ...value, variation: [] }
				: value
		);
		if ( ! batch.every( isWishlistProduct ) )
			throw new Error( 'Invalid product response' );
		products.push( ...batch );
	}
	return products;
}

/**
 * Fetch `extensions` for some product IDs, keyed by ID.
 *
 * @param ids       Product or variation IDs.
 * @param endpoint  Store API products URL.
 * @param variation Whether the IDs are variations (the Store API only returns
 *                  variations when asked for that type).
 */
async function fetchExtensions(
	ids: number[],
	endpoint: string,
	variation = false
): Promise< Map< number, Record< string, unknown > > > {
	const found = new Map< number, Record< string, unknown > >();
	for ( let start = 0; start < ids.length; start += 100 ) {
		const url = new URL( endpoint, location.href );
		url.searchParams.set(
			'include',
			ids.slice( start, start + 100 ).join( ',' )
		);
		url.searchParams.set( 'per_page', '100' );
		url.searchParams.set( '_fields', 'id,extensions' );
		if ( variation ) url.searchParams.set( 'type', 'variation' );
		const { response, data } = await requestJson( url.href );
		if ( ! response.ok || ! Array.isArray( data ) ) continue;
		for ( const value of data ) {
			if (
				value &&
				typeof value.id === 'number' &&
				value.extensions &&
				typeof value.extensions === 'object'
			)
				found.set( value.id, value.extensions );
		}
	}
	return found;
}

/**
 * Give Shopper Lists items the theme data public products carry — the
 * parent's badges and the saved item's own savings note — so account and
 * guest wishlist items render alike. Decoration only: a failed lookup leaves
 * the items as they were.
 *
 * @param items    Shopper Lists items.
 * @param endpoint Store API products URL.
 */
export async function withProductExtensions(
	items: WishlistProduct[],
	endpoint?: string
): Promise< WishlistProduct[] > {
	if ( ! endpoint || items.length === 0 ) return items;
	const unique = ( ids: number[] ) =>
		[ ...new Set( ids ) ].filter( ( id ) => id > 0 );
	try {
		const [ parents, variations ] = await Promise.all( [
			fetchExtensions(
				unique( items.map( ( item ) => item.product_id || item.id ) ),
				endpoint
			),
			fetchExtensions(
				unique( items.map( ( item ) => item.variation_id || 0 ) ),
				endpoint,
				true
			),
		] );
		return items.map( ( item ) => {
			const parent = parents.get( item.product_id || item.id );
			const own = item.variation_id
				? variations.get( item.variation_id )
				: parent;
			return {
				...item,
				extensions: {
					...( own ?? {} ),
					[ BADGES_EXTENSION ]: parent?.[ BADGES_EXTENSION ],
				},
			};
		} );
	} catch {
		return items;
	}
}
