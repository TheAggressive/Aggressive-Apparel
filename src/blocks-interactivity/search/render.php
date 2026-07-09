<?php
/**
 * Search Block — Server Render.
 *
 * Outputs the search trigger button (a search icon). Clicking it opens the
 * full-screen search modal, which is rendered once in wp_footer by
 * Aggressive_Apparel\Core\Search (portal pattern) and shares the
 * aggressive-apparel/search Interactivity store with this trigger via
 * viewScriptModule.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive_Apparel
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fires when the search trigger block render callback runs.
 *
 * Ensures modal shell + store state load even when has_block() misses FSE
 * template parts during wp_enqueue_scripts.
 */
do_action( 'aggressive_apparel_search_block_rendered' );

$label      = isset( $attributes['label'] ) ? sanitize_text_field( (string) $attributes['label'] ) : __( 'Search', 'aggressive-apparel' );
$show_label = ! empty( $attributes['showLabel'] );
$icon_size  = isset( $attributes['iconSize'] ) ? (int) $attributes['iconSize'] : 24;
$icon_size  = max( 16, min( 48, $icon_size ) );

$label_html = $show_label
	? '<span class="aa-search-trigger__label">' . esc_html( $label ) . '</span>'
	: '';

printf(
	'<button type="button" %1$s data-wp-on--click="actions.open" data-wp-bind--aria-expanded="state.isOpen" aria-haspopup="dialog" aria-label="%2$s">%3$s%4$s</button>',
	get_block_wrapper_attributes(
		array(
			'class'               => 'aa-search-trigger',
			'data-wp-interactive' => 'aggressive-apparel/search',
		)
	),
	esc_attr( $label ),
	aggressive_apparel_get_icon(
		'search',
		array(
			'width'       => $icon_size,
			'height'      => $icon_size,
			'aria-hidden' => 'true',
		)
	),
	wp_kses_post( $label_html )
);
