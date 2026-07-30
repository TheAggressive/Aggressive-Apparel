<?php
/**
 * Nav Link Block Render
 *
 * Dynamic rendering allows automatic current page detection.
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$label         = $attributes['label'] ?? '';
$url           = $attributes['url'] ?? '';
$opens_new_tab = $attributes['opensInNewTab'] ?? false;
$description   = $attributes['description'] ?? '';
$is_current    = $attributes['isCurrent'] ?? false;
$is_mega_menu  = (bool) ( $block->context['aggressive-apparel/isMegaMenu'] ?? false );

// Auto-detect current page if not manually set.
if ( ! $is_current && ! empty( $url ) ) {
	$current_url = home_url( add_query_arg( array() ) );
	$link_url    = $url;

	// Normalize URLs for comparison.
	$current_path = wp_parse_url( $current_url, PHP_URL_PATH ) ?? '/';
	$link_path    = wp_parse_url( $link_url, PHP_URL_PATH ) ?? '/';

	// Handle relative URLs.
	if ( ! wp_parse_url( $link_url, PHP_URL_HOST ) ) {
		// It's a relative URL, compare paths directly.
		$is_current = trailingslashit( $current_path ) === trailingslashit( $link_path );
	} else {
		// Full URL, compare host and path.
		$current_host = wp_parse_url( $current_url, PHP_URL_HOST );
		$link_host    = wp_parse_url( $link_url, PHP_URL_HOST );

		if ( $current_host === $link_host ) {
			$is_current = trailingslashit( $current_path ) === trailingslashit( $link_path );
		}
	}
}

// Build class list (base class added automatically by get_block_wrapper_attributes).
$classes = array();
if ( $is_current ) {
	$classes[] = 'is-current';
}

// Build link attributes.
$escaped_url = esc_url( $url );
$link_attrs  = array(
	'class' => 'wp-block-aggressive-apparel-nav-link__link',
	'href'  => $escaped_url ? $escaped_url : '#',
);
if ( ! $is_mega_menu ) {
	$link_attrs['role'] = 'menuitem';
}

if ( $opens_new_tab ) {
	$link_attrs['target'] = '_blank';
	$link_attrs['rel']    = 'noopener noreferrer';
}

if ( $is_current ) {
	$link_attrs['aria-current'] = 'page';
}

// Build link attributes string.
$link_attr_string = '';
foreach ( $link_attrs as $attr => $value ) {
	$link_attr_string .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
}

// Build description HTML.
$description_html = '';
if ( ! empty( $description ) ) {
	$description_html = sprintf(
		'<span class="wp-block-aggressive-apparel-nav-link__description">%s</span>',
		esc_html( $description )
	);
}

$wrapper_attributes = array( 'class' => implode( ' ', $classes ) );
if ( ! $is_mega_menu ) {
	$wrapper_attributes['role'] = 'none';
}
$wrapper_tag = $is_mega_menu ? 'div' : 'li';

printf(
	'<%1$s %2$s><a%3$s><span class="wp-block-aggressive-apparel-nav-link__label">%4$s</span>%5$s</a></%1$s>',
	esc_attr( $wrapper_tag ),
	get_block_wrapper_attributes( $wrapper_attributes ),
	aggressive_apparel_trusted_html( $link_attr_string ),
	esc_html( $label ),
	wp_kses_post( $description_html )
);
