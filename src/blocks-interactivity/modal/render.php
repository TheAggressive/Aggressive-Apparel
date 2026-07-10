<?php
/**
 * PHP file to use when rendering the block type on the server to show on the front end.
 *
 * The following variables are exposed to this file:
 *     $attributes (array): The block attributes.
 *     $content    (string): The block default content.
 *     $block      (WP_Block): The block instance.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive_Apparel
 */

defined( 'ABSPATH' ) || exit;

/**
 * WordPress-injected block attributes.
 *
 * @var array $attributes
 */

/**
 * WordPress-injected inner-blocks HTML.
 *
 * @var string $content
 */

$unique_id = ! empty( $attributes['modalId'] )
	? sanitize_html_class( $attributes['modalId'] )
	: 'modal-' . wp_unique_id();

$position                = sanitize_html_class( $attributes['position'] ?? 'center' );
$open_on_load            = ! empty( $attributes['openOnLoad'] );
$open_on_load_once       = ! empty( $attributes['openOnLoadOnce'] );
$disable_overlay         = ! empty( $attributes['disableOverlay'] );
$trigger_block_id        = $attributes['triggerBlockId'] ?? '';
$trigger_label           = $attributes['triggerLabel'] ?? __( 'Open Modal', 'aggressive-apparel' );
$enter_animation         = sanitize_html_class( $attributes['enterAnimation'] ?? 'fade' );
$exit_animation          = sanitize_html_class( $attributes['exitAnimation'] ?? 'fade' );
$animation_duration      = absint( $attributes['animationDuration'] ?? 300 );
$exit_intent_trigger     = ! empty( $attributes['exitIntentTrigger'] );
$exit_intent_reshow_days = absint( $attributes['exitIntentReshowDays'] ?? 7 );
$scroll_depth_trigger    = ! empty( $attributes['scrollDepthTrigger'] );
$scroll_depth_percent    = absint( $attributes['scrollDepthPercent'] ?? 50 );
$dialog_max_width        = sanitize_text_field( $attributes['dialogMaxWidth'] ?? '' );

// ── Close button attributes ───────────────────────────────────────────────────

$close_placement      = sanitize_html_class( $attributes['closeButtonPlacement'] ?? 'inside-top-right' );
$close_icon           = sanitize_text_field( $attributes['closeButtonIcon'] ?? 'close' );
$close_size           = sanitize_html_class( $attributes['closeButtonSize'] ?? 'md' );
$close_variant        = sanitize_html_class( $attributes['closeButtonVariant'] ?? 'ghost' );
$close_label          = sanitize_text_field( $attributes['closeButtonLabel'] ?? '' );
$close_color          = sanitize_text_field( $attributes['closeButtonColor'] ?? '' );
$close_bg_color       = sanitize_text_field( $attributes['closeButtonBgColor'] ?? '' );
$close_hover_color    = sanitize_text_field( $attributes['closeButtonHoverColor'] ?? '' );
$close_hover_bg_color = sanitize_text_field( $attributes['closeButtonHoverBgColor'] ?? '' );

$show_close_btn = 'none' !== $close_placement;
$is_outside     = str_starts_with( $close_placement, 'outside-' );

// ── Trigger button attributes ─────────────────────────────────────────────────

$trigger_variant       = sanitize_html_class( $attributes['triggerVariant'] ?? 'outlined' );
$trigger_size          = sanitize_html_class( $attributes['triggerSize'] ?? 'md' );
$trigger_full_width    = ! empty( $attributes['triggerFullWidth'] );
$trigger_border_radius = sanitize_text_field( $attributes['triggerBorderRadius'] ?? '' );
$trigger_bg_color      = sanitize_text_field( $attributes['triggerBgColor'] ?? '' );
$trigger_text_color    = sanitize_text_field( $attributes['triggerTextColor'] ?? '' );
$trigger_hover_bg      = sanitize_text_field( $attributes['triggerHoverBgColor'] ?? '' );
$trigger_hover_text    = sanitize_text_field( $attributes['triggerHoverTextColor'] ?? '' );

// ── Dialog design attributes ──────────────────────────────────────────────────

$dialog_padding       = sanitize_text_field( $attributes['dialogPadding'] ?? '' );
$dialog_border_radius = sanitize_text_field( $attributes['dialogBorderRadius'] ?? '' );
$overlay_opacity      = absint( $attributes['overlayOpacity'] ?? 50 );
$overlay_blur         = absint( $attributes['overlayBlur'] ?? 4 );
$overlay_color        = sanitize_text_field( $attributes['overlayColor'] ?? '' );

// ── Forward WP block supports (color.background, color.text, border) to dialog.
// get_block_wrapper_attributes() applies these to the wrapper; we also need
// them on the fixed-position dialog div so they actually render visually.

$style_attr   = $attributes['style'] ?? array();
$color_style  = $style_attr['color'] ?? array();
$border_style = $style_attr['border'] ?? array();

/**
 * Normalize the scalar or per-corner shape emitted by WordPress border support
 * into a valid CSS border-radius value.
 *
 * @param mixed $radius Raw block-support radius value.
 * @return string Normalized CSS value, or an empty string when invalid.
 */
$normalize_border_radius = static function ( $radius ): string {
	if ( is_string( $radius ) || is_int( $radius ) || is_float( $radius ) ) {
		return sanitize_text_field( (string) $radius );
	}

	if ( ! is_array( $radius ) ) {
		return '';
	}

	$corner_keys = array( 'topLeft', 'topRight', 'bottomRight', 'bottomLeft' );
	$values      = array();
	$has_value   = false;

	foreach ( $corner_keys as $index => $corner_key ) {
		if ( array_key_exists( $corner_key, $radius ) ) {
			$value = $radius[ $corner_key ];
		} elseif ( array_key_exists( $index, $radius ) ) {
			$value = $radius[ $index ];
		} else {
			$value = '';
		}

		if ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) {
			$value = sanitize_text_field( (string) $value );
		} else {
			$value = '';
		}

		if ( '' !== $value ) {
			$has_value = true;
		}

		$values[] = '' !== $value ? $value : '0';
	}

	if ( ! $has_value ) {
		return '';
	}

	return 1 === count( array_unique( $values ) )
		? $values[0]
		: implode( ' ', $values );
};

// Build close button HTML when needed.
$close_btn_html = '';
if ( $show_close_btn ) {
	// Inline CSS custom properties for colors.
	$css_vars = array();
	if ( $close_color ) {
		$css_vars[] = '--aa-close-btn-color: ' . esc_attr( $close_color );
	}
	if ( $close_bg_color ) {
		$css_vars[] = '--aa-close-btn-bg: ' . esc_attr( $close_bg_color );
	}
	if ( $close_hover_color ) {
		$css_vars[] = '--aa-close-btn-hover-color: ' . esc_attr( $close_hover_color );
	}
	if ( $close_hover_bg_color ) {
		$css_vars[] = '--aa-close-btn-hover-bg: ' . esc_attr( $close_hover_bg_color );
	}

	$btn_style = $css_vars ? ' style="' . implode( '; ', $css_vars ) . '"' : '';

	$btn_classes = implode(
		' ',
		array(
			'wp-block-aggressive-apparel-modal__close',
			'close-size-' . $close_size,
			'close-variant-' . $close_variant,
			'close-placement-' . $close_placement,
		)
	);

	// Icon SVG — all options map to theme icon slugs.
	$icon_sizes = array(
		'sm' => 16,
		'md' => 20,
		'lg' => 24,
	);
	$icon_px    = $icon_sizes[ $close_size ] ?? 20;
	$icon_slugs = array(
		'close'        => 'close',
		'arrow-left'   => 'arrow-left',
		'chevron-down' => 'chevron-down',
	);
	$icon_svg   = '';
	if ( 'text-only' !== $close_icon ) {
		$slug     = $icon_slugs[ $close_icon ] ?? 'close';
		$icon_svg = aggressive_apparel_get_icon(
			$slug,
			array(
				'width'       => $icon_px,
				'height'      => $icon_px,
				'aria-hidden' => 'true',
			)
		);
	}

	// Visible label span (optional).
	$label_html = $close_label
		? '<span class="wp-block-aggressive-apparel-modal__close-label">' . esc_html( $close_label ) . '</span>'
		: '';

	// aria-label: use the custom label text if provided, otherwise default.
	$aria_label = $close_label
		? esc_attr( $close_label )
		: esc_attr__( 'Close modal', 'aggressive-apparel' );

	$close_btn_html = sprintf(
		'<button class="%s" type="button" data-wp-on--click="actions.closeModal" aria-label="%s"%s>%s%s</button>',
		esc_attr( $btn_classes ),
		$aria_label,
		aggressive_apparel_trusted_html( $btn_style ),
		$icon_svg,
		aggressive_apparel_trusted_html( $label_html )
	);
}

// ── Trigger button inline style + classes ─────────────────────────────────────

$trigger_css_vars = array();
if ( $trigger_bg_color ) {
	$trigger_css_vars[] = '--aa-trigger-bg: ' . esc_attr( $trigger_bg_color );
}
if ( $trigger_text_color ) {
	$trigger_css_vars[] = '--aa-trigger-text: ' . esc_attr( $trigger_text_color );
}
if ( $trigger_hover_bg ) {
	$trigger_css_vars[] = '--aa-trigger-hover-bg: ' . esc_attr( $trigger_hover_bg );
}
if ( $trigger_hover_text ) {
	$trigger_css_vars[] = '--aa-trigger-hover-text: ' . esc_attr( $trigger_hover_text );
}
if ( $trigger_border_radius ) {
	$trigger_css_vars[] = '--aa-trigger-radius: ' . esc_attr( $trigger_border_radius );
}

$trigger_style   = $trigger_css_vars ? ' style="' . implode( '; ', $trigger_css_vars ) . '"' : '';
$trigger_classes = implode(
	' ',
	array_filter(
		array(
			'wp-block-aggressive-apparel-modal__trigger',
			'trigger-variant-' . $trigger_variant,
			'trigger-size-' . $trigger_size,
			$trigger_full_width ? 'trigger-full-width' : '',
		)
	)
);

// ── Dialog inline style ───────────────────────────────────────────────────────
// Combines: animation duration, max-width, and forwarded WP block-support values.

$dialog_css_vars = array(
	'--aa-modal-duration: ' . esc_attr( (string) $animation_duration ) . 'ms',
);

if ( $dialog_max_width ) {
	$dialog_css_vars[] = '--aa-dialog-max-width: ' . esc_attr( $dialog_max_width );
}
if ( $dialog_padding ) {
	$dialog_css_vars[] = '--aa-dialog-padding: ' . esc_attr( $dialog_padding );
}
if ( $dialog_border_radius ) {
	$dialog_css_vars[] = '--aa-dialog-radius: ' . esc_attr( $dialog_border_radius );
}

// Forward color.background from WP block supports.
if ( ! empty( $color_style['background'] ) ) {
	$dialog_css_vars[] = '--aa-dialog-bg: ' . esc_attr( $color_style['background'] );
}

// Forward color.text from WP block supports.
if ( ! empty( $color_style['text'] ) ) {
	$dialog_css_vars[] = '--aa-dialog-text: ' . esc_attr( $color_style['text'] );
}

// Forward __experimentalBorder from WP block supports.
if ( ! empty( $border_style['color'] ) ) {
	$dialog_css_vars[] = '--aa-dialog-border-color: ' . esc_attr( $border_style['color'] );
}
if ( ! empty( $border_style['style'] ) ) {
	$dialog_css_vars[] = '--aa-dialog-border-style: ' . esc_attr( $border_style['style'] );
}
if ( ! empty( $border_style['width'] ) ) {
	$dialog_css_vars[] = '--aa-dialog-border-width: ' . esc_attr( $border_style['width'] );
}
$block_border_radius = $normalize_border_radius( $border_style['radius'] ?? '' );
if ( ! $dialog_border_radius && '' !== $block_border_radius ) {
	$dialog_css_vars[] = '--aa-dialog-border-radius: ' . esc_attr( $block_border_radius );
}

$dialog_inline_style = implode( '; ', $dialog_css_vars );

// ── Backdrop/shell inline style for overlay vars ──────────────────────────────

$backdrop_css_vars = array();
// Only emit opacity var when it differs from the default (50).
if ( 50 !== $overlay_opacity ) {
	$backdrop_css_vars[] = '--aa-overlay-opacity: ' . esc_attr( (string) $overlay_opacity ) . '%';
}
// Only emit blur var when it differs from the default (4).
if ( 4 !== $overlay_blur ) {
	$backdrop_css_vars[] = '--aa-overlay-blur: ' . esc_attr( (string) $overlay_blur ) . 'px';
}
// Override the scrim color when the editor sets one.
if ( $overlay_color ) {
	$backdrop_css_vars[] = '--aa-color-scrim: ' . esc_attr( $overlay_color );
}
$backdrop_style = $backdrop_css_vars ? ' style="' . implode( '; ', $backdrop_css_vars ) . '"' : '';

// ── Miscellaneous ─────────────────────────────────────────────────────────────

// Drawer positions exit off-screen via their position transform — JS skips exit animation for them.
$drawer_positions = array( 'bottom', 'top', 'left', 'right' );
$is_drawer        = in_array( $position, $drawer_positions, true );

// Register per-modal state.
wp_interactivity_state(
	'aggressive-apparel/modal',
	array(
		'modals' => array(
			$unique_id => array(
				'isOpen'               => false,
				'openOnLoad'           => $open_on_load,
				'openOnLoadOnce'       => $open_on_load_once,
				'animationDuration'    => $animation_duration,
				'exitIntentTrigger'    => $exit_intent_trigger,
				'exitIntentReshowDays' => $exit_intent_reshow_days,
				'scrollDepthTrigger'   => $scroll_depth_trigger,
				'scrollDepthPercent'   => $scroll_depth_percent,
			),
		),
	)
);

?>

<div
	<?php
	echo get_block_wrapper_attributes(
		array(
			'data-wp-interactive' => 'aggressive-apparel/modal',
			'data-wp-context'     => (string) wp_json_encode( array( 'id' => $unique_id ) ),
			'data-wp-init'        => 'actions.init',
		)
	);
	?>
>

	<?php if ( empty( $trigger_block_id ) && ! $exit_intent_trigger && ! $scroll_depth_trigger ) : ?>
	<button
		class="<?php echo esc_attr( $trigger_classes ); ?>"
		type="button"
		data-wp-on--click="actions.openModal"
		aria-controls="<?php echo esc_attr( $unique_id ); ?>"
		aria-haspopup="dialog"
		data-wp-bind--aria-expanded="state.modals[context.id].isOpen"
		<?php echo aggressive_apparel_trusted_html( $trigger_style ); ?>
	>
		<?php echo esc_html( $trigger_label ); ?>
	</button>
	<?php endif; ?>

	<div
		class="wp-block-aggressive-apparel-modal__announcer"
		data-modal-id="<?php echo esc_attr( $unique_id ); ?>"
		data-label="<?php echo esc_attr( $trigger_label ); ?>"
		aria-live="polite"
		aria-atomic="true"
	></div>

	<div
		class="aggressive-apparel-overlay wp-block-aggressive-apparel-modal__shell"
		data-modal-id="<?php echo esc_attr( $unique_id ); ?>"
		hidden
		<?php echo aggressive_apparel_trusted_html( $backdrop_style ); ?>
	>
		<?php if ( ! $disable_overlay ) : ?>
		<div
			class="aggressive-apparel-overlay__backdrop wp-block-aggressive-apparel-modal__backdrop"
			data-wp-on--click="actions.closeModal"
			aria-hidden="true"
		></div>
		<?php endif; ?>

		<div
			id="<?php echo esc_attr( $unique_id ); ?>"
			class="wp-block-aggressive-apparel-modal__dialog modal-position-<?php echo esc_attr( $position ); ?> modal-enter-<?php echo esc_attr( $enter_animation ); ?>"
			role="dialog"
			aria-modal="true"
			aria-labelledby="<?php echo esc_attr( $unique_id ); ?>-label"
			aria-label="<?php echo esc_attr( $trigger_label ); ?>"
			tabindex="-1"
			data-wp-on--keydown="actions.handleKeydown"
			data-exit-animation="<?php echo esc_attr( $is_drawer ? 'none' : $exit_animation ); ?>"
			style="<?php echo esc_attr( $dialog_inline_style ); ?>"
		>
			<?php if ( $show_close_btn && ! $is_outside ) : ?>
				<?php echo aggressive_apparel_trusted_html( $close_btn_html ); ?>
			<?php endif; ?>

			<div class="wp-block-aggressive-apparel-modal__dialog-body">
				<?php echo aggressive_apparel_trusted_html( $content ); ?>
			</div>
		</div>

		<?php if ( $show_close_btn && $is_outside ) : ?>
			<?php echo aggressive_apparel_trusted_html( $close_btn_html ); ?>
		<?php endif; ?>
	</div>

</div>
