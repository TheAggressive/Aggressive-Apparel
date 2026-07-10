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
	 * Render the dynamic icon block with supplied attributes.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Rendered block HTML.
	 */
	private function render_icon_block( array $attributes ): string {
		return render_block(
			array(
				'blockName'    => 'aggressive-apparel/icon',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Register routes and brand icons before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		Icon_Block::flush_list_cache_for_tests();
		Brand_Icons::init();
	}

	/**
	 * Clear icon cache after each test.
	 */
	public function tearDown(): void {
		remove_all_filters( 'aggressive_apparel_icon_definitions' );
		Icon_Block::flush_list_cache_for_tests();
		Brand_Icons::flush_cache_for_tests();
		Icons::flush_cache_for_tests();
		parent::tearDown();
	}

	/**
	 * Editors can list icons (slug + thumbnail SVG), sorted by slug.
	 */
	public function test_get_icon_list_returns_sorted_slugs(): void {
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		Icon_Block::flush_list_cache_for_tests();

		$response = Icon_Block::get_icon_list();
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data['icons'] ?? null );

		// Each entry carries a slug and a rendered SVG thumbnail.
		$this->assertArrayHasKey( 'slug', $data['icons'][0] );
		$this->assertArrayHasKey( 'svg', $data['icons'][0] );
		$this->assertStringContainsString( '<svg', $data['icons'][0]['svg'] );

		$slugs = array_column( $data['icons'], 'slug' );
		$this->assertContains( 'cart', $slugs );
		$this->assertContains( 'shipping-box', $slugs );

		$sorted = $slugs;
		sort( $sorted );
		$this->assertSame( $sorted, $slugs );
	}

	/**
	 * The editor icon list is served from a transient after the first build.
	 */
	public function test_get_icon_list_reuses_transient_cache(): void {
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );

		Icon_Block::flush_list_cache_for_tests();

		$first  = Icon_Block::get_icon_list()->get_data();
		$second = Icon_Block::get_icon_list()->get_data();

		$this->assertSame( $first['icons'], $second['icons'] );
		$this->assertNotEmpty( $first['icons'] );
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
	 * Brand icons render through the dynamic block with sizing and alignment.
	 */
	public function test_frontend_renders_brand_icon_with_size_and_alignment(): void {
		Brand_Icons::flush_cache_for_tests();
		Icons::flush_cache_for_tests();

		$html = $this->render_icon_block(
			array(
				'icon'         => 'shipping-box',
				'iconSize'     => 64,
				'isDecorative' => true,
				'textAlign'    => 'center',
			)
		);

		$this->assertStringContainsString( 'aggressive-apparel-icon', $html );
		$this->assertStringContainsString( 'has-text-align-center', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( 'width="64"', $html );
		$this->assertStringContainsString( 'height="64"', $html );
		$this->assertStringContainsString( 'aggressive-apparel-icon__svg', $html );
		$this->assertStringContainsString( '<path ', $html );
		$this->assertSame( array( 'shipping-box' ), Brand_Icons::loaded_slugs_for_tests() );
	}

	/**
	 * Meaningful icons expose one accessible image label on the wrapper.
	 */
	public function test_frontend_renders_meaningful_icon_accessibly(): void {
		$html = $this->render_icon_block(
			array(
				'icon'         => 'shield-check',
				'iconSize'     => 48,
				'isDecorative' => false,
				'label'        => 'Secure & protected',
			)
		);

		$this->assertStringContainsString( 'role="img"', $html );
		$this->assertStringContainsString( 'aria-label="Secure &amp; protected"', $html );
		$this->assertSame( 1, substr_count( $html, 'aria-hidden="true"' ) );
	}

	/**
	 * Core icons render without loading a generated brand definition.
	 */
	public function test_frontend_core_icon_does_not_load_brand_definition(): void {
		Brand_Icons::flush_cache_for_tests();
		Icons::flush_cache_for_tests();

		$html = $this->render_icon_block(
			array(
				'icon'         => 'cart',
				'iconSize'     => 24,
				'isDecorative' => true,
			)
		);

		$this->assertStringContainsString( 'width="24"', $html );
		$this->assertStringContainsString( '<path ', $html );
		$this->assertSame( array(), Brand_Icons::loaded_slugs_for_tests() );
	}

	/**
	 * Frontend sizes use the same safe bounds as editor previews.
	 */
	public function test_frontend_clamps_icon_size(): void {
		$small = $this->render_icon_block(
			array(
				'icon'     => 'cart',
				'iconSize' => 1,
			)
		);
		$large = $this->render_icon_block(
			array(
				'icon'     => 'cart',
				'iconSize' => 999,
			)
		);

		$this->assertStringContainsString( 'width="16"', $small );
		$this->assertStringContainsString( 'height="16"', $small );
		$this->assertStringContainsString( 'width="128"', $large );
		$this->assertStringContainsString( 'height="128"', $large );
	}

	/**
	 * Invalid icon slugs fail closed without partial block markup.
	 */
	public function test_frontend_invalid_icon_renders_nothing(): void {
		$html = $this->render_icon_block(
			array(
				'icon' => 'not-a-real-icon',
			)
		);

		$this->assertSame( '', $html );
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
	 * Wrapped SVG output includes the shared wrapper classes and inline size.
	 */
	public function test_render_wrapped_svg_outputs_sized_wrapper(): void {
		$html = Icon_Block::render_wrapped_svg(
			'shipping-box',
			32,
			array(
				'class' => 'ticker__label-icon',
			)
		);

		$this->assertStringContainsString( 'aggressive-apparel-icon__svg-wrap', $html );
		$this->assertStringContainsString( 'ticker__label-icon', $html );
		$this->assertStringContainsString( 'style="width:32px;height:32px;"', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertStringContainsString( '<svg', $html );
	}

	/**
	 * Unknown slugs return an empty wrapped string.
	 */
	public function test_render_wrapped_svg_returns_empty_for_unknown_slug(): void {
		$this->assertSame( '', Icon_Block::render_wrapped_svg( 'not-a-real-icon', 24 ) );
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
