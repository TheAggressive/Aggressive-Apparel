<?php
/**
 * Nav Submenu Block Render
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
$menu_type  = $attributes['menuType'] ?? 'dropdown';
$submenu_id = $attributes['submenuId'] ?? wp_unique_id( 'submenu-' );
$show_arrow = $attributes['showArrow'] ?? true;
// Inherit from parent navigation context.
$nav_id  = $block->context['aggressive-apparel/navigationId'] ?? '';
$open_on = $block->context['aggressive-apparel/navigationOpenOn'] ?? ( $attributes['openOn'] ?? 'hover' );

// Inherit panel styling from nav-menu context.
$panel_background_color = $block->context['aggressive-apparel/submenuBackgroundColor'] ?? '';
$panel_text_color       = $block->context['aggressive-apparel/submenuTextColor'] ?? '';
$panel_border_radius    = $block->context['aggressive-apparel/submenuBorderRadius'] ?? '';
$panel_border_width     = $block->context['aggressive-apparel/submenuBorderWidth'] ?? '';
$panel_border_color     = $block->context['aggressive-apparel/submenuBorderColor'] ?? '';
$panel_border_style     = $block->context['aggressive-apparel/submenuBorderStyle'] ?? 'solid';

// Build context for Interactivity API.
$context = wp_json_encode(
	array(
		'navId'     => $nav_id,
		'submenuId' => $submenu_id,
		'menuType'  => $menu_type,
		'openOn'    => $open_on,
	),
	JSON_HEX_TAG | JSON_HEX_AMP
);

// Build class list (base class added automatically by get_block_wrapper_attributes).
$classes = array(
	'wp-block-aggressive-apparel-nav-submenu--' . sanitize_html_class( $menu_type ),
);

// Determine hover/click bindings.
// Drill-down uses drillInto action on click (handled on trigger).
// Dropdown/mega use hover on wrapper if configured.
$wrapper_interactive_attrs = array();

if ( 'drilldown' !== $menu_type && 'hover' === $open_on ) {
	$wrapper_interactive_attrs['data-wp-on--mouseenter'] = 'callbacks.onHoverEnter';
	$wrapper_interactive_attrs['data-wp-on--mouseleave'] = 'callbacks.onHoverLeave';
	$wrapper_interactive_attrs['data-wp-on--focusin']    = 'callbacks.onHoverEnter';
	$wrapper_interactive_attrs['data-wp-on--focusout']   = 'callbacks.onHoverLeave';
}

// Determine is-open callback.
$is_open_callback = 'drilldown' === $menu_type ? 'callbacks.isInDrillStack' : 'callbacks.isSubmenuOpen';

$wrapper_attributes_array = array(
	'class'                  => implode( ' ', $classes ),
	'role'                   => 'none',
	'data-wp-interactive'    => 'aggressive-apparel/navigation',
	'data-wp-context'        => $context,
	'data-wp-class--is-open' => $is_open_callback,
);

// Drilldown submenus need to listen for state changes to update their is-open class.
// This is necessary because the shared state registry isn't reactive with the Interactivity API.
if ( 'drilldown' === $menu_type ) {
	$wrapper_attributes_array['data-wp-on-window--aa-nav-state-change'] = 'callbacks.onSubmenuStateChange';
}

// Merge interactive attributes.
$wrapper_attributes_array = array_merge( $wrapper_attributes_array, $wrapper_interactive_attrs );

$wrapper_attributes = get_block_wrapper_attributes( $wrapper_attributes_array );

// Trigger attributes (click handling).
$trigger_attrs = array();
if ( 'drilldown' === $menu_type ) {
	$trigger_attrs['data-wp-on--click'] = 'actions.drillInto';
} else {
	// Toggle on click for accessible interaction even in hover mode.
	$trigger_attrs['data-wp-on--click'] = 'actions.toggleSubmenu';
}

// Panel attributes - use popover only for click mode to avoid conflicts with CSS hover.
// Note: Popover API requires popovertarget on a button, but our trigger is an <a>.
// For now, we only use popover styling for click mode, relying on JS + CSS for hover.
$panel_popover_attrs = '';
// Disabled popover attribute for now - conflicts with hover mode CSS.
// The Popover API works best with button triggers and click-only interaction.
// Our CSS :has() and JS hover intent provide the dropdown behavior instead.

// Build panel inline styles from color and border attributes.
// Border uses CSS custom properties to override the glassmorphism defaults.
$panel_styles = array();
if ( ! empty( $panel_background_color ) ) {
	$panel_styles[] = '--submenu-bg: ' . esc_attr( $panel_background_color );
}
if ( ! empty( $panel_text_color ) ) {
	$panel_styles[] = '--submenu-color: ' . esc_attr( $panel_text_color );
}
if ( ! empty( $panel_border_radius ) ) {
	$panel_styles[] = 'border-radius: ' . esc_attr( $panel_border_radius );
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
$panel_style_attr = ! empty( $panel_styles ) ? ' style="' . implode( '; ', $panel_styles ) . '"' : '';

// Arrow icon SVG - different for drill-down (chevron right) vs dropdown (chevron down).
$arrow_html = '';
if ( $show_arrow ) {
	if ( 'drilldown' === $menu_type ) {
		$arrow_html = '<span class="wp-block-aggressive-apparel-nav-submenu__arrow wp-block-aggressive-apparel-nav-submenu__arrow--right" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
				<path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/>
			</svg>
		</span>';
	} else {
		$arrow_html = '<span class="wp-block-aggressive-apparel-nav-submenu__arrow" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
				<path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
			</svg>
		</span>';
	}
}

// Build trigger attributes string.
$trigger_attr_string = '';
foreach ( $trigger_attrs as $attr => $value ) {
	$trigger_attr_string .= sprintf( ' %s="%s"', esc_attr( $attr ), esc_attr( $value ) );
}

// Determine if we should use a button or link for the trigger.
// Use button when there's no URL (action-only), link when there's a destination.
$has_url = ! empty( $url );

// Panel visibility binding - different for drill-down.
$panel_visibility_binding = 'drilldown' === $menu_type ? 'callbacks.isCurrentDrillLevel' : 'callbacks.isSubmenuOpen';

// Drilldown header - shows back button and current level title.
// Only rendered for drilldown menu type.
// The entire header is a button for better touch target / UX.
$drilldown_header = '';
if ( 'drilldown' === $menu_type ) {
	$drilldown_header = sprintf(
		'<button type="button" class="wp-block-aggressive-apparel-nav-submenu__drilldown-header" aria-label="%s" data-wp-on--click="actions.drillBack">
			<span class="wp-block-aggressive-apparel-nav-submenu__back" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M15 18l-6-6 6-6"/>
				</svg>
			</span>
			<span class="wp-block-aggressive-apparel-nav-submenu__drilldown-title">%s</span>
		</button>',
		esc_attr__( 'Go back', 'aggressive-apparel' ),
		esc_html( $label )
	);
}

// Build trigger element HTML based on whether URL exists.
if ( $has_url ) {
	// Use anchor tag when there's a navigation destination.
	$trigger_element = sprintf(
		'<a class="wp-block-aggressive-apparel-nav-submenu__link" href="%s" role="menuitem" aria-haspopup="true" aria-controls="%s" aria-expanded="false" data-wp-bind--aria-expanded="%s">
			<span class="wp-block-aggressive-apparel-nav-submenu__label">%s</span>
			%s
		</a>',
		esc_url( $url ),
		esc_attr( $submenu_id ),
		esc_attr( $is_open_callback ),
		esc_html( $label ),
		$arrow_html
	);
} else {
	// Use button when there's no URL (action-only trigger).
	$trigger_element = sprintf(
		'<button type="button" class="wp-block-aggressive-apparel-nav-submenu__link" role="menuitem" aria-haspopup="true" aria-controls="%s" aria-expanded="false" data-wp-bind--aria-expanded="%s">
			<span class="wp-block-aggressive-apparel-nav-submenu__label">%s</span>
			%s
		</button>',
		esc_attr( $submenu_id ),
		esc_attr( $is_open_callback ),
		esc_html( $label ),
		$arrow_html
	);
}

printf(
	'<li %s>
		<div class="wp-block-aggressive-apparel-nav-submenu__trigger"%s>
			%s
		</div>
		<div class="wp-block-aggressive-apparel-nav-submenu__panel" id="%s" role="menu" aria-label="%s" data-wp-class--is-visible="%s"%s%s>
			%s
			<ul class="wp-block-aggressive-apparel-nav-submenu__panel-inner" role="menu">
				%s
			</ul>
		</div>
	</li>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$trigger_attr_string, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in loop above.
	$trigger_element, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with escaping above.
	esc_attr( $submenu_id ),
	esc_attr( $label ),
	esc_attr( $panel_visibility_binding ),
	$panel_popover_attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Contains only safe attributes.
	$panel_style_attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with esc_attr() above.
	$drilldown_header, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built with escaping above.
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks are already escaped.
);
