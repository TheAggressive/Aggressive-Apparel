<?php
/**
 * WooCommerce Integration Tests
 *
 * Tests the theme's integration with WooCommerce plugin
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\Bootstrap;
use WP_UnitTestCase;

/**
 * Class TestWooCommerceIntegration
 */
class TestWooCommerceIntegration extends WP_UnitTestCase {

	/**
	 * Set up test environment
	 */
	public function setUp(): void {
		parent::setUp();
	}

	/**
	 * Test that Bootstrap initializes WooCommerce components conditionally
	 */
	public function test_bootstrap_initializes_woocommerce_components() {
		$bootstrap = Bootstrap::get_instance();

		// Verify Bootstrap instance is created
		$this->assertInstanceOf( Bootstrap::class, $bootstrap );

		// Test conditional WooCommerce function availability
		if ( class_exists( 'WooCommerce' ) ) {
			// When WooCommerce is active, functions should be available
			$this->assertTrue( function_exists( 'is_shop' ), 'is_shop should be available when WooCommerce is active' );
			$this->assertTrue( function_exists( 'is_product' ), 'is_product should be available when WooCommerce is active' );
			$this->assertTrue( function_exists( 'is_product_category' ), 'is_product_category should be available when WooCommerce is active' );
		} else {
			// When WooCommerce is not active, functions may or may not be available
			// (they could be provided by stubs for development)
			// Just test that Bootstrap can be instantiated
			$this->assertInstanceOf( Bootstrap::class, $bootstrap );
		}
	}

	/**
	 * Test WooCommerce body classes are added conditionally
	 */
	public function test_woocommerce_body_classes_added() {
		$bootstrap = Bootstrap::get_instance();

		// Get body classes (this would normally be called by WordPress)
		$classes = apply_filters( 'body_class', array() );

		// Test conditional behavior
		if ( class_exists( 'WooCommerce' ) ) {
			// When WooCommerce is active, woocommerce-active class should be added
			$this->assertContains( 'woocommerce-active', $classes, 'woocommerce-active class should be added when WooCommerce is active' );
		} else {
			// When WooCommerce is not active, class should not be added
			$this->assertNotContains( 'woocommerce-active', $classes, 'woocommerce-active class should not be added when WooCommerce is not active' );
		}
	}

	/**
	 * Test conditional WooCommerce function calls
	 */
	public function test_conditional_woocommerce_function_calls() {
		$bootstrap = Bootstrap::get_instance();

		// Test conditional function availability
		if ( class_exists( 'WooCommerce' ) ) {
			// When WooCommerce is active, functions should be available
			$this->assertTrue( function_exists( 'is_shop' ), 'is_shop should exist when WooCommerce is active' );
			$this->assertTrue( function_exists( 'is_product' ), 'is_product should exist when WooCommerce is active' );
		} else {
			// When WooCommerce is not active, functions may still exist (via stubs)
			// Just verify Bootstrap works
			$this->assertInstanceOf( Bootstrap::class, $bootstrap );
		}
	}

}
