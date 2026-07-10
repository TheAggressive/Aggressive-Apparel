<?php
/**
 * Dark Mode Toggle — frontend render.
 *
 * Modern icon button with sun/moon marks. Optional text label that can flip
 * between light-mode and dark-mode copy.
 *
 * @package Aggressive_Apparel
 */

$label      = isset( $attributes['label'] ) ? sanitize_text_field( (string) $attributes['label'] ) : '';
$label_dark = isset( $attributes['labelDark'] ) ? sanitize_text_field( (string) $attributes['labelDark'] ) : '';
$show_label = isset( $attributes['showLabel'] ) ? (bool) $attributes['showLabel'] : false;
$size       = isset( $attributes['size'] ) ? sanitize_key( (string) $attributes['size'] ) : 'medium';
$alignment  = isset( $attributes['alignment'] ) ? sanitize_key( (string) $attributes['alignment'] ) : 'left';

if ( ! in_array( $size, array( 'small', 'medium', 'large' ), true ) ) {
	$size = 'medium';
}

if ( ! in_array( $alignment, array( 'left', 'center', 'right' ), true ) ) {
	$alignment = 'left';
}

// Default visible labels when showLabel is on but fields are empty.
if ( $show_label ) {
	if ( '' === $label ) {
		$label = __( 'Dark Mode', 'aggressive-apparel' );
	}

	if ( '' === $label_dark ) {
		// Blank dark label: flip the default pair, else reuse a custom light label.
		$label_dark = ( __( 'Dark Mode', 'aggressive-apparel' ) === $label )
			? __( 'Light Mode', 'aggressive-apparel' )
			: $label;
	}
}

$sun_path  = 'M 14.984375 0.98632812 A 1.0001 1.0001 0 0 0 14 2 L 14 5 A 1.0001 1.0001 0 1 0 16 5 L 16 2 A 1.0001 1.0001 0 0 0 14.984375 0.98632812 z M 5.796875 4.7988281 A 1.0001 1.0001 0 0 0 5.1015625 6.515625 L 7.2226562 8.6367188 A 1.0001 1.0001 0 1 0 8.6367188 7.2226562 L 6.515625 5.1015625 A 1.0001 1.0001 0 0 0 5.796875 4.7988281 z M 24.171875 4.7988281 A 1.0001 1.0001 0 0 0 23.484375 5.1015625 L 21.363281 7.2226562 A 1.0001 1.0001 0 1 0 22.777344 8.6367188 L 24.898438 6.515625 A 1.0001 1.0001 0 0 0 24.171875 4.7988281 z M 15 8 A 7 7 0 0 0 8 15 A 7 7 0 0 0 15 22 A 7 7 0 0 0 22 15 A 7 7 0 0 0 15 8 z M 2 14 A 1.0001 1.0001 0 1 0 2 16 L 5 16 A 1.0001 1.0001 0 1 0 5 14 L 2 14 z M 25 14 A 1.0001 1.0001 0 1 0 25 16 L 28 16 A 1.0001 1.0001 0 1 0 28 14 L 25 14 z M 7.9101562 21.060547 A 1.0001 1.0001 0 0 0 7.2226562 21.363281 L 5.1015625 23.484375 A 1.0001 1.0001 0 1 0 6.515625 24.898438 L 8.6367188 22.777344 A 1.0001 1.0001 0 0 0 7.9101562 21.060547 z M 22.060547 21.060547 A 1.0001 1.0001 0 0 0 21.363281 22.777344 L 23.484375 24.898438 A 1.0001 1.0001 0 1 0 24.898438 23.484375 L 22.777344 21.363281 A 1.0001 1.0001 0 0 0 22.060547 21.060547 z M 14.984375 23.986328 A 1.0001 1.0001 0 0 0 14 25 L 14 28 A 1.0001 1.0001 0 1 0 16 28 L 16 25 A 1.0001 1.0001 0 0 0 14.984375 23.986328 z';
$moon_path = 'M12 22C17.5228 22 22 17.5228 22 12C22 11.5373 21.3065 11.4608 21.0672 11.8568C19.9289 13.7406 17.8615 15 15.5 15C11.9101 15 9 12.0899 9 8.5C9 6.13845 10.2594 4.07105 12.1432 2.93276C12.5392 2.69347 12.4627 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z';

?>
<div
	<?php
	echo get_block_wrapper_attributes(
		array(
			'class' => sprintf(
				'is-size-%s has-text-align-%s',
				$size,
				$alignment
			),
		)
	);
	?>
>
	<button
		type="button"
		class="dark-mode-toggle__button"
		aria-pressed="false"
		aria-label="<?php esc_attr_e( 'Switch to dark mode', 'aggressive-apparel' ); ?>"
		data-label-light="<?php esc_attr_e( 'Switch to dark mode', 'aggressive-apparel' ); ?>"
		data-label-dark="<?php esc_attr_e( 'Switch to light mode', 'aggressive-apparel' ); ?>"
		<?php if ( $show_label ) : ?>
			data-text-label-light="<?php echo esc_attr( $label ); ?>"
			data-text-label-dark="<?php echo esc_attr( $label_dark ); ?>"
		<?php endif; ?>
	>
		<span class="dark-mode-toggle__icons" aria-hidden="true">
			<svg class="dark-mode-toggle__icon dark-mode-toggle__icon--sun" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 30 30" fill="currentColor" focusable="false">
				<path d="<?php echo esc_attr( $sun_path ); ?>" />
			</svg>
			<svg class="dark-mode-toggle__icon dark-mode-toggle__icon--moon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" focusable="false">
				<path d="<?php echo esc_attr( $moon_path ); ?>" />
			</svg>
		</span>
		<?php if ( $show_label ) : ?>
			<span class="dark-mode-toggle__label"><?php echo esc_html( $label ); ?></span>
		<?php endif; ?>
	</button>
</div>
