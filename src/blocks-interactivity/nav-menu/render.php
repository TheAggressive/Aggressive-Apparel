<?php
/**
 * Nav Menu Block Render
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$orientation = $attributes['orientation'] ?? 'horizontal';

// Build class list.
$classes = array(
	'wp-block-aggressive-apparel-nav-menu',
	'wp-block-aggressive-apparel-nav-menu--' . sanitize_html_class( $orientation ),
);

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'               => implode( ' ', $classes ),
		'role'                => 'menubar',
		'aria-orientation'    => esc_attr( $orientation ),
		'data-wp-interactive' => 'aggressive-apparel/navigation',
		'data-wp-on--keydown' => 'callbacks.onArrowKey',
	)
);

printf(
	'<ul %s>%s</ul>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks are already escaped.
);
