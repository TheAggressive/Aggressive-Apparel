<?php
/**
 * Wishlist Item Image — Server Render
 *
 * Image src/alt are filled client-side via callbacks.syncItemImage once
 * the wishlist Store API payload hydrates each card.
 *
 * @var array $attributes
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$allowed_ratios  = array( '1/1', '3/4', '4/3', '16/9' );
$image_ratio     = isset( $attributes['imageRatio'] ) ? (string) $attributes['imageRatio'] : '1/1';
$image_ratio     = in_array( $image_ratio, $allowed_ratios, true ) ? $image_ratio : '1/1';
$link_to_product = ! isset( $attributes['linkToProduct'] ) || (bool) $attributes['linkToProduct'];

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => 'aa-wl-item-image' ) ); ?>>
<?php if ( $link_to_product ) : ?>
	<a
		class="aa-wl-item-image__link"
		data-wp-bind--href="context.item.permalink"
		data-wp-bind--aria-label="context.item.name"
	>
		<img
			class="aa-wl-item-image__img"
			style="<?php echo esc_attr( 'aspect-ratio:' . $image_ratio . ';' ); ?>"
			alt=""
			data-wp-watch="callbacks.syncItemImage"
		/>
	</a>
<?php else : ?>
	<img
		class="aa-wl-item-image__img"
		style="<?php echo esc_attr( 'aspect-ratio:' . $image_ratio . ';' ); ?>"
		alt=""
		data-wp-watch="callbacks.syncItemImage"
	/>
<?php endif; ?>
</div>
