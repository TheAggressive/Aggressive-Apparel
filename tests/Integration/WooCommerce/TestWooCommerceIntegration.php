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
	 * When WooCommerce is active, the theme declares WooCommerce theme support —
	 * i.e. WooCommerce_Support actually ran. (The previous version only asserted
	 * that WooCommerce's own is_shop()/is_product() functions exist, which tests
	 * WooCommerce, not this theme.)
	 */
	public function test_theme_declares_woocommerce_support_when_active() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active in this environment.' );
		}

		$this->assertTrue(
			current_theme_supports( 'woocommerce' ),
			'Theme should declare WooCommerce support when WooCommerce is active.'
		);
		$this->assertTrue(
			current_theme_supports( 'wc-product-gallery-zoom' ),
			'Theme should declare product-gallery zoom support.'
		);
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

}
