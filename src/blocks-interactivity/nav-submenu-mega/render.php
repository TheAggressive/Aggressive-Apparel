<?php
/**
 * Nav Mega Menu Block Render
 *
 * Desktop-only full-viewport-width mega panel. JS sets --mega-panel-left
 * to the negative of the <li>'s viewport offset so the panel starts at
 * the left edge of the viewport.
 *
 * COMPAT: Adds legacy wp-block-aggressive-apparel-nav-submenu classes so the
 * navigation store's CSS selectors and JS selectors continue to work without
 * changes.
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$label      = $attributes['label'] ?? '';
$url        = $attributes['url'] ?? '';
$submenu_id = ! empty( $attributes['submenuId'] ) ? $attributes['submenuId'] : wp_unique_id( 'mega-' );
$show_arrow = $attributes['showArrow'] ?? true;
$columns    = (int) ( $attributes['columns'] ?? 4 );
$nav_id     = $block->context['aggressive-apparel/navigationId'] ?? '';
$open_on    = $block->context['aggressive-apparel/navigationOpenOn'] ?? ( $attributes['openOn'] ?? 'hover' );

// Inherit panel styling from navigation context.
$panel_background_color = $block->context['aggressive-apparel/submenuBackgroundColor'] ?? '';
$panel_text_color       = $block->context['aggressive-apparel/submenuTextColor'] ?? '';
$panel_border_width     = $block->context['aggressive-apparel/submenuBorderWidth'] ?? '';
$panel_border_color     = $block->context['aggressive-apparel/submenuBorderColor'] ?? '';
$panel_border_style     = $block->context['aggressive-apparel/submenuBorderStyle'] ?? 'solid';

$context = wp_json_encode(
	array(
		'submenuId' => $submenu_id,
		'navId'     => $nav_id,
		'menuType'  => 'mega',
		'openOn'    => $open_on,
	),
	JSON_HEX_TAG | JSON_HEX_AMP
);

// Hover bindings on wrapper for hover mode.
$wrapper_attrs = array(
	// Legacy compat: navigation store targets these classes.
	'class'                  => 'wp-block-aggressive-apparel-nav-submenu wp-block-aggressive-apparel-nav-submenu--mega',
	'role'                   => 'none',
	'data-wp-interactive'    => 'aggressive-apparel/navigation',
	'data-wp-context'        => $context,
	'data-wp-class--is-open' => 'callbacks.isSubmenuOpen',
);

if ( 'hover' === $open_on ) {
	$wrapper_attrs['data-wp-on--mouseenter'] = 'callbacks.onHoverEnter';
	$wrapper_attrs['data-wp-on--mouseleave'] = 'callbacks.onHoverLeave';
	$wrapper_attrs['data-wp-on--focusin']    = 'callbacks.onHoverEnter';
	$wrapper_attrs['data-wp-on--focusout']   = 'callbacks.onHoverLeave';
}

$wrapper_attributes = get_block_wrapper_attributes( $wrapper_attrs );

// Build panel inline styles.
$panel_styles = array();
if ( ! empty( $panel_background_color ) ) {
	$panel_styles[] = '--submenu-bg: ' . esc_attr( $panel_background_color );
}
if ( ! empty( $panel_text_color ) ) {
	$panel_styles[] = '--submenu-color: ' . esc_attr( $panel_text_color );
}
if ( ! empty( $panel_border_width ) ) {
	$panel_styles[] = '--panel-border-width: ' . esc_attr( $panel_border_width );
}
if ( ! empty( $panel_border_color ) ) {
	$panel_styles[] = '--panel-border-color: ' . esc_attr( $panel_border_color );
}
if ( ! empty( $panel_border_style ) && 'solid' !== $panel_border_style ) {
	$panel_styles[] = '--panel-border-style: ' . esc_attr( $panel_border_style );
}
$panel_styles[] = '--mega-columns: ' . $columns;
$block_gap      = $block->parsed_block['attrs']['style']['spacing']['blockGap'] ?? null;
if ( null !== $block_gap ) {
	if ( str_starts_with( $block_gap, 'var:preset|spacing|' ) ) {
		$gap_slug  = str_replace( 'var:preset|spacing|', '', $block_gap );
		$gap_value = '0' === $gap_slug ? '0' : 'var(--wp--preset--spacing--' . esc_attr( $gap_slug ) . ')';
	} else {
		$gap_value = esc_attr( $block_gap );
	}
	$panel_styles[] = '--submenu-panel-gap: ' . $gap_value;
}
$panel_style_attr = ! empty( $panel_styles ) ? ' style="' . implode( '; ', $panel_styles ) . '"' : '';

// Arrow SVG — chevron down.
$arrow_html = '';
if ( $show_arrow ) {
	$arrow_html = '<span class="wp-block-aggressive-apparel-nav-submenu__arrow" aria-hidden="true">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
			<path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
		</svg>
	</span>';
}

$has_url = ! empty( $url );

if ( $has_url ) {
	$trigger_el = sprintf(
		'<a class="wp-block-aggressive-apparel-nav-submenu__link" href="%s" role="menuitem" aria-haspopup="menu" aria-controls="%s" aria-expanded="false" data-wp-bind--aria-expanded="callbacks.isSubmenuOpen" data-wp-on--click="actions.toggleSubmenu">
			<span class="wp-block-aggressive-apparel-nav-submenu__label">%s</span>
			%s
		</a>',
		esc_url( $url ),
		esc_attr( $submenu_id ),
		esc_html( $label ),
		$arrow_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
	);
} else {
	$trigger_el = sprintf(
		'<button type="button" class="wp-block-aggressive-apparel-nav-submenu__link" role="menuitem" aria-haspopup="menu" aria-controls="%s" aria-expanded="false" data-wp-bind--aria-expanded="callbacks.isSubmenuOpen" data-wp-on--click="actions.toggleSubmenu">
			<span class="wp-block-aggressive-apparel-nav-submenu__label">%s</span>
			%s
		</button>',
		esc_attr( $submenu_id ),
		esc_html( $label ),
		$arrow_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
	);
}

// The mega panel uses role="region" (not role="menu") because inner blocks can
// include headings, images, and columns — not only menuitem elements.
printf(
	'<li %s>
		<div class="wp-block-aggressive-apparel-nav-submenu__trigger">%s</div>
		<div class="wp-block-aggressive-apparel-nav-submenu__panel" id="%s" role="region" aria-label="%s"%s>
			<div class="wp-block-aggressive-apparel-nav-submenu__panel-content">
				<ul class="wp-block-aggressive-apparel-nav-submenu__panel-inner">%s</ul>
			</div>
		</div>
	</li>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$trigger_el, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with escaping above.
	esc_attr( $submenu_id ),
	esc_attr( $label ),
	$panel_style_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_attr() above.
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks already escaped.
);
