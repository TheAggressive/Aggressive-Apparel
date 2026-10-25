<?php
/**
 * Product archive context tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\Bootstrap;
use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Load_More;
use Aggressive_Apparel\WooCommerce\Product_Context;
use Aggressive_Apparel\WooCommerce\Product_Filters;
use WP_UnitTestCase;

/**
 * Verify every WooCommerce product archive — shop, categories, tags and
 * attribute archives such as /collection/womens/ — gets the same storefront
 * treatment, and nothing else does.
 */
class TestProductArchiveContext extends WP_UnitTestCase {

	private const COLLECTION = 'pa_aa_collection';
	private const FABRIC     = 'pa_aa_fabric';

	private const LOAD_MORE_STYLE        = 'aggressive-apparel-load-more';
	private const LOAD_MORE_MODULE       = '@aggressive-apparel/load-more';
	private const PRODUCT_FILTERS_STYLE  = 'aggressive-apparel-product-filters';
	private const PRODUCT_FILTERS_MODULE = '@aggressive-apparel/product-filters';

	/**
	 * Register public attribute taxonomies and enable the filters feature.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is required for product archive tests.' );
		}

		foreach ( array( self::COLLECTION, self::FABRIC ) as $taxonomy ) {
			register_taxonomy( $taxonomy, array( 'product' ), array( 'public' => true ) );
		}

		update_option( Feature_Settings::OPTION_KEY, array( 'product_filters' => true ) );
		$this->reset_assets();
	}

	/**
	 * Remove test taxonomies, settings and queued assets.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		unregister_taxonomy( self::COLLECTION );
		unregister_taxonomy( self::FABRIC );
		delete_option( Feature_Settings::OPTION_KEY );
		$this->reset_assets();

		parent::tearDown();
	}

	/**
	 * Every product archive kind is a product archive and listing.
	 *
	 * @dataProvider product_archives
	 *
	 * @param string $kind Archive kind.
	 * @return void
	 */
	public function test_product_archives_are_detected( string $kind ): void {
		$this->go_to_archive( $kind );

		$this->assertTrue( Product_Context::is_product_archive() );
		$this->assertTrue( Product_Context::is_product_listing() );
		$this->assertTrue( Product_Context::is_product_display_page() );
		$this->assertTrue( Product_Filters::is_filterable_archive() );
		$this->assertContains( 'woocommerce-shop-page', Bootstrap::get_instance()->add_body_classes( array() ) );
	}

	/**
	 * Product archive kinds.
	 *
	 * @return array<string, array{string}>
	 */
	public function product_archives(): array {
		return array(
			'shop'                    => array( 'shop' ),
			'product category'        => array( 'product_cat' ),
			'product tag'             => array( 'product_tag' ),
			'collection attribute'    => array( self::COLLECTION ),
			'other product attribute' => array( self::FABRIC ),
		);
	}

	/**
	 * Non-product pages stay out of storefront archive features.
	 *
	 * @dataProvider non_product_pages
	 *
	 * @param string $kind Page kind.
	 * @return void
	 */
	public function test_non_product_pages_are_not_archives( string $kind ): void {
		switch ( $kind ) {
			case 'post':
				$this->go_to( (string) get_permalink( self::factory()->post->create() ) );
				break;
			case 'page':
				$this->go_to( (string) get_permalink( self::factory()->post->create( array( 'post_type' => 'page' ) ) ) );
				break;
			case 'blog category':
				$this->go_to( (string) get_term_link( self::factory()->category->create() ) );
				break;
		}

		$this->assertFalse( Product_Context::is_product_archive() );
		$this->assertFalse( Product_Context::is_product_listing() );
		$this->assertFalse( Product_Context::is_product_display_page() );
		$this->assertFalse( Product_Filters::is_filterable_archive() );
		$this->assertNotContains( 'woocommerce-shop-page', Bootstrap::get_instance()->add_body_classes( array() ) );
	}

	/**
	 * Non-product page kinds.
	 *
	 * @return array<string, array{string}>
	 */
	public function non_product_pages(): array {
		return array(
			'post'          => array( 'post' ),
			'page'          => array( 'page' ),
			'blog category' => array( 'blog category' ),
		);
	}

	/**
	 * A collection archive loads the filter and Load More bundles.
	 *
	 * @return void
	 */
	public function test_collection_archive_enqueues_listing_assets(): void {
		$this->go_to_archive( self::COLLECTION );

		( new Product_Filters() )->enqueue_assets();
		( new Load_More() )->enqueue_assets();

		$this->assertTrue( wp_style_is( self::PRODUCT_FILTERS_STYLE, 'enqueued' ) );
		$this->assertContains( self::PRODUCT_FILTERS_MODULE, wp_script_modules()->get_queue() );
		$this->assertTrue( wp_style_is( self::LOAD_MORE_STYLE, 'enqueued' ) );
		$this->assertContains( self::LOAD_MORE_MODULE, wp_script_modules()->get_queue() );
	}

	/**
	 * Unrelated pages still skip them.
	 *
	 * @return void
	 */
	public function test_non_product_page_skips_listing_assets(): void {
		$this->go_to( (string) get_permalink( self::factory()->post->create() ) );

		( new Product_Filters() )->enqueue_assets();
		( new Load_More() )->enqueue_assets();

		$this->assertFalse( wp_style_is( self::PRODUCT_FILTERS_STYLE, 'enqueued' ) );
		$this->assertNotContains( self::PRODUCT_FILTERS_MODULE, wp_script_modules()->get_queue() );
		$this->assertFalse( wp_style_is( self::LOAD_MORE_STYLE, 'enqueued' ) );
		$this->assertNotContains( self::LOAD_MORE_MODULE, wp_script_modules()->get_queue() );
	}

	/**
	 * The filter store learns which collection the archive is scoped to, so
	 * URL sync keeps the collection while other filters change.
	 *
	 * @return void
	 */
	public function test_collection_archive_seeds_its_term_for_filters(): void {
		$term = $this->go_to_archive( self::COLLECTION );

		Product_Filters::ensure_assets();
		$seed = wp_interactivity_state( 'aggressive-apparel/product-filters' )['archiveTerm'];

		$this->assertSame( self::COLLECTION, $seed['taxonomy'] );
		$this->assertSame( $term->slug, $seed['slug'] );
		$this->assertSame( get_term_link( $term ), $seed['link'] );
		$this->assertSame(
			array(
				'taxonomy' => self::COLLECTION,
				'term'     => $term->slug,
			),
			Product_Context::get_current_product_term_archive()
		);
	}

	/**
	 * Categories keep their own routing and seed no archive term.
	 *
	 * @return void
	 */
	public function test_category_archive_seeds_no_archive_term(): void {
		$term = $this->go_to_archive( 'product_cat' );

		Product_Filters::ensure_assets();
		$state = wp_interactivity_state( 'aggressive-apparel/product-filters' );

		$this->assertSame( '', $state['archiveTerm']['taxonomy'] );
		$this->assertSame( $term->slug, $state['currentCategorySlug'] );
	}

	/**
	 * Visit an archive of the given kind.
	 *
	 * @param string $kind `shop` or a product taxonomy.
	 * @return \WP_Term|null The archive's term, if any.
	 */
	private function go_to_archive( string $kind ): ?\WP_Term {
		if ( 'shop' === $kind ) {
			$this->go_to( (string) get_post_type_archive_link( 'product' ) );
			return null;
		}

		$term = get_term( self::factory()->term->create( array( 'taxonomy' => $kind ) ), $kind );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->go_to( (string) get_term_link( $term ) );

		return $term;
	}

	/**
	 * Dequeue listing assets and reset Product_Filters idempotency.
	 *
	 * @return void
	 */
	private function reset_assets(): void {
		foreach ( array( self::LOAD_MORE_STYLE, self::PRODUCT_FILTERS_STYLE ) as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}

		foreach ( array( self::LOAD_MORE_MODULE, self::PRODUCT_FILTERS_MODULE ) as $module ) {
			wp_dequeue_script_module( $module );
			wp_deregister_script_module( $module );
		}

		$assets_flag = new \ReflectionProperty( Product_Filters::class, 'assets_enqueued' );
		$assets_flag->setValue( null, false );
	}
}
