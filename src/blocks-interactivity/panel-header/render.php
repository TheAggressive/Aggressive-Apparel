<?php
/**
 * Panel Header Block Render
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$justify_content = $attributes['justifyContent'] ?? 'flex-end';

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'wp-block-aggressive-apparel-panel-header',
		'style' => sprintf( '--panel-header-justify: %s;', esc_attr( $justify_content ) ),
	)
);

printf(
	'<div %s><div class="wp-block-aggressive-apparel-panel-header__content">%s</div></div>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks are already escaped.
);
