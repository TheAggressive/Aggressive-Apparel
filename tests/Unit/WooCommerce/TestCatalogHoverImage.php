<?php
/**
 * Catalog hover image tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Catalog_Hover_Image;
use WC_Product_Simple;
use WP_UnitTestCase;

/**
 * Verifies the secondary card image never competes with the primary.
 */
class TestCatalogHoverImage extends WP_UnitTestCase {

	/**
	 * Reset the loop post.
	 */
	public function tear_down(): void {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * The secondary is hidden until hover and never shown on touch, so it is
	 * lazy and low priority even where core would load an early image eagerly.
	 */
	public function test_secondary_image_is_lazy_and_low_priority(): void {
		$attachment_id = self::factory()->attachment->create_object(
			'hover-tee-back.jpg',
			0,
			array( 'post_mime_type' => 'image/jpeg' )
		);
		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'width'  => 600,
				'height' => 600,
				'file'   => 'hover-tee-back.jpg',
			)
		);

		$product = new WC_Product_Simple();
		$product->set_name( 'Hover Tee' );
		$product->set_gallery_image_ids( array( $attachment_id ) );
		$product->save();
		$GLOBALS['post'] = get_post( $product->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the block renders in the product's loop.

		$html = ( new Catalog_Hover_Image() )->inject_hover_image(
			'<figure class="wp-block-post-featured-image"><img src="front.jpg" alt="" /></figure>',
			array()
		);

		$this->assertMatchesRegularExpression( '/<img[^>]*class="aa-hover-img__secondary[^"]*"[^>]*>/', $html );
		$this->assertMatchesRegularExpression( '/<img[^>]*aa-hover-img__secondary[^>]*loading="lazy"/', $html );
		$this->assertMatchesRegularExpression( '/<img[^>]*aa-hover-img__secondary[^>]*fetchpriority="low"/', $html );
	}
}
