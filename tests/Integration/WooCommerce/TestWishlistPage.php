<?php
/**
 * Wishlist Page Integration Tests
 *
 * @package Aggressive_Apparel\Tests\Integration\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Wishlist;
use WP_UnitTestCase;

/**
 * Wishlist page auto-creation tests.
 */
class TestWishlistPage extends WP_UnitTestCase {

	/**
	 * Admin user ID for page creation capability checks.
	 *
	 * @var int
	 */
	private int $admin_id = 0;

	/**
	 * Set up each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		delete_option( Wishlist::PAGE_ID_OPTION );
		update_option(
			Feature_Settings::OPTION_KEY,
			array(
				'wishlist' => true,
			)
		);

		$this->admin_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $this->admin_id );
	}

	/**
	 * Clean up after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( Wishlist::PAGE_ID_OPTION );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Auto-created page should include the wishlist block template.
	 *
	 * @return void
	 */
	public function test_create_page_stores_default_wishlist_block_content(): void {
		$page_id = Wishlist::create_page();

		$this->assertGreaterThan( 0, $page_id );
		$this->assertSame( $page_id, Wishlist::get_page_id() );

		$post = get_post( $page_id );
		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertStringContainsString( 'aggressive-apparel/wishlist', $post->post_content );
		$this->assertStringContainsString( 'aggressive-apparel/wishlist-item-image', $post->post_content );
	}

	/**
	 * Existing pages with the wishlist block should be adopted instead of duplicated.
	 *
	 * @return void
	 */
	public function test_find_existing_page_id_detects_wishlist_block_page(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Saved Items',
				'post_content' => Wishlist::get_default_page_content(),
			)
		);

		$this->assertSame( $page_id, Wishlist::find_existing_page_id() );

		$wishlist = new Wishlist();
		$wishlist->maybe_create_page();

		$this->assertSame( $page_id, Wishlist::get_page_id() );
		$this->assertSame( 1, (int) wp_count_posts( 'page' )->publish );
	}

	/**
	 * Page URL helper should resolve to the stored page permalink.
	 *
	 * @return void
	 */
	public function test_get_page_url_uses_created_page_permalink(): void {
		$page_id = Wishlist::create_page();
		$url     = Wishlist::get_page_url();

		$this->assertSame( get_permalink( $page_id ), $url );
	}

	/**
	 * Heart toggle markup should use the unified document-delegate contract.
	 *
	 * @return void
	 */
	public function test_heart_button_html_uses_data_aa_product_id(): void {
		$html = Wishlist::get_heart_button_html( 42 );

		$this->assertStringContainsString( 'data-aa-product-id="42"', $html );
		$this->assertStringContainsString( 'aggressive-apparel-wishlist__toggle', $html );
		$this->assertStringNotContainsString( 'data-wp-on--click', $html );
	}

	/**
	 * Render tracking should suppress duplicates for only the same product.
	 *
	 * @return void
	 */
	public function test_button_render_tracking_is_scoped_to_product_id(): void {
		Wishlist::mark_button_block_rendered( 42 );

		$this->assertTrue( Wishlist::has_button_block_rendered( 42 ) );
		$this->assertFalse( Wishlist::has_button_block_rendered( 43 ) );
	}
}
