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
			wp_style_is( 'aggressive-apparel-mini-cart', 'enqueued' ),
			'Mini-cart stylesheet should load through the normal WooCommerce style queue'
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
}
