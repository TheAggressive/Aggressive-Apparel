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

/**
 * Return $value when it is in $allowed, otherwise $fallback.
 *
 * @param string   $value    Candidate value.
 * @param string[] $allowed  Allowlist.
 * @param string   $fallback Fallback when $value is not allowed.
 */
$aa_ticker_pick = static function ( string $value, array $allowed, string $fallback ): string {
	return in_array( $value, $allowed, true ) ? $value : $fallback;
};

$allowed_directions       = array( 'left', 'right' );
$allowed_label_types      = array( 'text', 'icon' );
$allowed_indicator_shapes = array( 'square', 'circle', 'diamond', 'none' );
$allowed_patterns         = array(
	'none',
	'diagonal',
	'crosshatch',
	'dots',
	'halftone',
	'noise',
	'grain',
	'scratch',
	'grunge',
	'herringbone',
	'carbon',
	'honeycomb',
	'linen',
);
$allowed_blend_modes      = array(
	'normal',
	'overlay',
	'multiply',
	'screen',
	'soft-light',
	'difference',
);
$allowed_font_weights     = array( '', '400', '500', '600', '700', '800', '900' );
$allowed_text_transforms  = array( '', 'uppercase', 'lowercase', 'capitalize' );

$speed            = max( 1, absint( $attributes['speed'] ?? 30 ) );
$ticker_direction = $aa_ticker_pick(
	(string) ( $attributes['direction'] ?? 'left' ),
	$allowed_directions,
	'left'
);
$gap              = absint( $attributes['gap'] ?? 48 );
$fade_edges       = ! empty( $attributes['fadeEdges'] );
$fade_width       = absint( $attributes['fadeWidth'] ?? 64 );
$pause_on_hover   = ! empty( $attributes['pauseOnHover'] );

// Label attributes.
$show_label      = ! empty( $attributes['showLabel'] );
$label_type      = $aa_ticker_pick(
	sanitize_key( (string) ( $attributes['labelType'] ?? 'text' ) ),
	$allowed_label_types,
	'text'
);
$label_text      = sanitize_text_field( (string) ( $attributes['labelText'] ?? 'LIVE' ) );
$label_icon      = sanitize_key( (string) ( $attributes['labelIcon'] ?? '' ) );
$label_icon_size = Icon_Block::sanitize_size( $attributes['labelIconSize'] ?? 16 );
// Color attributes may be hex, oklch(), or theme `var(--wp--preset--*)` — escape on output.
$label_bg        = (string) ( $attributes['labelBg'] ?? '' );
$label_color     = (string) ( $attributes['labelColor'] ?? '' );
$show_indicator  = ! empty( $attributes['showIndicator'] );
$indicator_shape = $aa_ticker_pick(
	sanitize_key( (string) ( $attributes['indicatorShape'] ?? 'square' ) ),
	$allowed_indicator_shapes,
	'square'
);
$indicator_color = (string) ( $attributes['indicatorColor'] ?? '' );

// Pattern attributes.
$pattern         = $aa_ticker_pick(
	sanitize_key( (string) ( $attributes['pattern'] ?? 'none' ) ),
	$allowed_patterns,
	'none'
);
$pattern_color   = (string) ( $attributes['patternColor'] ?? '' );
$pattern_blend   = $aa_ticker_pick(
	sanitize_key( (string) ( $attributes['patternBlendMode'] ?? 'normal' ) ),
	$allowed_blend_modes,
	'normal'
);
$pattern_opacity = (int) ( $attributes['patternOpacity'] ?? 100 );
$pattern_scale   = (int) ( $attributes['patternScale'] ?? 100 );
$has_pattern     = 'none' !== $pattern;

// Label typography attributes.
$label_font_size      = absint( $attributes['labelFontSize'] ?? 0 );
$label_font_weight    = $aa_ticker_pick(
	(string) ( $attributes['labelFontWeight'] ?? '' ),
	$allowed_font_weights,
	''
);
$label_letter_spacing = (float) ( $attributes['labelLetterSpacing'] ?? 0 );
$label_text_transform = $aa_ticker_pick(
	(string) ( $attributes['labelTextTransform'] ?? '' ),
	$allowed_text_transforms,
	''
);

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

$pause_label = __( 'Pause animation', 'aggressive-apparel' );
$play_label  = __( 'Play animation', 'aggressive-apparel' );

?>

<div
	<?php
	echo get_block_wrapper_attributes(
		array(
			'class'                    => implode( ' ', $classes ),
			'style'                    => $inline_style,
			'data-ticker-speed'        => (string) $speed,
			'data-ticker-direction'    => $ticker_direction,
			'data-wp-interactive'      => 'aggressive-apparel/ticker',
			'data-wp-context'          => wp_json_encode(
				array(
					'isPaused'     => false,
					'isHeld'       => false,
					'pauseOnHover' => $pause_on_hover,
					'motionLocked' => false,
					'controlLabel' => $pause_label,
					'i18n'         => array(
						'play'  => $play_label,
						'pause' => $pause_label,
					),
				)
			),
			'data-wp-init'             => 'callbacks.init',
			'data-wp-class--is-paused' => 'state.isEffectivelyPaused',
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
		type="button"
		class="ticker__pause"
		data-wp-on--click="actions.togglePause"
		data-wp-bind--aria-pressed="state.isPausedPressed"
		data-wp-bind--aria-label="context.controlLabel"
		data-wp-bind--disabled="context.motionLocked"
		aria-pressed="false"
		aria-label="<?php echo esc_attr( $pause_label ); ?>"
	>
		<span class="ticker__pause-icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" focusable="false">
				<rect class="ticker__pause-bar" x="6" y="4" width="4" height="16" />
				<rect class="ticker__pause-bar" x="14" y="4" width="4" height="16" />
				<polygon class="ticker__play-tri" points="6,4 20,12 6,20" />
			</svg>
		</span>
		<span class="screen-reader-text" data-wp-text="context.controlLabel">
			<?php echo esc_html( $pause_label ); ?>
		</span>
	</button>

</div>
