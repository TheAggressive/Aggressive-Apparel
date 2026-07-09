<?php
/**
 * Wishlist Item Image — Server Render
 *
 * @var array $attributes
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$image_ratio     = sanitize_text_field( $attributes['imageRatio'] ?? '1/1' );
$link_to_product = ! isset( $attributes['linkToProduct'] ) || (bool) $attributes['linkToProduct'];

$img_style = 'aspect-ratio:' . esc_attr( $image_ratio ) . ';';

$img_html = sprintf(
	'<img class="aa-wl-item-image__img" style="%s" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" alt="" data-wp-watch="callbacks.syncItemImage" />',
	esc_attr( $img_style )
);
?>
<div
	<?php
	echo get_block_wrapper_attributes(
		array( 'class' => 'aa-wl-item-image' )
	);
	?>
>
<?php if ( $link_to_product ) : ?>
	<a class="aa-wl-item-image__link" data-wp-bind--href="context.item.permalink">
		<?php echo $img_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</a>
<?php else : ?>
	<?php echo $img_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php endif; ?>
</div>
