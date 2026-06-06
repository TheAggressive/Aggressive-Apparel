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

use Aggressive_Apparel\Assets\Asset_Loader;

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
		Block_Filter_Hooks::add_featured_image( array( $this, 'inject_hover_image' ), 20 );
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
		$enqueued = Asset_Loader::enqueue_feature_style(
			'aggressive-apparel-catalog-hover-image',
			'build/styles/woocommerce/catalog-hover-image'
		);

		if ( $enqueued ) {
			// Exit duration is user-configurable; emit CSS variable only when it
			// differs from the stylesheet default (350ms) to avoid no-op output.
			$raw_duration = Feature_Settings::get_hover_image_exit_duration();
			if ( 350 !== $raw_duration ) {
				$duration = max( 50, min( 1500, $raw_duration ) );
				wp_add_inline_style(
					'aggressive-apparel-catalog-hover-image',
					':root{--aa-primary-exit-duration:' . $duration . 'ms}'
				);
			}
		}
	}

	/**
	 * Inject the secondary gallery image into product card featured image blocks.
	 *
	 * Registered on the featured-image block filter; exits early when there is
	 * no current product or no gallery image.
	 *
	 * @param string               $block_content Block HTML.
	 * @param array<string, mixed> $block         Block data.
	 * @return string Modified HTML.
	 */
	public function inject_hover_image( string $block_content, array $block ): string {
		unset( $block );

		$product = $this->get_current_product();
		if ( ! $product ) {
			return $block_content;
		}

		$gallery_ids = $product->get_gallery_image_ids();
		if ( empty( $gallery_ids ) ) {
			return $block_content;
		}

		$hover_image_id = (int) $gallery_ids[0];
		$animation      = Feature_Settings::get_hover_image_animation();
		$exit           = Feature_Settings::get_hover_image_exit_animation();

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
			'<div class="aa-hover-img aa-hover-img--%s" data-aa-exit="%s" aria-hidden="true">%s</div>',
			esc_attr( $animation ),
			esc_attr( $exit ),
			$hover_img,
		);

		// Inject before the last closing </figure> or </div> in the block.
		return Block_Render_Helper::append_before_wrapper_close( $block_content, $hover_html );
	}

	/**
	 * Get the WooCommerce product for the current post in the loop.
	 *
	 * @return \WC_Product|null
	 */
	private function get_current_product(): ?\WC_Product {
		return Product_Context::get_current_product();
	}
}
