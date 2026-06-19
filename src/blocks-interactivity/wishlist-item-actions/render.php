<?php
/**
 * Wishlist Item Actions — Server Render
 *
 * @var array $attributes
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

use Aggressive_Apparel\WooCommerce\Feature_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_remove      = ! isset( $attributes['showRemove'] ) || (bool) $attributes['showRemove'];
$show_add_to_cart = ! empty( $attributes['showAddToCart'] );
$default_atc      = class_exists( Feature_Settings::class ) ? Feature_Settings::get_simple_product_button_text() : __( 'Add to Cart', 'aggressive-apparel' );
$atc_label        = sanitize_text_field( $attributes['addToCartLabel'] ?? $default_atc );
if ( __( 'Add to Cart', 'aggressive-apparel' ) === $atc_label ) {
	$atc_label = $default_atc;
}
$remove_label = sanitize_text_field( $attributes['removeLabel'] ?? '' );

if ( ! $show_remove && ! $show_add_to_cart ) {
	return;
}

$heart_svg = '<svg class="aa-wl-item-actions__remove-icon" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" aria-hidden="true" width="16" height="16"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';

$wrapper_attrs = get_block_wrapper_attributes(
	array( 'class' => 'aa-wl-item-actions' )
);
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php if ( $show_add_to_cart ) : ?>
	<a
		class="aa-wl-item-actions__atc wp-element-button"
		data-wp-bind--href="context.item.addToCartUrl"
	>
		<?php echo esc_html( $atc_label ); ?>
	</a>
	<?php endif; ?>

	<?php if ( $show_remove ) : ?>
	<button
		type="button"
		class="aa-wl-item-actions__remove"
		data-wp-on--click="actions.removeItem"
		aria-label="<?php echo $remove_label ? esc_attr( $remove_label ) : esc_attr__( 'Remove from wishlist', 'aggressive-apparel' ); ?>"
	>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
		echo $heart_svg;
		if ( $remove_label ) {
			echo '<span class="aa-wl-item-actions__remove-label">' . esc_html( $remove_label ) . '</span>';
		}
		?>
	</button>
	<?php endif; ?>

</div>
