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
$text_align    = sanitize_key( $attributes['textAlign'] ?? '' );

// Shared with the editor preview so the two render identically.
$svg_markup = aggressive_apparel_icon_block_svg( $icon_slug, $attributes['iconSize'] ?? 48 );

if ( '' === $svg_markup ) {
	return;
}

$wrapper_class = 'aggressive-apparel-icon';

if ( in_array( $text_align, array( 'left', 'center', 'right' ), true ) ) {
	$wrapper_class .= ' has-text-align-' . $text_align;
}

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
