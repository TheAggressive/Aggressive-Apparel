<?php
/**
 * Search content-leakage test.
 *
 * The public search endpoint must never surface unpublished content. The
 * enforcement is layered (the index, a post_status=publish query, and a
 * post-hydration is_searchable_post recheck); this asserts the observable
 * result: a draft sharing a search term with a published post is never
 * returned.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Security;

use Aggressive_Apparel\Core\Search_Index;
use Aggressive_Apparel\Core\Search_Results;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Search leakage test case.
 */
class TestSearchLeakage extends WP_UnitTestCase {

	/**
	 * Search over an index containing a published and a draft post must return
	 * only the published one.
	 */
	public function test_search_never_returns_unpublished_posts(): void {
		$index = new Search_Index();
		$index->maybe_install();

		// A real index build runs asynchronously via Action Scheduler, which the
		// unit environment does not execute. Flip the "ready" flag directly;
		// option name/version are read via reflection to avoid magic strings.
		$ref = new ReflectionClass( Search_Index::class );
		update_option( (string) $ref->getConstant( 'INDEX_OPTION' ), $ref->getConstant( 'INDEX_VERSION' ), false );

		if ( ! $index->is_ready() ) {
			$this->markTestSkipped( 'Search index table unavailable in this environment.' );
		}

		$token     = 'zzsearchleaktoken';
		$published = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_title'  => "Alpha {$token} widget",
			)
		);
		$draft = self::factory()->post->create(
			array(
				'post_status' => 'draft',
				'post_title'  => "Beta {$token} widget",
			)
		);

		$index->sync_ids( array( $published, $draft ) );

		$items = ( new Search_Results( $index ) )->posts( $token );
		$ids   = array_map( static fn( array $item ): int => (int) $item['id'], $items );

		if ( ! in_array( $published, $ids, true ) ) {
			// The tokenizer did not surface the published post in this env, so
			// there is nothing to conclude about leakage. Skip, don't false-fail.
			$this->markTestSkipped( 'Published post was not searchable in this environment.' );
		}

		$this->assertNotContains( $draft, $ids, 'Search returned an unpublished (draft) post — content leak.' );
	}
}
