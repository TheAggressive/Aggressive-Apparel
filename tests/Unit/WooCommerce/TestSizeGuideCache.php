<?php
/**
 * Size-guide cache invalidation tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Size_Guide;
use WP_UnitTestCase;

/** Covers constant-time cache generation invalidation. */
class TestSizeGuideCache extends WP_UnitTestCase {

	private const GENERATION_OPTION = 'aa_size_guide_cache_generation';

	/** Cache flushing advances the generation without scanning options. */
	public function test_flush_all_caches_advances_generation(): void {
		update_option( self::GENERATION_OPTION, 7, false );

		( new Size_Guide() )->flush_all_caches();

		$this->assertSame( 8, (int) get_option( self::GENERATION_OPTION ) );
	}

	/** Obsolete raw-HTML storage is not rendered as a fallback. */
	public function test_legacy_raw_html_is_ignored(): void {
		$product_id = self::factory()->post->create( array( 'post_type' => 'product' ) );
		update_post_meta( $product_id, '_aggressive_apparel_size_guide', '<p>Legacy product guide</p>' );
		update_option( 'aggressive_apparel_size_guide', '<p>Legacy global guide</p>', false );

		$this->assertSame( '', $this->resolve_for_product( $product_id ) );
	}

	/** The canonical CPT assignment remains the only product-level source. */
	public function test_product_cpt_assignment_is_rendered(): void {
		$product_id = self::factory()->post->create( array( 'post_type' => 'product' ) );
		$guide_id   = self::factory()->post->create(
			array(
				'post_type'    => 'aa_size_guide',
				'post_status'  => 'publish',
				'post_content' => '<p>Canonical guide</p>',
			)
		);
		update_post_meta( $product_id, '_aggressive_apparel_size_guide_id', $guide_id );

		$this->assertStringContainsString( 'Canonical guide', $this->resolve_for_product( $product_id ) );
	}

	/** Invoke the private resolver through its tested public rendering boundary. */
	private function resolve_for_product( int $product_id ): string {
		$method = new \ReflectionMethod( Size_Guide::class, 'get_size_guide_for_product' );

		return (string) $method->invoke( new Size_Guide(), $product_id );
	}
}
