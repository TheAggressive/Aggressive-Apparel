<?php
/**
 * Product Tabs Block — Server Render.
 *
 * Renders WooCommerce product tabs in the configured display style using
 * the Product_Tabs service layer. The block is intended for placement on
 * the single product template in place of the native Product Details block.
 *
 * The block's `displayStyle` attribute overrides the global setting so the
 * admin can set per-placement style from the editor.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive_Apparel
 */

defined( 'ABSPATH' ) || exit;

use Aggressive_Apparel\WooCommerce\Product_Tabs;
use Aggressive_Apparel\WooCommerce\Product_Tabs_Config;
use Aggressive_Apparel\WooCommerce\Product_Context;

if ( ! class_exists( Product_Tabs::class ) ) {
	return;
}

// Only render on single product pages.
if ( ! function_exists( 'is_product' ) || ! is_product() ) {
	// Allow rendering in editor previews.
	if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
}

// Honour the block attribute if set, otherwise fall through to the global setting.
$block_display_style = isset( $attributes['displayStyle'] ) ? sanitize_key( (string) $attributes['displayStyle'] ) : '';

// Override the global option when the block has its own explicit setting.
$option_override = null;
if ( '' !== $block_display_style ) {
	$valid_styles = array( 'accordion', 'inline', 'modern-tabs', 'scrollspy' );
	if ( in_array( $block_display_style, $valid_styles, true ) ) {
		$option_override = $block_display_style;
	}
}

// Temporarily override the stored display style when the block specifies one.
if ( null !== $option_override ) {
	add_filter(
		'option_' . Product_Tabs_Config::OPTION_KEY,
		static function ( $value ) use ( $option_override ) {
			if ( is_array( $value ) ) {
				$value['display_style'] = $option_override;
			} else {
				$value = array( 'display_style' => $option_override );
			}
			return $value;
		},
		99
	);
}

// Instantiate the Product_Tabs service and wire the custom-tab filter.
$product_tabs_service = new Product_Tabs();
add_filter( 'woocommerce_product_tabs', array( $product_tabs_service, 'add_custom_tabs' ), 20 );

// Enqueue Interactivity API state for tab behaviour (accordion/modern-tabs/scrollspy).
$display_style = $product_tabs_service->get_display_style();
if ( 'inline' !== $display_style && function_exists( 'wp_interactivity_state' ) ) {
	wp_interactivity_state(
		'aggressive-apparel/product-tabs',
		array(
			'style'         => $display_style,
			'activeTab'     => 0,
			'activeSection' => '',
		)
	);
}

// Retrieve and render the tabs.
$renderer = new \Aggressive_Apparel\WooCommerce\Product_Tabs_Renderer(
	new \Aggressive_Apparel\WooCommerce\Product_Tabs_Content(),
	$product_tabs_service
);

$hide_content_titles = ! empty( $attributes['hideContentTitles'] );

$renderable_tabs = $renderer->get_renderable_woocommerce_tabs( array(), $hide_content_titles );

if ( empty( $renderable_tabs ) ) {
	return;
}

// Render without a wrapper so the Product_Tabs_Renderer controls the root element.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output from renderer is already escaped via kses inside.
echo $renderer->render_tabs_by_style( $renderable_tabs, '', $hide_content_titles );
