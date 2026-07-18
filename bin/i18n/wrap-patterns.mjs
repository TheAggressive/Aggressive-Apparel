#!/usr/bin/env node
/**
 * Wrap visible text in patterns/*.php with esc_html__() for gettext.
 * Skips files that already call gettext, structural-only files, and
 * template-chrome patterns we author by hand.
 *
 * Usage: node bin/i18n/wrap-patterns.mjs [--dry-run]
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const patternsDir = path.resolve( __dirname, '../../patterns' );
const dryRun = process.argv.includes( '--dry-run' );

const SKIP = new Set( [
	// Hand-authored / already wrapped in Stage 4.
	'page-404.php',
	'template-no-products.php',
	'template-related-products-heading.php',
	'template-no-posts.php',
	'template-no-search-results.php',
	'template-latest-posts-heading.php',
	'template-cart-heading.php',
	'template-checkout-heading.php',
	'template-cart-empty.php',
	'template-cart-upsell-heading.php',
] );

/** @param {string} text */
function phpEscape( text ) {
	return text.replace( /\\/g, '\\\\' ).replace( /'/g, "\\'" );
}

/**
 * Whether a text node looks like user-facing copy (not numbers/symbols only).
 *
 * @param {string} text
 */
function isTranslatable( text ) {
	const t = text.trim();
	if ( t.length < 2 ) {
		return false;
	}
	// Skip pure numbers / 404 / prices / symbols.
	if ( /^[\d\s%$€£+\-–—./:]+$/.test( t ) ) {
		return false;
	}
	// Need at least one letter.
	if ( ! /[A-Za-z]/.test( t ) ) {
		return false;
	}
	return true;
}

/**
 * Wrap text content of common block tags.
 *
 * @param {string} content
 */
function wrapContent( content ) {
	let changed = 0;

	// Avoid double-wrapping.
	if ( /esc_html__\s*\(|__\s*\(|esc_html_e\s*\(/.test( content ) ) {
		return { content, changed: 0, skipped: 'already-i18n' };
	}

	const next = content.replace(
		/(<(?:h[1-6]|p|li|figcaption|blockquote|cite|span|strong|em|a)(?:\s[^>]*)?>)([^<]+)(<\/(?:h[1-6]|p|li|figcaption|blockquote|cite|span|strong|em|a)>)/gi,
		( match, open, text, close ) => {
			if ( ! isTranslatable( text ) ) {
				return match;
			}
			// Preserve leading/trailing whitespace outside the gettext string.
			const leading = text.match( /^\s*/ )?.[ 0 ] ?? '';
			const trailing = text.match( /\s*$/ )?.[ 0 ] ?? '';
			const core = text.trim();
			if ( ! isTranslatable( core ) ) {
				return match;
			}
			changed += 1;
			return `${ open }${ leading }<?php echo esc_html__( '${ phpEscape( core ) }', 'aggressive-apparel' ); ?>${ trailing }${ close }`;
		}
	);

	return { content: next, changed, skipped: changed ? null : 'no-matches' };
}

const files = fs
	.readdirSync( patternsDir )
	.filter( ( f ) => f.endsWith( '.php' ) )
	.sort();

let totalChanged = 0;
let filesChanged = 0;

for ( const file of files ) {
	if ( SKIP.has( file ) ) {
		continue;
	}
	const full = path.join( patternsDir, file );
	const original = fs.readFileSync( full, 'utf8' );
	const { content, changed, skipped } = wrapContent( original );

	if ( ! changed ) {
		if ( process.env.VERBOSE ) {
			console.log( `skip ${ file } (${ skipped })` );
		}
		continue;
	}

	filesChanged += 1;
	totalChanged += changed;
	console.log( `${ dryRun ? 'dry' : 'wrap' } ${ file }: ${ changed } string(s)` );
	if ( ! dryRun ) {
		fs.writeFileSync( full, content );
	}
}

console.log(
	`\n${ dryRun ? 'Would wrap' : 'Wrapped' } ${ totalChanged } string(s) in ${ filesChanged } file(s).`
);
