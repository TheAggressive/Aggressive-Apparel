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

// Extract attributes with defaults (matching animate-on-scroll).
$intensity          = $attributes['intensity'] ?? 50;
$visibility_trigger = $attributes['visibilityTrigger'] ?? 0.3;
$detection_boundary = $attributes['detectionBoundary'] ?? array(
	'top'    => '0%',
	'right'  => '0%',
	'bottom' => '0%',
	'left'   => '0%',
);
$activation_buffer  = $attributes['activationBuffer'] ?? 20;

$enable_mouse_interaction = $attributes['enableMouseInteraction'] ?? false;
$disable_on_mobile        = ! empty( $attributes['disableOnMobile'] );

// Debug Mode is a saved attribute: gate it per-request so visitors
// without editing capabilities never see overlays or download the
// debug script chunk, even on a page saved with it enabled.
$debug_mode = ( $attributes['debugMode'] ?? false )
	&& aggressive_apparel_can_view_block_debug();

$parallax_direction         = $attributes['parallaxDirection'] ?? 'down';
$mouse_influence_multiplier = $attributes['mouseInfluenceMultiplier'] ?? 0.5;
$max_mouse_translation      = $attributes['maxMouseTranslation'] ?? 20;
$depth_intensity_multiplier = $attributes['depthIntensityMultiplier'] ?? 50;
$transition_duration        = $attributes['transitionDuration'] ?? 0.1;
$perspective_distance       = $attributes['perspectiveDistance'] ?? 1000;
$max_mouse_rotation         = $attributes['maxMouseRotation'] ?? 5;
$depth_of_field             = $attributes['depthOfField'] ?? false;

$context = array(
	'intensity'                => $intensity,
	'visibilityTrigger'        => $visibility_trigger,
	'detectionBoundary'        => $detection_boundary,
	'activationBuffer'         => $activation_buffer,
	'enableMouseInteraction'   => $enable_mouse_interaction,
	'disableOnMobile'          => $disable_on_mobile,
	'debugMode'                => $debug_mode,
	'parallaxDirection'        => $parallax_direction,
	'mouseInfluenceMultiplier' => $mouse_influence_multiplier,
	'maxMouseTranslation'      => $max_mouse_translation,
	'depthIntensityMultiplier' => $depth_intensity_multiplier,
	'transitionDuration'       => $transition_duration,
	'perspectiveDistance'      => $perspective_distance,
	'maxMouseRotation'         => $max_mouse_rotation,
	'depthOfField'             => $depth_of_field,
	'isIntersecting'           => false,
	'intersectionRatio'        => 0,
	'hasInitialized'           => false,
	'previousProgress'         => 0,
);

// Generate a unique ID for this parallax block instance.
$parallax_instance_id  = 'parallax_' . uniqid();
$context['instanceId'] = $parallax_instance_id;

$style_string = sprintf(
	'--parallax-perspective: %spx;',
	esc_attr( (string) $perspective_distance )
);

$classes = array(
	'wp-block-aggressive-apparel-parallax',
	'aggressive-apparel-parallax',
	'aggressive-apparel-parallax--direction-' . $parallax_direction,
);

if ( $enable_mouse_interaction ) {
	$classes[] = 'aggressive-apparel-parallax--mouse-interaction';
}

if ( $disable_on_mobile ) {
	$classes[] = 'aggressive-apparel-parallax--disable-on-mobile';
}

if ( $debug_mode ) {
	$classes[] = 'aggressive-apparel-parallax--debug';

	// Debug-only stylesheet + translated strings blob; kept out of the
	// block's own assets so production visitors never download them.
	aggressive_apparel_enqueue_block_debug_assets();
}

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

// Markup matches the editor canvas (container → content).
printf(
	'<div %s data-instance-id="%s">
		<div class="aggressive-apparel-parallax__container">
			<div class="aggressive-apparel-parallax__content">%s</div>
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
	wp_kses_post( $content )
);
