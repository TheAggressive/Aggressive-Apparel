import { expect, test } from '@playwright/test';
import { ownPostPhp } from './fixture-cleanup';
import { wpCli } from './wp-cli';

/**
 * Component style snapshot — catches unintended visual change.
 *
 * A fixture page holds the theme's building blocks (core buttons, search and
 * typography blocks, native form controls, the theme's button/control
 * classes). The test records each one's computed paint — colours, borders,
 * radius, padding, type — in light and dark mode and compares it with the
 * committed snapshot. Only CSS values are recorded, never pixels or text
 * geometry, so the snapshot is identical in the Studio and CI browsers.
 *
 * An intended change: review the diff, then `pnpm test:e2e
 * tests/e2e/component-styles.spec.ts --update-snapshots` and commit it.
 */

const OWNER = 'component-styles';

const MARKUP = `
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading" id="vc-h1">Heading one</h1><!-- /wp:heading -->
<!-- wp:heading --><h2 class="wp-block-heading" id="vc-h2">Heading two</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p id="vc-p">Body copy with <a id="vc-link" href="#vc">a link</a> and <code id="vc-code">code</code>.</p><!-- /wp:paragraph -->
<!-- wp:quote --><blockquote class="wp-block-quote" id="vc-quote"><!-- wp:paragraph --><p>Quoted.</p><!-- /wp:paragraph --></blockquote><!-- /wp:quote -->
<!-- wp:buttons --><div class="wp-block-buttons">
<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" id="vc-button" href="#vc">Primary</a></div><!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" id="vc-button-outline" href="#vc">Outline</a></div><!-- /wp:button -->
</div><!-- /wp:buttons -->
<!-- wp:search {"label":"Search","buttonText":"Search"} /-->
<!-- wp:html -->
<form id="vc-form" onsubmit="return false">
  <label for="vc-text">Name</label><input id="vc-text" type="text">
  <select id="vc-select"><option>One</option></select>
  <textarea id="vc-textarea"></textarea>
  <input id="vc-checkbox" type="checkbox"><label for="vc-checkbox">Check</label>
  <input id="vc-submit" type="submit" value="Send">
</form>
<button type="button" class="aggressive-apparel-button aggressive-apparel-button--primary" id="vc-aa-primary">Theme primary</button>
<button type="button" class="aggressive-apparel-button aggressive-apparel-button--outline" id="vc-aa-outline">Theme outline</button>
<button type="button" class="aa-icon-button" id="vc-icon" aria-label="Icon"><svg width="16" height="16" aria-hidden="true"><rect width="16" height="16"/></svg></button>
<button type="button" class="aa-choice-pill" id="vc-pill">M</button>
<button type="button" class="aa-choice-pill is-selected" id="vc-pill-selected" aria-pressed="true">L</button>
<!-- /wp:html -->
`;

const ELEMENTS: Record< string, string > = {
	'heading 1': '#vc-h1',
	'heading 2': '#vc-h2',
	paragraph: '#vc-p',
	link: '#vc-link',
	code: '#vc-code',
	quote: '#vc-quote',
	'core button': '#vc-button',
	'core button (outline)': '#vc-button-outline',
	'search input': '.wp-block-search__input',
	'search button': '.wp-block-search__button',
	'text input': '#vc-text',
	select: '#vc-select',
	textarea: '#vc-textarea',
	checkbox: '#vc-checkbox',
	'submit input': '#vc-submit',
	'theme button (primary)': '#vc-aa-primary',
	'theme button (outline)': '#vc-aa-outline',
	'icon button': '#vc-icon',
	'choice pill': '#vc-pill',
	'choice pill (selected)': '#vc-pill-selected',
};

const PROPERTIES = [
	'color',
	'background-color',
	'border-top-width',
	'border-top-style',
	'border-top-color',
	'border-left-width',
	'border-left-color',
	'border-top-left-radius',
	'padding-top',
	'padding-left',
	'font-family',
	'font-size',
	'font-weight',
	'letter-spacing',
	'text-transform',
	'text-decoration-line',
	'min-height',
	'box-shadow',
	'appearance',
];

let pageId = 0;
let pagePath = '';

test.use( { storageState: { cookies: [], origins: [] } } );

test.beforeAll( () => {
	const out = wpCli( [
		'eval',
		`$id = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'E2E Component Styles', 'post_content' => ${ JSON.stringify( MARKUP ) }));
${ ownPostPhp( '$id', OWNER ) }
echo $id . '|' . get_permalink($id);`,
	] );
	const [ id, url ] = out.trim().split( '|' );
	pageId = Number.parseInt( id, 10 );
	if ( ! pageId || ! url ) {
		throw new Error(
			`Failed to create the component-styles fixture: ${ out }`
		);
	}
	pagePath = new URL( url ).pathname;
} );

test.afterAll( () => {
	if ( pageId ) {
		wpCli( [ 'post', 'delete', String( pageId ), '--force' ] );
	}
} );

for ( const scheme of [ 'light', 'dark' ] as const ) {
	test( `theme components keep their ${ scheme }-mode paint`, async ( {
		page,
	} ) => {
		await page.emulateMedia( {
			colorScheme: scheme,
			reducedMotion: 'reduce',
		} );
		await page.setViewportSize( { width: 1200, height: 900 } );
		await page.goto( pagePath, { waitUntil: 'networkidle' } );

		const styles = await page.evaluate(
			( { elements, properties } ) => {
				const out: Record< string, Record< string, string > | string > =
					{};
				for ( const [ name, selector ] of Object.entries( elements ) ) {
					const el = document.querySelector( selector );
					if ( ! el ) {
						out[ name ] = 'missing';
						continue;
					}
					const computed = getComputedStyle( el );
					out[ name ] = Object.fromEntries(
						properties.map( ( p ) => [
							p,
							computed.getPropertyValue( p ),
						] )
					);
				}
				return out;
			},
			{ elements: ELEMENTS, properties: PROPERTIES }
		);

		expect( JSON.stringify( styles, null, 2 ) ).toMatchSnapshot(
			`component-styles-${ scheme }.json`
		);
	} );
}
