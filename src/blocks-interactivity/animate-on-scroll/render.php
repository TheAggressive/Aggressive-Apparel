<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * The following variables are exposed to the file:
 *     $attributes (array): The block attributes.
 *     $content (string): The block default content.
 *     $block (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive_Apparel
 */

// Build animation class that combines animation type and direction.
$default_classes = array( 'wp-block-animate-on-scroll' );

// Add the base animation class.
// Map 'blur' to 'blur-in' to avoid conflict with Tailwind's .blur utility.
if ( ! empty( $attributes['animation'] ) ) {
	$animation_class   = 'blur' === $attributes['animation'] ? 'blur-in' : esc_attr( $attributes['animation'] );
	$default_classes[] = $animation_class;
}

// Add direction for animations that support it.
if ( ! empty( $attributes['direction'] ) &&
	in_array( $attributes['animation'], array( 'slide', 'flip', 'rotate', 'zoom', 'bounce' ), true ) ) {
	$default_classes[] = esc_attr( $attributes['direction'] );
}

// Join classes with spaces.
$combined_classes = implode( ' ', array_filter( $default_classes ) );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                     => $combined_classes,
		'data-wp-interactive'       => 'aggressive-apparel/animate-on-scroll',
		'data-wp-context'           => wp_json_encode(
			array(
				'isVisible'         => false,
				'debugMode'         => $attributes['debugMode'],
				'visibilityTrigger' => $attributes['threshold'],
				'detectionBoundary' => $attributes['detectionBoundary'],
				'id'                => uniqid(),
				'reAnimateOnScroll' => $attributes['reAnimateOnScroll'] ?? false,
			)
		),
		'data-wp-init'              => 'callbacks.initObserver',
		'data-wp-class--is-visible' => 'context.isVisible',
		'data-stagger-children'     => $attributes['staggerChildren'] ? 'true' : 'false',
		'data-wp-on-window--resize' => 'callbacks.handleResize',
		'aria-label'                => __( 'Animated content', 'aggressive-apparel' ),
		'aria-live'                 => 'polite',
	)
);
?>

<div
	<?php echo wp_kses_post( $wrapper_attributes ); ?>
	style="
		--wp-block-animate-on-scroll-animation-duration: <?php echo esc_attr( $attributes['duration'] ); ?>s;
		--wp-block-animate-on-scroll-stagger-delay: <?php echo esc_attr( $attributes['staggerDelay'] ); ?>s;
		--wp-block-animate-on-scroll-initial-delay: <?php echo esc_attr( $attributes['initialDelay'] ?? 0 ); ?>s;
		--wp-block-animate-on-scroll-slide-distance: <?php echo esc_attr( $attributes['slideDistance'] ?? 50 ); ?>px;
		--wp-block-animate-on-scroll-zoom-in-start: <?php echo esc_attr( $attributes['zoomInStart'] ?? 0.5 ); ?>;
		--wp-block-animate-on-scroll-zoom-out-start: <?php echo esc_attr( $attributes['zoomOutStart'] ?? 1.5 ); ?>;
		--wp-block-animate-on-scroll-rotate-angle: <?php echo esc_attr( $attributes['rotationAngle'] ?? 90 ); ?>deg;
		--wp-block-animate-on-scroll-blur-amount: <?php echo esc_attr( $attributes['blurAmount'] ?? 20 ); ?>px;
		--wp-block-animate-on-scroll-perspective: <?php echo esc_attr( $attributes['perspective'] ?? 1000 ); ?>px;
		--wp-block-animate-on-scroll-bounce-distance: <?php echo esc_attr( $attributes['bounceDistance'] ?? 30 ); ?>px;
		--wp-block-animate-on-scroll-elastic-distance: <?php echo esc_attr( $attributes['elasticDistance'] ?? 50 ); ?>px;
	"
>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is already escaped by the block editor
	echo $content;
	?>
</div>
