<?php
/**
 * Product Filter Toggle Block — Server Render
 *
 * Emits the trigger button that opens the product-filters drawer.
 * The button is wired to the existing Interactivity API store
 * (registered by Product_Filters), so this block only needs the
 * correct directives — no separate view script module is required.
 *
 * Accessibility model:
 *   - `<button type="button">` so the trigger can never accidentally
 *     submit a parent form (e.g. WooCommerce search).
 *   - `aria-haspopup="dialog"` + `aria-controls` describe the
 *     button-to-drawer relationship.
 *   - `aria-expanded` is bound to the live `isDrawerOpen` state.
 *   - The accessible name is sourced from visible content (or a
 *     `screen-reader-text` span when the icon-only preset is used)
 *     so WCAG 2.5.3 "Label in Name" is satisfied for voice control.
 *   - The active-filter badge is decorative for sighted users
 *     (`aria-hidden="true"`); a sibling `screen-reader-text` span
 *     bound to `state.triggerCountLabel` carries the count to AT.
 *
 * Available variables:
 *   $attributes (array)
 *   $content    (string)
 *   $block      (WP_Block)
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

use Aggressive_Apparel\WooCommerce\Feature_Settings;
use Aggressive_Apparel\WooCommerce\Product_Filters;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	! class_exists( Product_Filters::class ) ||
	! class_exists( Feature_Settings::class ) ||
	! Feature_Settings::is_enabled( 'product_filters' )
) {
	return;
}

if ( ! Product_Filters::is_filterable_archive() ) {
	return;
}

$default_label = Feature_Settings::get_filter_toggle_text();
$label         = isset( $attributes['label'] ) ? (string) $attributes['label'] : $default_label;
if ( __( 'Filter', 'aggressive-apparel' ) === $label ) {
	$label = $default_label;
}
$show_label  = ! isset( $attributes['showLabel'] ) || (bool) $attributes['showLabel'];
$show_icon   = ! isset( $attributes['showIcon'] ) || (bool) $attributes['showIcon'];
$icon_only   = isset( $attributes['iconOnly'] ) && (bool) $attributes['iconOnly'];
$mobile_only = isset( $attributes['mobileOnly'] ) ? (string) $attributes['mobileOnly'] : 'auto';

if ( $icon_only ) {
	$show_icon  = true;
	$show_label = false;
}

$layout = Product_Filters::get_active_layout();

$mobile_only_class = '';
if ( 'always' === $mobile_only ) {
	$mobile_only_class = ' aa-filter-toggle--mobile-only';
} elseif ( 'auto' === $mobile_only && 'drawer' !== $layout ) {
	$mobile_only_class = ' aa-filter-toggle--mobile-only';
}

$icon_only_class = $icon_only ? ' aa-filter-toggle--icon-only' : '';

$wrapper_classes = 'aa-filter-toggle' . $mobile_only_class . $icon_only_class;

$trimmed_label = trim( $label );

// In icon-only mode the visible label span is suppressed, so we surface
// the same string inside a `screen-reader-text` span — that becomes the
// button's accessible name without re-introducing visible text.
$label_html = '';
if ( '' !== $trimmed_label ) {
	if ( $show_label ) {
		$label_html = sprintf(
			'<span class="aa-filter-toggle__label">%s</span>',
			esc_html( $trimmed_label )
		);
	} else {
		$label_html = sprintf(
			'<span class="screen-reader-text">%s</span>',
			esc_html( $trimmed_label )
		);
	}
}

// Accessible count announcement. Empty when no filters active so it
// adds nothing to the accessible name; when filters are applied it
// reads as e.g. "(3 filters applied)".
$sr_count_html = '<span class="screen-reader-text" data-wp-text="state.triggerCountLabel"></span>';

// Visual count badge — purely decorative; the screen-reader span
// above carries the real meaning to AT.
$visual_count_html = '<span class="aa-filter-toggle__count" aria-hidden="true" data-wp-text="state.activeFilterCount" data-wp-bind--hidden="state.hasNoActiveFilters" hidden></span>';

printf(
	'<button %1$s>%2$s%3$s%4$s%5$s</button>',
	get_block_wrapper_attributes(
		array(
			'class'                       => $wrapper_classes,
			'type'                        => 'button',
			'data-wp-interactive'         => 'aggressive-apparel/product-filters',
			'data-wp-on--click'           => 'actions.openDrawer',
			'data-wp-bind--aria-expanded' => 'state.isDrawerOpen',
			'aria-expanded'               => 'false',
			'aria-haspopup'               => 'dialog',
			'aria-controls'               => 'aa-product-filters-drawer',
		)
	),
	$show_icon
		? aggressive_apparel_get_icon(
			'filter',
			array(
				'width'       => 20,
				'height'      => 20,
				'aria-hidden' => 'true',
			)
		)
		: '',
	wp_kses_post( $label_html ),
	wp_kses_post( $sr_count_html ),
	wp_kses_post( $visual_count_html )
);
