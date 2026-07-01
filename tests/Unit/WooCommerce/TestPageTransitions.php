<?php
/**
 * Page transition speculative-loading tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Page_Transitions;
use Aggressive_Apparel\WooCommerce\Block_Filter_Hooks;
use WP_UnitTestCase;

/**
 * Verify page transitions use safe, intent-driven prefetching.
 */
class TestPageTransitions extends WP_UnitTestCase {

	/**
	 * Clean up request-context filters.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'aggressive_apparel_is_listing_page' );

		parent::tearDown();
	}

	/**
	 * Modern WordPress should own speculation-rule output.
	 *
	 * @return void
	 */
	public function test_registers_wordpress_speculation_filters(): void {
		$transitions = new Page_Transitions();
		$transitions->init();

		$this->assertNotFalse(
			has_filter(
				'wp_speculation_rules_configuration',
				array( $transitions, 'configure_speculative_loading' )
			)
		);
		$this->assertNotFalse(
			has_filter(
				'wp_speculation_rules_href_exclude_paths',
				array( $transitions, 'exclude_sensitive_paths' )
			)
		);
		remove_action( 'wp_enqueue_scripts', array( $transitions, 'enqueue_styles' ) );
		remove_action( 'wp_enqueue_scripts', array( $transitions, 'enqueue_script' ) );
		remove_action( 'wp_head', array( $transitions, 'output_direction_script' ) );
		remove_filter( 'wp_speculation_rules_configuration', array( $transitions, 'configure_speculative_loading' ) );
		remove_filter( 'wp_speculation_rules_href_exclude_paths', array( $transitions, 'exclude_sensitive_paths' ) );
		remove_filter(
			Block_Filter_Hooks::hook_name( 'core/post-featured-image' ),
			array( $transitions, 'inject_archive_transition_name' )
		);
		remove_filter(
			Block_Filter_Hooks::hook_name( 'woocommerce/product-image-gallery' ),
			array( $transitions, 'inject_single_gallery_transition_name' )
		);
	}

	/**
	 * Product routes should prefetch on deliberate hover without prerendering.
	 *
	 * @return void
	 */
	public function test_product_routes_use_moderate_prefetch(): void {
		add_filter( 'aggressive_apparel_is_listing_page', '__return_true' );

		$configuration = ( new Page_Transitions() )->configure_speculative_loading(
			array(
				'mode'      => 'prerender',
				'eagerness' => 'eager',
			)
		);

		$this->assertSame(
			array(
				'mode'      => 'prefetch',
				'eagerness' => 'moderate',
			),
			$configuration
		);
	}

	/**
	 * Core-disabled speculative loading must stay disabled.
	 *
	 * @return void
	 */
	public function test_disabled_core_configuration_stays_disabled(): void {
		add_filter( 'aggressive_apparel_is_listing_page', '__return_true' );

		$this->assertNull( ( new Page_Transitions() )->configure_speculative_loading( null ) );
	}

	/**
	 * Transaction and personalized routes should remain untouched.
	 *
	 * @return void
	 */
	public function test_excludes_sensitive_commerce_paths(): void {
		$paths = ( new Page_Transitions() )->exclude_sensitive_paths( array( '/existing/*', '/cart' ), 'prefetch' );

		$this->assertContains( '/existing/*', $paths );
		$this->assertContains( '/cart', $paths );
		$this->assertContains( '/cart/*', $paths );
		$this->assertContains( '/checkout', $paths );
		$this->assertContains( '/checkout/*', $paths );
		$this->assertContains( '/my-account', $paths );
		$this->assertContains( '/my-account/*', $paths );
		$this->assertSame( 1, array_count_values( $paths )['/cart'] );
	}

}
