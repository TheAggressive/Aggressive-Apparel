<?php
/**
 * Conditional commerce asset tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Load_More;
use Aggressive_Apparel\WooCommerce\Wishlist;
use WP_UnitTestCase;

/**
 * Verify optional commerce bundles follow the surfaces that use them.
 */
class TestConditionalCommerceAssets extends WP_UnitTestCase {

	private const LOAD_MORE_STYLE  = 'aggressive-apparel-load-more';
	private const LOAD_MORE_MODULE = '@aggressive-apparel/load-more';
	private const WISHLIST_STYLE   = 'aggressive-apparel-wishlist';
	private const WISHLIST_MODULE  = '@aggressive-apparel/wishlist';

	/**
	 * Enable wishlist for direct integration tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		update_option(
			Feature_Settings::OPTION_KEY,
			array(
				'wishlist' => true,
			)
		);
	}

	/**
	 * Clear queues and filters between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		foreach ( array( self::LOAD_MORE_STYLE, self::WISHLIST_STYLE ) as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}

		if ( function_exists( 'wp_dequeue_script_module' ) ) {
			wp_dequeue_script_module( self::LOAD_MORE_MODULE );
			wp_dequeue_script_module( self::WISHLIST_MODULE );
			wp_deregister_script_module( self::LOAD_MORE_MODULE );
			wp_deregister_script_module( self::WISHLIST_MODULE );
		}

		remove_all_filters( 'aggressive_apparel_load_more_needs_assets' );
		remove_all_filters( 'aggressive_apparel_wishlist_needs_assets' );
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Wishlist::PAGE_ID_OPTION );

		parent::tearDown();
	}

	/**
	 * Plain editorial pages should not receive either optional bundle.
	 *
	 * @return void
	 */
	public function test_plain_post_does_not_enqueue_optional_commerce_assets(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		( new Load_More() )->enqueue_assets();
		( new Wishlist() )->enqueue_assets();

		$this->assertFalse( wp_style_is( self::LOAD_MORE_STYLE, 'enqueued' ) );
		$this->assertFalse( wp_style_is( self::WISHLIST_STYLE, 'enqueued' ) );
		$this->assertNotContains( self::LOAD_MORE_MODULE, wp_script_modules()->get_queue() );
		$this->assertNotContains( self::WISHLIST_MODULE, wp_script_modules()->get_queue() );
	}

	/**
	 * Product Collections on ordinary pages should be detected before render.
	 *
	 * @return void
	 */
	public function test_product_collection_page_enqueues_wishlist_assets(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:woocommerce/product-collection /-->',
			)
		);

		$this->go_to( get_permalink( $page_id ) );

		( new Wishlist() )->enqueue_assets();

		$this->assertTrue( wp_style_is( self::WISHLIST_STYLE, 'enqueued' ) );
		$this->assertContains( self::WISHLIST_MODULE, wp_script_modules()->get_queue() );
	}

	/**
	 * Render-time button generation should recover from missed early detection.
	 *
	 * @return void
	 */
	public function test_dynamic_wishlist_button_enqueues_its_assets(): void {
		$html = ( new Wishlist() )->get_heart_button_html( 42 );

		$this->assertStringContainsString( 'data-aa-product-id="42"', $html );
		$this->assertTrue( wp_style_is( self::WISHLIST_STYLE, 'enqueued' ) );
		$this->assertContains( self::WISHLIST_MODULE, wp_script_modules()->get_queue() );
	}

	/**
	 * Custom archive integrations can explicitly opt into Load More assets.
	 *
	 * @return void
	 */
	public function test_load_more_filter_supports_custom_archive_routes(): void {
		add_filter( 'aggressive_apparel_load_more_needs_assets', '__return_true' );

		( new Load_More() )->enqueue_assets();

		$this->assertTrue( wp_style_is( self::LOAD_MORE_STYLE, 'enqueued' ) );
		$this->assertContains( self::LOAD_MORE_MODULE, wp_script_modules()->get_queue() );
	}
}
