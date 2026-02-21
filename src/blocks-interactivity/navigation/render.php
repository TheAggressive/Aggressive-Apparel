<?php
/**
 * Navigation Block Render (v2)
 *
 * Consolidated render that includes toggle button, desktop menubar,
 * and mobile panel with auto-synced content. Replaces the old
 * nav-menu, menu-toggle, navigation-panel, panel-header, panel-body,
 * panel-footer, and panel-close-button blocks.
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

// ============================================================================
// Extract attributes
// ============================================================================

$breakpoint = (int) ( $attributes['breakpoint'] ?? 1024 );
$aria_label = $attributes['ariaLabel'] ?? __( 'Main navigation', 'aggressive-apparel' );
$open_on    = $attributes['openOn'] ?? 'hover';

// Toggle attributes (absorbed from menu-toggle).
$toggle_label      = $attributes['toggleLabel'] ?? __( 'Menu', 'aggressive-apparel' );
$toggle_icon_style = $attributes['toggleIconStyle'] ?? 'hamburger';
$toggle_anim_type  = $attributes['toggleAnimationType'] ?? 'to-x';
$show_toggle_label = $attributes['showToggleLabel'] ?? false;

// Panel attributes (absorbed from navigation-panel).
$panel_position        = $attributes['panelPosition'] ?? 'right';
$panel_animation_style = $attributes['panelAnimationStyle'] ?? 'slide';
$panel_width           = $attributes['panelWidth'] ?? 'min(320px, 85vw)';
$show_panel_overlay    = $attributes['showPanelOverlay'] ?? true;

// Panel colors.
$panel_bg_color        = $attributes['panelBackgroundColor'] ?? '';
$panel_text_color      = $attributes['panelTextColor'] ?? '';
$panel_hover_color     = $attributes['panelLinkHoverColor'] ?? '';
$panel_hover_bg        = $attributes['panelLinkHoverBg'] ?? '';
$panel_overlay_color   = $attributes['panelOverlayColor'] ?? '';
$panel_overlay_opacity = $attributes['panelOverlayOpacity'] ?? 50;

// Indicator.
$indicator_color = $attributes['indicatorColor'] ?? '';

// ============================================================================
// Generate IDs
// ============================================================================

$nav_id = ! empty( $attributes['navId'] ) ? $attributes['navId'] : wp_unique_id( 'nav-' );

// Store globally for child blocks (nav-link, nav-submenu) that use context.
global $aggressive_apparel_current_nav_id, $aggressive_apparel_current_nav_breakpoint;
$aggressive_apparel_current_nav_id         = $nav_id;
$aggressive_apparel_current_nav_breakpoint = $breakpoint;

$panel_id  = aggressive_apparel_get_panel_id( $nav_id );
$toggle_id = aggressive_apparel_get_toggle_id( $nav_id );

// ============================================================================
// Build Interactivity API context
// ============================================================================

$context = aggressive_apparel_encode_nav_context(
	aggressive_apparel_build_nav_context( $nav_id, $breakpoint, $open_on )
);

// Seed the global store with this nav's mutable state so the portaled panel
// (rendered via wp_footer, outside .wp-site-blocks) and the <nav> share
// reactive state through state._panels[navId].
if ( function_exists( 'wp_interactivity_state' ) ) {
	$current_state = wp_interactivity_state( 'aggressive-apparel/navigation' );
	$panels_state  = $current_state['_panels'] ?? array();

	$panels_state[ $nav_id ] = array(
		'isOpen'          => false,
		'isMobile'        => false,
		'activeSubmenuId' => null,
		'drillStack'      => array(),
	);

	wp_interactivity_state(
		'aggressive-apparel/navigation',
		array( '_panels' => $panels_state )
	);
}

// ============================================================================
// Separate inner block content into menu items vs utility blocks
// ============================================================================
// Inner blocks produce <li> elements (nav-link, nav-submenu) and other blocks
// (site-logo, search, buttons, etc.). We separate them so menu items go into
// <ul role="menubar"> and utility blocks stay outside.

$menu_items_html = '';
$utility_html    = '';

if ( ! empty( $content ) ) {
	// Match <li> elements with nav-link or nav-submenu classes.
	// These can be deeply nested (nav-submenu contains inner <li> elements),
	// so we match only top-level <li> items by tracking tag depth.
	$dom = new DOMDocument();
	// Suppress warnings from HTML5 tags and load as UTF-8.
	$wrapped = '<div id="aa-nav-parse-root">' . $content . '</div>';
	@$dom->loadHTML( '<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DOMDocument warnings for HTML5 tags.

	$root = $dom->getElementById( 'aa-nav-parse-root' );
	if ( $root ) {
		foreach ( $root->childNodes as $node ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument API.
			if ( XML_ELEMENT_NODE !== $node->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument API.
				continue;
			}

			$html_fragment = $dom->saveHTML( $node );
			if ( false === $html_fragment ) {
				continue;
			}

			// Check if this is a nav-link or nav-submenu <li>.
			if (
				'li' === $node->nodeName // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument API.
				&& $node instanceof DOMElement
				&& (
					str_contains( $node->getAttribute( 'class' ), 'wp-block-aggressive-apparel-nav-link' )
					|| str_contains( $node->getAttribute( 'class' ), 'wp-block-aggressive-apparel-nav-submenu' )
				)
			) {
				$menu_items_html .= $html_fragment;
			} else {
				$utility_html .= $html_fragment;
			}
		}
	}
}

// ============================================================================
// Build desktop menubar
// ============================================================================

$menubar_html = sprintf(
	'<ul class="aa-nav__menubar" role="menubar" aria-orientation="horizontal" data-wp-interactive="aggressive-apparel/navigation" data-wp-on--keydown="callbacks.onArrowKey">%s<li class="aa-nav__indicator-wrap" role="none" aria-hidden="true"><span class="aa-nav__indicator"></span></li></ul>',
	$menu_items_html
);

// ============================================================================
// Build toggle button (absorbed from menu-toggle)
// ============================================================================

$toggle_classes = array(
	'aa-nav__toggle',
	'aa-nav__toggle--' . sanitize_html_class( $toggle_icon_style ),
	'aa-nav__toggle--anim-' . sanitize_html_class( $toggle_anim_type ),
);

if ( 'dots' === $toggle_icon_style ) {
	$icon_html = sprintf(
		'<span class="aa-nav__toggle-icon aa-nav__toggle-icon--dots">%s</span>',
		str_repeat( '<span class="aa-nav__toggle-dot"></span>', 3 )
	);
} else {
	$icon_html = sprintf(
		'<span class="aa-nav__toggle-icon">%s</span>',
		str_repeat( '<span class="aa-nav__toggle-bar"></span>', 3 )
	);
}

$toggle_label_html = '';
if ( $show_toggle_label ) {
	$toggle_label_html = sprintf(
		'<span class="aa-nav__toggle-label">%s</span>',
		esc_html( $toggle_label )
	);
}

// Toggle inherits data-wp-interactive and data-wp-context from parent <nav>.
// No own context needed — getContext() returns the nav's shared context.
$toggle_html = sprintf(
	'<button id="%s" class="%s" type="button" aria-expanded="false" aria-controls="%s" aria-label="%s" data-wp-on--click="actions.toggle" data-wp-bind--aria-expanded="state.isOpen" data-wp-class--is-active="state.isOpen" data-wp-on-window--aa-nav-state-change="callbacks.onStateChange">%s%s</button>',
	esc_attr( $toggle_id ),
	esc_attr( implode( ' ', $toggle_classes ) ),
	esc_attr( $panel_id ),
	esc_attr( $toggle_label ),
	$icon_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML.
	$toggle_label_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
);

// ============================================================================
// Build mobile panel (absorbed from navigation-panel)
// Portaled outside .wp-site-blocks via wp_footer to escape stacking contexts.
// ============================================================================

$panel_classes = array(
	'aa-nav__panel',
	'aa-nav__panel--' . sanitize_html_class( $panel_position ),
	'aa-nav__panel--' . sanitize_html_class( $panel_animation_style ),
);

$panel_style_parts = array(
	sprintf( '--panel-width: %s', esc_attr( $panel_width ) ),
	'pointer-events: none',
);

if ( $panel_bg_color ) {
	$panel_style_parts[] = sprintf( '--panel-bg: %s', esc_attr( $panel_bg_color ) );
}
if ( $panel_text_color ) {
	$panel_style_parts[] = sprintf( '--panel-color: %s', esc_attr( $panel_text_color ) );
}
if ( $panel_hover_color ) {
	$panel_style_parts[] = sprintf( '--panel-link-hover-color: %s', esc_attr( $panel_hover_color ) );
}
if ( $panel_hover_bg ) {
	$panel_style_parts[] = sprintf( '--panel-link-hover-bg: %s', esc_attr( $panel_hover_bg ) );
}
if ( $panel_overlay_color || 50 !== (int) $panel_overlay_opacity ) {
	$ov_color            = ! empty( $panel_overlay_color ) ? $panel_overlay_color : '#000000';
	$ov_opacity          = ( (int) $panel_overlay_opacity / 100 );
	$panel_style_parts[] = sprintf(
		'--panel-overlay-bg: color-mix(in srgb, %s %s%%, transparent)',
		esc_attr( $ov_color ),
		esc_attr( (string) ( $ov_opacity * 100 ) )
	);
}
// Propagate indicator color so the mobile vertical accent bar inherits it.
if ( $indicator_color ) {
	$panel_style_parts[] = sprintf( '--indicator-color: %s', esc_attr( $indicator_color ) );
}

$panel_inline_style = implode( '; ', $panel_style_parts ) . ';';

// Overlay.
$overlay_html = '';
if ( $show_panel_overlay ) {
	$overlay_html = '<div class="aa-nav__panel-overlay" data-wp-on--click="actions.close" aria-hidden="true" role="presentation"></div>';
}

// Close button.
$close_button_html = sprintf(
	'<button type="button" class="aa-nav__panel-close" aria-label="%s" data-wp-on--click="actions.close"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg></button>',
	esc_attr__( 'Close menu', 'aggressive-apparel' )
);

// Auto-sync: clone desktop menu items for mobile panel.
// Transform dropdown/mega → drilldown for mobile.
// Prefix IDs with "mobile-" to avoid duplicate DOM IDs.
$mobile_menu_html = $menu_items_html;
if ( ! empty( $mobile_menu_html ) ) {
	$mobile_menu_html = str_replace(
		array(
			'wp-block-aggressive-apparel-nav-submenu--dropdown',
			'wp-block-aggressive-apparel-nav-submenu--mega',
		),
		'wp-block-aggressive-apparel-nav-submenu--drilldown',
		$mobile_menu_html
	);

	// Prefix submenu IDs to avoid duplicate DOM IDs between desktop and mobile.
	// Matches id="submenu-*" in the cloned panel elements.
	$mobile_menu_html = preg_replace(
		'/id="(submenu-[^"]+)"/',
		'id="mobile-$1"',
		$mobile_menu_html
	);

	// Update submenuId in data-wp-context JSON to match the new mobile IDs.
	$mobile_menu_html = preg_replace(
		'/"submenuId"\s*:\s*"(submenu-[^"]+)"/',
		'"submenuId":"mobile-$1"',
		$mobile_menu_html
	);

	// Update aria-controls references to match new mobile IDs.
	$mobile_menu_html = preg_replace(
		'/aria-controls="(submenu-[^"]+)"/',
		'aria-controls="mobile-$1"',
		$mobile_menu_html
	);

	// Strip desktop hover event bindings — on mobile, focusout triggers
	// onHoverLeave which closes the accordion immediately after opening.
	$mobile_menu_html = preg_replace(
		'/ data-wp-on--(?:mouseenter|mouseleave|focusin|focusout)="[^"]*"/',
		'',
		$mobile_menu_html
	);

	// Fix context values: dropdown/mega → drilldown, hover → click.
	$mobile_menu_html = preg_replace(
		'/"menuType"\s*:\s*"(?:dropdown|mega)"/',
		'"menuType":"drilldown"',
		$mobile_menu_html
	);
	$mobile_menu_html = preg_replace(
		'/"openOn"\s*:\s*"hover"/',
		'"openOn":"click"',
		$mobile_menu_html
	);
}

// Panel content: header + body with mobile indicator.
$panel_content_html = sprintf(
	'<div class="aa-nav__panel-header">%s</div><div class="aa-nav__panel-body"><ul class="aa-nav__panel-menu" role="menu" aria-orientation="vertical" data-wp-on--keydown="callbacks.onArrowKey"><span class="aa-nav__mobile-indicator" aria-hidden="true"></span>%s</ul></div>',
	$close_button_html,
	$mobile_menu_html
);

// Panel is portaled to wp_footer. It gets data-wp-interactive and data-wp-context
// from the portal wrapper (see aggressive_apparel_flush_nav_panels()).
// data-wp-init is on the portal wrapper, not the panel div itself.
//
// IMPORTANT: The overlay is a SIBLING of the panel, not a child. The panel uses
// transform (for slide/push/reveal) which creates a containing block — any
// position:fixed child would be trapped inside the panel's bounds. The overlay
// needs position:fixed relative to the viewport, so it must be outside.
$panel_html = sprintf(
	'%s<div id="%s" class="%s" style="%s" role="dialog" aria-modal="true" aria-label="%s" aria-hidden="true" data-nav-id="%s" data-animation-style="%s" data-position="%s" data-wp-bind--aria-hidden="!state.isOpen" data-wp-class--is-open="state.isOpen" data-wp-class--has-drill-stack="callbacks.hasDrillHistory" data-wp-on-window--aa-nav-state-change="callbacks.onStateChange"><div class="aa-nav__panel-content">%s</div></div>',
	$overlay_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML. Must be outside panel for fixed positioning.
	esc_attr( $panel_id ),
	esc_attr( implode( ' ', $panel_classes ) ),
	$panel_inline_style, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each value escaped above.
	esc_attr__( 'Navigation menu', 'aggressive-apparel' ),
	esc_attr( $nav_id ),
	esc_attr( $panel_animation_style ),
	esc_attr( $panel_position ),
	$panel_content_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML.
);

// ============================================================================
// Buffer the panel for wp_footer output (portal pattern)
// ============================================================================
// The panel is rendered outside .wp-site-blocks so position: fixed is not
// trapped by ancestor stacking contexts (container-type, transform, etc.).

aggressive_apparel_buffer_panel_html( $nav_id, $panel_html );

// ============================================================================
// Compose the nav element
// ============================================================================

$nav_style = '--navigation-breakpoint: ' . esc_attr( (string) $breakpoint ) . 'px;';
if ( $indicator_color ) {
	$nav_style .= ' --indicator-color: ' . esc_attr( $indicator_color ) . ';';
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id'                                     => $nav_id,
		'aria-label'                             => esc_attr( $aria_label ),
		'data-wp-interactive'                    => 'aggressive-apparel/navigation',
		'data-wp-context'                        => $context,
		'data-wp-init'                           => 'callbacks.init',
		'data-wp-on-window--keydown'             => 'callbacks.onEscape',
		'data-wp-on-window--aa-nav-state-change' => 'callbacks.onStateChange',
		'data-wp-class--is-open'                 => 'state.isOpen',
		'data-wp-class--is-mobile'               => 'state.isMobile',
		'style'                                  => $nav_style,
	)
);

// Screen reader announcer.
$announcer_id   = aggressive_apparel_get_announcer_id( $nav_id );
$announcer_html = sprintf(
	'<div id="%s" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>',
	esc_attr( $announcer_id )
);

printf(
	'<nav %s>%s%s%s%s</nav>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$announcer_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML.
	$utility_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks already escaped.
	$menubar_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML.
	$toggle_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML.
);
