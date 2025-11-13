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

		// Ensure WooCommerce is active for tests
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}
	}

	/**
	 * Test that Bootstrap initializes WooCommerce components
	 */
	public function test_bootstrap_initializes_woocommerce_components() {
		$bootstrap = Bootstrap::get_instance();

		// Verify Bootstrap instance is created
		$this->assertInstanceOf( Bootstrap::class, $bootstrap );

		// Verify WooCommerce functions are conditionally available
		$this->assertTrue( function_exists( 'is_shop' ) );
		$this->assertTrue( function_exists( 'is_product' ) );
		$this->assertTrue( function_exists( 'is_product_category' ) );
	}

	/**
	 * Test WooCommerce body classes are added
	 */
	public function test_woocommerce_body_classes_added() {
		// Simulate WooCommerce being active
		if ( ! defined( 'WOOCOMMERCE_VERSION' ) ) {
			define( 'WOOCOMMERCE_VERSION', '7.0.0' );
		}

		$bootstrap = Bootstrap::get_instance();

		// Get body classes (this would normally be called by WordPress)
		$classes = apply_filters( 'body_class', array() );

		// Verify WooCommerce classes are added
		$this->assertContains( 'woocommerce-active', $classes );
	}

	/**
	 * Test conditional WooCommerce function calls
	 */
	public function test_conditional_woocommerce_function_calls() {
		$bootstrap = Bootstrap::get_instance();

		// Test that our code safely handles WooCommerce functions
		// These would normally be tested in an actual WordPress environment
		$this->assertTrue( class_exists( 'WooCommerce' ) );

		// Verify function existence checks work
		$this->assertTrue( function_exists( 'is_shop' ) );
		$this->assertTrue( function_exists( 'is_product' ) );
	}

	/**
	 * Test WebP system integration with WooCommerce
	 */
	public function test_webp_integration_with_woocommerce() {
		$bootstrap = Bootstrap::get_instance();

		// Verify WebP classes are loaded
		$this->assertTrue( class_exists( 'Aggressive_Apparel\\Core\\WebP_Support' ) );
		$this->assertTrue( class_exists( 'Aggressive_Apparel\\Core\\WebP_Utils' ) );

		// Test WebP utility functions
		$webp_utils = new \Aggressive_Apparel\Core\WebP_Utils();

		// Test basic WebP URL generation
		$test_url = 'https://example.com/wp-content/uploads/2023/01/test.jpg';
		$webp_url = $webp_utils->get_webp_url( $test_url );

		// Should return false for non-existent images
		$this->assertFalse( $webp_url );
	}
}
