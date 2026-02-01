<?php
/**
 * Copyright Block Render
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$owner_name   = sanitize_text_field( $attributes['ownerName'] ?? 'Aggressive Apparel' );
$show_start   = (bool) ( $attributes['showStartYear'] ?? false );
$start_year   = sanitize_text_field( $attributes['startYear'] ?? '2024' );
$separator    = sanitize_text_field( $attributes['separator'] ?? '–' );
$prefix       = sanitize_text_field( $attributes['prefix'] ?? '©' );
$suffix       = $attributes['suffix'] ?? '';
$text_align   = sanitize_text_field( $attributes['textAlign'] ?? '' );
$current_year = gmdate( 'Y' );

if ( $show_start && $start_year !== $current_year ) {
	$year_display = $start_year . $separator . $current_year;
} else {
	$year_display = $current_year;
}

$copyright_text = sprintf(
	'%s %s %s',
	esc_html( $prefix ),
	esc_html( $year_display ),
	esc_html( $owner_name )
);

$suffix_html = '';
if ( $suffix ) {
	$suffix_html = sprintf(
		'<span class="wp-block-aggressive-apparel-copyright__suffix">%s</span>',
		wp_kses_post( $suffix )
	);
}

$extra_attrs = $text_align ? array( 'class' => 'has-text-align-' . $text_align ) : array();

printf(
	'<p %s><span class="wp-block-aggressive-apparel-copyright__text">%s</span>%s</p>',
	get_block_wrapper_attributes( $extra_attrs ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by WordPress.
	$copyright_text, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped above.
	$suffix_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped with wp_kses_post above.
);
