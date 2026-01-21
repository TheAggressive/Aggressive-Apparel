<?php
/**
 * Panel Footer Block Render
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

// Don't render if there's no content.
if ( empty( trim( $content ) ) ) {
	return;
}

$justify_content = $attributes['justifyContent'] ?? 'center';

$style_parts = array(
	sprintf( '--panel-footer-justify: %s', esc_attr( $justify_content ) ),
);

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'wp-block-aggressive-apparel-panel-footer',
		'style' => implode( '; ', $style_parts ) . ';',
	)
);

printf(
	'<div %s><div class="wp-block-aggressive-apparel-panel-footer__content">%s</div></div>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks are already escaped.
);
