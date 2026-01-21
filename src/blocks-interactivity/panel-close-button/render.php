<?php
/**
 * Panel Close Button Block Render
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$icon_color       = $attributes['iconColor'] ?? '';
$icon_hover_color = $attributes['iconHoverColor'] ?? '';
$bg_color         = $attributes['backgroundColor'] ?? '';
$bg_hover_color   = $attributes['backgroundHoverColor'] ?? '';
$label            = $attributes['label'] ?? __( 'Close menu', 'aggressive-apparel' );

// Build inline styles with CSS variables.
$style_parts = array();

if ( $icon_color ) {
	$style_parts[] = sprintf( '--close-btn-color: %s', esc_attr( $icon_color ) );
}
if ( $icon_hover_color ) {
	$style_parts[] = sprintf( '--close-btn-hover-color: %s', esc_attr( $icon_hover_color ) );
}
if ( $bg_color ) {
	$style_parts[] = sprintf( '--close-btn-bg: %s', esc_attr( $bg_color ) );
}
if ( $bg_hover_color ) {
	$style_parts[] = sprintf( '--close-btn-hover-bg: %s', esc_attr( $bg_hover_color ) );
}

$wrapper_attrs = array(
	'class'             => 'wp-block-aggressive-apparel-panel-close-button',
	'type'              => 'button',
	'aria-label'        => esc_attr( $label ),
	'data-wp-on--click' => 'actions.close',
);

// Only add style attribute if there are custom colors set.
if ( ! empty( $style_parts ) ) {
	$wrapper_attrs['style'] = implode( '; ', $style_parts ) . ';';
}

$wrapper_attributes = get_block_wrapper_attributes( $wrapper_attrs );

?>
<button <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
		<line x1="18" y1="6" x2="6" y2="18"></line>
		<line x1="6" y1="6" x2="18" y2="18"></line>
	</svg>
</button>
