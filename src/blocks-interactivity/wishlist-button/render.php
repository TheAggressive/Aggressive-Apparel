<?php
/**
 * Wishlist Button Block — Server Render
 *
 * Emits an Add-to-Wishlist heart button bound to the current
 * product context (single product pages or items inside a
 * Query Loop / Product Collection). The button reuses the
 * shared wishlist script module. Clicks are handled by the document
 * delegate in `wishlist.ts` — no separate view script module is required.
 *
 * Accessibility model:
 *   - `<button type="button">` so the trigger never accidentally
 *     submits a parent form (e.g. a single-product variation form).
 *   - `aria-pressed` is synced client-side after each toggle.
 *   - The accessible name is sourced from visible content (or a
 *     `screen-reader-text` span when the icon-only preset is used)
 *     so WCAG 2.5.3 "Label in Name" is satisfied for voice control.
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
use Aggressive_Apparel\WooCommerce\Wishlist;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	! class_exists( Wishlist::class ) ||
	! class_exists( Feature_Settings::class ) ||
	! Feature_Settings::is_enabled( 'wishlist' )
) {
	return;
}

$product_id = Wishlist::get_current_product_id();
if ( $product_id <= 0 ) {
	return;
}

Wishlist::ensure_assets();

// Prevent duplicate automatic placement for this product only.
Wishlist::mark_button_block_rendered( $product_id );

$default_label = Feature_Settings::get_wishlist_button_text();
$label         = isset( $attributes['label'] ) ? (string) $attributes['label'] : $default_label;
if ( __( 'Add to Wishlist', 'aggressive-apparel' ) === $label ) {
	$label = $default_label;
}
$show_label = ! empty( $attributes['showLabel'] );
$show_icon  = ! isset( $attributes['showIcon'] ) || (bool) $attributes['showIcon'];
$icon_only  = ! isset( $attributes['iconOnly'] ) || (bool) $attributes['iconOnly'];
$size       = isset( $attributes['size'] ) ? (string) $attributes['size'] : 'default';

if ( $icon_only ) {
	$show_icon  = true;
	$show_label = false;
}

$button_classes  = 'aggressive-apparel-wishlist__toggle';
$button_classes .= $icon_only ? ' aggressive-apparel-wishlist__toggle--icon-only' : '';
$button_classes .= 'large' === $size ? ' aggressive-apparel-wishlist__toggle--large' : '';

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'              => $button_classes,
		'type'               => 'button',
		'data-aa-product-id' => (string) $product_id,
		'aria-pressed'       => 'false',
	)
);

$icon_html = '';
if ( $show_icon ) {
	$icon_html = '<svg class="aggressive-apparel-wishlist__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';
}

$trimmed_label = trim( $label );

// In icon-only mode the visible label span is suppressed, so we
// surface the same string inside a `screen-reader-text` span — that
// becomes the button's accessible name without re-introducing
// visible text.
$label_html = '';
if ( '' !== $trimmed_label ) {
	if ( $show_label ) {
		$label_html = sprintf(
			'<span class="aggressive-apparel-wishlist__label">%s</span>',
			esc_html( $trimmed_label )
		);
	} else {
		$label_html = sprintf(
			'<span class="screen-reader-text">%s</span>',
			esc_html( $trimmed_label )
		);
	}
}

printf(
	'<button %1$s>%2$s%3$s</button>',
	wp_kses_post( $wrapper_attributes ),
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static trusted SVG markup.
	$icon_html,
	wp_kses_post( $label_html )
);
