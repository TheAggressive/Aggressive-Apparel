<?php
/**
 * Lookbook Block — Server-side Render.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$media_url = $attributes['mediaUrl'] ?? '';
$media_alt = $attributes['mediaAlt'] ?? '';
$hotspots  = $attributes['hotspots'] ?? array();

if ( empty( $media_url ) ) {
	return;
}

$context = wp_json_encode(
	array(
		'activeHotspot' => -1,
		'productData'   => null,
		'isLoading'     => false,
		'hotspots'      => array_map(
			function ( $h ) {
				return array(
					'x'           => (float) ( $h['x'] ?? 0 ),
					'y'           => (float) ( $h['y'] ?? 0 ),
					'productId'   => (int) ( $h['productId'] ?? 0 ),
					'productName' => sanitize_text_field( $h['productName'] ?? '' ),
				);
			},
			$hotspots,
		),
	),
);

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'               => 'aggressive-apparel-lookbook',
		'data-wp-interactive' => 'aggressive-apparel/lookbook',
		'data-wp-context'     => $context,
	),
);

// $wrapper_attributes is pre-escaped by get_block_wrapper_attributes().
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output from get_block_wrapper_attributes().
printf( '<div %s>', $wrapper_attributes );
echo '<div class="aggressive-apparel-lookbook__image-wrapper">';
printf(
	'<img class="aggressive-apparel-lookbook__image" src="%s" alt="%s" loading="lazy" />',
	esc_url( $media_url ),
	esc_attr( $media_alt ),
);

foreach ( $hotspots as $index => $hotspot ) {
	$x          = (float) ( $hotspot['x'] ?? 0 );
	$y          = (float) ( $hotspot['y'] ?? 0 );
	$product_id = (int) ( $hotspot['productId'] ?? 0 );
	$name       = sanitize_text_field( $hotspot['productName'] ?? '' );

	printf(
		'<button type="button" class="aggressive-apparel-lookbook__hotspot" style="left:%s%%;top:%s%%" data-wp-on--click="actions.toggleHotspot" data-wp-context=\'%s\' data-wp-class--is-active="state.isActiveHotspot" aria-label="%s">',
		esc_attr( (string) $x ),
		esc_attr( (string) $y ),
		esc_attr( wp_json_encode( array( 'hotspotIndex' => $index ) ) ),
		esc_attr(
			$name
				? sprintf(
					/* translators: %s: product name. */
					__( 'View product: %s', 'aggressive-apparel' ),
					$name,
				)
				: sprintf(
					/* translators: %d: hotspot number. */
					__( 'View product %d', 'aggressive-apparel' ),
					$index + 1,
				),
		),
	);
	echo '<span class="aggressive-apparel-lookbook__hotspot-dot"></span>';
	echo '</button>';
}

echo '</div>'; // image-wrapper.

// Product card popover (populated via Interactivity API).
echo '<div class="aggressive-apparel-lookbook__popover" data-wp-bind--hidden="state.isPopoverHidden" data-wp-style--left="state.popoverLeft" data-wp-style--top="state.popoverTop">';
echo '<div class="aggressive-apparel-lookbook__popover-content" data-wp-html="state.popoverHtml"></div>';
echo '</div>';

echo '</div>'; // block wrapper.
