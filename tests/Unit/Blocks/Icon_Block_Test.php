<?php
/**
 * Icon block REST tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Icon_Block;
use Aggressive_Apparel\Core\Brand_Icons;
use Aggressive_Apparel\Core\Icons;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Icon block REST test case.
 */
class Icon_Block_Test extends WP_UnitTestCase {

	/**
	 * Register routes and brand icons before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		Brand_Icons::init();
	}

	/**
	 * Clear icon cache after each test.
	 */
	public function tearDown(): void {
		remove_all_filters( 'aggressive_apparel_icon_definitions' );
		Icons::flush_cache_for_tests();
		parent::tearDown();
	}

	/**
	 * Editors can list icon slugs.
	 */
	public function test_get_icon_list_returns_sorted_slugs(): void {
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$response = Icon_Block::get_icon_list();
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data['icons'] ?? null );
		$this->assertContains( 'cart', $data['icons'] );
		$this->assertContains( 'shipping-box', $data['icons'] );
		$sorted = $data['icons'];
		sort( $sorted );
		$this->assertSame( $sorted, $data['icons'] );
	}

	/**
	 * Preview endpoint returns trusted SVG markup.
	 */
	public function test_get_icon_preview_returns_svg_markup(): void {
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$request = new WP_REST_Request( 'GET', '/aggressive-apparel/v1/icons/shipping-box' );
		$request->set_param( 'slug', 'shipping-box' );
		$request->set_param( 'size', 64 );

		$response = Icon_Block::get_icon_preview( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'shipping-box', $data['slug'] );
		$this->assertStringContainsString( '<svg', $data['svg'] );
		$this->assertStringContainsString( 'width="64"', $data['svg'] );
		$this->assertStringContainsString( 'aggressive-apparel-icon__svg', $data['svg'] );
	}

	/**
	 * Unknown slugs return 404.
	 */
	public function test_get_icon_preview_returns_404_for_unknown_slug(): void {
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$request = new WP_REST_Request( 'GET', '/aggressive-apparel/v1/icons/not-a-real-icon' );
		$request->set_param( 'slug', 'not-a-real-icon' );

		$response = Icon_Block::get_icon_preview( $request );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Size param is clamped to the supported range.
	 */
	public function test_sanitize_size_clamps_values(): void {
		$this->assertSame( 16, Icon_Block::sanitize_size( 4 ) );
		$this->assertSame( 48, Icon_Block::sanitize_size( 48 ) );
		$this->assertSame( 128, Icon_Block::sanitize_size( 999 ) );
	}

	/**
	 * Subscribers cannot access icon REST data.
	 */
	public function test_can_edit_content_requires_editor_capability(): void {
		$subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$this->assertFalse( Icon_Block::can_edit_content() );

		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		$this->assertTrue( Icon_Block::can_edit_content() );
	}
}
