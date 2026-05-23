<?php
/**
 * Catalog Hover Image Class
 *
 * Injects a product's first gallery image onto cards in WooCommerce archive
 * pages. The secondary image is revealed on hover using a CSS-only transition
 * chosen in Store Enhancements.
 *
 * @package Aggressive_Apparel
 * @since 1.64.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Catalog Hover Image
 *
 * @since 1.64.0
 */
class Catalog_Hover_Image {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'render_block', array( $this, 'inject_hover_image' ), 20, 2 );
	}

	/**
	 * Enqueue the hover image stylesheet on listing pages.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_listing_page() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/catalog-hover-image.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-catalog-hover-image',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/catalog-hover-image.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}
	}

	/**
	 * Inject the secondary gallery image into product card featured image blocks.
	 *
	 * @param string               $block_content Block HTML.
	 * @param array<string, mixed> $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_hover_image( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) || 'core/post-featured-image' !== $block['blockName'] ) {
			return $block_content;
		}

		if ( ! $this->is_listing_page() ) {
			return $block_content;
		}

		$product = $this->get_current_product();
		if ( ! $product ) {
			return $block_content;
		}

		$gallery_ids = $product->get_gallery_image_ids();
		if ( empty( $gallery_ids ) ) {
			return $block_content;
		}

		$hover_image_id = (int) $gallery_ids[0];
		$animation      = (string) get_option( Feature_Settings::HOVER_IMAGE_ANIMATION_OPTION, 'fade' );

		$hover_img = wp_get_attachment_image(
			$hover_image_id,
			'woocommerce_thumbnail',
			false,
			array(
				'class'       => 'aa-hover-img__secondary',
				'aria-hidden' => 'true',
				'alt'         => '',
			)
		);

		if ( '' === $hover_img ) {
			return $block_content;
		}

		$hover_html = sprintf(
			'<div class="aa-hover-img aa-hover-img--%s" aria-hidden="true">%s</div>',
			esc_attr( $animation ),
			$hover_img,
		);

		// Inject before the last closing </figure> or </div> in the block.
		return preg_replace(
			'/(<\/(?:figure|div)>\s*)$/i',
			$hover_html . '$1',
			$block_content,
			1,
		) ?? $block_content;
	}

	/**
	 * Get the WooCommerce product for the current post in the loop.
	 *
	 * @return \WC_Product|null
	 */
	private function get_current_product(): ?\WC_Product {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( get_the_ID() );
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Check whether the current page is a product listing.
	 *
	 * @return bool
	 */
	private function is_listing_page(): bool {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}
		return is_shop() || is_product_category() || is_product_tag() || is_search();
	}
}
