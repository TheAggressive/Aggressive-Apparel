<?php
/**
 * Product Card Contract
 *
 * Stamps stable data attributes onto product-card image and link markup so
 * commerce blocks (e.g. product-color-swatches) can target them without
 * scraping WooCommerce/CSS class names.
 *
 * Contract:
 * - `data-aa-product-image` on the product card `<img>`
 * - `data-aa-product-link` on product permalink `<a>` elements
 *
 * @package Aggressive_Apparel
 * @since 1.142.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Card Contract
 *
 * @since 1.142.0
 */
class Product_Card_Contract {

	/**
	 * Attribute marking the swappable product-card image.
	 */
	public const ATTR_IMAGE = 'data-aa-product-image';

	/**
	 * Attribute marking product permalink anchors.
	 */
	public const ATTR_LINK = 'data-aa-product-link';

	/**
	 * Register block render filters.
	 *
	 * @return void
	 */
	public function init(): void {
		// Priority 5: stamp attrs before badge/hover/quick-view overlays append markup.
		Block_Filter_Hooks::add_featured_image( array( $this, 'stamp_image_block' ), 5 );
		Block_Filter_Hooks::add( 'woocommerce/product-image', array( $this, 'stamp_image_block' ), 5 );
		Block_Filter_Hooks::add( 'core/post-title', array( $this, 'stamp_title_block' ), 5 );
	}

	/**
	 * Stamp image + link contract attrs onto product image blocks.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 * @return string
	 */
	public function stamp_image_block( string $block_content, array $block ): string {
		if ( '' === $block_content || Product_Context::resolve_product_id( $block ) <= 0 ) {
			return $block_content;
		}

		// Always stamp any permalink anchors present — WC may wrap images even
		// when the isLink attribute is absent or named differently across versions.
		return $this->stamp_attributes( $block_content, true, true );
	}

	/**
	 * Stamp link contract attrs onto linked product titles.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 * @return string
	 */
	public function stamp_title_block( string $block_content, array $block ): string {
		if (
			'' === $block_content
			|| empty( $block['attrs']['isLink'] )
			|| Product_Context::resolve_product_id( $block ) <= 0
		) {
			return $block_content;
		}

		return $this->stamp_attributes( $block_content, false, true );
	}

	/**
	 * Apply contract attributes with WP_HTML_Tag_Processor.
	 *
	 * @param string $html       Block HTML.
	 * @param bool   $stamp_img  Whether to mark the first <img>.
	 * @param bool   $stamp_link Whether to mark <a> elements.
	 * @return string
	 */
	private function stamp_attributes( string $html, bool $stamp_img, bool $stamp_link ): string {
		if ( ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}

		$processor   = new \WP_HTML_Tag_Processor( $html );
		$img_stamped = ! $stamp_img;

		while ( $processor->next_tag() ) {
			$tag = strtolower( (string) $processor->get_tag() );

			if ( ! $img_stamped && 'img' === $tag ) {
				$processor->set_attribute( self::ATTR_IMAGE, 'true' );
				$img_stamped = true;
			}

			if ( $stamp_link && 'a' === $tag ) {
				$processor->set_attribute( self::ATTR_LINK, 'true' );
			}

			if ( $img_stamped && ! $stamp_link ) {
				break;
			}
		}

		return $processor->get_updated_html();
	}
}
