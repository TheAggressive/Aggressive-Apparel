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

use Aggressive_Apparel\Blocks\Icon_Block;

/**
 * Template variables.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner blocks HTML.
 * @var WP_Block $block      Block instance.
 */

$speed            = absint( $attributes['speed'] ?? 30 );
$ticker_direction = 'right' === ( $attributes['direction'] ?? 'left' ) ? 'right' : 'left';
$gap              = absint( $attributes['gap'] ?? 48 );
$fade_edges       = ! empty( $attributes['fadeEdges'] );
$fade_width       = absint( $attributes['fadeWidth'] ?? 64 );
$pause_on_hover   = ! empty( $attributes['pauseOnHover'] );

// Label attributes.
$show_label      = ! empty( $attributes['showLabel'] );
$label_type      = sanitize_key( $attributes['labelType'] ?? 'text' );
$label_text      = $attributes['labelText'] ?? 'LIVE';
$label_icon      = sanitize_key( $attributes['labelIcon'] ?? '' );
$label_icon_size = Icon_Block::sanitize_size( $attributes['labelIconSize'] ?? 16 );
$label_bg        = $attributes['labelBg'] ?? '';
$label_color     = $attributes['labelColor'] ?? '';
$show_indicator  = ! empty( $attributes['showIndicator'] );
$indicator_shape = $attributes['indicatorShape'] ?? 'square';
$indicator_color = $attributes['indicatorColor'] ?? '';

// Pattern attributes.
$pattern         = $attributes['pattern'] ?? 'none';
$pattern_color   = $attributes['patternColor'] ?? '';
$pattern_blend   = $attributes['patternBlendMode'] ?? 'normal';
$pattern_opacity = (int) ( $attributes['patternOpacity'] ?? 100 );
$pattern_scale   = (int) ( $attributes['patternScale'] ?? 100 );
$has_pattern     = 'none' !== $pattern;

// Label typography attributes.
$label_font_size      = absint( $attributes['labelFontSize'] ?? 0 );
$label_font_weight    = $attributes['labelFontWeight'] ?? '';
$label_letter_spacing = (float) ( $attributes['labelLetterSpacing'] ?? 0 );
$label_text_transform = $attributes['labelTextTransform'] ?? '';

// Build wrapper inline styles.
$inline_style = sprintf(
	'--ticker-gap:%dpx;--ticker-fade-width:%dpx;',
	$gap,
	$fade_width
);

// Pattern CSS custom properties applied directly to the .ticker__pattern element.
$pattern_style = '';
if ( $has_pattern ) {
	if ( $pattern_color ) {
		$pattern_style .= '--tp-color:' . esc_attr( $pattern_color ) . ';';
	}
	if ( 'normal' !== $pattern_blend ) {
		$pattern_style .= '--tp-blend:' . esc_attr( $pattern_blend ) . ';';
	}
	if ( 100 !== $pattern_opacity ) {
		$pattern_style .= '--tp-opacity:' . ( $pattern_opacity / 100 ) . ';';
	}
	if ( 100 !== $pattern_scale ) {
		$pattern_style .= '--tp-scale:' . ( $pattern_scale / 100 ) . ';';
	}
}

// Build label and indicator inline styles applied directly on their elements.
$label_style       = '';
$indicator_style   = '';
$label_icon_markup = '';
if ( $show_label ) {
	if ( $label_bg ) {
		$label_style .= '--tl-bg:' . esc_attr( $label_bg ) . ';';
	}
	if ( $label_color ) {
		$label_style .= 'color:' . esc_attr( $label_color ) . ';';
	}
	if ( 'text' === $label_type ) {
		if ( $label_font_size > 0 ) {
			$label_style .= 'font-size:' . $label_font_size . 'px;';
		}
		if ( $label_font_weight ) {
			$label_style .= 'font-weight:' . esc_attr( $label_font_weight ) . ';';
		}
		if ( 0.0 !== $label_letter_spacing ) {
			$label_style .= 'letter-spacing:' . $label_letter_spacing . 'em;';
		}
		if ( $label_text_transform ) {
			$label_style .= 'text-transform:' . esc_attr( $label_text_transform ) . ';';
		}
	}
	if ( 'icon' === $label_type && '' !== $label_icon ) {
		$label_icon_markup = Icon_Block::render_wrapped_svg(
			$label_icon,
			$label_icon_size,
			array(
				'class' => 'ticker__label-icon',
			)
		);
	}
	if ( $indicator_color ) {
		$indicator_style = 'color:' . esc_attr( $indicator_color ) . ';';
	}
}

// Build wrapper classes.
$classes = array( 'wp-block-aggressive-apparel-ticker' );
if ( $has_pattern ) {
	$classes[] = 'has-pattern-' . sanitize_html_class( $pattern );
}

?>

<div
	<?php
	echo get_block_wrapper_attributes(
		array(
			'class'                    => implode( ' ', $classes ),
			'style'                    => $inline_style,
			'data-ticker-speed'        => (string) max( 1, $speed ),
			'data-ticker-direction'    => $ticker_direction,
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
>

	<?php if ( $has_pattern ) : ?>
	<span class="ticker__pattern"<?php echo $pattern_style ? ' style="' . esc_attr( $pattern_style ) . '"' : ''; ?> aria-hidden="true" inert></span>
	<?php endif; ?>

	<?php if ( $show_label ) : ?>
	<div class="ticker__label"<?php echo $label_style ? ' style="' . esc_attr( $label_style ) . '"' : ''; ?>>
		<?php if ( $show_indicator && 'none' !== $indicator_shape ) : ?>
		<span
			class="ticker__indicator ticker__indicator--<?php echo esc_attr( $indicator_shape ); ?>"
			<?php echo $indicator_style ? 'style="' . esc_attr( $indicator_style ) . '"' : ''; ?>
			aria-hidden="true"
		></span>
		<?php endif; ?>
		<?php if ( '' !== $label_icon_markup ) : ?>
			<?php echo aggressive_apparel_trusted_html( $label_icon_markup ); ?>
		<?php else : ?>
			<?php echo esc_html( $label_text ); ?>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="ticker__scroll<?php echo $fade_edges ? ' has-fade-edges' : ''; ?>">
		<div class="ticker__track">
			<div class="ticker__content">
				<?php echo aggressive_apparel_trusted_html( $content ); ?>
			</div>
			<div class="ticker__content" aria-hidden="true" inert>
				<?php echo aggressive_apparel_trusted_html( $content ); ?>
			</div>
		</div>
	</div>

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

</div>
