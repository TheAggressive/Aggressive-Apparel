<?php
/**
 * Nav Drilldown Block Render
 *
 * Mobile-only drilldown submenu. Slides in from the right to cover
 * aa-nav__panel-body, revealing a back button and child links.
 *
 * Animation styles:
 *   overlay — submenu slides over the main list (main list stays put)
 *   push    — main list slides out left as submenu slides in from right
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content (nav-link items).
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

$label           = $attributes['label'] ?? '';
$url             = $attributes['url'] ?? '';
$submenu_id      = ! empty( $attributes['submenuId'] ) ? $attributes['submenuId'] : wp_unique_id( 'drilldown-' );
$show_arrow      = $attributes['showArrow'] ?? true;
$animation_style = $attributes['animationStyle'] ?? 'overlay';
$nav_id          = $block->context['aggressive-apparel/navigationId'] ?? '';

$context = wp_json_encode(
	array(
		'submenuId' => $submenu_id,
		'panelSlug' => $nav_id,
		'menuType'  => 'drilldown',
	),
	JSON_HEX_TAG | JSON_HEX_AMP
);

// Modifier class drives animation-style CSS and :has() targeting for push mode.
$classes = array( 'wp-block-aggressive-apparel-nav-submenu-drilldown--' . sanitize_html_class( $animation_style ) );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                                        => implode( ' ', $classes ),
		'role'                                         => 'none',
		'data-wp-interactive'                          => 'aggressive-apparel/navigation-panel',
		'data-wp-context'                              => $context,
		'data-wp-class--is-open'                       => 'callbacks.isInDrillStack',
		// React to state changes dispatched by the panel store (portal boundary workaround).
		'data-wp-on-window--aa-nav-panel-state-change' => 'callbacks.onSubmenuStateChange',
	)
);

// Chevron-right arrow on the trigger.
$arrow_html = '';
if ( $show_arrow ) {
	$arrow_html = '<span class="wp-block-aggressive-apparel-nav-submenu-drilldown__arrow" aria-hidden="true">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
			<path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/>
		</svg>
	</span>';
}

$has_url = ! empty( $url );

if ( $has_url ) {
	$trigger_el = sprintf(
		'<a class="wp-block-aggressive-apparel-nav-submenu-drilldown__link" href="%s" role="menuitem" aria-haspopup="menu" aria-controls="%s" aria-expanded="false" data-wp-bind--aria-expanded="callbacks.isInDrillStack" data-wp-on--click="actions.drillInto">
			<span class="wp-block-aggressive-apparel-nav-submenu-drilldown__label">%s</span>
			%s
		</a>',
		esc_url( $url ),
		esc_attr( $submenu_id ),
		esc_html( $label ),
		$arrow_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
	);
} else {
	$trigger_el = sprintf(
		'<button type="button" class="wp-block-aggressive-apparel-nav-submenu-drilldown__link" role="menuitem" aria-haspopup="menu" aria-controls="%s" aria-expanded="false" data-wp-bind--aria-expanded="callbacks.isInDrillStack" data-wp-on--click="actions.drillInto">
			<span class="wp-block-aggressive-apparel-nav-submenu-drilldown__label">%s</span>
			%s
		</button>',
		esc_attr( $submenu_id ),
		esc_html( $label ),
		$arrow_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG.
	);
}

// Back button — closes the drilldown and returns to the parent level.
// Screen readers hear "Back from Shop" while sighted users see "← Shop".
// The visible label is aria-hidden so the sr-only text is the sole accessible name.
$back_button = sprintf(
	'<button type="button" class="wp-block-aggressive-apparel-nav-submenu-drilldown__back-button" data-wp-on--click="actions.drillBack">
		<span class="wp-block-aggressive-apparel-nav-submenu-drilldown__back-icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M15 18l-6-6 6-6"/>
			</svg>
		</span>
		<span class="wp-block-aggressive-apparel-nav-submenu-drilldown__back-label" aria-hidden="true">%s</span>
		<span class="screen-reader-text">%s</span>
	</button>',
	esc_html( $label ),
	/* translators: %s: submenu name, e.g. "Back from Shop" */
	esc_html( sprintf( __( 'Back from %s', 'aggressive-apparel' ), $label ) )
);

printf(
	'<li %s>
		<div class="wp-block-aggressive-apparel-nav-submenu-drilldown__trigger">%s</div>
		<div class="wp-block-aggressive-apparel-nav-submenu-drilldown__panel" id="%s" role="region" aria-label="%s">
			%s
			<ul class="wp-block-aggressive-apparel-nav-submenu-drilldown__panel-inner" role="menu" aria-label="%s">%s</ul>
		</div>
	</li>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$trigger_el, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with escaping above.
	esc_attr( $submenu_id ),
	esc_attr( $label ),
	$back_button, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with escaping above.
	esc_attr( $label ),
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks already escaped.
);
