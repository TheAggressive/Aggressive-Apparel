<?php
/**
 * Server-side rendering for Dark Mode Toggle Block
 *
 * @package Aggressive_Apparel
 */

/**
 * Render the dark mode toggle block.
 *
 * @param array $attributes Block attributes.
 * @return string Rendered HTML.
 */
if ( ! function_exists( 'render_dark_mode_toggle' ) ) {

	function render_dark_mode_toggle( $attributes ) {

		$label      = $attributes['label'] ?? __( 'Dark Mode', 'aggressive-apparel' );
		$show_label = $attributes['showLabel'] ?? true;
		$size       = $attributes['size'] ?? 'medium';
		$alignment  = $attributes['alignment'] ?? 'left';

		// Ensure wrapper attributes exist.
		$wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
			? get_block_wrapper_attributes(
				array(
					'class' => 'wp-block-aggressive-apparel-dark-mode-toggle has-alignment-' . $alignment,
				)
			)
			: 'class="wp-block-aggressive-apparel-dark-mode-toggle has-alignment-' . esc_attr( $alignment ) . '"';

		$button_classes = 'dark-mode-toggle__button dark-mode-toggle__button--' . $size;

		ob_start();
		?>
		<div <?php echo wp_kses_data( $wrapper_attributes ); ?>>
			<div
				class="dark-mode-toggle"
				data-label="<?php echo esc_attr( $label ); ?>"
				data-show-label="<?php echo esc_attr( $show_label ? 'true' : 'false' ); ?>"
				data-size="<?php echo esc_attr( $size ); ?>"
			>
				<?php if ( $show_label ) : ?>
					<label for="dark-mode-toggle-button" class="dark-mode-toggle__label">
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endif; ?>

				<button
					id="dark-mode-toggle-button"
					class="<?php echo esc_attr( $button_classes ); ?>"
					aria-label="<?php echo esc_attr__( 'Toggle dark mode', 'aggressive-apparel' ); ?>"
					type="button"
				>
					<span class="dark-mode-toggle__switch">
						<span class="dark-mode-toggle__icon dark-mode-toggle__icon--sun">☀️</span>
						<span class="dark-mode-toggle__icon dark-mode-toggle__icon--moon">🌙</span>
					</span>
				</button>

			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
