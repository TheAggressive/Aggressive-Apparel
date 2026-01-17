<?php
/**
 * Navigation Block Render
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$breakpoint = (int) ( $attributes['breakpoint'] ?? 1024 );
$aria_label = $attributes['ariaLabel'] ?? __( 'Main navigation', 'aggressive-apparel' );
$open_on    = $attributes['openOn'] ?? 'hover';

// Generate unique ID for this navigation instance.
// Prefer attribute-saved ID for context sharing, fall back to runtime ID.
$nav_id = ! empty( $attributes['navId'] ) ? $attributes['navId'] : wp_unique_id( 'nav-' );

// Store the runtime navId globally so child blocks can access it.
// This is necessary because providesContext only works with saved attributes,
// not runtime-generated values.
global $aggressive_apparel_current_nav_id, $aggressive_apparel_current_nav_breakpoint;
$aggressive_apparel_current_nav_id         = $nav_id;
$aggressive_apparel_current_nav_breakpoint = $breakpoint;

// Build context for Interactivity API using shared function.
$context = aggressive_apparel_encode_nav_context(
	aggressive_apparel_build_nav_context( $nav_id, $breakpoint, $open_on )
);

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id'                                     => $nav_id,
		'aria-label'                             => esc_attr( $aria_label ),
		'data-wp-interactive'                    => 'aggressive-apparel/navigation',
		'data-wp-context'                        => $context,
		'data-wp-init'                           => 'callbacks.init',
		'data-wp-on-window--keydown'             => 'callbacks.onEscape',
		'data-wp-on-window--aa-nav-state-change' => 'callbacks.onStateChange',
		'data-wp-class--is-open'                 => 'state.isOpen',
		'data-wp-class--is-mobile'               => 'state.isMobile',
		'style'                                  => '--navigation-breakpoint: ' . esc_attr( $breakpoint ) . 'px;',
	)
);

// Screen reader announcer for dynamic content.
$announcer_id   = aggressive_apparel_get_announcer_id( $nav_id );
$announcer_html = sprintf( '<div id="%s" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>', esc_attr( $announcer_id ) );

printf(
	'<nav %s>%s%s</nav>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$announcer_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Contains only safe HTML.
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks are already escaped.
);
