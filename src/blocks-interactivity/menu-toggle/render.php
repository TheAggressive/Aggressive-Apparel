<?php
/**
 * Menu Toggle Block Render
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$label          = $attributes['label'] ?? __( 'Menu', 'aggressive-apparel' );
$icon_style     = $attributes['iconStyle'] ?? 'hamburger';
$animation_type = $attributes['animationType'] ?? 'to-x';
$show_label     = $attributes['showLabel'] ?? false;

// Get navigation ID and breakpoint from context using shared functions.
$nav_id     = aggressive_apparel_get_nav_id_from_context( $block );
$breakpoint = aggressive_apparel_get_nav_breakpoint_from_context( $block );

// Build IDs using shared functions.
$panel_id  = aggressive_apparel_get_panel_id( $nav_id );
$toggle_id = aggressive_apparel_get_toggle_id( $nav_id );

// Build context for Interactivity API using shared function.
$context = aggressive_apparel_encode_nav_context(
	aggressive_apparel_build_nav_context( $nav_id, $breakpoint )
);

// Build class list.
$classes = array(
	'wp-block-aggressive-apparel-menu-toggle',
	'wp-block-aggressive-apparel-menu-toggle--' . sanitize_html_class( $icon_style ),
	'wp-block-aggressive-apparel-menu-toggle--anim-' . sanitize_html_class( $animation_type ),
);

$wrapper_attributes_array = array(
	'class'                                  => implode( ' ', $classes ),
	'type'                                   => 'button',
	'aria-expanded'                          => 'false',
	'aria-controls'                          => esc_attr( $panel_id ),
	'aria-label'                             => esc_attr( $label ),
	'data-wp-interactive'                    => 'aggressive-apparel/navigation',
	'data-wp-context'                        => $context,
	'data-wp-on--click'                      => 'actions.toggle',
	'data-wp-bind--aria-expanded'            => 'state.isOpen',
	'data-wp-class--is-active'               => 'state.isOpen',
	'data-wp-on-window--aa-nav-state-change' => 'callbacks.onStateChange',
);

if ( $toggle_id ) {
	$wrapper_attributes_array['id'] = esc_attr( $toggle_id );
}

$wrapper_attributes = get_block_wrapper_attributes( $wrapper_attributes_array );

// Build icon markup based on style.
if ( 'dots' === $icon_style ) {
	$icon_html = sprintf(
		'<span class="wp-block-aggressive-apparel-menu-toggle__icon wp-block-aggressive-apparel-menu-toggle__icon--dots">%s</span>',
		str_repeat( '<span class="wp-block-aggressive-apparel-menu-toggle__dot"></span>', 3 )
	);
} else {
	$icon_html = sprintf(
		'<span class="wp-block-aggressive-apparel-menu-toggle__icon">%s</span>',
		str_repeat( '<span class="wp-block-aggressive-apparel-menu-toggle__bar"></span>', 3 )
	);
}

// Optional visible label.
$label_html = '';
if ( $show_label ) {
	$label_html = sprintf(
		'<span class="wp-block-aggressive-apparel-menu-toggle__label">%s</span>',
		esc_html( $label )
	);
}

printf(
	'<button %s>%s%s</button>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$icon_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Contains only safe HTML.
	$label_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped above.
);
