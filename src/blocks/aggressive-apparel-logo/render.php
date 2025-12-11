<?php
/**
 * Responsive Logo Block - Frontend Render.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

// Extract and sanitize attributes.
$alt               = isset( $attributes['alt'] ) ? sanitize_text_field( $attributes['alt'] ) : 'Aggressive Apparel';
$light_color       = isset( $attributes['lightColor'] ) ? sanitize_hex_color( $attributes['lightColor'] ) : '#000000';
$light_hover_color = isset( $attributes['lightHoverColor'] ) ? sanitize_hex_color( $attributes['lightHoverColor'] ) : '#333333';
$dark_color        = isset( $attributes['darkColor'] ) ? sanitize_hex_color( $attributes['darkColor'] ) : '#ffffff';
$dark_hover_color  = isset( $attributes['darkHoverColor'] ) ? sanitize_hex_color( $attributes['darkHoverColor'] ) : '#cccccc';
$breakpoint        = isset( $attributes['breakpoint'] ) ? sanitize_key( $attributes['breakpoint'] ) : 'tablet';
$large_width       = isset( $attributes['largeWidth'] ) ? absint( $attributes['largeWidth'] ) : 200;
$large_height      = isset( $attributes['largeHeight'] ) && $attributes['largeHeight'] > 0 ? absint( $attributes['largeHeight'] ) : null;
$small_width       = isset( $attributes['smallWidth'] ) ? absint( $attributes['smallWidth'] ) : 48;
$small_height      = isset( $attributes['smallHeight'] ) && $attributes['smallHeight'] > 0 ? absint( $attributes['smallHeight'] ) : null;
$link_to_home      = isset( $attributes['linkToHome'] ) ? (bool) $attributes['linkToHome'] : true;

// Validate breakpoint.
$valid_breakpoints = array( 'mobile', 'tablet', 'desktop' );
if ( ! in_array( $breakpoint, $valid_breakpoints, true ) ) {
	$breakpoint = 'tablet';
}

// Large SVG - visible by default.
$large_style = sprintf(
	'display:block;width:%dpx;height:%s',
	$large_width,
	$large_height ? $large_height . 'px' : 'auto'
);

// Small SVG - hidden by default.
$small_style = sprintf(
	'display:none;width:%dpx;height:%s',
	$small_width,
	$small_height ? $small_height . 'px' : 'auto'
);

// Inline SVGs.
$svg_large = sprintf(
	'<svg class="aggressive-apparel-logo__svg aggressive-apparel-logo__svg--large" style="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 675 72" aria-hidden="true"><g fill="currentColor"><polygon points="513,54 525.6,72 525.6,22.7 513,22.7"/><path d="M657,44.9h-27.4c-11.3,0-14.5-0.8-16.6-2.3c-2.1-1.5-3.7-3.7-4.5-6.6H639l18-13.2h-49.3v-9h48.9l18-13.9h-72.8H594h-8.4c0,0-22.7,41-23.1,42.2C561.6,39.9,539.6,0,539.6,0h-10.8h-5.4h-65.7c-16.5,0-25,7.6-25.6,22.8H432h-18h-40.9c0.7-6,3.9-9,9.7-9H414L432,0h-38.8h-7.4H291h-3h-72v22.8h-36L198,36h6.4v8.9h-46.7V29.9c0-3.1,0.2-5.7,0.7-7.7c0.5-2.1,1.2-3.7,2.4-4.9c1.1-1.2,2.6-2.1,4.5-2.6c1.9-0.5,4.3-0.8,7.1-0.8H198L216,0h-43.9c-4.5,0-8.5,0.7-12,2c-3.5,1.3-6.4,3.2-8.8,5.8c-2.4,2.5-4.2,5.6-5.4,9.2c-0.6,1.8-1.1,3.8-1.4,5.9H108L126,36h6.4v8.9H85.7V29.9c0-3.1,0.2-5.7,0.7-7.7c0.5-2.1,1.2-3.7,2.4-4.9c1.1-1.2,2.6-2.1,4.5-2.6c1.9-0.5,4.3-0.8,7.1-0.8H126L144,0h-43.9c-4.5,0-8.5,0.7-12,2c-3.5,1.3-6.4,3.2-8.8,5.8c-2.4,2.5-4.2,5.6-5.4,9.2c-0.2,0.5-0.4,1.1-0.5,1.7V0H28.1c-4.5,0-8.4,0.7-11.9,2c-3.5,1.3-6.4,3.2-8.8,5.8C5,10.2,3.2,13.3,1.9,17C0.6,20.6,0,24.7,0,29.3V54l13.7,18V36h45.9v36l10-13.2H144h2.1h73.6l10,13.2V13.9h48c-0.4,2.9-1.4,5.1-3,6.7c-1.6,1.5-3.7,2.3-6.5,2.3H234l45,36h36l0,0c0.4,0,0.8,0,1.3,0H360h18h4h24.4h72c17.1,0,25.6-8.3,25.6-24.8V22.8h-54h-4.9c0.7-6,3.9-9,9.7-9H531L562.5,72L594,14.3v15.3c0,4.5,0.6,8.5,1.9,12.1c1.3,3.6,3.2,6.7,5.6,9.2c2.4,2.5,5.4,4.5,8.9,5.8c3.5,1.4,7.4,2.1,11.8,2.1H675L657,44.9z M59.6,23.1H14.6c0.3-1.3,0.7-2.6,1.3-3.7c0.6-1.1,1.6-2.1,3.1-2.9c1.4-0.8,3.4-1.4,5.9-1.9c2.5-0.4,5.8-0.7,10-0.7h24.8V23.1z M270.2,35.8c8.6-0.9,14.6-4.5,17.8-10.6v4.4c0,4.5,0.6,8.5,1.9,12.1c0.7,2.1,1.7,4,2.8,5.7L270.2,35.8z M389.5,44.9H378h-6.8h-54.7c-4.2,0-7.4-0.8-9.5-2.3c-2.1-1.5-3.7-3.7-4.5-6.6H333l18-13.2h-49.3v-9h59.9c-1.1,3.1-1.6,6.8-1.6,10.9V36h54h4.9c-0.4,2.9-1.4,5.1-3,6.6c-1.6,1.5-3.8,2.3-6.6,2.3H389.5z M450,36h40.9c-0.4,2.9-1.4,5.1-3,6.6c-1.6,1.5-3.8,2.3-6.6,2.3h-50.9c0.9-2.6,1.4-5.6,1.6-8.9h0.1H450z"/></g></svg>',
	esc_attr( $large_style )
);

$svg_small = sprintf(
	'<svg class="aggressive-apparel-logo__svg aggressive-apparel-logo__svg--small" style="%s" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 199 175" aria-hidden="true"><g fill="currentColor"><path d="M12.6,75.3c0-12.1,2.4-22,5.1-29.9c6.5-18.9,26.5-33.8,54.7-33.8h106.8L199,0H68.4c-10.8,0-20.5,1.6-29,4.8c-8.5,3.2-15.6,7.9-21.5,14C12.1,24.9,7.7,32.4,4.6,41.3C1.5,50.2,0,60.2,0,71.3V134l44.6,41l-32-43.7V75.3z"/><path d="M167.6,17.1h-5.2h-8.5H73.3c-8.8,0-16.6,1.3-23.4,3.9c-6.9,2.6-12.6,6.4-17.3,11.3c-4.7,5-8.3,11-10.8,18.2C19.2,57.6,18,65.7,18,74.8v48.6l27,35.4V88h90.4v70.8l27-35.4V29.7h5.2v101.7l-32,43.7l44.6-41V17.1h-1.8H167.6z M135.4,62.5H46.7c0.5-2.6,1.4-5.1,2.6-7.4c1.2-2.3,3.2-4.2,6-5.8c2.8-1.6,6.7-2.8,11.6-3.7c4.9-0.9,11.4-1.3,19.6-1.3h48.9V62.5z"/></g></svg>',
	esc_attr( $small_style )
);

// CSS custom properties for colors (including hover).
$css_vars = sprintf(
	'--logo-light-color:%s;--logo-light-hover:%s;--logo-dark-color:%s;--logo-dark-hover:%s',
	esc_attr( $light_color ),
	esc_attr( $light_hover_color ),
	esc_attr( $dark_color ),
	esc_attr( $dark_hover_color )
);

// Build wrapper attributes.
$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'           => 'aggressive-apparel-logo',
		'style'           => $css_vars,
		'data-breakpoint' => $breakpoint,
	)
);

// Build the inner content.
$inner_content = sprintf(
	'<span class="aggressive-apparel-logo__inner" role="img" aria-label="%s">%s%s</span>',
	esc_attr( $alt ),
	$svg_large,
	$svg_small
);

// Optionally wrap in a link.
if ( $link_to_home ) {
	$inner_content = sprintf(
		'<a href="%s" class="aggressive-apparel-logo__link" rel="home">%s</a>',
		esc_url( home_url( '/' ) ),
		$inner_content
	);
}

// Output the block.
printf(
	'<div %s>%s</div>',
	$wrapper_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$inner_content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
