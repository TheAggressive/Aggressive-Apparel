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

describe( 'preset references in block markup', () => {
	// A slug that is not defined renders a class with no CSS behind it, so the
	// block silently inherits instead (a missing footer text color once left
	// un-colored children black on the black footer in light mode).
	const slugs = ( list: Array< { slug: string } > ) =>
		new Set( list.map( ( entry ) => entry.slug ) );
	const defined: Record< string, Set< string > > = {
		color: new Set( [
			...slugs( theme.settings.color.palette ),
			...Object.keys( adaptive ),
		] ),
		gradient: slugs( theme.settings.color.gradients ),
		'font-size': slugs( theme.settings.typography.fontSizes ),
		'font-family': slugs( theme.settings.typography.fontFamilies ),
		spacing: slugs( theme.settings.spacing.spacingSizes ),
	};
	const ATTRIBUTE_KIND: Record< string, string > = {
		textColor: 'color',
		backgroundColor: 'color',
		overlayColor: 'color',
		gradient: 'gradient',
		fontSize: 'font-size',
		fontFamily: 'font-family',
	};

	const files = [ 'templates', 'parts', 'patterns', 'styles' ].flatMap(
		( dir ) =>
			fs
				.readdirSync( path.join( ROOT, dir ), { recursive: true } )
				.map( String )
				.filter( ( file ) => /\.(html|php|json)$/.test( file ) )
				// WooCommerce's email editor renders this with its own palette.
				.filter( ( file ) => file !== 'wooemailtemplate.html' )
				.map( ( file ) => path.join( dir, file ) )
	);

	it( 'resolves every preset slug used by templates, parts, patterns and section styles', () => {
		const unresolved: string[] = [];
		for ( const file of files ) {
			const source = fs.readFileSync( path.join( ROOT, file ), 'utf8' );
			for ( const [ , kind, slug ] of source.matchAll(
				/var:preset\|([a-z-]+)\|([a-z0-9-]+)/g
			) ) {
				if ( defined[ kind ] && ! defined[ kind ].has( slug ) ) {
					unresolved.push( `${ file }: ${ kind } ${ slug }` );
				}
			}
			for ( const [ , attribute, slug ] of source.matchAll(
				/"(textColor|backgroundColor|overlayColor|gradient|fontSize|fontFamily)":"([a-z][a-z0-9-]*)"/g
			) ) {
				const kind = ATTRIBUTE_KIND[ attribute ];
				if ( ! defined[ kind ].has( slug ) ) {
					unresolved.push( `${ file }: ${ kind } ${ slug }` );
				}
			}
		}

		expect( unresolved ).toEqual( [] );
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

	it( 'documents every type role, layout preset and aspect ratio in design-system.md', () => {
		const doc = readDoc( 'design-system.md' );
		const semanticSpacing = theme.settings.spacing.spacingSizes
			.map( ( size: { slug: string } ) => size.slug )
			.filter( ( slug: string ) => /^(gutter|section-)/.test( slug ) );
		const required = [
			...Object.keys( theme.settings.custom.typeRole ),
			...semanticSpacing,
			...theme.settings.dimensions.aspectRatios.map(
				( ratio: { slug: string } ) => ratio.slug
			),
		];

		expect(
			required.filter( ( slug ) => ! doc.includes( `\`${ slug }\`` ) )
		).toEqual( [] );
	} );
} );
