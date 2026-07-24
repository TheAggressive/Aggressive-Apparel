<?php
/**
 * Test WooCommerce Integration
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Integration;

use WP_UnitTestCase;
use Aggressive_Apparel\WooCommerce\WooCommerce_Support;
use Aggressive_Apparel\WooCommerce\Product_Loop;

/**
 * WooCommerce Integration Test Case
 */
class TestWooCommerceIntegration extends WP_UnitTestCase {
	/**
	 * WooCommerce Support instance
	 *
	 * @var WooCommerce_Support
	 */
	private $woocommerce_support;

	/**
	 * Product Loop instance
	 *
	 * @var Product_Loop
	 */
	private $product_loop;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->woocommerce_support = new WooCommerce_Support();
		$this->product_loop        = new Product_Loop();

		// Initialize product loop to register filters
		$this->product_loop->init();
	}

	/**
	 * Test WooCommerce Support class functionality
	 */
	public function test_woocommerce_support_functionality() {
		// Test that the class can be instantiated
		$this->assertInstanceOf(
			WooCommerce_Support::class,
			$this->woocommerce_support,
			'WooCommerce Support should be instantiable'
		);

		// Test conditional WooCommerce support
		if ( class_exists( 'WooCommerce' ) ) {
			// When WooCommerce is active, theme should declare support
			$this->assertTrue(
				current_theme_supports( 'woocommerce' ),
				'Theme should declare WooCommerce support when WooCommerce is active'
			);

			// Test that WooCommerce gallery features are supported
			$this->assertTrue(
				current_theme_supports( 'wc-product-gallery-zoom' ),
				'Theme should support WooCommerce gallery zoom'
			);

			$this->assertTrue(
				current_theme_supports( 'wc-product-gallery-lightbox' ),
				'Theme should support WooCommerce gallery lightbox'
			);
		} else {
			// When WooCommerce is not active, theme should not declare support
			$this->assertFalse(
				current_theme_supports( 'woocommerce' ),
				'Theme should not declare WooCommerce support when WooCommerce is not active'
			);
		}
	}

	/**
	 * Test Product Loop class functionality
	 */
	public function test_product_loop_functionality() {
		$this->assertInstanceOf(
			Product_Loop::class,
			$this->product_loop,
			'Product Loop should be instantiable'
		);

		// Test that product loop filters are available (regardless of WooCommerce status)
		$this->assertTrue( has_filter( 'loop_shop_columns' ), 'Product columns filter should be available' );
		$this->assertTrue( has_filter( 'loop_shop_per_page' ), 'Products per page filter should be available' );

		// Test that filters can be applied
		$columns = apply_filters( 'loop_shop_columns', 4 );
		$this->assertIsInt( $columns, 'Product columns should be an integer' );
		$this->assertGreaterThan( 0, $columns, 'Product columns should be greater than 0' );

		$per_page = apply_filters( 'loop_shop_per_page', 12 );
		$this->assertIsInt( $per_page, 'Products per page should be an integer' );
		$this->assertGreaterThan( 0, $per_page, 'Products per page should be greater than 0' );
	}


	/**
	 * Test WooCommerce templates exist
	 */
	public function test_woocommerce_templates_exist() {
		$templates = array(
			'archive-product.html',
			'single-product.html',
			'page-cart.html',
			'page-checkout.html',
			'taxonomy-product_cat.html',
		);

		$template_dir = get_template_directory() . '/templates/';

		// Templates should exist regardless of WooCommerce status
		foreach ( $templates as $template ) {
			$this->assertFileExists(
				$template_dir . $template,
				"WooCommerce template {$template} should exist"
			);
		}
	}

	/**
	 * Test the single-product notice does not extend WooCommerce wrappers
	 * across the footer or create a flow gap above the product content.
	 */
	public function test_single_product_notice_preserves_template_boundaries() {
		$template_path    = get_template_directory() . '/templates/single-product.html';
		$template_content = file_get_contents( $template_path );

		$this->assertNotFalse( $template_content, 'Single-product template should be readable.' );

		$top_level_blocks = array_values(
			array_filter(
				parse_blocks( $template_content ),
				static fn( array $block ): bool => ! empty( $block['blockName'] )
			)
		);

		$main_index   = null;
		$footer_index = null;

		foreach ( $top_level_blocks as $index => $block ) {
			$this->assertNotSame(
				'aggressive-apparel/store-notices',
				$block['blockName'],
				'Store notices must not be a top-level block after the footer.'
			);

			if ( 'core/group' === $block['blockName'] && 'main' === ( $block['attrs']['tagName'] ?? null ) ) {
				$main_index = $index;
			}

			if ( 'core/template-part' === $block['blockName'] && 'footer' === ( $block['attrs']['slug'] ?? null ) ) {
				$footer_index = $index;
			}
		}

		$this->assertNotNull( $main_index, 'Single-product template should contain a main group.' );
		$this->assertNotNull( $footer_index, 'Single-product template should contain a footer template part.' );
		$this->assertGreaterThan( $main_index, $footer_index, 'Footer should follow the completed main group.' );

		$main = $top_level_blocks[ $main_index ];

		$this->assertSame(
			'aggressive-apparel/store-notices',
			$main['innerBlocks'][0]['blockName'] ?? null,
			'Store notices should be the first block inside main.'
		);
		$this->assertSame(
			'var:preset|spacing|0',
			$main['attrs']['style']['spacing']['blockGap'] ?? null,
			'Main should use zero automatic block gap because the first notice block is fixed-positioned.'
		);

		$spaced_sections = array_filter(
			$main['innerBlocks'],
			static function ( array $block ): bool {
				if ( 'core/group' !== $block['blockName'] ) {
					return false;
				}

				$is_tabs    = 'wide' === ( $block['attrs']['align'] ?? null );
				$is_related = 'surface-muted' === ( $block['attrs']['backgroundColor'] ?? null );

				return $is_tabs || $is_related;
			}
		);

		$this->assertCount( 2, $spaced_sections, 'Tabs and related-products groups should be present.' );

		foreach ( $spaced_sections as $section ) {
			$this->assertSame(
				'var:preset|spacing|fluid-24',
				$section['attrs']['style']['spacing']['margin']['top'] ?? null,
				'Visible product sections should own their top spacing explicitly.'
			);
		}
	}
}
