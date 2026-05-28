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
$disable_overlay         = ! empty( $attributes['disableOverlay'] );
$trigger_block_id        = $attributes['triggerBlockId'] ?? '';
$trigger_label           = $attributes['triggerLabel'] ?? __( 'Open Modal', 'aggressive-apparel' );
$enter_animation         = sanitize_html_class( $attributes['enterAnimation'] ?? 'fade' );
$animation_duration      = absint( $attributes['animationDuration'] ?? 300 );
$exit_intent_trigger     = ! empty( $attributes['exitIntentTrigger'] );
$exit_intent_reshow_days = absint( $attributes['exitIntentReshowDays'] ?? 7 );

// Register per-modal state. Multiple renders on the same page merge correctly via array_replace_recursive.
wp_interactivity_state(
	'aggressive-apparel/modal',
	array(
		'modals' => array(
			$unique_id => array(
				'isOpen'               => false,
				'openOnLoad'           => $open_on_load,
				'animationDuration'    => $animation_duration,
				'exitIntentTrigger'    => $exit_intent_trigger,
				'exitIntentReshowDays' => $exit_intent_reshow_days,
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

	<?php if ( empty( $trigger_block_id ) && ! $exit_intent_trigger ) : ?>
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
			style="--aa-modal-duration: <?php echo esc_attr( (string) $animation_duration ); ?>ms;"
		>
			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>

</div>
