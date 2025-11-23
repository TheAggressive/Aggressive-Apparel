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
if ( ! empty( $attributes['animation'] ) ) {
	$default_classes[] = esc_attr( $attributes['animation'] );
}

// Add direction for animations that support it.
if ( ! empty( $attributes['direction'] ) &&
	in_array( $attributes['animation'], array( 'slide', 'flip', 'rotate', 'zoom' ), true ) ) {
	$default_classes[] = esc_attr( $attributes['direction'] );
}

// Join classes with spaces.
$combined_classes = implode( ' ', array_filter( $default_classes ) );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                           => $combined_classes,
		'data-wp-interactive'             => 'aggressive-apparel/animate-on-scroll',
		'data-wp-context'                 => wp_json_encode(
			array(
				'isVisible'         => false,
				'debugMode'         => $attributes['debugMode'],
				'visibilityTrigger' => $attributes['threshold'],
				'detectionBoundary' => $attributes['detectionBoundary'],
				'id'                => uniqid(),

			)
		),
		'data-wp-init'                    => 'callbacks.initObserver',
		'data-wp-class--is-visible'       => 'context.isVisible',
		'data-stagger-children'           => $attributes['staggerChildren'] ? 'true' : 'false',
		'data-wp-on-async-window--resize' => 'callbacks.handleResize',
	)
);
?>

<div
	<?php echo wp_kses_post( $wrapper_attributes ); ?>
	style="
		--wp-block-animate-on-scroll-animation-duration: <?php echo esc_attr( $attributes['duration'] ); ?>s;
		--wp-block-animate-on-scroll-stagger-delay: <?php echo esc_attr( $attributes['staggerDelay'] ); ?>s;
	"
>
	<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is already escaped by the block editor
	echo $content;
	?>
</div>
