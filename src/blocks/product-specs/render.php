<?php
/**
 * Product Specs block — server render.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

use Aggressive_Apparel\WooCommerce\Product_Specs_Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aa_product_id = isset( $block->context['postId'] )
	? absint( $block->context['postId'] )
	: absint( get_the_ID() );

if ( $aa_product_id <= 0 || 'product' !== get_post_type( $aa_product_id ) ) {
	return;
}

$aa_markup = Product_Specs_Renderer::render( $aa_product_id, $attributes );

if ( '' === $aa_markup ) {
	return;
}

printf(
	'<div %1$s>%2$s</div>',
	get_block_wrapper_attributes(
		array( 'class' => 'aa-product-specs is-layout-' . Product_Specs_Renderer::layout( $attributes ) )
	),
	aggressive_apparel_trusted_html( $aa_markup ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by Product_Specs_Renderer.
);
