import { exportName } from '../_transferName';
import { countEdits } from '../_gridEdits';

describe( 'exportName', () => {
	it( 'names the site and the day', () => {
		expect( exportName( 'Shop.Local', new Date( 2026, 10, 3 ) ) ).toBe(
			'size-and-specs-shop.local-2026-11-03.json'
		);
		expect( exportName( '', new Date( 2026, 0, 9 ) ) ).toBe(
			'size-and-specs-site-2026-01-09.json'
		);
	} );
} );

describe( 'countEdits', () => {
	it( 'counts every unsaved value across rows', () => {
		expect( countEdits( {} ) ).toBe( 0 );
		expect(
			countEdits( { 4: { fit: '1', fabric: '' }, 9: { fit: '2' } } )
		).toBe( 3 );
	} );
} );
