<?php
/**
 * Recently Viewed Products Block — Server Render.
 *
 * Outputs an Interactivity API placeholder that view.js populates from
 * localStorage and the WooCommerce Store API on the client side.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive_Apparel
 */

defined( 'ABSPATH' ) || exit;

$max_display = isset( $attributes['maxDisplay'] ) ? (int) $attributes['maxDisplay'] : 4;
$heading     = isset( $attributes['heading'] ) ? sanitize_text_field( (string) $attributes['heading'] ) : __( 'Recently Viewed', 'aggressive-apparel' );

// On a single product page, pass the current product ID so view.js can
// record the visit and exclude the current product from the list.
$current_product_id = 0;
if ( function_exists( 'is_product' ) && is_product() ) {
	$current_product_id = (int) get_the_ID();
}

$context = (string) wp_json_encode(
	array(
		'currentProductId' => $current_product_id,
		'maxDisplay'       => $max_display,
		'products'         => array(),
		'loaded'           => false,
		'restBase'         => esc_url_raw( rest_url( 'wc/store/v1/products' ) ),
	)
);

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'aggressive-apparel-recently-viewed',
		'data-wp-interactive' => 'aggressive-apparel/recently-viewed',
		'data-wp-context'     => $context,
		'data-wp-init'        => 'callbacks.init',
	)
);
?>
<section 
<?php
echo wp_kses(
	$wrapper_attrs,
	array(
		'class'               => array(),
		'id'                  => array(),
		'style'               => array(),
		'data-wp-interactive' => array(),
		'data-wp-context'     => array(),
		'data-wp-init'        => array(),
	)
);
?>
>
	<div data-wp-bind--hidden="!context.loaded">
		<?php if ( $heading ) : ?>
		<h2 class="aggressive-apparel-recently-viewed__title">
			<?php echo esc_html( $heading ); ?>
		</h2>
		<?php endif; ?>
		<div
			class="aggressive-apparel-recently-viewed__grid"
			data-wp-html="state.productsHtml"
		></div>
	</div>
</section>
