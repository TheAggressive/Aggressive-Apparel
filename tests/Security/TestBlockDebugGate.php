<?php
/**
 * Block-debug capability gate tests.
 *
 * Front-end block debug tooling ships code + strings that must never reach
 * ordinary visitors. `aggressive_apparel_can_view_block_debug()` is the single
 * gate; these assert it denies the public and honors the override filter.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Security;

use WP_UnitTestCase;

/**
 * Block-debug gate test case.
 */
class TestBlockDebugGate extends WP_UnitTestCase {

	/** Gate filter name. */
	private const FILTER = 'aggressive_apparel_can_view_block_debug';

	/**
	 * Remove any override filter between tests.
	 */
	public function tearDown(): void {
		remove_all_filters( self::FILTER );
		parent::tearDown();
	}

	/**
	 * Logged-out visitors cannot view block debug.
	 */
	public function test_visitor_cannot_view_block_debug(): void {
		wp_set_current_user( 0 );
		$this->assertFalse( \aggressive_apparel_can_view_block_debug() );
	}

	/**
	 * Subscribers (no edit_posts) cannot view block debug.
	 */
	public function test_subscriber_cannot_view_block_debug(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse( \aggressive_apparel_can_view_block_debug() );
	}

	/**
	 * Editors (edit_posts) may view block debug.
	 */
	public function test_editor_can_view_block_debug(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertTrue( \aggressive_apparel_can_view_block_debug() );
	}

	/**
	 * The filter can revoke access even for a privileged user.
	 */
	public function test_filter_can_revoke_for_privileged(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( self::FILTER, '__return_false' );
		$this->assertFalse( \aggressive_apparel_can_view_block_debug() );
	}

	/**
	 * The filter can grant access to a visitor (explicit opt-in only).
	 */
	public function test_filter_can_grant_for_visitor(): void {
		wp_set_current_user( 0 );
		add_filter( self::FILTER, '__return_true' );
		$this->assertTrue( \aggressive_apparel_can_view_block_debug() );
	}
}
