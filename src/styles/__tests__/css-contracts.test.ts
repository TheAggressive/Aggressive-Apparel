/**
 * @jest-environment node
 */

/**
 * Stylesheet contracts, asserted on compiled output.
 *
 * Each test states a rule the theme relies on — a selector, the value it sets,
 * whether it is !important and in which cascade layer — and checks it in the
 * CSS the build ships. Formatting, nesting and rule order in the source do not
 * matter; only what reaches the browser does.
 *
 * @package Aggressive_Apparel
 */

import { CompiledCss } from '../testing/compiled-css';

const REDUCED_MOTION = '@media (prefers-reduced-motion:reduce)';

describe( 'cascade layers', () => {
	it( 'fixes the theme layer order in main.css, reset lowest and forms highest', async () => {
		const main = await CompiledCss.load( 'src/styles/main.css' );

		expect( main.layerOrder() ).toEqual( [
			'aa-reset',
			'aa-tokens',
			'aa-base',
			'aa-components',
			'aa-blocks',
			'aa-woocommerce',
			'aa-utilities',
			'aa-forms',
		] );
	} );

	it( 'keeps the native field baseline in aa-forms and the Fluent Forms adapter out of it', async () => {
		const forms = await CompiledCss.load(
			'src/styles/components/forms.css'
		);
		const declarations = forms.all();

		expect( forms.layerOrder() ).toEqual( [ 'aa-forms' ] );
		expect(
			declarations.filter(
				( d ) =>
					d.prop === 'padding' &&
					d.value.startsWith( 'var(--aa-field-padding-block)' )
			)
		).toContainEqual( expect.objectContaining( { layer: 'aa-forms' } ) );
		expect(
			forms
				.selectors()
				.filter( ( s ) =>
					s.includes( '.fluentform.ff-inherit-theme-style' )
				)
		).toEqual( [] );
	} );
} );

describe( 'WooCommerce notices', () => {
	let notices: CompiledCss;

	beforeAll( async () => {
		notices = await CompiledCss.load(
			'src/styles/woocommerce/notices.css'
		);
	} );

	it.each( [
		[ '.woocommerce-message', 'success' ],
		[ '.woocommerce-error', 'error' ],
		[ '.woocommerce-info', 'info' ],
		[ '.woocommerce-warning', 'warning' ],
		[ '.wc-block-components-notice-banner.is-success', 'success' ],
		[ '.wc-block-components-notice-banner.is-error', 'error' ],
		[ '.wc-block-components-notice-banner.is-info', 'info' ],
		[ '.wc-block-components-notice-banner.is-warning', 'warning' ],
	] )( '%s takes the %s status colour', async ( selector, status ) => {
		expect(
			await notices.declarations( selector, '--aa-wc-notice-color' )
		).toContainEqual(
			expect.objectContaining( { value: `var(--aa-color-${ status })` } )
		);
	} );

	it( 'keeps notice text in the theme foreground colour', async () => {
		expect(
			await notices.declarations(
				'.wc-block-components-notice-banner > .wc-block-components-notice-banner__content',
				'color'
			)
		).toEqual( [
			expect.objectContaining( { value: 'var(--aa-color-foreground)' } ),
		] );
	} );

	it( 'paints notice actions as the canonical secondary button, hover included', async () => {
		const action =
			'.wc-block-components-notice-banner > .wc-block-components-notice-banner__content .wc-forward';

		expect(
			await notices.declarations( action, 'background-color' )
		).toEqual( [
			expect.objectContaining( {
				value: 'var(--wp--custom--button--secondary--background)',
				important: true,
			} ),
		] );
		expect(
			await notices.declarations(
				`${ action }:hover`,
				'background-color'
			)
		).toEqual( [
			expect.objectContaining( {
				value: 'var(--wp--custom--button--secondary--hover-background)',
				important: true,
			} ),
		] );
	} );

	it( 'drops action transitions under reduced motion', async () => {
		expect(
			await notices.declarations(
				'.wc-block-components-notice-banner .button',
				'transition'
			)
		).toContainEqual(
			expect.objectContaining( {
				value: 'none',
				conditions: [ REDUCED_MOTION ],
			} )
		);
	} );
} );

describe( 'mini-cart badge', () => {
	let miniCart: CompiledCss;

	beforeAll( async () => {
		miniCart = await CompiledCss.load(
			'src/styles/woocommerce/mini-cart.css'
		);
	} );

	it( 'pulses a ping ring with a defined animation', async () => {
		const [ animation ] = await miniCart.declarations(
			'.wc-block-mini-cart__badge::after',
			'animation'
		);

		expect( animation.value ).toContain( 'aa-badge-ping' );
		expect( miniCart.keyframes() ).toContain( 'aa-badge-ping' );
	} );

	it( 'reveals the count on pointer hover and on keyboard focus', async () => {
		expect(
			await miniCart.declarations(
				'.wc-block-mini-cart__badge:hover',
				'background-color'
			)
		).toEqual( [
			expect.objectContaining( {
				value: 'var(--aa-color-accent-fill)',
				important: true,
				conditions: [ '@media (hover:hover)' ],
			} ),
		] );
		expect(
			await miniCart.declarations(
				'.wc-block-mini-cart__button:focus-visible .wc-block-mini-cart__badge',
				'background-color'
			)
		).toEqual( [
			expect.objectContaining( {
				value: 'var(--aa-color-accent-fill)',
				important: true,
			} ),
		] );
	} );

	it( 'stops the ping and the transition under reduced motion', async () => {
		expect(
			await miniCart.declarations(
				'.wc-block-mini-cart__badge::after',
				'animation'
			)
		).toContainEqual(
			expect.objectContaining( {
				value: 'none',
				conditions: [ REDUCED_MOTION ],
			} )
		);
		expect(
			await miniCart.declarations(
				'.wc-block-mini-cart__badge',
				'transition'
			)
		).toContainEqual(
			expect.objectContaining( {
				value: 'none',
				conditions: [ REDUCED_MOTION ],
			} )
		);
	} );
} );

describe( 'quick view card actions', () => {
	it( 'places the action stack by its own modifier classes, never a nested element', async () => {
		const selectors = (
			await CompiledCss.load( 'src/styles/woocommerce/quick-view.css' )
		).selectors();

		for ( const placement of [ 'corner', 'bottom-bar' ] ) {
			expect(
				selectors.some( ( s ) =>
					s.includes(
						`.aggressive-apparel-card-actions.aggressive-apparel-card-actions--${ placement }`
					)
				)
			).toBe( true );
		}
		expect(
			selectors.filter( ( s ) =>
				/\.aggressive-apparel-card-actions \.aggressive-apparel-card-actions--/.test(
					s
				)
			)
		).toEqual( [] );
	} );
} );

describe( 'product card defaults', () => {
	it( 'keep zero specificity so editor palette and typography classes win', async () => {
		const blocks = await CompiledCss.load(
			'src/styles/woocommerce/blocks.css'
		);

		expect(
			await blocks.declarations(
				':where(.wp-block-woocommerce-product-collection)',
				'color'
			)
		).toEqual( [
			expect.objectContaining( {
				value: 'var(--aa-color-foreground)',
				important: false,
			} ),
		] );
		expect(
			await blocks.declarations(
				':where(.wp-block-woocommerce-product-title, .wc-block-grid__product-title)',
				'font-weight'
			)
		).toEqual( [
			expect.objectContaining( {
				value: 'var(--aa-type-product-title-weight)',
				important: false,
			} ),
		] );
	} );
} );

describe( 'buttons and controls', () => {
	let main: CompiledCss;

	beforeAll( async () => {
		main = await CompiledCss.load( 'src/styles/main.css' );
	} );

	const BUTTONS =
		':where(.wp-element-button, .wp-block-button__link, .aggressive-apparel-button)';

	it( 'give every button a minimum height and block pointer input while disabled or loading', async () => {
		expect(
			await main.declarations( BUTTONS, 'min-height' )
		).toContainEqual(
			expect.objectContaining( { value: 'var(--aa-button-height-md)' } )
		);
		expect(
			await main.declarations(
				`${ BUTTONS }:is(:disabled, [aria-disabled=true])`,
				'pointer-events'
			)
		).toContainEqual( expect.objectContaining( { value: 'none' } ) );
		expect(
			await main.declarations(
				`${ BUTTONS }.is-loading`,
				'pointer-events'
			)
		).toContainEqual( expect.objectContaining( { value: 'none' } ) );
	} );

	it( 'size icon, choice and stepper controls to the shared touch targets', async () => {
		expect(
			await main.declarations( ':where(.aa-icon-button)', 'min-width' )
		).toContainEqual(
			expect.objectContaining( { value: 'var(--aa-icon-button-size)' } )
		);
		expect(
			await main.declarations( ':where(.aa-choice-pill)', 'min-height' )
		).toContainEqual(
			expect.objectContaining( { value: 'var(--aa-control-min)' } )
		);
		expect(
			await main.declarations( ':where(.aa-stepper-button)', 'min-width' )
		).toContainEqual(
			expect.objectContaining( { value: 'var(--aa-icon-button-size)' } )
		);
	} );

	it( 'keep the icon-button resting background on hover by default', async () => {
		expect(
			await main.declarations(
				':where(.aa-icon-button)',
				'--aa-icon-button-hover-background'
			)
		).toEqual( [
			expect.objectContaining( {
				value: 'var(--aa-icon-button-background)',
			} ),
		] );
	} );

	it( 'draw the shared focus ring on keyboard focus', async () => {
		for ( const control of [
			':where(.aa-icon-button)',
			':where(.aa-choice-pill)',
		] ) {
			expect(
				await main.declarations(
					`${ control }:focus-visible`,
					'box-shadow'
				)
			).toEqual( [
				expect.objectContaining( { value: 'var(--aa-focus-ring)' } ),
			] );
		}
	} );

	it( 'paint the outline variant through specificity, not !important', async () => {
		expect(
			await main.declarations(
				'.aggressive-apparel-button.aggressive-apparel-button--outline',
				'background'
			)
		).toEqual( [
			expect.objectContaining( {
				value: 'var(--wp--custom--button--secondary--background)',
				important: false,
			} ),
		] );
	} );
} );

describe( 'product card swatches', () => {
	it( 'keep their pointer target, shrinking only to the dense tier on narrow cards', async () => {
		const swatches = await CompiledCss.load(
			'src/blocks-interactivity/product-color-swatches/style.css'
		);
		const minWidth = await swatches.declarations(
			'.aa-product-color-swatches__swatch',
			'min-width'
		);

		expect( minWidth ).toContainEqual(
			expect.objectContaining( {
				value: 'var(--aa-card-swatch-min)',
				conditions: [],
			} )
		);
		expect( minWidth ).toContainEqual(
			expect.objectContaining( {
				value: 'var(--aa-card-swatch-dense-min)',
				conditions: [ '@container product-card (width<11rem)' ],
			} )
		);
	} );
} );

describe( 'checkout fields', () => {
	it( 'consume the shared field fill instead of restating it', async () => {
		const checkout = await CompiledCss.load(
			'src/styles/woocommerce/checkout.css'
		);

		expect(
			checkout
				.values()
				.filter( ( v ) =>
					/var\(--aa-color-foreground\)\s*8%/.test( v )
				)
		).toEqual( [] );
	} );
} );

describe( 'browser floor (postcss.config.cjs targets)', () => {
	let main: CompiledCss;

	beforeAll( async () => {
		main = await CompiledCss.load( 'src/styles/main.css' );
	} );

	it( 'ships light-dark() and @layer as written, never lowered to fallbacks', async () => {
		const tokens = await CompiledCss.load( 'src/styles/base/tokens.css' );

		expect(
			tokens.values().some( ( v ) => v.includes( 'light-dark(' ) )
		).toBe( true );
		expect( main.layerOrder().length ).toBeGreaterThan( 0 );
		for ( const sheet of [ main, tokens ] ) {
			expect(
				sheet
					.values()
					.filter( ( v ) => v.includes( '--lightningcss-' ) )
			).toEqual( [] );
		}
	} );

	it( 'pairs every backdrop-filter with the -webkit- prefix Safari 17 needs', () => {
		const all = main.all();
		const unprefixed = all.filter( ( d ) => d.prop === 'backdrop-filter' );

		expect( unprefixed.length ).toBeGreaterThan( 0 );
		for ( const d of unprefixed ) {
			expect(
				all.some(
					( p ) =>
						p.prop === '-webkit-backdrop-filter' &&
						p.selector === d.selector &&
						p.value === d.value
				)
			).toBe( true );
		}
	} );

	it( 'keeps scroll-driven timelines as longhands, not folded into animation', async () => {
		const timeline = main
			.all()
			.filter( ( d ) => d.prop === 'animation-timeline' );

		expect( timeline ).toContainEqual(
			expect.objectContaining( { value: 'scroll(root)' } )
		);
		expect(
			main
				.all()
				.filter(
					( d ) =>
						d.prop === 'animation' &&
						/scroll\(|view\(/.test( d.value )
				)
		).toEqual( [] );
	} );
} );

describe( 'keyframes', () => {
	/** Theme keyframe names: document-global, so namespaced to avoid plugins. */
	const THEME_NAME = /^(aa|aggressive-apparel)-[a-z0-9-]+$/;

	/** Every compiled stylesheet entry (partials are inlined into these). */
	const entries = ( require( 'node:fs' ) as typeof import( 'node:fs' ) )
		.readdirSync( 'src', { recursive: true, encoding: 'utf8' } )
		.filter(
			( file ) =>
				file.endsWith( '.css' ) &&
				! ( require( 'node:path' ) as typeof import( 'node:path' ) )
					.basename( file )
					.startsWith( '_' ) &&
				! file.includes( '__tests__' )
		)
		.map( ( file ) => `src/${ file }` )
		.sort();

	let sheets: Array< [ string, CompiledCss ] >;
	let shared: Set< string >;

	beforeAll( async () => {
		sheets = await Promise.all(
			entries.map( async ( file ): Promise< [ string, CompiledCss ] > => [
				file,
				await CompiledCss.load( file ),
			] )
		);
		shared = new Set(
			(
				await CompiledCss.load( 'src/styles/base/tokens.css' )
			).keyframes()
		);
	} );

	it( 'namespaces every keyframe the theme declares', () => {
		const unprefixed = sheets.flatMap( ( [ file, css ] ) =>
			css
				.keyframes()
				.filter( ( name ) => ! THEME_NAME.test( name ) )
				.map( ( name ) => `${ file }: @keyframes ${ name }` )
		);

		expect( entries.length ).toBeGreaterThan( 50 );
		expect( unprefixed ).toEqual( [] );
	} );

	it( 'declares each theme animation in its own sheet or the shared tokens sheet', () => {
		const missing = sheets.flatMap( ( [ file, css ] ) => {
			const local = new Set( css.keyframes() );
			return css
				.all()
				.filter(
					( d ) =>
						d.prop === 'animation' || d.prop === 'animation-name'
				)
				.flatMap( ( d ) => d.value.split( /[\s,]+/ ) )
				.filter(
					( token ) =>
						THEME_NAME.test( token ) &&
						! local.has( token ) &&
						! shared.has( token )
				)
				.map( ( token ) => `${ file }: ${ token }` );
		} );

		expect( shared ).toContain( 'aa-spin' );
		expect( [ ...new Set( missing ) ] ).toEqual( [] );
	} );
} );

describe( 'editorial type roles', () => {
	it( 'Headline (Sans) switches to the sans family in sentence case, overridable by editor picks', async () => {
		const styles = await CompiledCss.load(
			'src/styles/components/block-styles.css'
		);
		const headline = '.wp-block-heading.is-style-headline';

		expect( await styles.declarations( headline, 'font-family' ) ).toEqual(
			[
				expect.objectContaining( {
					value: 'var(--wp--preset--font-family--space-grotesk)',
					important: false,
				} ),
			]
		);
		expect(
			await styles.declarations( headline, 'text-transform' )
		).toEqual( [ expect.objectContaining( { value: 'none' } ) ] );
		// Long words break instead of scrolling the page at 200% text.
		for ( const role of [
			headline,
			'.wp-block-heading.is-style-campaign',
		] ) {
			expect(
				await styles.declarations( role, 'overflow-wrap' )
			).toEqual( [ expect.objectContaining( { value: 'anywhere' } ) ] );
		}
		// Takes the section's text color, not the global accent heading color.
		for ( const role of [
			headline,
			'.wp-block-heading.is-style-campaign',
		] ) {
			expect( await styles.declarations( role, 'color' ) ).toEqual( [
				expect.objectContaining( {
					value: 'inherit',
					important: false,
				} ),
			] );
		}
		expect( await styles.declarations( headline, 'font-size' ) ).toEqual( [
			expect.objectContaining( {
				value: 'var(--aa-type-headline-size)',
				important: false,
			} ),
		] );
	} );

	it( 'Campaign Display and Lead size from their type roles', async () => {
		const styles = await CompiledCss.load(
			'src/styles/components/block-styles.css'
		);

		expect(
			await styles.declarations(
				'.wp-block-heading.is-style-campaign',
				'font-size'
			)
		).toEqual( [
			expect.objectContaining( {
				value: 'var(--aa-type-display-size)',
				important: false,
			} ),
		] );
		expect(
			await styles.declarations( '.is-style-lead', 'font-size' )
		).toEqual( [
			expect.objectContaining( {
				value: 'var(--aa-type-lead-size)',
				important: false,
			} ),
		] );
	} );
} );

describe( 'product card buttons in narrow slots', () => {
	it( 'compact the add-to-cart pill in every card under 320px, including two-up mobile cards', async () => {
		const blocks = await CompiledCss.load(
			'src/styles/woocommerce/blocks.css'
		);
		const button =
			'.wp-block-woocommerce-product-button .wp-block-button__link';
		const conditionsFor = async ( prop: string ) =>
			( await blocks.declarations( button, prop ) ).map( ( d ) =>
				d.conditions.join( ' ' )
			);

		expect( await conditionsFor( 'font-size' ) ).toEqual(
			expect.arrayContaining( [
				expect.stringMatching( /product-card \(width ?< ?320px\)/ ),
			] )
		);
		expect( await conditionsFor( 'padding-inline' ) ).toEqual(
			expect.arrayContaining( [
				expect.stringMatching( /product-card \(width ?< ?200px\)/ ),
			] )
		);
	} );
} );

describe( 'responsive product grid columns', () => {
	it( 'derive the track minimum from the theme gap, so Columns: N renders N', async () => {
		const blocks = await CompiledCss.load(
			'src/styles/woocommerce/blocks.css'
		);
		const grid =
			'.wp-block-woocommerce-product-template.wc-block-product-template__responsive';

		const [ columns ] = await blocks.declarations(
			grid,
			'grid-template-columns'
		);
		expect( columns.important ).toBe( false );
		expect( columns.value ).toContain( 'var(--aa-product-grid-gap)' );
		expect( columns.value ).toContain( 'var(--aa-product-grid-columns)' );

		for ( const count of [ 2, 3, 4, 5, 6 ] ) {
			expect(
				await blocks.declarations(
					`${ grid }.columns-${ count }`,
					'--aa-product-grid-columns'
				)
			).toEqual( [
				expect.objectContaining( { value: String( count ) } ),
			] );
		}
	} );

	it( 'give homepage editorial grids of 4+ columns two large cards per row below desktop', async () => {
		const blocks = await CompiledCss.load(
			'src/styles/woocommerce/blocks.css'
		);

		expect(
			await blocks.declarations(
				'.is-style-editorial-grid .wp-block-woocommerce-product-template.wc-block-product-template__responsive:is(.columns-4,.columns-5,.columns-6)',
				'--aa-product-grid-columns'
			)
		).toEqual( [
			expect.objectContaining( {
				value: '2',
				conditions: [ '@media (width<1024px)' ],
			} ),
		] );
	} );
} );

describe( 'product card names', () => {
	it( 'set listing names in the body face, sentence case, unlayered at (0,2,0) so they beat the global post-title style but not editor picks', async () => {
		const blocks = await CompiledCss.load(
			'src/styles/woocommerce/blocks.css'
		);
		const name =
			'.wp-block-woocommerce-product-template .wp-block-post-title';

		const [ family ] = await blocks.declarations( name, 'font-family' );
		expect( family ).toEqual(
			expect.objectContaining( {
				value: 'var(--wp--preset--font-family--space-grotesk)',
				important: false,
				layer: null,
			} )
		);
		expect( await blocks.declarations( name, 'text-transform' ) ).toEqual( [
			expect.objectContaining( { value: 'none', important: false } ),
		] );
		// Size is the editor's (template fontSize attribute), never restated here.
		expect( await blocks.declarations( name, 'font-size' ) ).toEqual( [] );
	} );
} );

describe( 'editorial category tiles', () => {
	it( 'seat the category name on the lower edge of the tile', async () => {
		const blocks = await CompiledCss.load(
			'src/styles/woocommerce/blocks.css'
		);
		const wrapper =
			'.aa-editorial-tiles .wc-block-featured-category .wc-block-featured-category__wrapper';

		expect( await blocks.declarations( wrapper, 'align-content' ) ).toEqual(
			[ expect.objectContaining( { value: 'flex-end' } ) ]
		);
	} );
} );

describe( 'product gallery thumbnails', () => {
	it( 'align the desktop strip to the top of a portrait frame', async () => {
		const thumbs = await CompiledCss.load(
			'src/styles/woocommerce/product-gallery-thumbnails.css'
		);
		const [ align ] = await thumbs.declarations(
			'.wc-block-product-gallery .wc-block-product-gallery-thumbnails',
			'align-self'
		);

		expect( align.value ).toBe( 'flex-start' );
		expect( align.conditions ).toEqual( [
			expect.stringMatching( /width\s*>\s*600px|min-width/ ),
		] );
	} );
} );

describe( 'desktop navigation menubar', () => {
	it( "caps the item gap at the editor's block gap and tightens it on narrow desktops", async () => {
		const nav = await CompiledCss.load(
			'src/blocks-interactivity/navigation/style.css'
		);
		const [ gap ] = await nav.declarations( '.aa-nav__menubar', 'gap' );

		expect( gap.value.replace( /\s+/g, '' ) ).toBe(
			'min(var(--navigation-gap),2.25vw - .5rem)'.replace( /\s+/g, '' )
		);
	} );
} );

describe( 'centred short product rows', () => {
	it( 'drop empty tracks and keep card width instead of stretching', async () => {
		const blocks = await CompiledCss.load(
			'src/styles/woocommerce/blocks.css'
		);
		const grid =
			'.wp-block-woocommerce-product-collection.aa-grid-centered .wp-block-woocommerce-product-template.wc-block-product-template__responsive';

		const [ columns ] = await blocks.declarations(
			grid,
			'grid-template-columns'
		);
		expect( columns.value ).toMatch( /^repeat\(auto-fit,/ );
		expect( columns.value ).not.toContain( '1fr' );
		expect( await blocks.declarations( grid, 'justify-content' ) ).toEqual(
			[ expect.objectContaining( { value: 'center' } ) ]
		);
	} );
} );

describe( 'editorial sections', () => {
	it( 'size the hero slogan to its column, and to the full width without a photo', async () => {
		const editorial = await CompiledCss.load(
			'src/styles/components/editorial.css'
		);
		const campaign = editorial
			.all()
			.filter(
				( d ) =>
					d.selector.startsWith( '.aa-campaign-hero' ) &&
					d.selector.includes( '.is-style-campaign' )
			);
		const typeOnly = campaign.filter( ( d ) =>
			d.selector.includes( ':not(:has(.wp-block-image))' )
		);

		expect( campaign.map( ( d ) => d.prop ) ).toEqual(
			expect.arrayContaining( [ 'max-width', 'font-size' ] )
		);
		expect( typeOnly.map( ( d ) => d.prop ) ).toContain( 'font-size' );
		expect( campaign.every( ( d ) => ! d.important ) ).toBe( true );
	} );

	it( 'make the lookbook a phone rail, overriding only core’s mobile stacking and margin pinning', async () => {
		const editorial = await CompiledCss.load(
			'src/styles/components/editorial.css'
		);
		const rail = editorial
			.all()
			.filter( ( d ) => d.selector.includes( '.aa-editorial-looks' ) );

		expect(
			rail
				.filter( ( d ) => d.important )
				.map( ( d ) => d.prop )
				.sort()
		).toEqual( [ 'flex-basis', 'flex-wrap', 'margin-inline' ] );
		expect(
			rail.every( ( d ) => /600px/.test( d.conditions.join() ) )
		).toBe( true );
		expect( rail.map( ( d ) => d.prop ) ).toEqual(
			expect.arrayContaining( [ 'overflow-x', 'scroll-snap-type' ] )
		);
	} );

	it( 'run bleed photos the full row height without turning off the column stretch', async () => {
		const editorial = await CompiledCss.load(
			'src/styles/components/editorial.css'
		);
		const fill = editorial
			.all()
			.filter(
				( d ) =>
					d.selector.startsWith( '.aa-bleed-photo' ) &&
					d.prop === 'height'
			);

		expect( fill.length ).toBeGreaterThan( 0 );
		// The column itself must keep height:auto, or flex stretch stops.
		expect(
			fill.some( ( d ) => /^\.aa-bleed-photo$/.test( d.selector ) )
		).toBe( false );
		expect(
			fill.every( ( d ) => /600px/.test( d.conditions.join() ) )
		).toBe( true );
	} );

	it( 'make the category tiles a swipe rail on phones', async () => {
		const blocks = await CompiledCss.load(
			'src/styles/woocommerce/blocks.css'
		);
		const rail = blocks
			.all()
			.filter( ( d ) =>
				d.selector.includes( '.aa-editorial-tiles.is-layout-grid' )
			);

		expect( rail.map( ( d ) => d.prop ) ).toEqual(
			expect.arrayContaining( [
				'display',
				'overflow-x',
				'scroll-snap-type',
			] )
		);
		expect(
			rail.every( ( d ) => /782px/.test( d.conditions.join() ) )
		).toBe( true );
	} );

	it( 'keep editorial splits side by side on tablets, overriding only core stacking', async () => {
		const editorial = await CompiledCss.load(
			'src/styles/components/editorial.css'
		);
		const split = editorial
			.all()
			.filter( ( d ) => d.selector.includes( '.aa-tablet-split' ) );

		expect(
			split
				.filter( ( d ) => d.important )
				.map( ( d ) => d.prop )
				.sort()
		).toEqual( [ 'flex-basis', 'flex-wrap' ] );
		expect(
			split.every(
				( d ) =>
					/600px/.test( d.conditions.join() ) &&
					/782px/.test( d.conditions.join() )
			)
		).toBe( true );
	} );

	it( 'scroll a rail grid sideways with a fraction of the next card showing', async () => {
		const blocks = await CompiledCss.load(
			'src/styles/woocommerce/blocks.css'
		);
		const rail = blocks
			.all()
			.filter( ( d ) => d.selector.startsWith( '.aa-grid-rail' ) );

		expect( rail.map( ( d ) => d.prop ) ).toEqual(
			expect.arrayContaining( [
				'display',
				'overflow-x',
				'scroll-snap-type',
				'flex',
			] )
		);
		expect(
			rail
				.filter( ( d ) => d.prop === '--aa-rail-visible' )
				.map( ( d ) => d.value )
		).toEqual( expect.arrayContaining( [ '4.5', '2.5', '1.6' ] ) );
	} );

	it( 'show every card photo whole, in a square slot', async () => {
		const blocks = await CompiledCss.load(
			'src/styles/woocommerce/blocks.css'
		);
		const slot =
			'.wp-block-woocommerce-product-template .wp-block-post-featured-image';

		expect( await blocks.declarations( slot, 'aspect-ratio' ) ).toEqual( [
			expect.objectContaining( { value: '1' } ),
		] );
		expect(
			await blocks.declarations( `${ slot } img`, 'object-fit' )
		).toEqual( [ expect.objectContaining( { value: 'contain' } ) ] );
	} );

	it( 'end the lead card image level with the 2 x 2 beside it (1:1.11)', async () => {
		const blocks = await CompiledCss.load(
			'src/styles/woocommerce/blocks.css'
		);

		expect(
			await blocks.declarations(
				'.aa-grid-lead .wp-block-woocommerce-product-template .wp-block-post-featured-image',
				'aspect-ratio'
			)
		).toEqual( [
			expect.objectContaining( {
				value: '1/1.11',
			} ),
		] );
	} );

	it( 'let a linked 100%-width image fill its column, not its natural size', async () => {
		const editorial = await CompiledCss.load(
			'src/styles/components/editorial.css'
		);

		expect(
			await editorial.declarations(
				'.wp-block-image>a:has(>img[style*="width:100%"])',
				'display'
			)
		).toEqual( [
			expect.objectContaining( { value: 'block', important: false } ),
		] );
	} );
} );
