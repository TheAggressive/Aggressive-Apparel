/**
 * Product Specs settings — the export file's name.
 *
 * Says which site and day it came from ("size-and-specs-shop.local-2026-11-03
 * .json"), so files from different sites never get mixed up. Pure, so it's
 * tested on its own.
 *
 * @package Aggressive_Apparel
 */

export function exportName( host: string, date: Date ): string {
	const site = host.toLowerCase().replace( /[^a-z0-9.-]+/g, '-' ) || 'site';
	const day = [
		date.getFullYear(),
		String( date.getMonth() + 1 ).padStart( 2, '0' ),
		String( date.getDate() ).padStart( 2, '0' ),
	].join( '-' );
	return `size-and-specs-${ site }-${ day }.json`;
}
