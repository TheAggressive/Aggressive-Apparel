/**
 * WCAG 2.x contrast for the theme's `oklch()` color tokens.
 *
 * theme.json authors every adaptive color in OKLCH. These helpers convert a
 * token to linear sRGB (Björn Ottosson's OKLab matrices, clamped to gamut) and
 * return the WCAG relative-luminance contrast ratio, so token contracts can be
 * asserted against the source of truth instead of a rendered page.
 *
 * @package Aggressive_Apparel
 */

const OKLCH = /^oklch\(\s*([\d.]+)(%?)\s+([\d.]+)\s+([\d.]+)\s*\)$/;

/**
 * Parse an `oklch(L C H)` string; L may be a percentage or a 0–1 number.
 *
 * @param value CSS color string.
 * @return L (0–1), C, H (degrees).
 */
export function parseOklch( value: string ): [ number, number, number ] {
	const match = value.trim().match( OKLCH );
	if ( ! match ) {
		throw new Error( `Not an oklch() color: ${ value }` );
	}
	const lightness = Number( match[ 1 ] );
	return [
		match[ 2 ] ? lightness / 100 : lightness,
		Number( match[ 3 ] ),
		Number( match[ 4 ] ),
	];
}

/**
 * WCAG relative luminance of an `oklch()` color.
 *
 * @param value CSS color string.
 */
export function relativeLuminance( value: string ): number {
	const [ L, C, H ] = parseOklch( value );
	const hue = ( H * Math.PI ) / 180;
	const a = C * Math.cos( hue );
	const b = C * Math.sin( hue );

	const l = ( L + 0.3963377774 * a + 0.2158037573 * b ) ** 3;
	const m = ( L - 0.1055613458 * a - 0.0638541728 * b ) ** 3;
	const s = ( L - 0.0894841775 * a - 1.291485548 * b ) ** 3;

	const clamp = ( channel: number ) => Math.min( 1, Math.max( 0, channel ) );
	const red = clamp( 4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s );
	const green = clamp(
		-1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s
	);
	const blue = clamp(
		-0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s
	);

	// OKLab → linear sRGB already yields linear channels, which is what the
	// WCAG luminance weights apply to.
	return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

/**
 * WCAG contrast ratio between two `oklch()` colors (1–21).
 *
 * @param first  CSS color string.
 * @param second CSS color string.
 */
export function contrastRatio( first: string, second: string ): number {
	const [ high, low ] = [
		relativeLuminance( first ),
		relativeLuminance( second ),
	].sort( ( x, y ) => y - x );
	return ( high + 0.05 ) / ( low + 0.05 );
}
