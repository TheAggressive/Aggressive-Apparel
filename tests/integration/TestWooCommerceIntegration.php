<?php
/**
 * Test WooCommerce Integration
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Integration;

use WP_UnitTestCase;
use Aggressive_Apparel\WooCommerce\Support;
use Aggressive_Apparel\WooCommerce\Product_Loop;

/**
 * WooCommerce Integration Test Case
 */
class TestWooCommerceIntegration extends WP_UnitTestCase {
	/**
	 * WooCommerce Support instance
	 *
	 * @var Support
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

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available' );
		}

		$this->woocommerce_support = new Support();
		$this->product_loop = new Product_Loop();
	}

	/**
	 * Test WooCommerce Support class functionality
	 */
	public function test_woocommerce_support_functionality() {
		// Test that the class can be instantiated
		$this->assertInstanceOf(
			Support::class,
			$this->woocommerce_support,
			'WooCommerce Support should be instantiable'
		);

		// Test that theme declares WooCommerce support
		$this->assertTrue(
			current_theme_supports( 'woocommerce' ),
			'Theme should declare WooCommerce support'
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

		// Test that product loop columns filter is properly set
		$columns = apply_filters( 'loop_shop_columns', 4 );
		$this->assertIsInt( $columns, 'Product columns should be an integer' );
		$this->assertGreaterThan( 0, $columns, 'Product columns should be greater than 0' );

		// Test that products per page filter works
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

		foreach ( $templates as $template ) {
			$this->assertFileExists(
				$template_dir . $template,
				"WooCommerce template {$template} should exist"
			);
		}
	}
}
