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

$label      = isset( $attributes['label'] ) ? sanitize_text_field( (string) $attributes['label'] ) : __( 'Dark Mode', 'aggressive-apparel' );
$show_label = isset( $attributes['showLabel'] ) ? (bool) $attributes['showLabel'] : true;
$size       = isset( $attributes['size'] ) ? sanitize_key( (string) $attributes['size'] ) : 'medium';
$alignment  = isset( $attributes['alignment'] ) ? sanitize_key( (string) $attributes['alignment'] ) : 'left';

if ( ! in_array( $size, array( 'small', 'medium', 'large' ), true ) ) {
	$size = 'medium';
}

if ( ! in_array( $alignment, array( 'left', 'center', 'right' ), true ) ) {
	$alignment = 'left';
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => sprintf(
			'is-size-%s has-text-align-%s',
			$size,
			$alignment
		),
	)
);
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes(). ?>>
	<button
		type="button"
		class="dark-mode-toggle__button"
		aria-pressed="false"
		aria-label="<?php esc_attr_e( 'Switch to dark mode', 'aggressive-apparel' ); ?>"
		data-label-light="<?php esc_attr_e( 'Switch to dark mode', 'aggressive-apparel' ); ?>"
		data-label-dark="<?php esc_attr_e( 'Switch to light mode', 'aggressive-apparel' ); ?>"
	>
		<span class="dark-mode-toggle__track" aria-hidden="true">
			<span class="dark-mode-toggle__thumb"></span>
		</span>
		<?php if ( $show_label && '' !== $label ) : ?>
			<span class="dark-mode-toggle__label"><?php echo esc_html( $label ); ?></span>
		<?php endif; ?>
	</button>
</div>
