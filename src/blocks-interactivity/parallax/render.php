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

	defined( 'ABSPATH' ) || exit;

// The render callback receives $attributes, $content, and $block parameters
// Extract attributes with defaults (matching animate-on-scroll).
$intensity          = $attributes['intensity'] ?? 50;
$visibility_trigger = $attributes['visibilityTrigger'] ?? 0.3;
$detection_boundary = $attributes['detectionBoundary'] ?? array(
	'top'    => '0%',
	'right'  => '0%',
	'bottom' => '0%',
	'left'   => '0%',
);

// Visibility trigger for intersection detection.
$enable_mouse_interaction = $attributes['enableMouseInteraction'] ?? false;
$debug_mode               = $attributes['debugMode'] ?? false;

// New attributes.
$parallax_direction         = $attributes['parallaxDirection'] ?? 'down';
$mouse_influence_multiplier = $attributes['mouseInfluenceMultiplier'] ?? 0.5;
$max_mouse_translation      = $attributes['maxMouseTranslation'] ?? 20;
$depth_intensity_multiplier = $attributes['depthIntensityMultiplier'] ?? 50;
$transition_duration        = $attributes['transitionDuration'] ?? 0.1;
$perspective_distance       = $attributes['perspectiveDistance'] ?? 1000;
$max_mouse_rotation         = $attributes['maxMouseRotation'] ?? 5;
$depth_of_field             = $attributes['depthOfField'] ?? false;

// Build context for Interactivity API.
try {
	$context = array(
		// Core parallax settings (matching animate-on-scroll).
		'intensity'                => $intensity,
		'visibilityTrigger'        => $visibility_trigger,
		'detectionBoundary'        => $detection_boundary,
		'enableMouseInteraction'   => $enable_mouse_interaction,
		'debugMode'                => $debug_mode,
		'parallaxDirection'        => $parallax_direction,
		'mouseInfluenceMultiplier' => $mouse_influence_multiplier,
		'maxMouseTranslation'      => $max_mouse_translation,
		'depthIntensityMultiplier' => $depth_intensity_multiplier,
		'transitionDuration'       => $transition_duration,
		'perspectiveDistance'      => $perspective_distance,
		'maxMouseRotation'         => $max_mouse_rotation,
		'depthOfField'             => $depth_of_field,

		// Runtime state for Interactivity API.
		'isIntersecting'           => false,
		'intersectionRatio'        => 0,
		'hasInitialized'           => false,
		'previousProgress'         => 0,
	);
} catch ( Throwable $e ) {
	// Return a minimal context to prevent complete failure.
	$context = array(
		'intensity'              => 50,
		'visibilityTrigger'      => 0.3,
		'detectionBoundary'      => array(
			'top'    => '0%',
			'right'  => '0%',
			'bottom' => '0%',
			'left'   => '0%',
		),
		'enableMouseInteraction' => false,
		'debugMode'              => false,
		'isIntersecting'         => false,
		'intersectionRatio'      => 0,
		'hasInitialized'         => false,
		'previousProgress'       => 0,
	);
}

// Generate a unique ID for this parallax block instance.
$parallax_instance_id  = 'parallax_' . uniqid();
$context['instanceId'] = $parallax_instance_id;

// Build CSS custom properties.
$css_vars = array(
	'--parallax-perspective' => $perspective_distance . 'px',
);

$style_string = '';
foreach ( $css_vars as $property => $value ) {
	$style_string .= sprintf( '%s: %s; ', $property, $value );
}

// Build wrapper classes.
$classes = array(
	'wp-block-aggressive-apparel-parallax',
	'aggressive-apparel-parallax',
	'aggressive-apparel-parallax--direction-' . $parallax_direction,
);

if ( $enable_mouse_interaction ) {
	$classes[] = 'aggressive-apparel-parallax--mouse-interaction';
}

if ( $debug_mode ) {
	$classes[] = 'aggressive-apparel-parallax--debug';
}

	// Get wrapper attributes with Interactivity API integration.
	// Intersecting/initialized classes are toggled imperatively by the
	// shared frame engine, so no reactive class bindings are needed here.
	$wrapper_attributes = get_block_wrapper_attributes(
		array(
			'class'               => implode( ' ', $classes ),
			'data-wp-interactive' => 'aggressive-apparel/parallax',
			'data-wp-context'     => wp_json_encode( $context ),
			'data-wp-init'        => 'callbacks.initParallax',
			'style'               => $style_string,
		)
	);

	// Render inner blocks (nested content).
	// In dynamic blocks, inner blocks are already rendered into $content.
	$inner_content = $content;

	// Build final HTML structure.
	printf(
		'<div %s data-instance-id="%s">
			<div class="aggressive-apparel-parallax__container">
				<div class="aggressive-apparel-parallax__visual-layer"></div>
				<div class="aggressive-apparel-parallax__content-layer">
					<div class="aggressive-apparel-parallax__content">%s</div>
				</div>
			</div>
		</div>',
		wp_kses(
			$wrapper_attributes,
			array(
				'class'               => array(),
				'id'                  => array(),
				'style'               => array(),
				'data-wp-interactive' => array(),
				'data-wp-context'     => array(),
				'data-wp-init'        => array(),
			)
		),
		esc_attr( $parallax_instance_id ),
		wp_kses_post( $inner_content )
	);
