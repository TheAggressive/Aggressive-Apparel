<?php
/**
 * Page Transitions Class
 *
 * Enables smooth cross-page navigation via the View Transitions API.
 * Adds view-transition-name properties to product images for shared
 * element transitions between archive and single product pages.
 *
 * Progressive enhancement — unsupported browsers get normal page loads.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page Transitions
 *
 * @since 1.18.0
 */
class Page_Transitions {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_filter( 'render_block', array( $this, 'inject_transition_names' ), 10, 2 );
	}

	/**
	 * Enqueue page transition styles on all frontend pages except checkout.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/page-transitions.css';
		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'aggressive-apparel-page-transitions',
				AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/page-transitions.css',
				array(),
				(string) filemtime( $css_file ),
			);
		}
	}

	/**
	 * Inject view-transition-name on product images for shared element transitions.
	 *
	 * On archive pages: targets core/post-featured-image blocks.
	 * On single product: targets woocommerce/product-image-gallery block.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Block data including blockName.
	 * @return string Modified block HTML.
	 */
	public function inject_transition_names( string $block_content, array $block ): string {
		$block_name = $block['blockName'] ?? '';

		if ( 'core/post-featured-image' === $block_name ) {
			return $this->handle_archive_image( $block_content );
		}

		if ( 'woocommerce/product-image-gallery' === $block_name ) {
			return $this->handle_single_gallery( $block_content );
		}

		return $block_content;
	}

	/**
	 * Add transition name to product featured images on archive/listing pages.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @return string Modified HTML.
	 */
	private function handle_archive_image( string $block_content ): string {
		if ( ! $this->is_listing_page() ) {
			return $block_content;
		}

		$product_id = (int) get_the_ID();
		if ( $product_id <= 0 ) {
			return $block_content;
		}

		$transition_style = sprintf( 'view-transition-name:product-img-%d', $product_id );

		return $this->inject_inline_style( $block_content, $transition_style );
	}

	/**
	 * Add transition name to the product image gallery on single product pages.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @return string Modified HTML.
	 */
	private function handle_single_gallery( string $block_content ): string {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return $block_content;
		}

		$product_id = (int) get_the_ID();
		if ( $product_id <= 0 ) {
			return $block_content;
		}

		$transition_style = sprintf( 'view-transition-name:product-img-%d', $product_id );

		return $this->inject_inline_style( $block_content, $transition_style );
	}

	/**
	 * Inject an inline style into the first HTML element of block content.
	 *
	 * Appends to existing style attribute or creates a new one.
	 *
	 * @param string $html  Block HTML.
	 * @param string $style CSS declarations to inject.
	 * @return string Modified HTML.
	 */
	private function inject_inline_style( string $html, string $style ): string {
		// If there's an existing style attribute, append to it.
		if ( preg_match( '/^(<[a-z][^>]*)\bstyle="([^"]*)"/i', $html ) ) {
			return (string) preg_replace(
				'/^(<[a-z][^>]*)\bstyle="([^"]*)"/i',
				'$1style="$2;' . esc_attr( $style ) . '"',
				$html,
				1
			);
		}

		// No existing style — add one to the first element.
		return (string) preg_replace(
			'/^(<[a-z][^>]*?)(\s*\/?>)/i',
			'$1 style="' . esc_attr( $style ) . '"$2',
			$html,
			1
		);
	}

	/**
	 * Check if the current page is a product listing (archive, category, tag, shop).
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
