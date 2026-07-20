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
			'background-color: transparent !important;',
			$css,
			'Notice actions should retain the outline-button treatment.'
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
}
