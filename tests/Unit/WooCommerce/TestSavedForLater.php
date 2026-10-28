<?php
/**
 * Saved for Later layout tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Saved_For_Later;
use WP_UnitTestCase;

/**
 * Verify the hooked Saved for Later block takes the cart's wide alignment.
 */
class TestSavedForLater extends WP_UnitTestCase {

	/**
	 * A hooked block with no attributes.
	 *
	 * @return array<string, mixed>
	 */
	private function hooked_block(): array {
		return array(
			'blockName'    => 'woocommerce/saved-for-later',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerContent' => array(),
		);
	}

	/**
	 * The block after a default (wide) cart is wide, in three columns.
	 *
	 * @return void
	 */
	public function test_aligns_block_hooked_after_cart(): void {
		$block = ( new Saved_For_Later() )->align_hooked_block(
			$this->hooked_block(),
			'woocommerce/saved-for-later',
			'after',
			array( 'blockName' => 'woocommerce/cart' )
		);

		$this->assertIsArray( $block );
		$this->assertSame( 'wide', $block['attrs']['align'] );
		$this->assertSame( 3, $block['attrs']['columnCount'] );
	}

	/**
	 * The block follows a full-width cart's alignment.
	 *
	 * @return void
	 */
	public function test_matches_cart_alignment(): void {
		$block = ( new Saved_For_Later() )->align_hooked_block(
			$this->hooked_block(),
			'woocommerce/saved-for-later',
			'after',
			array(
				'blockName' => 'woocommerce/cart',
				'attrs'     => array( 'align' => 'full' ),
			)
		);

		$this->assertIsArray( $block );
		$this->assertSame( 'full', $block['attrs']['align'] );
	}

	/**
	 * Attributes another filter already set are kept.
	 *
	 * @return void
	 */
	public function test_keeps_existing_attributes(): void {
		$hooked          = $this->hooked_block();
		$hooked['attrs'] = array(
			'align'       => 'full',
			'columnCount' => 2,
		);

		$block = ( new Saved_For_Later() )->align_hooked_block( $hooked, 'woocommerce/saved-for-later', 'after', array( 'blockName' => 'woocommerce/cart' ) );

		$this->assertIsArray( $block );
		$this->assertSame( 'full', $block['attrs']['align'] );
		$this->assertSame( 2, $block['attrs']['columnCount'] );
	}

	/**
	 * Other anchors, positions and suppressed insertions pass through.
	 *
	 * @return void
	 */
	public function test_ignores_other_insertions(): void {
		$service = new Saved_For_Later();
		$hooked  = $this->hooked_block();

		$this->assertSame( $hooked, $service->align_hooked_block( $hooked, 'woocommerce/saved-for-later', 'before', array( 'blockName' => 'woocommerce/cart' ) ) );
		$this->assertSame( $hooked, $service->align_hooked_block( $hooked, 'woocommerce/saved-for-later', 'after', array( 'blockName' => 'core/group' ) ) );
		$this->assertNull( $service->align_hooked_block( null, 'woocommerce/saved-for-later', 'after', array( 'blockName' => 'woocommerce/cart' ) ) );
	}

	/**
	 * Photo links leave the accessibility tree; the name link stays.
	 *
	 * @return void
	 */
	public function test_hides_image_links_only(): void {
		$item = '<li><div class="wc-block-components-product-image"><a href="/p/"><img alt="" src="x.jpg"></a></div>'
			. '<h2 class="wp-block-post-title"><a href="/p/">Hat</a></h2></li>';
		$html = ( new Saved_For_Later() )->hide_redundant_image_links( '<ul>' . $item . '<template>' . $item . '</template></ul>' );

		$processor = new \WP_HTML_Tag_Processor( $html );
		$hidden    = array();
		while ( $processor->next_tag( array( 'tag_name' => 'a' ) ) ) {
			$hidden[] = $processor->get_attribute( 'aria-hidden' ) . '|' . $processor->get_attribute( 'tabindex' );
		}

		$this->assertSame( array( 'true|-1', '|', 'true|-1', '|' ), $hidden );
	}

	/**
	 * An image box without a link leaves the name link alone.
	 *
	 * @return void
	 */
	public function test_skips_image_box_without_link(): void {
		$html = ( new Saved_For_Later() )->hide_redundant_image_links(
			'<li><div class="wc-block-components-product-image"><img alt="" src="x.jpg"></div>'
			. '<h2 class="wp-block-post-title"><a href="/p/">Hat</a></h2></li>'
		);

		$processor = new \WP_HTML_Tag_Processor( $html );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'a' ) ) );
		$this->assertNull( $processor->get_attribute( 'aria-hidden' ) );
		$this->assertNull( $processor->get_attribute( 'tabindex' ) );
	}

	/**
	 * The filters are registered; alignment runs after WooCommerce's heading seed.
	 *
	 * @return void
	 */
	public function test_registers_filters(): void {
		$service = new Saved_For_Later();
		$service->init();

		$this->assertSame( 20, has_filter( 'hooked_block_woocommerce/saved-for-later', array( $service, 'align_hooked_block' ) ) );
		$this->assertSame( 10, has_filter( 'render_block_woocommerce/saved-for-later', array( $service, 'hide_redundant_image_links' ) ) );
	}
}
