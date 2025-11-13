<?php
/**
 * Test Bootstrap Class
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Unit;

use WP_UnitTestCase;
use Aggressive_Apparel\Bootstrap;

/**
 * Bootstrap Test Case
 */
class TestBootstrap extends WP_UnitTestCase {
	/**
	 * Bootstrap instance
	 *
	 * @var Bootstrap
	 */
	private $bootstrap;

	/**
	 * Set up test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->bootstrap = Bootstrap::get_instance();
	}

	/**
	 * Test singleton instance
	 */
	public function test_get_instance_returns_singleton() {
		$instance1 = Bootstrap::get_instance();
		$instance2 = Bootstrap::get_instance();

		$this->assertSame( $instance1, $instance2, 'Should return the same instance' );
	}

	/**
	 * Test body classes adds WooCommerce classes when active
	 */
	public function test_body_classes_adds_woocommerce_when_active() {
		// Test with WooCommerce inactive (should not add classes)
		$classes = $this->bootstrap->add_body_classes( array() );
		$this->assertIsArray( $classes, 'Should return an array' );

		// If WooCommerce is loaded, test that classes are added
		if ( class_exists( 'WooCommerce' ) ) {
			$this->assertContains( 'woocommerce-active', $classes, 'Should add woocommerce-active class' );

			// Test on shop page (simulate conditions)
			global $wp_query;
			$original_query = $wp_query;

			// Mock is_shop() to return true
			add_filter( 'woocommerce_is_shop_page', '__return_true' );
			$classes_with_shop = $this->bootstrap->add_body_classes( array() );

			if ( function_exists( 'is_shop' ) && is_shop() ) {
				$this->assertContains( 'woocommerce-shop-page', $classes_with_shop, 'Should add woocommerce-shop-page class' );
			}

			// Restore original query
			$GLOBALS['wp_query'] = $original_query;
			remove_filter( 'woocommerce_is_shop_page', '__return_true' );
		}
	}

	/**
	 * Test theme initialization
	 */
	public function test_theme_initialized() {
		// Check if theme supports are registered.
		$this->assertTrue(
			current_theme_supports( 'title-tag' ),
			'Bootstrap should initialize theme support'
		);
	}
}
