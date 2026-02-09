<?php
/**
 * Product Filter Styling Class
 *
 * Styles native WooCommerce product filter blocks to match the theme and
 * replaces the default color attribute display with swatch circles.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Product Filter Styling
 *
 * @since 1.17.0
 */
class Product_Filter_Styling {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_filter( 'render_block', array( $this, 'enhance_color_filter' ), 10, 2 );
	}

	/**
	 * Enqueue filter override styles on shop pages.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( ! $this->is_shop_page() ) {
			return;
		}

		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/product-filters.css';
		if ( ! file_exists( $css_file ) ) {
			return;
		}

		wp_enqueue_style(
			'aggressive-apparel-product-filters',
			AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/product-filters.css',
			array(),
			(string) filemtime( $css_file ),
		);
	}

	/**
	 * Enhance the color attribute filter block with swatch circles.
	 *
	 * @param string $block_content Block HTML.
	 * @param array  $block         Block data.
	 * @return string Modified HTML.
	 */
	public function enhance_color_filter( string $block_content, array $block ): string {
		if ( ! isset( $block['blockName'] ) ) {
			return $block_content;
		}

		// Target the attribute filter block when it renders the color attribute.
		if ( 'woocommerce/product-filter-attribute' !== $block['blockName'] ) {
			return $block_content;
		}

		// Only enhance when the block is configured for the color attribute.
		$attribute_id = $block['attrs']['attributeId'] ?? 0;
		if ( ! $this->is_color_attribute( (int) $attribute_id ) ) {
			return $block_content;
		}

		// Add a wrapper class so our CSS can target this specific instance.
		$block_content = str_replace(
			'wp-block-woocommerce-product-filter-attribute',
			'wp-block-woocommerce-product-filter-attribute aggressive-apparel-filter--color-swatches',
			$block_content,
		);

		return $block_content;
	}

	/**
	 * Check whether an attribute ID corresponds to the color attribute.
	 *
	 * @param int $attribute_id Attribute ID.
	 * @return bool
	 */
	private function is_color_attribute( int $attribute_id ): bool {
		if ( $attribute_id <= 0 || ! function_exists( 'wc_get_attribute' ) ) {
			return false;
		}

		$attribute = wc_get_attribute( $attribute_id );
		return $attribute && 'pa_color' === $attribute->slug;
	}

	/**
	 * Check if the current page is a shop/archive page.
	 *
	 * @return bool
	 */
	private function is_shop_page(): bool {
		if ( ! function_exists( 'is_shop' ) ) {
			return false;
		}
		return is_shop() || is_product_category() || is_product_tag();
	}
}
