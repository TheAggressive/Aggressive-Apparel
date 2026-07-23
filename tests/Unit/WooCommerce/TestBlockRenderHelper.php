<?php
/**
 * Block render helper tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Block_Render_Helper;
use WP_UnitTestCase;

/**
 * Covers the shared block-alignment helper used when a render_block filter wraps
 * a block in its own container.
 */
class TestBlockRenderHelper extends WP_UnitTestCase {

	/**
	 * Wide and full map to their WordPress alignment classes.
	 *
	 * @return void
	 */
	public function test_wide_and_full_map_to_alignment_classes(): void {
		$this->assertSame( 'alignwide', Block_Render_Helper::alignment_class( array( 'attrs' => array( 'align' => 'wide' ) ) ) );
		$this->assertSame( 'alignfull', Block_Render_Helper::alignment_class( array( 'attrs' => array( 'align' => 'full' ) ) ) );
	}

	/**
	 * Non-width alignments and missing attributes yield an empty string.
	 *
	 * @return void
	 */
	public function test_other_or_missing_alignment_yields_empty_string(): void {
		$this->assertSame( '', Block_Render_Helper::alignment_class( array( 'attrs' => array( 'align' => 'left' ) ) ) );
		$this->assertSame( '', Block_Render_Helper::alignment_class( array( 'attrs' => array( 'align' => '' ) ) ) );
		$this->assertSame( '', Block_Render_Helper::alignment_class( array( 'attrs' => array() ) ) );
		$this->assertSame( '', Block_Render_Helper::alignment_class( array() ) );
	}
}
