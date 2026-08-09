<?php
/**
 * Test Styles Class
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit\Assets;

use WP_UnitTestCase;
use Aggressive_Apparel\Assets\Styles;

/**
 * Styles Test Case
 */
class TestStyles extends WP_UnitTestCase {
	/**
	 * Styles instance
	 *
	 * @var Styles
	 */
	private $styles;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->styles = new Styles();
		$this->styles->init();
	}

	/**
	 * Test styles are registered
	 */
	public function test_styles_enqueued() {
		// Trigger the enqueue action.
		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue(
			wp_style_is( 'aggressive-apparel-main', 'registered' ),
			'Main stylesheet should be registered'
		);

		$this->assertTrue(
			wp_style_is( 'aggressive-apparel-woocommerce-notices', 'enqueued' ),
			'WooCommerce notices stylesheet should load through the normal WooCommerce style queue'
		);

		$this->assertTrue(
			wp_style_is( 'aggressive-apparel-mini-cart', 'enqueued' ),
			'Mini-cart stylesheet should load through the normal WooCommerce style queue'
		);
	}

	/**
	 * Classic and block notices should share one built theme component.
	 */
	public function test_woocommerce_notice_variants_are_built() {
		$css_file = get_template_directory() . '/build/styles/woocommerce/notices.css';

		$this->assertFileExists( $css_file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local build artifact in a test.
		$css = file_get_contents( $css_file );
		$this->assertIsString( $css );

		$this->assertStringContainsString( '.woocommerce-message', $css, 'Classic success notices should be built.' );
		$this->assertStringContainsString( '.woocommerce-error', $css, 'Classic error notices should be built.' );
		$this->assertStringContainsString( '.wc-block-components-notice-banner', $css, 'Block notices should be built.' );
		$this->assertStringContainsString( 'prefers-reduced-motion:reduce', $css, 'Reduced-motion fallback should be built.' );
	}

	/**
	 * WooCommerce's late block bundles must not reclaim notice text or actions.
	 */
	public function test_woocommerce_notice_vendor_overrides_are_explicit() {
		$css_file = get_template_directory() . '/src/styles/woocommerce/notices.css';

		$this->assertFileExists( $css_file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source artifact in a test.
		$css = file_get_contents( $css_file );
		$this->assertIsString( $css );

		$this->assertStringContainsString(
			'.wc-block-components-notice-banner > .wc-block-components-notice-banner__content',
			$css,
			'Notice content should target WooCommerce block markup exactly.'
		);
		$this->assertStringContainsString(
			'.wc-block-components-notice-banner > .wc-block-components-notice-banner__content .wc-forward',
			$css,
			'Notice actions should target WooCommerce block markup exactly.'
		);
		$this->assertStringContainsString(
			'color: var(--aa-color-foreground) !important;',
			$css,
			'Notice text contrast should beat WooCommerce late-loading bundles.'
		);
		$this->assertStringContainsString(
			'background-color: var(--wp--custom--button--secondary--background) !important;',
			$css,
			'Notice actions should inherit the canonical secondary-button treatment.'
		);
		$this->assertStringContainsString(
			'background-color: var(--wp--custom--button--secondary--hover-background) !important;',
			$css,
			'Notice action hover states should inherit the canonical secondary-button treatment.'
		);
	}

	/**
	 * The branded mini-cart badge interaction must survive CSS optimization.
	 */
	public function test_mini_cart_badge_brand_ui_is_built() {
		$css_file = get_template_directory() . '/build/styles/woocommerce/mini-cart.css';

		$this->assertFileExists( $css_file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local build artifact in a test.
		$css = file_get_contents( $css_file );
		$this->assertIsString( $css );

		$this->assertStringContainsString( '.wc-block-mini-cart__badge:before', $css, 'Badge dot should be built.' );
		$this->assertStringContainsString( '.wc-block-mini-cart__badge:after', $css, 'Badge ping ring should be built.' );
		$this->assertStringContainsString( 'aa-badge-ping', $css, 'Badge ping animation should be built.' );
		$this->assertStringContainsString( '.wc-block-mini-cart__badge:hover', $css, 'Pointer reveal should be built.' );
		$this->assertStringContainsString( '.wc-block-mini-cart__button:focus-visible .wc-block-mini-cart__badge', $css, 'Keyboard reveal should be built.' );
		$this->assertStringContainsString( 'prefers-reduced-motion:reduce', $css, 'Reduced-motion fallback should be built.' );
	}

	/**
	 * Quick View placement modifiers must target the action stack itself.
	 */
	public function test_quick_view_card_position_selectors_are_compound(): void {
		$css_file = get_template_directory() . '/build/styles/woocommerce/quick-view.css';

		$this->assertFileExists( $css_file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local build artifact in a test.
		$css = file_get_contents( $css_file );
		$this->assertIsString( $css );

		$this->assertStringContainsString(
			'.aggressive-apparel-card-actions.aggressive-apparel-card-actions--corner',
			$css,
			'Corner placement must target modifier classes on the action stack itself.'
		);
		$this->assertStringContainsString(
			'.aggressive-apparel-card-actions.aggressive-apparel-card-actions--bottom-bar',
			$css,
			'Bottom-bar placement must target modifier classes on the action stack itself.'
		);
		$this->assertStringNotContainsString(
			'.aggressive-apparel-card-actions .aggressive-apparel-card-actions--corner',
			$css,
			'Placement must not require a nonexistent nested action-stack element.'
		);
	}

	/**
	 * Product-card defaults must not outrank styles saved by the Site Editor.
	 *
	 * Load More and Infinite Scroll append freshly rendered cards to the native
	 * product template. Keeping theme defaults in :where() ensures the palette
	 * and typography classes on those cards win regardless of stylesheet order.
	 */
	public function test_product_card_defaults_preserve_editor_styles() {
		$css_file = get_template_directory() . '/src/styles/woocommerce/blocks.css';

		$this->assertFileExists( $css_file );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source artifact in a test.
		$css = file_get_contents( $css_file );
		$this->assertIsString( $css );

		$this->assertStringContainsString(
			':where(.wp-block-woocommerce-product-collection)',
			$css,
			'Product Collection defaults should remain specificity-free.'
		);
		$this->assertStringContainsString(
			':where(.wp-block-woocommerce-product-title, .wc-block-grid__product-title)',
			$css,
			'Product title defaults should remain specificity-free.'
		);

		$theme_json_file = get_template_directory() . '/theme.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local theme configuration in a test.
		$theme_json = file_get_contents( $theme_json_file );
		$this->assertIsString( $theme_json );
		$this->assertStringNotContainsString(
			'font-weight: 400 !important',
			$theme_json,
			'Global heading defaults must not override editor-selected typography.'
		);
	}

	/**
	 * Button roles and adaptive paint must be configured in theme.json.
	 */
	public function test_button_system_is_driven_by_theme_json() {
		$theme_json_file = get_template_directory() . '/theme.json';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local theme configuration in a test.
		$theme_json = json_decode( (string) file_get_contents( $theme_json_file ), true );

		$this->assertIsArray( $theme_json );
		$this->assertSame(
			'var(--wp--preset--color--accent)',
			$theme_json['settings']['custom']['button']['primary']['background']
		);
		$this->assertSame(
			'var(--wp--preset--color--foreground)',
			$theme_json['settings']['custom']['button']['secondary']['border']
		);
		$this->assertSame(
			'transparent',
			$theme_json['settings']['custom']['button']['icon']['background']
		);
		$this->assertSame(
			'var(--wp--preset--color--accent)',
			$theme_json['settings']['custom']['button']['icon']['hoverText']
		);
		$this->assertArrayNotHasKey(
			'hoverBackground',
			$theme_json['settings']['custom']['button']['icon'],
			'Default icon controls must retain their resting background on hover.'
		);
		$this->assertSame(
			'var(--wp--preset--color--foreground)',
			$theme_json['settings']['custom']['button']['choice']['selectedBackground']
		);
		$this->assertStringContainsString(
			'--wp--preset--color--surface',
			$theme_json['settings']['custom']['focusRing']
		);

		$button_styles = $theme_json['styles']['elements']['button'];
		$this->assertSame(
			'var(--wp--custom--button--primary--background)',
			$button_styles['color']['background']
		);
		$this->assertSame( '2px', $button_styles['border']['width'] );
		$this->assertSame(
			'var(--wp--custom--radius--button)',
			$button_styles['border']['radius'],
			'Primary and secondary actions must preserve the branded pill silhouette.'
		);
		$this->assertSame( 'calc(infinity * 1px)', $theme_json['settings']['custom']['radius']['button'] );
		$this->assertSame( '700', $button_styles['typography']['fontWeight'] );
		$this->assertSame( '2.75rem', $theme_json['settings']['custom']['size']['iconButton'] );
			$this->assertSame(
				'2.75rem',
				$theme_json['settings']['custom']['size']['controlMin'],
				'Compact button recipes must retain a 44px minimum target.'
			);
			$this->assertSame(
				'2rem',
				$theme_json['settings']['custom']['size']['denseControlMin'],
				'Dense merchandising-card choices must retain a WCAG AA-safe 32px target.'
			);
			$this->assertSame( '2rem', $theme_json['settings']['custom']['size']['cardSwatchMin'] );
			$this->assertSame( '1.75rem', $theme_json['settings']['custom']['size']['cardSwatchDenseMin'] );
		$this->assertArrayNotHasKey(
			':focus',
			$button_styles,
			'Keyboard focus must preserve the active button variant paint.'
		);
		$this->assertArrayNotHasKey(
			':active',
			$button_styles,
			'Pressed-state mechanics belong to the shared button stylesheet.'
		);
	}

	/**
	 * Shared button and swatch mechanics preserve accessible interaction targets.
	 */
	public function test_button_and_swatch_accessibility_contract_is_in_source_css() {
		$button_file = get_template_directory() . '/src/styles/components/buttons.css';
		$swatch_file = get_template_directory() . '/src/blocks-interactivity/product-color-swatches/style.css';

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local source artifacts in a test.
		$button_css = file_get_contents( $button_file );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local source artifacts in a test.
		$swatch_css = file_get_contents( $swatch_file );

		$this->assertIsString( $button_css );
		$this->assertIsString( $swatch_css );
		$this->assertStringContainsString( 'min-height: var(--aa-button-height-md);', $button_css );
		$this->assertStringContainsString( 'box-shadow: var(--aa-focus-ring);', $button_css );
		$this->assertStringContainsString( 'pointer-events: none;', $button_css );
		$this->assertStringContainsString( ':where(.aa-icon-button) {', $button_css );
		$this->assertStringContainsString( 'min-width: var(--aa-icon-button-size);', $button_css );
		$this->assertStringContainsString( ':where(.aa-choice-pill) {', $button_css );
		$this->assertStringContainsString( ':where(.aa-stepper-button) {', $button_css );
		$this->assertStringContainsString(
			'--aa-icon-button-hover-background: var(--aa-icon-button-background);',
			$button_css
		);
		$this->assertStringContainsString(
			'.aggressive-apparel-button.aggressive-apparel-button--outline',
			$button_css,
			'Secondary paint must outrank WordPress global element styles without using !important.'
		);
		$this->assertMatchesRegularExpression(
			'/\\.aa-product-color-swatches__swatch\\s*\\{[^}]*min-width:\\s*var\\(--aa-card-swatch-min\\);[^}]*min-height:\\s*var\\(--aa-card-swatch-min\\);/s',
			$swatch_css,
			'Card swatches must retain their theme.json-backed 32px pointer target.'
		);
		$this->assertStringContainsString(
			'@container product-card (width < 11rem)',
			$swatch_css,
			'Narrow product cards must opt into the explicit dense interaction tier.'
		);
		$this->assertStringContainsString(
			'min-width: var(--aa-card-swatch-dense-min);',
			$swatch_css,
			'Dense card swatches must use the theme.json-backed 28px target token.'
		);
	}
}
