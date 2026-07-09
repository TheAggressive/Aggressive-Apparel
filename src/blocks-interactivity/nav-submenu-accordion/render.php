<?php
/**
 * Nav Accordion Block Render
 *
 * Mobile-only accordion submenu. Expands inline below the trigger using a
 * CSS grid-collapse animation. One accordion open at a time (enforced by
 * the navigation-panel store's activeSubmenuId).
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content (nav-link items).
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$label      = $attributes['label'] ?? '';
$url        = $attributes['url'] ?? '';
$submenu_id = ! empty( $attributes['submenuId'] ) ? $attributes['submenuId'] : wp_unique_id( 'accordion-' );
$show_arrow = $attributes['showArrow'] ?? true;
$nav_id     = $block->context['aggressive-apparel/navigationId'] ?? '';

$context = wp_json_encode(
	array(
		'submenuId' => $submenu_id,
		'panelSlug' => $nav_id,
		'menuType'  => 'accordion',
	),
	JSON_HEX_TAG | JSON_HEX_AMP
);

$arrow_html = '';
if ( $show_arrow ) {
	$arrow_html = '<span class="wp-block-aggressive-apparel-nav-submenu-accordion__arrow" aria-hidden="true">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
			<path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
		</svg>
	</span>';
}

$has_url = ! empty( $url );

if ( $has_url ) {
	$trigger_el = sprintf(
		'<a class="wp-block-aggressive-apparel-nav-submenu-accordion__link" href="%s" role="menuitem" aria-controls="%s" aria-expanded="false" data-wp-bind--aria-expanded="callbacks.isSubmenuOpen" data-wp-on--click="actions.toggleSubmenu">
			<span class="wp-block-aggressive-apparel-nav-submenu-accordion__label">%s</span>
			%s
		</a>',
		esc_url( $url ),
		esc_attr( $submenu_id ),
		esc_html( $label ),
		$arrow_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
	);
} else {
	$trigger_el = sprintf(
		'<button type="button" class="wp-block-aggressive-apparel-nav-submenu-accordion__link" role="menuitem" aria-controls="%s" aria-expanded="false" data-wp-bind--aria-expanded="callbacks.isSubmenuOpen" data-wp-on--click="actions.toggleSubmenu">
			<span class="wp-block-aggressive-apparel-nav-submenu-accordion__label">%s</span>
			%s
		</button>',
		esc_attr( $submenu_id ),
		esc_html( $label ),
		$arrow_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
	);
}

printf(
	'<li %s>
		<div class="wp-block-aggressive-apparel-nav-submenu-accordion__trigger">%s</div>
		<div class="wp-block-aggressive-apparel-nav-submenu-accordion__panel" id="%s">
			<div class="wp-block-aggressive-apparel-nav-submenu-accordion__panel-content">
				<ul class="wp-block-aggressive-apparel-nav-submenu-accordion__panel-inner" role="menu" aria-label="%s">%s</ul>
			</div>
		</div>
	</li>',
	get_block_wrapper_attributes(
		array(
			'role'                   => 'none',
			'data-wp-interactive'    => 'aggressive-apparel/navigation-panel',
			'data-wp-context'        => $context,
			'data-wp-class--is-open' => 'callbacks.isSubmenuOpen',
		)
	),
	$trigger_el, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with escaping above.
	esc_attr( $submenu_id ),
	esc_attr( $label ),
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks already escaped.
);
