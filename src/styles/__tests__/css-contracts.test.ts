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
