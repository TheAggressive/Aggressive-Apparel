import { prefersReducedMotion, scrollBehavior } from '../motion';

function setPreference( reduce: boolean ): void {
	window.matchMedia = jest.fn().mockReturnValue( {
		matches: reduce,
	} ) as unknown as typeof window.matchMedia;
}

describe( 'motion preference', () => {
	it( 'reports a reduced-motion preference and switches scrolling to instant', () => {
		setPreference( true );

		expect( prefersReducedMotion() ).toBe( true );
		expect( scrollBehavior() ).toBe( 'auto' );
	} );

	it( 'scrolls smoothly when the visitor has no preference', () => {
		setPreference( false );

		expect( prefersReducedMotion() ).toBe( false );
		expect( scrollBehavior() ).toBe( 'smooth' );
	} );

	it( 'treats a browser without matchMedia as no preference', () => {
		// @ts-expect-error -- simulate an environment without matchMedia.
		window.matchMedia = undefined;

		expect( prefersReducedMotion() ).toBe( false );
	} );
} );
