<?php
/**
 * Advanced Parallax Container Block
 *
 * Full implementation featuring:
 * - Intersection Observer integration
 * - Container for nested blocks with individual parallax controls
 * - Advanced parallax effects with customizable settings
 * The following variables are exposed to this file:
 * $attributes (array) : The block attributes .
 * $content (string) : The block default content .
 * $block( WP_Block ): The block instance .
 *
 * @see https:// github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive Apparel
 */

	declare(strict_types=1);

	defined( 'ABSPATH' ) || exit;

// Extract attributes with defaults.
$intensity                    = $attributes['intensity'] ?? 50;
$enable_intersection_observer = $attributes['enableIntersectionObserver'] ?? true;
$intersection_threshold       = $attributes['intersectionThreshold'] ?? 0.1;
$enable_mouse_interaction     = $attributes['enableMouseInteraction'] ?? false;
$parallax_direction           = $attributes['parallaxDirection'] ?? 'down';
$debug_mode                   = $attributes['debugMode'] ?? false;

// Build context for Interactivity API.
$context = array(
	// Core parallax settings.
	'intensity'                  => $intensity,
	'enableIntersectionObserver' => $enable_intersection_observer,
	'intersectionThreshold'      => $intersection_threshold,
	'enableMouseInteraction'     => $enable_mouse_interaction,
	'parallaxDirection'          => $parallax_direction,
	'debugMode'                  => $debug_mode,

	// Runtime state.
	'isIntersecting'             => false,
	'scrollProgress'             => 0,
	'mouseX'                     => 0,
	'mouseY'                     => 0,
	'hasInitialized'             => false,

	// Layer information (will be populated by JavaScript).
	'layers'                     => array(),
);

// Build CSS custom properties.
$css_vars = array(
	'--parallax-intensity'     => ( $intensity / 100 ),
	'--intersection-threshold' => $intersection_threshold,
);

$style_string = '';
foreach ( $css_vars as $property => $value ) {
	$style_string .= sprintf( '%s: %s; ', $property, $value );
}

// Build wrapper classes.
$classes = array(
	'wp-block-aggressive-apparel-parallax',
	'parallax-direction-' . $parallax_direction,
);

if ( $enable_mouse_interaction ) {
	$classes[] = 'has-mouse-interaction';
}

if ( $debug_mode ) {
	$classes[] = 'debug-mode';
}

// Get wrapper attributes with Interactivity API integration.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                          => implode( ' ', $classes ),
		'data-wp-interactive'            => 'aggressive-apparel/parallax',
		'data-wp-context'                => wp_json_encode( $context ),
		'data-wp-init'                   => 'callbacks.initParallax',
		'data-wp-watch'                  => 'callbacks.watchIntersection',
		'data-wp-class--is-intersecting' => 'context.isIntersecting',
		'data-wp-class--has-initialized' => 'context.hasInitialized',
		'data-wp-class--debug-mode'      => 'context.debugMode',
		'style'                          => $style_string,
	)
);

// Render inner blocks (nested content).
$inner_content = '';
if ( ! empty( $block->inner_blocks ) ) {
	foreach ( $block->inner_blocks as $index => $inner_block ) {
		// Render the block normally - parallax attributes should be added via blocks.getSaveContent.extraProps filter during save.
		$inner_content .= $inner_block->render();
	}
}

// Debug overlay (always present, but conditionally visible).
$debug_overlay = '<div class="parallax-debug-overlay" data-wp-html="callbacks.getDebugInfo" data-wp-class--hidden="!context.debugMode"></div>';

// Build final HTML structure.
printf(
	'<div %s>
		<div class="parallax-container">
			<div class="parallax-content">%s</div>
			%s
		</div>
	</div>',
	wp_kses(
		$wrapper_attributes,
		array(
			'class'                          => array(),
			'id'                             => array(),
			'style'                          => array(),
			'data-wp-interactive'            => array(),
			'data-wp-context'                => array(),
			'data-wp-init'                   => array(),
			'data-wp-watch'                  => array(),
			'data-wp-class--is-intersecting' => array(),
			'data-wp-class--has-initialized' => array(),
			'data-wp-class--debug-mode'      => array(),
		)
	),
	wp_kses_post( $inner_content ),
	wp_kses(
		$debug_overlay,
		array(
			'div' => array(
				'class'                 => array(),
				'data-wp-html'          => array(),
				'data-wp-class--hidden' => array(),
			),
		)
	)
);
