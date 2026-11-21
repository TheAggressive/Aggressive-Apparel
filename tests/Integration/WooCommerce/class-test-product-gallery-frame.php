<?php
/**
 * Product gallery frame tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Integration\WooCommerce;

use Aggressive_Apparel\WooCommerce\Product_Gallery_Frame;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * The gallery frame follows the product's own photography, within bounds.
 */
class Test_Product_Gallery_Frame extends WP_UnitTestCase {

	/**
	 * Frame ratios for representative photography.
	 *
	 * @return array<string, array{0: int, 1: int, 2: array{0: int, 1: int}}>
	 */
	public static function provide_photography(): array {
		return array(
			'3:4 portrait keeps its ratio' => array( 600, 800, array( 750, 1000 ) ),
			'square stays square'          => array( 800, 800, array( 1000, 1000 ) ),
			'landscape clamps to square'   => array( 1600, 900, array( 1000, 1000 ) ),
			'very tall clamps to 2:3'      => array( 400, 1200, array( 667, 1000 ) ),
		);
	}

	/**
	 * The frame takes the main image's ratio, clamped between 2:3 and 1:1.
	 *
	 * @dataProvider provide_photography
	 *
	 * @param int                   $width    Image width.
	 * @param int                   $height   Image height.
	 * @param array{0: int, 1: int} $expected Frame ratio as integers.
	 */
	public function test_ratio_follows_the_main_image_within_bounds( int $width, int $height, array $expected ): void {
		$product = $this->create_product( $width, $height );

		$this->assertSame( $expected, Product_Gallery_Frame::ratio( $product->get_id() ) );
	}

	/** Without a measurable image WooCommerce's default frame stays. */
	public function test_no_image_leaves_the_gallery_untouched(): void {
		$product = new WC_Product_Simple();
		$product->set_name( 'Frame probe' );
		$product->save();

		$this->assertNull( Product_Gallery_Frame::ratio( $product->get_id() ) );

		$html = '<div class="wc-block-product-gallery" style="gap:1rem"></div>';
		$this->assertSame( $html, ( new Product_Gallery_Frame() )->frame( $html, array(), $this->block_for( $product->get_id() ) ) );
	}

	/** The ratio is written to the custom properties WooCommerce's frame reads, keeping existing styles. */
	public function test_frame_sets_woocommerce_ratio_properties(): void {
		$product = $this->create_product( 600, 800 );
		$html    = '<div class="wc-block-product-gallery" style="gap:1rem"><img src="x.jpg" alt=""></div>';

		$framed = ( new Product_Gallery_Frame() )->frame( $html, array(), $this->block_for( $product->get_id() ) );

		$this->assertStringContainsString(
			'style="gap:1rem;--wc-block-product-gallery-large-image-ratio-width:750;--wc-block-product-gallery-large-image-ratio-height:1000"',
			$framed
		);
		$this->assertStringContainsString( '<img src="x.jpg" alt="">', $framed );
	}

	/** The service hooks the gallery block's render. */
	public function test_init_hooks_the_gallery_render(): void {
		$frame = new Product_Gallery_Frame();
		$frame->init();

		$this->assertSame( 10, has_filter( 'render_block_woocommerce/product-gallery', array( $frame, 'frame' ) ) );
	}

	/**
	 * Product with a main image of the given size.
	 *
	 * @param int $width  Image width.
	 * @param int $height Image height.
	 */
	private function create_product( int $width, int $height ): WC_Product_Simple {
		$image = self::factory()->attachment->create_object(
			array(
				'file'           => 'frame-probe.jpg',
				'post_mime_type' => 'image/jpeg',
			)
		);
		wp_update_attachment_metadata(
			$image,
			array(
				'width'  => $width,
				'height' => $height,
			)
		);

		$product = new WC_Product_Simple();
		$product->set_name( 'Frame probe' );
		$product->set_image_id( $image );
		$product->save();

		return $product;
	}

	/**
	 * A product gallery block instance in a product's context (the block
	 * declares `usesContext: postId`).
	 *
	 * @param int $product_id Product ID.
	 */
	private function block_for( int $product_id ): \WP_Block {
		return new \WP_Block(
			array(
				'blockName'    => 'woocommerce/product-gallery',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			array( 'postId' => $product_id )
		);
	}
}
