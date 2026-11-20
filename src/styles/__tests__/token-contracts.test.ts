/**
 * @jest-environment node
 */

/**
 * Design-token contracts, asserted on theme.json (the source of truth).
 *
 * - Every text/surface pairing the system offers clears WCAG AA in BOTH
 *   schemes, so a token tweak cannot silently fail contrast in one mode.
 * - The token docs list every token theme.json defines, so documentation
 *   cannot drift from the implementation unnoticed.
 *
 * @package Aggressive_Apparel
 */

import fs from 'node:fs';
import path from 'node:path';
import { contrastRatio, relativeLuminance } from '../testing/oklch-contrast';

interface AdaptiveColor {
	slug: string;
	light: string;
	dark: string;
}

const ROOT = path.resolve( __dirname, '../../..' );
const theme = JSON.parse(
	fs.readFileSync( path.join( ROOT, 'theme.json' ), 'utf8' )
);
const adaptive: Record< string, AdaptiveColor > = Object.fromEntries(
	( theme.settings.custom.adaptiveColors as AdaptiveColor[] ).map(
		( color ) => [ color.slug, color ]
	)
);
const SCHEMES = [ 'light', 'dark' ] as const;

const readDoc = ( name: string ) =>
	fs.readFileSync( path.join( ROOT, 'docs', name ), 'utf8' );

/** Slugs written as `slug` in the first or second column of a markdown table row. */
const tableSlugs = ( markdown: string ) =>
	new Set(
		[
			...markdown.matchAll( /^\|(?:[^|]*\|)?\s*`([a-z0-9-]+)`\s*\|/gm ),
		].map( ( match ) => match[ 1 ] )
	);

describe( 'adaptive color contrast', () => {
	// Text roles × the surfaces body copy may sit on. surface-sunken is a
	// recessed well (image placeholders, input fills) and must also carry text.
	const TEXT = [ 'foreground', 'foreground-muted', 'accent' ];
	const SURFACES = [
		'surface',
		'surface-elevated',
		'surface-muted',
		'surface-sunken',
	];

	for ( const scheme of SCHEMES ) {
		for ( const text of TEXT ) {
			for ( const surface of SURFACES ) {
				it( `${ scheme }: ${ text } on ${ surface } is at least 4.5:1`, () => {
					expect(
						contrastRatio(
							adaptive[ text ][ scheme ],
							adaptive[ surface ][ scheme ]
						)
					).toBeGreaterThanOrEqual( 4.5 );
				} );
			}
		}

		it( `${ scheme }: accent-on-foreground reads on the foreground fill`, () => {
			expect(
				contrastRatio(
					adaptive[ 'accent-on-foreground' ][ scheme ],
					adaptive.foreground[ scheme ]
				)
			).toBeGreaterThanOrEqual( 4.5 );
		} );
	}

	it( 'orders light surfaces sunken < surface < muted < elevated', () => {
		const order = [
			'surface-sunken',
			'surface',
			'surface-muted',
			'surface-elevated',
		].map( ( slug ) => relativeLuminance( adaptive[ slug ].light ) );

		expect( [ ...order ].sort( ( a, b ) => a - b ) ).toEqual( order );
	} );
} );

describe( 'token documentation', () => {
	it( 'lists every palette and adaptive color in design-tokens.md', () => {
		const documented = tableSlugs( readDoc( 'design-tokens.md' ) );
		const defined = [
			...theme.settings.color.palette.map(
				( color: { slug: string } ) => color.slug
			),
			...Object.keys( adaptive ),
		];

		expect(
			defined.filter( ( slug ) => ! documented.has( slug ) )
		).toEqual( [] );
	} );

	it( 'lists every adaptive color in design-system.md', () => {
		const documented = tableSlugs( readDoc( 'design-system.md' ) );

		expect(
			Object.keys( adaptive ).filter(
				( slug ) => ! documented.has( slug )
			)
		).toEqual( [] );
	} );
} );
