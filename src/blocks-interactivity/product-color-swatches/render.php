<?php
/**
 * Product Color Swatches Block — Server Render
 *
 * Renders color variation swatches for a product in the loop context.
 * Clicking a swatch swaps the product card's image to that variation's image.
 *
 * Available variables:
 *   $attributes (array)
 *   $content    (string)
 *   $block      (WP_Block)
 *
 * @package Aggressive_Apparel
 */

use Aggressive_Apparel\WooCommerce\Color_Data_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wc_get_product' ) ) {
	return;
}

// Resolve the product from block context (Product Collection / Query Loop inject postId).
$product_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
if ( $product_id <= 0 ) {
	return;
}

$product = wc_get_product( $product_id );
if ( ! $product || ! $product->is_type( 'variable' ) ) {
	return;
}

// Cache swatch data and variation results across multiple products on the same page.
static $swatch_data_cache      = null;
static $product_swatches_cache = array();

if ( null === $swatch_data_cache ) {
	$swatch_data_cache = Color_Data_Manager::get_safe_swatch_data();
}

if ( isset( $product_swatches_cache[ $product_id ] ) ) {
	$swatches = $product_swatches_cache[ $product_id ];
} else {
	$variations = $product->get_available_variations();
	$swatches   = array();

	foreach ( $variations as $variation ) {
		$variation_id = (int) $variation['variation_id'];

		foreach ( $variation['attributes'] as $attr_name => $attr_value ) {
			if ( ! Color_Data_Manager::is_color_attribute( $attr_name ) || empty( $attr_value ) ) {
				continue;
			}

			$slug = sanitize_title( $attr_value );
			if ( isset( $swatches[ $slug ] ) ) {
				// First variation per color slug wins.
				continue;
			}

			// Try slug lookup, then lowercase raw value, then guess from name.
			$color_info  = $swatch_data_cache[ $slug ] ?? $swatch_data_cache[ strtolower( $attr_value ) ] ?? null;
			$color_value = $color_info['value'] ?? '#000000';
			$color_type  = $color_info['type'] ?? 'solid';
			$color_name  = $color_info['name'] ?? ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );

			$swatches[ $slug ] = array(
				'variationId' => $variation_id,
				'imageUrl'    => ! empty( $variation['image']['url'] ) ? esc_url_raw( $variation['image']['url'] ) : '',
				'imageSrcset' => ! empty( $variation['image']['srcset'] ) ? esc_attr( $variation['image']['srcset'] ) : '',
				'imageAlt'    => ! empty( $variation['image']['alt'] ) ? sanitize_text_field( $variation['image']['alt'] ) : $color_name,
				'colorValue'  => $color_value,
				'colorType'   => $color_type,
				'colorName'   => $color_name,
			);
			break;
		}
	}

	$product_swatches_cache[ $product_id ] = $swatches;
}

if ( empty( $swatches ) ) {
	return;
}

$shape          = isset( $attributes['swatchShape'] ) ? (string) $attributes['swatchShape'] : 'circle';
$size           = isset( $attributes['swatchSize'] ) ? (string) $attributes['swatchSize'] : 'md';
$max_visible    = isset( $attributes['maxVisible'] ) ? max( 1, (int) $attributes['maxVisible'] ) : 5;
$show_tooltip   = ! isset( $attributes['showTooltip'] ) || (bool) $attributes['showTooltip'];
$link_variation = ! isset( $attributes['linkToVariation'] ) || (bool) $attributes['linkToVariation'];
$transition     = isset( $attributes['swatchTransition'] ) ? (string) $attributes['swatchTransition'] : 'blur';

$shape             = in_array( $shape, array( 'circle', 'square', 'diamond' ), true ) ? $shape : 'circle';
$size              = in_array( $size, array( 'xs', 'sm', 'md', 'lg' ), true ) ? $size : 'md';
$valid_transitions = array( 'fade', 'blur', 'zoom-in', 'zoom-out', 'slide-up', 'slide-down', 'slide-left', 'flip', 'blur-zoom', 'flash', 'wipe', 'squeeze', 'rotate', 'tilt', 'desaturate', 'elastic', 'glitch', 'iris', 'dissolve', 'swing' );
$transition        = in_array( $transition, $valid_transitions, true ) ? $transition : 'blur';

$all_swatches   = array_values( $swatches );
$slug_keys      = array_keys( $swatches );
$visible_count  = min( $max_visible, count( $all_swatches ) );
$overflow_count = count( $all_swatches ) - $visible_count;

$container_context = array(
	'productId'       => $product_id,
	'activeSlug'      => null,
	'linkToVariation' => $link_variation,
	'transition'      => $transition,
);

$wrapper_classes = implode(
	' ',
	array(
		'aa-product-color-swatches',
		'is-shape-' . $shape,
		'is-size-' . $size,
		$show_tooltip ? 'has-tooltips' : '',
	)
);

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'           => trim( $wrapper_classes ),
		'data-wp-context' => wp_json_encode( $container_context ),
		'role'            => 'group',
		'aria-label'      => __( 'Color options', 'aggressive-apparel' ),
	)
);
?>
<div <?php echo wp_kses_post( $wrapper_attributes ); ?>>
	<?php
	for ( $i = 0; $i < $visible_count; $i++ ) :
		$swatch   = $all_swatches[ $i ];
		$slug_key = $slug_keys[ $i ];

		$is_pattern = 'pattern' === $swatch['colorType'];

		$button_context = array(
			'slug'        => $slug_key,
			'colorValue'  => $swatch['colorValue'],
			'colorType'   => $swatch['colorType'],
			'colorName'   => $swatch['colorName'],
			'imageUrl'    => $swatch['imageUrl'],
			'imageSrcset' => $swatch['imageSrcset'],
			'imageAlt'    => $swatch['imageAlt'],
		);

		$swatch_classes = 'aa-product-color-swatches__swatch';
		if ( $is_pattern ) {
			$swatch_classes .= ' is-pattern';
		}

		if ( $is_pattern ) {
			$inline_style = 'background-image: url(' . esc_url( $swatch['colorValue'] ) . ');';
		} else {
			$inline_style = '--swatch-color: ' . esc_attr( $swatch['colorValue'] ) . '; background-color: ' . esc_attr( $swatch['colorValue'] ) . ';';
		}

		/* translators: %s: Color name. */
		$aria_label = esc_attr( sprintf( __( 'View in %s', 'aggressive-apparel' ), $swatch['colorName'] ) );
		?>
		<button
			type="button"
			class="<?php echo esc_attr( $swatch_classes ); ?>"
			style="<?php echo esc_attr( $inline_style ); ?>"
			data-wp-context="<?php echo esc_attr( wp_json_encode( $button_context ) ); ?>"
			aria-pressed="false"
			aria-label="<?php echo $aria_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>"
			<?php if ( $show_tooltip ) : ?>
			data-tooltip="<?php echo esc_attr( $swatch['colorName'] ); ?>"
			<?php endif; ?>
		></button>
	<?php endfor; ?>

	<?php if ( $overflow_count > 0 ) : ?>
		<span class="aa-product-color-swatches__overflow" aria-hidden="true">
			+<?php echo esc_html( $overflow_count ); ?>
		</span>
	<?php endif; ?>
</div>
