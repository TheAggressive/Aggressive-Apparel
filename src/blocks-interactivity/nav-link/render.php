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

$label           = $attributes['label'] ?? '';
$url             = $attributes['url'] ?? '';
$opens_new_tab   = $attributes['opensInNewTab'] ?? false;
$description     = $attributes['description'] ?? '';
$is_current      = $attributes['isCurrent'] ?? false;
$is_inside_panel = $block->context['aggressive-apparel/isInsidePanel'] ?? false;

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

// Build class list.
$classes = array( 'wp-block-aggressive-apparel-nav-link' );
if ( $is_current ) {
	$classes[] = 'is-current';
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => implode( ' ', $classes ),
		'role'  => 'none',
	)
);

// Build link attributes.
$escaped_url = esc_url( $url );
$link_attrs  = array(
	'class' => 'wp-block-aggressive-apparel-nav-link__link',
	'href'  => $escaped_url ? $escaped_url : '#',
	'role'  => 'menuitem',
);

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

// SVG underline for hover animation (desktop nav only, not inside panel).
// Uses a simple wavy path that animates on hover via stroke-dashoffset.
$underline_svg = '';
if ( ! $is_inside_panel ) {
	$underline_svg = '<span class="wp-block-aggressive-apparel-nav-link__underline" aria-hidden="true">
		<svg viewBox="0 0 100 6" preserveAspectRatio="none">
			<path d="M0 3 Q 25 0, 50 3 T 100 3" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke"/>
		</svg>
	</span>';
}

printf(
	'<li %s><a%s><span class="wp-block-aggressive-apparel-nav-link__label">%s</span>%s%s</a></li>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$link_attr_string, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in loop above.
	esc_html( $label ),
	$description_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped above.
	$underline_svg // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG markup.
);
