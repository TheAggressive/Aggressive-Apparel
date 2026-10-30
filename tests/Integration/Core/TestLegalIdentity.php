<?php
/**
 * Legal Identity Integration Tests
 *
 * @package Aggressive_Apparel\Tests\Integration\Core
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\Core;

use Aggressive_Apparel\Core\Legal_Identity;
use WP_UnitTestCase;

/**
 * Legal page links shown in the Copyright block.
 */
class TestLegalIdentity extends WP_UnitTestCase {

	/**
	 * A published page links for everyone.
	 *
	 * @return void
	 */
	public function test_published_page_links_for_visitors(): void {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_set_current_user( 0 );

		$this->assertSame( get_permalink( $page_id ), Legal_Identity::get_page_url( $page_id ) );
	}

	/**
	 * A draft never reaches visitors, so the footer can't link to a 404.
	 *
	 * @return void
	 */
	public function test_draft_page_is_hidden_from_visitors(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		wp_set_current_user( 0 );

		$this->assertSame( '', Legal_Identity::get_page_url( $page_id ) );
	}

	/**
	 * Editors still see the draft link while the page is being written.
	 *
	 * @return void
	 */
	public function test_draft_page_links_for_editors(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( get_permalink( $page_id ), Legal_Identity::get_page_url( $page_id ) );
	}
}
