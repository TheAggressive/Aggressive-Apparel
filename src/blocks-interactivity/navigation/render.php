<?php
/**
 * Navigation Block Render (v3)
 *
 * Desktop navigation: a horizontal menubar with a sliding indicator and
 * hover/click submenus. The mobile panel and its trigger are now separate
 * blocks (navigation-panel, navigation-trigger). The trigger renders itself
 * and arrives here as utility content.
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

$indicator_color = $attributes['indicatorColor'] ?? '';

// ============================================================================
// Generate IDs
// ============================================================================

$nav_id = ! empty( $attributes['navId'] ) ? $attributes['navId'] : wp_unique_id( 'nav-' );

// ============================================================================
// Build Interactivity API context
// ============================================================================

$context = aggressive_apparel_encode_nav_context(
	aggressive_apparel_build_nav_context( $nav_id, $breakpoint, $open_on )
);

// Seed the global store with this nav's mutable state so the <nav> and its
// submenus share reactive state through state._navs[navId].
if ( function_exists( 'wp_interactivity_state' ) ) {
	$current_state = wp_interactivity_state( 'aggressive-apparel/navigation' );
	$navs_state    = $current_state['_navs'] ?? array();

	$navs_state[ $nav_id ] = array(
		'isMobile'        => false,
		'activeSubmenuId' => null,
	);

	wp_interactivity_state(
		'aggressive-apparel/navigation',
		array( '_navs' => $navs_state )
	);
}

// ============================================================================
// Separate inner block content into menu items vs utility blocks
// ============================================================================
// Inner blocks produce <li> elements (nav-link, nav-submenu) and other blocks
// (site-logo, navigation-trigger, search, buttons, etc.). Menu items go into
// <ul role="menubar"> and everything else stays as utility content.

$menu_items_html = '';
$utility_html    = '';

if ( ! empty( $content ) ) {
	$dom     = new DOMDocument();
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

			$node_class = $node instanceof DOMElement ? $node->getAttribute( 'class' ) : '';

			if (
				'li' === $node->nodeName // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMDocument API.
				&& $node instanceof DOMElement
				&& (
					str_contains( $node_class, 'wp-block-aggressive-apparel-nav-link' )
					|| str_contains( $node_class, 'wp-block-aggressive-apparel-nav-submenu' )
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
// Compose the nav element
// ============================================================================

// The mobile breakpoint is applied by JS (matchMedia on the `breakpoint`
// attribute), so it is intentionally not emitted as a CSS variable here.
$nav_style = '';
if ( $indicator_color ) {
	$nav_style .= '--indicator-color: ' . esc_attr( $indicator_color ) . ';';
}

// Forward blockGap to --navigation-gap so the menubar item spacing responds to
// the editor's block gap control.
$block_gap = $block->parsed_block['attrs']['style']['spacing']['blockGap'] ?? null;
if ( null !== $block_gap ) {
	if ( str_starts_with( $block_gap, 'var:preset|spacing|' ) ) {
		$gap_slug  = str_replace( 'var:preset|spacing|', '', $block_gap );
		$gap_value = '0' === $gap_slug ? '0' : 'var(--wp--preset--spacing--' . esc_attr( $gap_slug ) . ')';
	} else {
		$gap_value = esc_attr( $block_gap );
	}
	$nav_style .= ' --navigation-gap: ' . $gap_value . ';';
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id'                         => $nav_id,
		'aria-label'                 => esc_attr( $aria_label ),
		'data-wp-interactive'        => 'aggressive-apparel/navigation',
		'data-wp-context'            => $context,
		'data-wp-init'               => 'callbacks.init',
		'data-wp-on-window--keydown' => 'callbacks.onEscape',
		'data-wp-class--is-mobile'   => 'state.isMobile',
		'style'                      => trim( $nav_style ),
	)
);

// Screen reader announcer.
$announcer_id   = aggressive_apparel_get_announcer_id( $nav_id );
$announcer_html = sprintf(
	'<div id="%s" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>',
	esc_attr( $announcer_id )
);

printf(
	'<nav %s>%s%s%s</nav>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$announcer_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML.
	$utility_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inner blocks already escaped.
	$menubar_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML.
);
