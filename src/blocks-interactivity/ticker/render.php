<?php
/**
 * Ticker block server-side rendering.
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

$speed          = absint( $attributes['speed'] ?? 30 );
$direction      = 'right' === ( $attributes['direction'] ?? 'left' ) ? 'reverse' : 'normal';
$gap            = absint( $attributes['gap'] ?? 48 );
$fade_edges     = ! empty( $attributes['fadeEdges'] );
$fade_width     = absint( $attributes['fadeWidth'] ?? 64 );
$pause_on_hover = ! empty( $attributes['pauseOnHover'] );

// Resolve the block's background color for edge fade overlays.
$fade_color = '';
if ( $fade_edges ) {
	if ( ! empty( $attributes['backgroundColor'] ) ) {
		// Preset color — reference the CSS custom property.
		$fade_color = 'var(--wp--preset--color--' . esc_attr( $attributes['backgroundColor'] ) . ')';
	} elseif ( ! empty( $attributes['style']['color']['background'] ) ) {
		// Custom color — use the literal value.
		$fade_color = esc_attr( $attributes['style']['color']['background'] );
	}
}

// Build CSS custom properties.
$inline_style = sprintf(
	'--ticker-duration:%ds;--ticker-gap:%dpx;--ticker-direction:%s;--ticker-fade-width:%dpx;',
	$speed,
	$gap,
	esc_attr( $direction ),
	$fade_width
);

if ( $fade_color ) {
	$inline_style .= '--ticker-fade-color:' . $fade_color . ';';
}

// Build classes.
$classes = array( 'wp-block-aggressive-apparel-ticker' );
if ( $fade_edges ) {
	$classes[] = 'has-fade-edges';
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                    => implode( ' ', $classes ),
		'style'                    => $inline_style,
		'data-wp-interactive'      => 'aggressive-apparel/ticker',
		'data-wp-context'          => wp_json_encode(
			array(
				'isPaused'     => false,
				'pauseOnHover' => $pause_on_hover,
			)
		),
		'data-wp-init'             => 'callbacks.init',
		'data-wp-class--is-paused' => 'context.isPaused',
		'data-wp-on--mouseenter'   => 'actions.mouseEnter',
		'data-wp-on--mouseleave'   => 'actions.mouseLeave',
		'data-wp-on--focusin'      => 'actions.focusIn',
		'data-wp-on--focusout'     => 'actions.focusOut',
		'role'                     => 'marquee',
		'aria-live'                => 'off',
	)
);
?>

<div <?php echo wp_kses_post( $wrapper_attributes ); ?>>
	<button
		class="ticker__pause"
		data-wp-on--click="actions.togglePause"
		data-wp-bind--aria-pressed="state.isPausedString"
		aria-pressed="false"
	>
		<span class="ticker__pause-icon">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
				<rect class="ticker__pause-bar" x="6" y="4" width="4" height="16" />
				<rect class="ticker__pause-bar" x="14" y="4" width="4" height="16" />
				<polygon class="ticker__play-tri" points="6,4 20,12 6,20" />
			</svg>
		</span>
		<span class="screen-reader-text">
			<?php esc_html_e( 'Pause animation', 'aggressive-apparel' ); ?>
		</span>
	</button>

	<div class="ticker__track">
		<div class="ticker__content">
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content is already escaped by the block editor
			echo $content;
			?>
		</div>
		<div class="ticker__content" aria-hidden="true" inert>
			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Duplicate for seamless loop, original is escaped by block editor
			echo $content;
			?>
		</div>
	</div>
</div>
