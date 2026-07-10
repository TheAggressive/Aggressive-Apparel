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

$remove_aria = $remove_label
	? $remove_label
	: __( 'Remove from wishlist', 'aggressive-apparel' );
?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'aa-wl-item-actions' ) ); ?>>

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
		aria-label="<?php echo esc_attr( $remove_aria ); ?>"
	>
		<?php
		echo aggressive_apparel_get_icon(
			'heart',
			array(
				'class'       => 'aa-wl-item-actions__remove-icon',
				'width'       => 16,
				'height'      => 16,
				'aria-hidden' => 'true',
			)
		);
		if ( $remove_label ) {
			echo '<span class="aa-wl-item-actions__remove-label">' . esc_html( $remove_label ) . '</span>';
		}
		?>
	</button>
	<?php endif; ?>

</div>
