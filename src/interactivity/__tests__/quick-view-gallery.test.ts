import { buildGalleryImages } from '../quick-view/product-data';

// A script-module alias with no Jest mapping; the gallery only needs its
// tag stripper.
jest.mock(
	'@aggressive-apparel/helpers',
	() => ( { stripTags: ( value: string ) => value } ),
	{ virtual: true }
);
import type { StoreApiProduct } from '../quick-view/types';

it( 'shows a photo listed twice once, so one image never lights two dots', () => {
	const product = {
		name: 'Hat',
		images: [
			{ id: 11, src: 'hat.jpg', alt: 'Hat' },
			{ id: 12, src: 'hat-side.jpg', alt: 'Side' },
			{ id: 11, src: 'hat.jpg', alt: 'Hat' },
			{ src: 'external.jpg' },
			{ src: 'external.jpg' },
		],
	} as unknown as StoreApiProduct;

	expect(
		buildGalleryImages( product ).map( ( image ) => image.src )
	).toEqual( [ 'hat.jpg', 'hat-side.jpg', 'external.jpg' ] );
} );
