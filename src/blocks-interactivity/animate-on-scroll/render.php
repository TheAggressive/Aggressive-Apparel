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

// Check if sequence mode is enabled.
$use_sequence = ! empty( $attributes['useSequence'] ) && ! empty( $attributes['animationSequence'] );

if ( $use_sequence ) {
	// Sequence mode: add data attribute for JavaScript to apply animations to children.
	$default_classes[] = 'has-animation-sequence';
} else {
	// Single animation mode: add the base animation class.
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

	// Add exit animation classes if re-animate is enabled.
	if ( ! empty( $attributes['reAnimateOnScroll'] ) && ! empty( $attributes['exitAnimation'] ) ) {
		$exit_animation_class = 'blur' === $attributes['exitAnimation'] ? 'blur-in' : esc_attr( $attributes['exitAnimation'] );
		$default_classes[]    = 'exit-' . $exit_animation_class;

		// Add exit direction for animations that support it.
		if ( ! empty( $attributes['exitDirection'] ) &&
			in_array( $attributes['exitAnimation'], array( 'slide', 'flip', 'rotate', 'zoom', 'bounce' ), true ) ) {
			$default_classes[] = 'exit-' . esc_attr( $attributes['exitDirection'] );
		}
	}
}

// Join classes with spaces.
$combined_classes = implode( ' ', array_filter( $default_classes ) );

	$wrapper_attributes_array = array(
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
				'useSequence'       => $attributes['useSequence'] ?? false,
				'animationSequence' => $attributes['animationSequence'] ?? array(),
			)
		),
		'data-wp-init'              => 'callbacks.initObserver',
		'data-wp-class--is-visible' => 'context.isVisible',
		'data-stagger-children'     => $attributes['staggerChildren'] ? 'true' : 'false',
		'data-wp-on-window--resize' => 'callbacks.handleResize',
		'aria-label'                => __( 'Animated content', 'aggressive-apparel' ),
		'aria-live'                 => 'polite',
	);

	// Add data attribute for animation sequence if enabled.
	if ( $use_sequence ) {
		$wrapper_attributes_array['data-animation-sequence'] = wp_json_encode( $attributes['animationSequence'] );
	}

	$wrapper_attributes = get_block_wrapper_attributes( $wrapper_attributes_array );
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
	if ( $use_sequence && ! empty( $attributes['animationSequence'] ) ) {
		// For InnerBlocks, access inner blocks directly from the $block object
		// This is more reliable than parsing the $content string.
		$inner_blocks = $block->parsed_block['innerBlocks'] ?? array();

		// Fallback: if innerBlocks is empty, try parsing the content.
		if ( empty( $inner_blocks ) ) {
			$parsed_blocks = parse_blocks( $content );
			// Filter out empty blocks and get only blocks with blockName.
			foreach ( $parsed_blocks as $parsed_block ) {
				if ( ! empty( $parsed_block['blockName'] ) ) {
					$inner_blocks[] = $parsed_block;
				}
			}
		}

		$child_index = 0;

		foreach ( $inner_blocks as $inner_block ) {
			$sequence_index = $child_index % count( $attributes['animationSequence'] );
			$sequence_item  = $attributes['animationSequence'][ $sequence_index ];

			// Map 'blur' to 'blur-in' to avoid conflict with Tailwind's .blur utility.
			$animation_type = 'blur' === $sequence_item['animation'] ? 'blur-in' : $sequence_item['animation'];

			// Build wrapper div with sequence attributes.
			$wrapper_attrs = array(
				'data-animate-sequence-type' => esc_attr( $animation_type ),
			);

			// Add direction if animation supports it.
			if ( ! empty( $sequence_item['direction'] ) &&
				in_array( $sequence_item['animation'], array( 'slide', 'flip', 'rotate', 'zoom', 'bounce' ), true ) ) {
				$wrapper_attrs['data-animate-sequence-direction'] = esc_attr( $sequence_item['direction'] );
			}

			// Build inline styles for custom CSS variables.
			$inline_styles = array();
			if ( isset( $sequence_item['slideDistance'] ) ) {
				$inline_styles[] = '--wp-block-animate-on-scroll-slide-distance: ' . esc_attr( $sequence_item['slideDistance'] ) . 'px';
			}
			if ( isset( $sequence_item['zoomInStart'] ) ) {
				$inline_styles[] = '--wp-block-animate-on-scroll-zoom-in-start: ' . esc_attr( $sequence_item['zoomInStart'] );
			}
			if ( isset( $sequence_item['zoomOutStart'] ) ) {
				$inline_styles[] = '--wp-block-animate-on-scroll-zoom-out-start: ' . esc_attr( $sequence_item['zoomOutStart'] );
			}
			if ( isset( $sequence_item['rotationAngle'] ) ) {
				$inline_styles[] = '--wp-block-animate-on-scroll-rotate-angle: ' . esc_attr( $sequence_item['rotationAngle'] ) . 'deg';
			}
			if ( isset( $sequence_item['blurAmount'] ) ) {
				$inline_styles[] = '--wp-block-animate-on-scroll-blur-amount: ' . esc_attr( $sequence_item['blurAmount'] ) . 'px';
			}
			if ( isset( $sequence_item['perspective'] ) ) {
				$inline_styles[] = '--wp-block-animate-on-scroll-perspective: ' . esc_attr( $sequence_item['perspective'] ) . 'px';
			}
			if ( isset( $sequence_item['bounceDistance'] ) ) {
				$inline_styles[] = '--wp-block-animate-on-scroll-bounce-distance: ' . esc_attr( $sequence_item['bounceDistance'] ) . 'px';
			}
			if ( isset( $sequence_item['elasticDistance'] ) ) {
				$inline_styles[] = '--wp-block-animate-on-scroll-elastic-distance: ' . esc_attr( $sequence_item['elasticDistance'] ) . 'px';
			}

			// Build attributes string.
			$attrs_string = '';
			foreach ( $wrapper_attrs as $key => $value ) {
				$attrs_string .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
			}
			if ( ! empty( $inline_styles ) ) {
				$attrs_string .= ' style="' . esc_attr( implode( '; ', $inline_styles ) ) . '"';
			}

			// Output wrapped block.
			echo '<div' . $attrs_string . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped above
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is already escaped by render_block
			echo render_block( $inner_block );
			echo '</div>';

			++$child_index;
		}
	} else {
		// Normal mode: output content as-is.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is already escaped by the block editor
		echo $content;
	}
	?>
</div>
