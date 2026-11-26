/**
 * Every theme pattern parses into valid blocks in the real editor.
 *
 * Static blocks (group, cover, heading, buttons…) must match their save()
 * output exactly; a pattern whose markup drifts inserts as "This block
 * contains unexpected or invalid content". Pattern PHP renders per request,
 * so this also covers markup that depends on live store data.
 *
 * @package Aggressive_Apparel
 */

import { test, expect } from '@playwright/test';
import { openPageEditor } from './helpers';

test( 'theme patterns parse into valid, registered blocks', async ( {
	page,
} ) => {
	await openPageEditor( page );

	const report = await page.evaluate( async () => {
		const patterns = ( await window.wp.apiFetch( {
			path: '/wp/v2/block-patterns/patterns',
		} ) ) as Array< { name: string; content: string } >;

		type ParsedBlock = {
			name: string;
			isValid: boolean;
			innerBlocks: ParsedBlock[];
			validationIssues?: Array< { args: unknown[] } >;
		};
		const problems: string[] = [];
		const walk = ( pattern: string, blocks: ParsedBlock[] ) => {
			for ( const block of blocks ) {
				if ( block.name === 'core/missing' ) {
					problems.push( `${ pattern }: unregistered block` );
				} else if ( ! block.isValid ) {
					// First validator message, e.g. "Expected attribute
					// `class` of value `…`, saw `…`." — enough to locate it.
					const [ format = '', ...args ] =
						block.validationIssues?.[ 0 ]?.args ?? [];
					let index = 0;
					const reason = String( format )
						.replace( /%[so]/g, () => {
							const arg = args[ index++ ];
							return typeof arg === 'string'
								? arg
								: JSON.stringify( arg );
						} )
						.slice( 0, 600 );
					problems.push(
						`${ pattern }: invalid ${ block.name } — ${ reason }`
					);
				}
				walk( pattern, block.innerBlocks );
			}
		};

		const theme = patterns.filter( ( pattern ) =>
			pattern.name.startsWith( 'aggressive-apparel/' )
		);
		for ( const pattern of theme ) {
			walk(
				pattern.name,
				window.wp.blocks.parse( pattern.content ) as ParsedBlock[]
			);
		}

		return { count: theme.length, problems };
	} );

	expect( report.count ).toBeGreaterThan( 0 );
	expect( report.problems ).toEqual( [] );
} );
