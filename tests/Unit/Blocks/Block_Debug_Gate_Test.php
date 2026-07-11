<?php
/**
 * Capability gating tests for block Debug Mode.
 *
 * Debug Mode is a saved block attribute; these tests lock in that it can
 * never reach the rendered page for users without editing capabilities —
 * neither in the Interactivity context nor as an enqueued stylesheet.
 *
 * @package Aggressive_Apparel\Tests\Unit\Blocks
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Blocks;

use Aggressive_Apparel\Blocks\Blocks;
use WP_UnitTestCase;

/**
 * Tests for aggressive_apparel_can_view_block_debug() and the render gates.
 */
class Block_Debug_Gate_Test extends WP_UnitTestCase {

	/**
	 * Register theme blocks once for render tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! Blocks::is_block_registered( 'aggressive-apparel/animate-on-scroll' ) ) {
			Blocks::register();
		}
	}

	/**
	 * Reset user + filters between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'aggressive_apparel_can_view_block_debug' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Extract and decode the data-wp-context JSON from rendered block HTML.
	 *
	 * @param string $html Rendered block HTML.
	 * @return array<string,mixed> Decoded context.
	 */
	private function get_block_context( string $html ): array {
		$this->assertSame(
			1,
			preg_match( '/data-wp-context="([^"]+)"/', $html, $matches ),
			'Rendered block should expose a data-wp-context attribute.'
		);

		$context = json_decode( html_entity_decode( $matches[1], ENT_QUOTES ), true );
		$this->assertIsArray( $context );

		return $context;
	}

	/**
	 * The helper requires an editing capability, not a mere login.
	 *
	 * @return void
	 */
	public function test_helper_requires_editing_capability(): void {
		wp_set_current_user( 0 );
		$this->assertFalse( aggressive_apparel_can_view_block_debug(), 'Logged-out visitors must not see debug.' );

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );
		$this->assertFalse( aggressive_apparel_can_view_block_debug(), 'Logged-in customers must not see debug.' );

		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$this->assertTrue( aggressive_apparel_can_view_block_debug(), 'Editors may see debug.' );
	}

	/**
	 * The gate is filterable in both directions.
	 *
	 * @return void
	 */
	public function test_helper_is_filterable(): void {
		wp_set_current_user( 0 );
		add_filter( 'aggressive_apparel_can_view_block_debug', '__return_true' );
		$this->assertTrue( aggressive_apparel_can_view_block_debug() );
		remove_all_filters( 'aggressive_apparel_can_view_block_debug' );

		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		add_filter( 'aggressive_apparel_can_view_block_debug', '__return_false' );
		$this->assertFalse( aggressive_apparel_can_view_block_debug() );
	}

	/**
	 * Animate On Scroll strips a saved debugMode:true for visitors and
	 * keeps it for editors.
	 *
	 * @return void
	 */
	public function test_animate_on_scroll_context_is_gated(): void {
		$markup = '<!-- wp:aggressive-apparel/animate-on-scroll {"debugMode":true} -->'
			. '<!-- wp:paragraph --><p>content</p><!-- /wp:paragraph -->'
			. '<!-- /wp:aggressive-apparel/animate-on-scroll -->';

		wp_set_current_user( 0 );
		$context = $this->get_block_context( do_blocks( $markup ) );
		$this->assertFalse( $context['debugMode'], 'Visitors must receive debugMode: false.' );
		$this->assertFalse(
			wp_style_is( 'aggressive-apparel-debug-overlays', 'enqueued' ),
			'Debug stylesheet must not be enqueued for visitors.'
		);

		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$context = $this->get_block_context( do_blocks( $markup ) );
		$this->assertTrue( $context['debugMode'], 'Editors keep debugMode: true.' );
		$this->assertTrue(
			wp_style_is( 'aggressive-apparel-debug-overlays', 'enqueued' ),
			'Debug stylesheet is enqueued when debug renders.'
		);
	}

	/**
	 * Parallax strips a saved debugMode:true for visitors.
	 *
	 * @return void
	 */
	public function test_parallax_context_is_gated(): void {
		$markup = '<!-- wp:aggressive-apparel/parallax {"debugMode":true} -->'
			. '<!-- wp:paragraph --><p>content</p><!-- /wp:paragraph -->'
			. '<!-- /wp:aggressive-apparel/parallax -->';

		wp_set_current_user( 0 );
		$context = $this->get_block_context( do_blocks( $markup ) );
		$this->assertFalse( $context['debugMode'], 'Visitors must receive debugMode: false.' );

		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$context = $this->get_block_context( do_blocks( $markup ) );
		$this->assertTrue( $context['debugMode'], 'Editors keep debugMode: true.' );
	}
}
