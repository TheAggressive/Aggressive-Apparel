<?php
/**
 * Mega Menu Content Block Render
 *
 * Dynamic rendering for the mega menu content wrapper.
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$layout     = $attributes['layout'] ?? 'columns';
$columns    = $attributes['columns'] ?? 4;
$full_width = $attributes['fullWidth'] ?? true;

// Build class list.
$classes = array(
	'wp-block-aggressive-apparel-mega-menu-content',
	'wp-block-aggressive-apparel-mega-menu-content--' . sanitize_html_class( $layout ),
);

if ( $full_width ) {
	$classes[] = 'wp-block-aggressive-apparel-mega-menu-content--full-width';
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => implode( ' ', $classes ),
		'style' => '--mega-menu-columns:' . absint( $columns ),
	)
);

printf(
	'<div %s>%s</div>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks content is already escaped.
);
