<?php
/**
 * Size Guide block — server render.
 *
 * Resolves the product exclusively from block context and delegates markup,
 * caching, assignment precedence, and duplicate protection to the shared
 * Size_Guide service.
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

use Aggressive_Apparel\WooCommerce\Size_Guide;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aa_product_id = isset( $block->context['postId'] )
	? absint( $block->context['postId'] )
	: absint( get_the_ID() );

if ( $aa_product_id <= 0 || 'product' !== get_post_type( $aa_product_id ) ) {
	return;
}

$aa_markup = ( new Size_Guide() )->render_block_markup( $aa_product_id, $attributes );

if ( '' !== $aa_markup ) {
	echo aggressive_apparel_trusted_html( $aa_markup );
}
