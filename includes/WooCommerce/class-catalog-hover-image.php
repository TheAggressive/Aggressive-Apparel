<?php
/**
 * Catalog Hover Image Class
 *
 * Injects a product's first gallery image onto product card featured-image
 * blocks wherever they appear: archives, category pages, search results,
 * single-product related/upsell rows, home-page product blocks, and
 * REST-rendered infinite-scroll cards.  The secondary image is revealed on
 * hover using a CSS-only transition chosen in Store Enhancements.
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
	 * Enqueue the hover image stylesheet.
	 *
	 * Loaded on every frontend page: the CSS only activates when the
	 * .aa-hover-img element is present, so there is no visual cost on pages
	 * that do not contain product cards.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
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
	 * Runs on every core/post-featured-image render; exits early when there is
	 * no current product or no gallery image, so it is safe on non-product pages.
	 *
	 * @param string               $block_content Block HTML.
	 * @param array<string, mixed> $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_hover_image( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) || 'core/post-featured-image' !== $block['blockName'] ) {
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
			'aggressive-apparel-product-thumbnail',
			false,
			array(
				'class'       => 'aa-hover-img__secondary',
				'aria-hidden' => 'true',
				'alt'         => '',
				// Match the grid layout so the browser picks the right srcset
				// entry rather than defaulting to the 300px woocommerce_thumbnail.
				'sizes'       => '(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 25vw',
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
}
