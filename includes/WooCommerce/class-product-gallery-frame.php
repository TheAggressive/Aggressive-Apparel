<?php
/**
 * Product Gallery Frame
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sizes the product page's large-image frame to the product's own photography.
 *
 * WooCommerce frames every gallery slide in a 1:1 box and letterboxes the
 * image inside it (`object-fit: contain`), so portrait apparel photography
 * renders at two-thirds of the column width with empty bands either side.
 * The block exposes the frame ratio as two custom properties; this sets them
 * from the main image's real dimensions, clamped between 2:3 and 1:1 so a
 * panorama or an extreme crop can't produce an unusable frame. Images are
 * never cropped — the frame changes, not the photograph.
 */
final class Product_Gallery_Frame {

	/** Tallest frame allowed (width / height). */
	private const MIN_RATIO = 2 / 3;

	/** Widest frame allowed (width / height). */
	private const MAX_RATIO = 1.0;

	/** Register hooks. */
	public function init(): void {
		add_filter( 'render_block_woocommerce/product-gallery', array( $this, 'frame' ), 10, 3 );
	}

	/**
	 * Add the frame-ratio properties to the gallery's root element.
	 *
	 * @param string         $block_content Rendered block.
	 * @param array<mixed>   $parsed_block  Parsed block.
	 * @param \WP_Block|null $instance      Block instance.
	 */
	public function frame( string $block_content, array $parsed_block = array(), ?\WP_Block $instance = null ): string {
		$product_id = (int) ( $instance->context['postId'] ?? get_the_ID() );
		$ratio      = self::ratio( $product_id );
		if ( null === $ratio ) {
			return $block_content;
		}

		$tags = new \WP_HTML_Tag_Processor( $block_content );
		if ( ! $tags->next_tag() ) {
			return $block_content;
		}
		$style = (string) $tags->get_attribute( 'style' );
		$tags->set_attribute(
			'style',
			rtrim( $style, '; ' ) . ( '' === $style ? '' : ';' )
				. sprintf( '--wc-block-product-gallery-large-image-ratio-width:%d;--wc-block-product-gallery-large-image-ratio-height:%d', $ratio[0], $ratio[1] )
		);

		return $tags->get_updated_html();
	}

	/**
	 * Frame ratio for a product's main image as integer width/height, or null
	 * when there is no measurable image (WooCommerce's 1:1 default stays).
	 *
	 * @param int $product_id Product ID.
	 * @return array{0: int, 1: int}|null
	 */
	public static function ratio( int $product_id ): ?array {
		$image = $product_id > 0 ? (int) get_post_thumbnail_id( $product_id ) : 0;
		$meta  = $image > 0 ? wp_get_attachment_metadata( $image ) : false;
		if ( ! is_array( $meta ) || empty( $meta['width'] ) || empty( $meta['height'] ) ) {
			return null;
		}

		$ratio = max( self::MIN_RATIO, min( self::MAX_RATIO, (int) $meta['width'] / (int) $meta['height'] ) );

		// Three decimal places as integers: aspect-ratio takes <number> / <number>.
		return array( (int) round( $ratio * 1000 ), 1000 );
	}
}
