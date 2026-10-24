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

$icon_slug     = sanitize_key( $attributes['icon'] ?? 'shipping-box' );
$is_decorative = (bool) ( $attributes['isDecorative'] ?? true );
$label         = sanitize_text_field( $attributes['label'] ?? '' );

// Shared with the editor preview so the two render identically.
$svg_markup = aggressive_apparel_icon_block_svg( $icon_slug, $attributes['iconSize'] ?? 48 );

if ( '' === $svg_markup ) {
	return;
}

// Native `typography.textAlign` adds its own class; this covers legacy blocks.
$wrapper_class = trim( 'aggressive-apparel-icon ' . aggressive_apparel_legacy_text_align_class( $attributes, 'textAlign' ) );

$wrapper_attrs = array(
	'class' => $wrapper_class,
);

if ( $is_decorative || '' === $label ) {
	$wrapper_attrs['aria-hidden'] = 'true';
} else {
	$wrapper_attrs['role']       = 'img';
	$wrapper_attrs['aria-label'] = $label;
}

printf(
	'<span %1$s>%2$s</span>',
	get_block_wrapper_attributes( $wrapper_attrs ),
	aggressive_apparel_trusted_html( $svg_markup )
);
