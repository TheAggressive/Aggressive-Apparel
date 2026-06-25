<?php
/**
 * Search Block — Server Render.
 *
 * Outputs the search trigger button (a search icon). Clicking it opens the
 * full-screen search modal, which is rendered once in wp_footer by
 * Aggressive_Apparel\Core\Search (portal pattern) and shares the
 * aggressive-apparel/search Interactivity store with this trigger.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive_Apparel
 *
 * @var array $attributes Block attributes.
 */

use Aggressive_Apparel\Core\Icons;

defined( 'ABSPATH' ) || exit;

$label      = isset( $attributes['label'] ) ? sanitize_text_field( (string) $attributes['label'] ) : __( 'Search', 'aggressive-apparel' );
$show_label = ! empty( $attributes['showLabel'] );
$icon_size  = isset( $attributes['iconSize'] ) ? (int) $attributes['iconSize'] : 24;
$icon_size  = max( 16, min( 48, $icon_size ) );

$icon = Icons::get(
	'search',
	array(
		'width'       => $icon_size,
		'height'      => $icon_size,
		'aria-hidden' => 'true',
	)
);

$label_html = $show_label
	? '<span class="aa-search-trigger__label">' . esc_html( $label ) . '</span>'
	: '';

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'               => 'aa-search-trigger',
		'data-wp-interactive' => 'aggressive-apparel/search',
	)
);

printf(
	'<button type="button" %1$s data-wp-on--click="actions.open" data-wp-bind--aria-expanded="state.isOpen" aria-haspopup="dialog" aria-label="%2$s">%3$s%4$s</button>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped output.
	esc_attr( $label ),
	$icon, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns sanitized inline SVG.
	$label_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
);
