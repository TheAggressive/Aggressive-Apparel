<?php
/**
 * Tests for purging page caches on Site Editor design changes.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Core;

use Aggressive_Apparel\Core\Page_Cache_Purge;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\Core\Page_Cache_Purge
 */
class Page_Cache_Purge_Test extends WP_UnitTestCase {

	/**
	 * @var Page_Cache_Purge
	 */
	private Page_Cache_Purge $purge;

	/** Fresh instance per test; `purge()` stands in for `shutdown`. */
	public function setUp(): void {
		parent::setUp();
		$this->purge = new Page_Cache_Purge();
		$this->purge->init();
	}

	/** Number of full purges requested so far. */
	private function purges(): int {
		return did_action( 'breeze_clear_all_cache' );
	}

	/** Editing a template purges once, at the end of the request. */
	public function test_template_update_purges_once(): void {
		$template = self::factory()->post->create( array( 'post_type' => 'wp_template' ) );
		$before   = $this->purges();

		wp_update_post(
			array(
				'ID'           => $template,
				'post_content' => '<!-- wp:paragraph --><p>New card</p><!-- /wp:paragraph -->',
			)
		);
		self::factory()->post->create( array( 'post_type' => 'wp_block' ) );

		$this->assertSame( $before, $this->purges(), 'Nothing purges mid-request.' );
		$this->purge->purge();
		$this->assertSame( $before + 1, $this->purges(), 'Several design saves purge once.' );
		$this->purge->purge();
		$this->assertSame( $before + 1, $this->purges(), 'A purge is not repeated.' );
	}

	/** Reverting a customized template (deleting its post) purges. */
	public function test_template_reset_purges(): void {
		$template = self::factory()->post->create( array( 'post_type' => 'wp_template_part' ) );
		$this->purge->purge();
		$before = $this->purges();

		wp_delete_post( $template, true );
		$this->purge->purge();

		$this->assertSame( $before + 1, $this->purges() );
	}

	/** Drafts are not on the site: saving or deleting one never purges. */
	public function test_draft_design_entities_do_not_purge(): void {
		$before = $this->purges();

		$draft = self::factory()->post->create(
			array(
				'post_type'   => 'wp_block',
				'post_status' => 'draft',
			)
		);
		wp_update_post(
			array(
				'ID'           => $draft,
				'post_content' => '<!-- wp:paragraph --><p>Still drafting</p><!-- /wp:paragraph -->',
			)
		);
		wp_delete_post( $draft, true );
		$this->purge->purge();

		$this->assertSame( $before, $this->purges() );

		// Unpublishing does change the site.
		$published = self::factory()->post->create( array( 'post_type' => 'wp_block' ) );
		$this->purge->purge();
		$before = $this->purges();
		wp_update_post(
			array(
				'ID'          => $published,
				'post_status' => 'draft',
			)
		);
		$this->purge->purge();

		$this->assertSame( $before + 1, $this->purges() );
	}

	/** Ordinary content is left to the page cache's own per-URL purging. */
	public function test_content_saves_do_not_purge_everything(): void {
		$before = $this->purges();

		$post = self::factory()->post->create();
		wp_update_post(
			array(
				'ID'         => $post,
				'post_title' => 'Updated',
			)
		);
		wp_delete_post( self::factory()->post->create( array( 'post_type' => 'page' ) ), true );
		$this->purge->purge();

		$this->assertSame( $before, $this->purges() );
	}
}
