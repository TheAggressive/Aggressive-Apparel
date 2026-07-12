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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$resolve_wp_preset_value = static function ( string $value ): string {
	return (string) preg_replace(
		'/^var:preset\|([^|]+)\|(.+)$/',
		'var(--wp--preset--$1--$2)',
		$value
	);
};

$sanitize_css_color = static function ( mixed $value ) use ( $resolve_wp_preset_value ): string {
	$color = trim( wp_strip_all_tags( (string) $value ) );
	$color = str_replace( array( "\0", "\r", "\n", ';' ), '', $color );

	if ( '' === $color ) {
		return '';
	}

	$color = $resolve_wp_preset_value( $color );

	if ( sanitize_hex_color( $color ) ) {
		return $color;
	}

	// Palette slug → CSS custom property.
	if ( preg_match( '/^[a-z0-9_-]+$/i', $color ) ) {
		return sprintf( 'var(--wp--preset--color--%s)', sanitize_key( $color ) );
	}

	// CSS variables, including optional fallbacks: var(--token, #fff).
	if ( preg_match( '/^var\(\s*--[a-zA-Z0-9_-]+\s*(?:,[^;{}]*)?\)$/', $color ) ) {
		return $color;
	}

	// Adaptive palette values from theme.json / ColorGradientSettingsDropdown.
	if ( preg_match( '/^light-dark\(\s*[^;{}]+\s*,\s*[^;{}]+\s*\)$/i', $color ) ) {
		return $color;
	}

	// Functional color notations (hex already handled above).
	if ( preg_match( '/^(rgba?|hsla?|oklch|oklab|lch|lab|color|color-mix)\s*\([^;{}]*\)$/i', $color ) ) {
		return $color;
	}

	return '';
};

$sanitize_hotspot = static function ( mixed $hotspot ): ?array {
	if ( ! is_array( $hotspot ) ) {
		return null;
	}

	$x = isset( $hotspot['x'] ) ? (float) $hotspot['x'] : 0.0;
	$y = isset( $hotspot['y'] ) ? (float) $hotspot['y'] : 0.0;

	return array(
		'x'           => max( 0.0, min( 100.0, $x ) ),
		'y'           => max( 0.0, min( 100.0, $y ) ),
		'productId'   => max( 0, absint( $hotspot['productId'] ?? 0 ) ),
		'productName' => sanitize_text_field( $hotspot['productName'] ?? '' ),
	);
};

$build_attr_string = static function ( array $attrs ): string {
	$parts = array();

	foreach ( $attrs as $name => $value ) {
		if ( ! is_string( $name ) || ! preg_match( '/^[A-Za-z][A-Za-z0-9_:.\-]*(?:--[A-Za-z0-9_\-]+)*$/', $name ) ) {
			continue;
		}

		if ( null === $value || false === $value ) {
			continue;
		}

		$parts[] = true === $value
			? esc_attr( $name )
			: sprintf( '%s="%s"', esc_attr( $name ), esc_attr( (string) $value ) );
	}

	return $parts ? ' ' . implode( ' ', $parts ) : '';
};

$media_id          = isset( $attributes['mediaId'] ) ? absint( $attributes['mediaId'] ) : 0;
$media_url         = isset( $attributes['mediaUrl'] ) ? esc_url_raw( (string) $attributes['mediaUrl'] ) : '';
$media_alt         = isset( $attributes['mediaAlt'] ) ? sanitize_text_field( (string) $attributes['mediaAlt'] ) : '';
$raw_hotspots      = isset( $attributes['hotspots'] ) && is_array( $attributes['hotspots'] ) ? $attributes['hotspots'] : array();
$hotspots          = array_values( array_filter( array_map( $sanitize_hotspot, array_slice( $raw_hotspots, 0, 50 ) ) ) );
$hotspot_bg_color  = $sanitize_css_color( $attributes['hotspotBgColor'] ?? '' );
$hotspot_txt_color = $sanitize_css_color( $attributes['hotspotTextColor'] ?? '' );
$hotspot_size      = isset( $attributes['hotspotSize'] ) ? max( 20, min( 48, absint( $attributes['hotspotSize'] ) ) ) : 32;
$open_on_hover     = ! empty( $attributes['openOnHover'] );
$card_bg_color     = $sanitize_css_color( $attributes['cardBgColor'] ?? '' );
$card_txt_color    = $sanitize_css_color( $attributes['cardTextColor'] ?? '' );
$action_bg_color   = $sanitize_css_color( $attributes['actionBgColor'] ?? '' );
$action_icon_color = $sanitize_css_color( $attributes['actionIconColor'] ?? '' );

if ( '' === $media_url ) {
	return;
}

$popover_id = wp_unique_id( 'aa-lookbook-popover-' );

$context = wp_json_encode(
	array(
		'activeHotspot' => -1,
		'restBase'      => esc_url_raw( rest_url( 'wc/store/v1/products/' ) ),
		'openOnHover'   => $open_on_hover,
		'i18n'          => array(
			'loading'         => __( 'Loading product.', 'aggressive-apparel' ),
			'noProduct'       => __( 'No product selected.', 'aggressive-apparel' ),
			'loadError'       => __( 'Product could not be loaded.', 'aggressive-apparel' ),
			'viewProduct'     => __( 'View product', 'aggressive-apparel' ),
			/* translators: %s: product name. */
			'viewProductName' => __( 'View product: %s', 'aggressive-apparel' ),
		),
		'hotspots'      => array_map(
			function ( $h ) {
				return array(
					'x'           => $h['x'],
					'y'           => $h['y'],
					'productId'   => $h['productId'],
					'productName' => $h['productName'],
				);
			},
			$hotspots,
		),
	),
);
if ( false === $context ) {
	$context = '{}';
}

$css_vars   = array();
$css_vars[] = '--aa-lookbook-hotspot-size: ' . esc_attr( (string) $hotspot_size ) . 'px';
if ( $hotspot_bg_color ) {
	$css_vars[] = '--aa-lookbook-hotspot-bg: ' . esc_attr( $hotspot_bg_color );
}
if ( $hotspot_txt_color ) {
	$css_vars[] = '--aa-lookbook-hotspot-color: ' . esc_attr( $hotspot_txt_color );
}
if ( $card_bg_color ) {
	$css_vars[] = '--aa-lookbook-card-bg: ' . esc_attr( $card_bg_color );
}
if ( $card_txt_color ) {
	$css_vars[] = '--aa-lookbook-card-text: ' . esc_attr( $card_txt_color );
}
if ( $action_bg_color ) {
	$css_vars[] = '--aa-lookbook-action-bg: ' . esc_attr( $action_bg_color );
}
if ( $action_icon_color ) {
	$css_vars[] = '--aa-lookbook-action-color: ' . esc_attr( $action_icon_color );
}

$wrapper_extra = array(
	'class'                        => 'aggressive-apparel-lookbook',
	'data-wp-interactive'          => 'aggressive-apparel/lookbook',
	'data-wp-context'              => $context,
	'data-wp-init'                 => 'callbacks.init',
	'data-wp-on-document--keydown' => 'actions.onDocumentKeydown',
	'data-wp-on-document--click'   => 'actions.onDocumentClick',
);
if ( $open_on_hover ) {
	$wrapper_extra['data-wp-on--mouseleave'] = 'actions.closeHotspot';
}
$css_vars_attr = $css_vars ? implode( '; ', $css_vars ) . ';' : '';
$wrapper_attrs = get_block_wrapper_attributes( $wrapper_extra );

if ( $css_vars_attr ) {
	if ( str_contains( $wrapper_attrs, 'style="' ) ) {
		$wrapper_attrs = str_replace( 'style="', 'style="' . $css_vars_attr, $wrapper_attrs );
	} else {
		$wrapper_attrs .= ' style="' . esc_attr( $css_vars_attr ) . '"';
	}
}

printf( '<div %s>', aggressive_apparel_trusted_html( $wrapper_attrs ) );
echo '<div class="aggressive-apparel-lookbook__image-wrapper">';

// Prefer the attachment renderer: srcset/sizes, width/height, and
// context-aware loading/fetchpriority instead of hardcoded lazy.
$image_html = '';
if ( $media_id ) {
	$image_attrs = array( 'class' => 'aggressive-apparel-lookbook__image' );
	if ( '' !== $media_alt ) {
		$image_attrs['alt'] = $media_alt;
	}
	$image_html = wp_get_attachment_image( $media_id, 'full', false, $image_attrs );
}

if ( '' !== $image_html ) {
	echo aggressive_apparel_trusted_html( $image_html );
} else {
	printf(
		'<img class="aggressive-apparel-lookbook__image" src="%s" alt="%s" loading="lazy" />',
		esc_url( $media_url ),
		esc_attr( $media_alt ),
	);
}

foreach ( $hotspots as $index => $hotspot ) {
	$x           = (float) ( $hotspot['x'] ?? 0 );
	$y           = (float) ( $hotspot['y'] ?? 0 );
	$name        = sanitize_text_field( $hotspot['productName'] ?? '' );
	$hover_attrs = $open_on_hover
		? $build_attr_string(
			array(
				'data-wp-on--mouseenter' => 'actions.openHotspot',
				'data-wp-on--mouseleave' => 'actions.scheduleHoverClose',
				'data-wp-on--focus'      => 'actions.openHotspot',
				'data-wp-on--blur'       => 'actions.scheduleHoverClose',
			)
		)
		: '';

	printf(
		'<button type="button" class="aggressive-apparel-lookbook__hotspot" style="left:%s%%;top:%s%%" data-aa-hotspot-index="%d" data-wp-on--click="actions.toggleHotspot" data-wp-on--keydown="actions.onHotspotKeydown"%s aria-expanded="false" aria-controls="%s" aria-label="%s">',
		esc_attr( (string) $x ),
		esc_attr( (string) $y ),
		(int) $index,
		aggressive_apparel_trusted_html( $hover_attrs ),
		esc_attr( $popover_id ),
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
	echo '<span class="aggressive-apparel-lookbook__hotspot-dot" aria-hidden="true"></span>';
	echo '</button>';
}

echo '</div>'; // image-wrapper.

// Product card popover. Visibility, position, and content are managed
// imperatively in view.ts (single system — no bind/style directives here).
$popover_hover_attrs = $open_on_hover
	? $build_attr_string(
		array(
			'data-wp-on--mouseenter' => 'actions.cancelHoverClose',
			'data-wp-on--mouseleave' => 'actions.scheduleHoverClose',
			'data-wp-on--focusin'    => 'actions.cancelHoverClose',
			'data-wp-on--focusout'   => 'actions.scheduleHoverClose',
			'data-wp-on--keydown'    => 'actions.onPopoverKeydown',
		)
	)
	: $build_attr_string(
		array(
			'data-wp-on--keydown' => 'actions.onPopoverKeydown',
		)
	);
printf(
	'<div id="%s" class="aggressive-apparel-lookbook__popover" hidden%s>',
	esc_attr( $popover_id ),
	aggressive_apparel_trusted_html( $popover_hover_attrs ),
);
echo '<div class="aggressive-apparel-lookbook__popover-content"></div>';
echo '</div>';

echo '</div>'; // block wrapper.
