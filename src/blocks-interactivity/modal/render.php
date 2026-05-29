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
		$icon_svg = \Aggressive_Apparel\Core\Icons::get(
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

	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
	$close_btn_html = sprintf(
		'<button class="%s" type="button" data-wp-on--click="actions.closeModal" aria-label="%s"%s>%s%s</button>',
		esc_attr( $btn_classes ),
		$aria_label,
		$btn_style,
		$icon_svg,   // Safe: generated by Icons::get() which escapes all attributes.
		$label_html  // Safe: esc_html() applied above.
	);
	// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
}

// Dialog inline style (duration + optional max-width CSS var).
$dialog_inline_style = '--aa-modal-duration: ' . esc_attr( (string) $animation_duration ) . 'ms;';
if ( $dialog_max_width ) {
	$dialog_inline_style .= ' --aa-dialog-max-width: ' . esc_attr( $dialog_max_width ) . ';';
}

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

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'data-wp-interactive' => 'aggressive-apparel/modal',
		'data-wp-context'     => wp_json_encode( array( 'id' => $unique_id ) ),
		'data-wp-init'        => 'actions.init',
	)
);
?>

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php if ( empty( $trigger_block_id ) && ! $exit_intent_trigger && ! $scroll_depth_trigger ) : ?>
	<button
		class="wp-block-aggressive-apparel-modal__trigger"
		type="button"
		data-wp-on--click="actions.openModal"
		aria-controls="<?php echo esc_attr( $unique_id ); ?>"
		aria-haspopup="dialog"
		data-wp-bind--aria-expanded="state.modals[context.id].isOpen"
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
		class="wp-block-aggressive-apparel-modal__shell"
		data-modal-id="<?php echo esc_attr( $unique_id ); ?>"
		hidden
	>
		<?php if ( ! $disable_overlay ) : ?>
		<div
			class="wp-block-aggressive-apparel-modal__backdrop"
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
				<?php echo $close_btn_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>

			<div class="wp-block-aggressive-apparel-modal__dialog-body">
				<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>

		<?php if ( $show_close_btn && $is_outside ) : ?>
			<?php echo $close_btn_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php endif; ?>
	</div>

</div>
