#!/usr/bin/env node
/**
 * Storefront audit against any running site — staging with production
 * merchandise, or production after a release. Read-only by default.
 *
 *     node bin/audit/storefront.mjs https://staging.example.com [--cart] [--no-paypal] [--out dir]
 *
 * Pages are discovered from the live catalog through the Store API (no local
 * fixtures): home, shop, a category, a simple and a variable product, search,
 * account, cart, and with --cart a real add-to-cart and the checkout page (never
 * submitted). Each page is loaded at 375 / 768 / 1440 / 1920 in light and dark
 * and checked for: HTTP errors, sideways scroll, broken or placeholder images,
 * script errors, and WCAG 2.2 AA (axe) at 375 light and 1440 light and dark.
 * Product pages also report whether PayPal smart buttons rendered.
 *
 * Writes full-page screenshots and report.json / report.md to --out
 * (default ./storefront-audit-<time>). Exits 1 when any check fails.
 *
 * @package Aggressive_Apparel
 */

/* global document, innerHeight, innerWidth, scrollTo -- browser globals inside page.evaluate() callbacks. */

import AxeBuilder from '@axe-core/playwright';
import { chromium } from '@playwright/test';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const argv = process.argv.slice( 2 );
const base = argv
	.find( ( a ) => /^https?:\/\//.test( a ) )
	?.replace( /\/$/, '' );
if ( ! base ) {
	console.error(
		'Usage: node bin/audit/storefront.mjs <site URL> [--cart] [--out dir]'
	);
	process.exit( 2 );
}
const withCart = argv.includes( '--cart' );
// PayPal smart buttons must render on product pages unless the site has no
// PayPal (local development).
const expectPayPal = ! argv.includes( '--no-paypal' );
const outIndex = argv.indexOf( '--out' );
const out =
	outIndex > -1
		? argv[ outIndex + 1 ]
		: `storefront-audit-${ new Date().toISOString().replace( /\D/g, '' ).slice( 0, 14 ) }`;
mkdirSync( out, { recursive: true } );

const WIDTHS = [ 375, 768, 1440, 1920 ];
const SCHEMES = [ 'light', 'dark' ];
const AXE_AT = new Set( [ '375 light', '1440 light', '1440 dark' ] );
const WCAG = [
	'wcag2a',
	'wcag2aa',
	'wcag21a',
	'wcag21aa',
	'wcag22a',
	'wcag22aa',
];
const PAYPAL =
	'.wp-block-woocommerce-paypal-payments-product-smart-buttons, [id^="ppc-button"], .ppcp-button-apm';

async function storeApi( route ) {
	const response = await fetch( `${ base }/wp-json/wc/store/v1/${ route }` );
	if ( ! response.ok ) {
		throw new Error( `Store API ${ route }: HTTP ${ response.status }` );
	}
	return response.json();
}

/** Discover one of each page type from the live catalog. */
async function discoverPages() {
	const products = await storeApi(
		'products?per_page=50&stock_status=instock'
	);
	const categories = await storeApi( 'products/categories?per_page=50' );
	const simple = products.find(
		( p ) => p.type === 'simple' && p.is_purchasable
	);
	const variable = products.find( ( p ) => p.type === 'variable' );
	const category = categories
		.filter( ( c ) => c.count > 0 && c.slug !== 'uncategorized' )
		.sort( ( a, b ) => b.count - a.count )[ 0 ];
	const word = ( products[ 0 ]?.name ?? 'shirt' ).split( /\s+/ )[ 0 ];

	return [
		[ 'home', `${ base }/` ],
		[ 'shop', `${ base }/shop/` ],
		category && [ 'category', category.permalink ],
		simple && [ 'product-simple', simple.permalink ],
		variable && [ 'product-variable', variable.permalink ],
		[
			'search',
			`${ base }/?s=${ encodeURIComponent( word ) }&post_type=product`,
		],
		[ 'account', `${ base }/my-account/` ],
		[ 'cart', `${ base }/cart/` ],
	].filter( Boolean );
}

/** Scroll through the page so lazy images load, then wait for them. */
async function settle( page ) {
	await page.evaluate( async () => {
		for (
			let y = 0;
			y < document.body.scrollHeight;
			y += innerHeight / 2
		) {
			scrollTo( 0, y );
			await new Promise( ( r ) => setTimeout( r, 60 ) );
		}
		scrollTo( 0, 0 );
	} );
	await page
		.waitForFunction(
			() => [ ...document.images ].every( ( i ) => i.complete ),
			null,
			{ timeout: 15000 }
		)
		.catch( () => {} );
	// Cart and checkout render WooCommerce loading skeletons first; audit the
	// loaded page, not the placeholder.
	await page
		.waitForFunction(
			() =>
				! document.querySelector(
					'.wc-block-components-skeleton, .wc-block-components-skeleton__element'
				),
			null,
			{ timeout: 20000 }
		)
		.catch( () => {} );
	await page.evaluate( () => document.fonts.ready );
}

async function inspect( page ) {
	return page.evaluate( ( paypal ) => {
		const images = [ ...document.images ].filter(
			( i ) => i.getBoundingClientRect().width > 0
		);
		return {
			overflowX: document.documentElement.scrollWidth - innerWidth,
			broken: images
				.filter( ( i ) => i.complete && i.naturalWidth === 0 )
				.map( ( i ) => i.currentSrc || i.src ),
			placeholders: images
				.filter( ( i ) => i.naturalWidth > 0 && i.naturalWidth <= 2 )
				.map( ( i ) => i.currentSrc || i.src ),
			paypal: !! document.querySelector( paypal ),
		};
	}, PAYPAL );
}

async function axe( page ) {
	const { violations } = await new AxeBuilder( { page } )
		.withTags( WCAG )
		.analyze();
	return violations.map( ( v ) => ( {
		rule: v.id,
		impact: v.impact,
		nodes: v.nodes
			.slice( 0, 3 )
			.map(
				( n ) =>
					`${ n.target.join( ' ' ) }: ${ n.failureSummary?.split( '\n' )[ 1 ]?.trim() ?? '' }`
			),
		count: v.nodes.length,
	} ) );
}

const browser = await chromium.launch();
const results = [];
const pages = await discoverPages();

// With --cart, put one real product in a session cart (the customer's own
// path: the product page's button) and audit cart and checkout with it.
let storageState;
const simple = pages.find( ( [ name ] ) => name === 'product-simple' );
if ( withCart && simple ) {
	const context = await browser.newContext();
	const page = await context.newPage();
	await page.goto( simple[ 1 ], { waitUntil: 'networkidle' } );
	const button = page
		.locator(
			'main button.single_add_to_cart_button, main .wc-block-add-to-cart-with-options button[type=submit]'
		)
		.first();
	// The block form adds through the Store API after hydration, so poll the
	// cart rather than trusting the click; one retry covers an early click.
	let items = 0;
	for ( let attempt = 0; attempt < 2 && ! items; attempt++ ) {
		await button.click();
		for ( let i = 0; i < 40 && ! items; i++ ) {
			await page.waitForTimeout( 250 );
			items = await page.evaluate(
				async () =>
					(
						await (
							await fetch( '/wp-json/wc/store/v1/cart' )
						).json()
					).items_count
			);
		}
	}
	if ( ! items ) {
		console.error(
			`✘ Add to cart on ${ simple[ 1 ] } left the cart empty.`
		);
		process.exit( 1 );
	}
	storageState = await context.storageState();
	await context.close();
	pages.push( [ 'checkout', `${ base }/checkout/` ] );
}

for ( const scheme of SCHEMES ) {
	const context = await browser.newContext( {
		colorScheme: scheme,
		storageState,
	} );

	for ( const [ name, url ] of pages ) {
		for ( const width of WIDTHS ) {
			const page = await context.newPage();
			await page.setViewportSize( { width, height: 1000 } );
			const errors = [];
			page.on( 'pageerror', ( e ) => errors.push( e.message ) );
			page.on(
				'console',
				( m ) => m.type() === 'error' && errors.push( m.text() )
			);

			const response = await page.goto( url, {
				waitUntil: 'networkidle',
				timeout: 60000,
			} );
			await settle( page );
			const found = await inspect( page );
			const key = `${ width } ${ scheme }`;
			const violations = AXE_AT.has( key ) ? await axe( page ) : [];
			const shot = path.join(
				out,
				`${ name }-${ width }-${ scheme }.png`
			);
			await page.screenshot( { path: shot, fullPage: true } );

			const failures = [
				response &&
					response.status() >= 400 &&
					`HTTP ${ response.status() }`,
				found.overflowX > 0 &&
					`scrolls sideways by ${ found.overflowX }px`,
				found.broken.length &&
					`${ found.broken.length } broken image(s)`,
				found.placeholders.length &&
					`${ found.placeholders.length } placeholder image(s)`,
				errors.length && `${ errors.length } script error(s)`,
				violations.length &&
					`${ violations.length } axe rule(s) failed`,
				expectPayPal &&
					name.startsWith( 'product-' ) &&
					! found.paypal &&
					'PayPal smart buttons missing',
				// An empty cart sends /checkout/ back to /cart/.
				name === 'checkout' &&
					! page.url().includes( '/checkout' ) &&
					`redirected to ${ page.url() }`,
			].filter( Boolean );

			results.push( {
				name,
				url,
				width,
				scheme,
				failures,
				errors,
				violations,
				...found,
				shot,
			} );
			console.log(
				`${ failures.length ? '✘' : '✓' } ${ name } ${ key }${ failures.length ? ' — ' + failures.join( '; ' ) : '' }`
			);
			await page.close();
		}
	}
	await context.close();
}
await browser.close();

const failed = results.filter( ( r ) => r.failures.length );
const paypal = results.filter(
	( r ) =>
		r.name.startsWith( 'product-' ) &&
		r.width === 1440 &&
		r.scheme === 'light'
);
const md = [
	`# Storefront audit: ${ base }`,
	'',
	`${ results.length } page views, ${ failed.length } with failures.`,
	'',
	...paypal.map(
		( r ) =>
			`- PayPal smart buttons on ${ r.name }: ${ r.paypal ? 'rendered' : '**not rendered**' }`
	),
	'',
	'| Page | View | Result |',
	'| --- | --- | --- |',
	...results.map(
		( r ) =>
			`| ${ r.name } | ${ r.width } ${ r.scheme } | ${ r.failures.join( '; ' ) || 'ok' } |`
	),
	'',
	...failed.flatMap( ( r ) => [
		`## ${ r.name } ${ r.width } ${ r.scheme }`,
		...r.violations.map(
			( v ) =>
				`- axe ${ v.rule } [${ v.impact }] ×${ v.count }: ${ v.nodes.join( ' | ' ) }`
		),
		...r.errors
			.slice( 0, 5 )
			.map( ( e ) => `- error: ${ e.slice( 0, 300 ) }` ),
		...[ ...r.broken, ...r.placeholders ]
			.slice( 0, 5 )
			.map( ( s ) => `- image: ${ s }` ),
		'',
	] ),
].join( '\n' );
writeFileSync(
	path.join( out, 'report.json' ),
	JSON.stringify( results, null, 2 )
);
writeFileSync( path.join( out, 'report.md' ), md );
console.log( `\nReport: ${ path.join( out, 'report.md' ) }` );
process.exit( failed.length ? 1 : 0 );
