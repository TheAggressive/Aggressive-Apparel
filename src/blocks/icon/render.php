<?php
/**
 * Aggressive Icon Block — server render.
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

use Aggressive_Apparel\Core\Icons;

$icon_slug     = sanitize_key( $attributes['icon'] ?? 'shipping-box' );
$icon_size     = max( 16, min( 128, absint( $attributes['iconSize'] ?? 48 ) ) );
$is_decorative = (bool) ( $attributes['isDecorative'] ?? true );
$label         = sanitize_text_field( $attributes['label'] ?? '' );

if ( ! Icons::exists( $icon_slug ) ) {
	return;
}

$svg_attrs = array(
	'width'       => $icon_size,
	'height'      => $icon_size,
	'class'       => 'aggressive-apparel-icon__svg',
	'fill'        => 'currentColor',
	'aria-hidden' => 'true',
);

$svg_markup = Icons::get( $icon_slug, $svg_attrs );

if ( '' === $svg_markup ) {
	return;
}

$wrapper_attrs = array(
	'class' => 'aggressive-apparel-icon',
);

if ( $is_decorative || '' === $label ) {
	$wrapper_attrs['aria-hidden'] = 'true';
} else {
	$wrapper_attrs['role']       = 'img';
	$wrapper_attrs['aria-label'] = $label;
}

printf(
	'<span %1$s>%2$s</span>',
	get_block_wrapper_attributes( $wrapper_attrs ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by WordPress.
	$svg_markup // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted theme SVG from Icons::get().
);
