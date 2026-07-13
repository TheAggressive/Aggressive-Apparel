<?php
/**
 * Conditional commerce asset tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\Blocks\Blocks;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Load_More;
use Aggressive_Apparel\WooCommerce\Product_Filters;
use Aggressive_Apparel\WooCommerce\Wishlist;
use WP_UnitTestCase;

/**
 * Verify optional commerce bundles follow the surfaces that use them.
 */
class TestConditionalCommerceAssets extends WP_UnitTestCase {

	private const LOAD_MORE_STYLE       = 'aggressive-apparel-load-more';
	private const LOAD_MORE_MODULE      = '@aggressive-apparel/load-more';
	private const WISHLIST_STYLE        = 'aggressive-apparel-wishlist';
	private const WISHLIST_MODULE       = '@aggressive-apparel/wishlist';
	private const PRODUCT_FILTERS_STYLE = 'aggressive-apparel-product-filters';
	private const PRODUCT_FILTERS_MODULE = '@aggressive-apparel/product-filters';

	/**
	 * Enable wishlist and product filters for direct integration tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->reset_commerce_asset_queues();

		update_option(
			Feature_Settings::OPTION_KEY,
			array(
				'wishlist'        => true,
				'product_filters' => true,
			)
		);
	}

	/**
	 * Clear queues and filters between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->reset_commerce_asset_queues();

		remove_all_filters( 'aggressive_apparel_load_more_needs_assets' );
		remove_all_filters( 'aggressive_apparel_wishlist_needs_assets' );
		remove_all_filters( 'aggressive_apparel_product_filters_needs_assets' );
		delete_option( Feature_Settings::OPTION_KEY );
		delete_option( Wishlist::PAGE_ID_OPTION );

		parent::tearDown();
	}

	/**
	 * Dequeue commerce styles/modules and reset Product_Filters idempotency.
	 *
	 * @return void
	 */
	private function reset_commerce_asset_queues(): void {
		foreach ( array( self::LOAD_MORE_STYLE, self::WISHLIST_STYLE, self::PRODUCT_FILTERS_STYLE ) as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}

		if ( function_exists( 'wp_dequeue_script_module' ) ) {
			wp_dequeue_script_module( self::LOAD_MORE_MODULE );
			wp_dequeue_script_module( self::WISHLIST_MODULE );
			wp_dequeue_script_module( self::PRODUCT_FILTERS_MODULE );
			wp_deregister_script_module( self::LOAD_MORE_MODULE );
			wp_deregister_script_module( self::WISHLIST_MODULE );
			wp_deregister_script_module( self::PRODUCT_FILTERS_MODULE );
		}

		$assets_flag = new \ReflectionProperty( Product_Filters::class, 'assets_enqueued' );
		$assets_flag->setAccessible( true );
		$assets_flag->setValue( null, false );
	}

	/**
	 * Plain editorial pages should not receive optional commerce bundles.
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
		( new Product_Filters() )->enqueue_assets();

		$this->assertFalse( wp_style_is( self::LOAD_MORE_STYLE, 'enqueued' ) );
		$this->assertFalse( wp_style_is( self::WISHLIST_STYLE, 'enqueued' ) );
		$this->assertFalse( wp_style_is( self::PRODUCT_FILTERS_STYLE, 'enqueued' ) );
		$this->assertNotContains( self::LOAD_MORE_MODULE, wp_script_modules()->get_queue() );
		$this->assertNotContains( self::WISHLIST_MODULE, wp_script_modules()->get_queue() );
		$this->assertNotContains( self::PRODUCT_FILTERS_MODULE, wp_script_modules()->get_queue() );
	}

	/**
	 * Product Collections without a wishlist control should not load its assets.
	 *
	 * @return void
	 */
	public function test_product_collection_page_does_not_enqueue_wishlist_assets(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:woocommerce/product-collection /-->',
			)
		);

		$this->go_to( get_permalink( $page_id ) );

		( new Wishlist() )->enqueue_assets();

		$this->assertFalse( wp_style_is( self::WISHLIST_STYLE, 'enqueued' ) );
		$this->assertNotContains( self::WISHLIST_MODULE, wp_script_modules()->get_queue() );
	}

	/**
	 * An explicitly placed Wishlist Button block should load wishlist assets.
	 *
	 * @return void
	 */
	public function test_wishlist_button_block_page_enqueues_wishlist_assets(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => '<!-- wp:woocommerce/product-collection --><!-- wp:aggressive-apparel/wishlist-button /--><!-- /wp:woocommerce/product-collection -->',
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
		$html = ( Wishlist::get_heart_button_html( 42 ) );

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

	/**
	 * Shop archives should load the product-filters bundle when the feature is on.
	 *
	 * @return void
	 */
	public function test_shop_archive_enqueues_product_filters_assets(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for product filters asset tests.' );
		}

		$archive = get_post_type_archive_link( 'product' );
		$this->assertIsString( $archive );
		$this->go_to( $archive );
		$this->assertTrue( function_exists( 'is_shop' ) && is_shop() );

		( new Product_Filters() )->enqueue_assets();

		$this->assertTrue( wp_style_is( self::PRODUCT_FILTERS_STYLE, 'enqueued' ) );
		$this->assertContains( self::PRODUCT_FILTERS_MODULE, wp_script_modules()->get_queue() );
	}

	/**
	 * Custom filterable routes can opt into product-filters assets.
	 *
	 * @return void
	 */
	public function test_product_filters_filter_supports_custom_routes(): void {
		add_filter( 'aggressive_apparel_product_filters_needs_assets', '__return_true' );

		( new Product_Filters() )->enqueue_assets();

		$this->assertTrue( wp_style_is( self::PRODUCT_FILTERS_STYLE, 'enqueued' ) );
		$this->assertContains( self::PRODUCT_FILTERS_MODULE, wp_script_modules()->get_queue() );
	}

	/**
	 * Filter toggle render recovers assets when early detection was missed.
	 *
	 * @return void
	 */
	public function test_filter_toggle_render_ensures_product_filters_assets(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for product filters asset tests.' );
		}

		if ( ! Blocks::is_block_registered( 'aggressive-apparel/filter-toggle' ) ) {
			Blocks::register();
		}

		$archive = get_post_type_archive_link( 'product' );
		$this->assertIsString( $archive );
		$this->go_to( $archive );

		$html = (string) render_block(
			array(
				'blockName'    => 'aggressive-apparel/filter-toggle',
				'attrs'        => array(
					'label'     => 'Filters',
					'showLabel' => true,
				),
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);

		$this->assertStringContainsString( 'aa-filter-toggle', $html );
		$this->assertTrue( wp_style_is( self::PRODUCT_FILTERS_STYLE, 'enqueued' ) );
		$this->assertContains( self::PRODUCT_FILTERS_MODULE, wp_script_modules()->get_queue() );
	}
}
