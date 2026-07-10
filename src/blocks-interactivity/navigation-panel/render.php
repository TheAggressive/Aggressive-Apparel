<?php
/**
 * Navigation Panel Block Render
 *
 * The mobile navigation panel. Lives in the Mobile Navigation template part
 * and is portaled to wp_footer so position: fixed escapes ancestor stacking
 * contexts. Opened/closed by the navigation-trigger block via the shared
 * aggressive-apparel/navigation-panel Interactivity store.
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

$panel_slug      = ! empty( $attributes['panelSlug'] ) ? (string) $attributes['panelSlug'] : 'mobile-nav';
$position        = $attributes['position'] ?? 'right';
$animation_style = $attributes['animationStyle'] ?? 'slide';
$menu_style      = $attributes['menuStyle'] ?? 'panel';
$panel_width     = 'fullscreen' === $menu_style ? '100vw' : ( $attributes['panelWidth'] ?? 'min(320px, 85vw)' );
$show_overlay    = $attributes['showOverlay'] ?? true;

$panel_hover_color = $attributes['panelLinkHoverColor'] ?? '';
$panel_hover_bg    = $attributes['panelLinkHoverBg'] ?? '';
$overlay_color     = $attributes['overlayColor'] ?? '';
$overlay_opacity   = $attributes['overlayOpacity'] ?? 50;
$indicator_color   = $attributes['indicatorColor'] ?? '';

// WordPress stores preset references as "var:preset|category|slug" in block
// attributes. Convert to the CSS custom-property form before output.
// e.g. "var:preset|spacing|6" → "var(--wp--preset--spacing--6)".
$resolve_wp_value = static function ( string $val ): string {
	return (string) preg_replace(
		'/var:preset\|([^|]+)\|(.+)/',
		'var(--wp--preset--$1--$2)',
		$val
	);
};

// Background / text color: read from the native WordPress Color sidebar.
// The panel is portaled outside .wp-site-blocks so the has-*-background-color
// class WordPress would normally generate can't reach it — we convert to a
// CSS variable instead.
$panel_bg_color = '';
if ( ! empty( $attributes['backgroundColor'] ) ) {
	$panel_bg_color = 'var(--wp--preset--color--' . sanitize_html_class( $attributes['backgroundColor'] ) . ')';
} elseif ( isset( $attributes['style']['color']['background'] ) ) {
	$panel_bg_color = $resolve_wp_value( (string) $attributes['style']['color']['background'] );
}

$panel_text_color = '';
if ( ! empty( $attributes['textColor'] ) ) {
	$panel_text_color = 'var(--wp--preset--color--' . sanitize_html_class( $attributes['textColor'] ) . ')';
} elseif ( isset( $attributes['style']['color']['text'] ) ) {
	$panel_text_color = $resolve_wp_value( (string) $attributes['style']['color']['text'] );
}

// Font size from the Typography sidebar.
$panel_font_size = '';
if ( ! empty( $attributes['fontSize'] ) ) {
	$panel_font_size = 'var(--wp--preset--font-size--' . sanitize_html_class( $attributes['fontSize'] ) . ')';
} elseif ( isset( $attributes['style']['typography']['fontSize'] ) ) {
	$panel_font_size = $resolve_wp_value( (string) $attributes['style']['typography']['fontSize'] );
}

// Padding from the Dimensions sidebar — applied to the scrollable panel body.
$panel_body_style = '';
$block_padding    = isset( $attributes['style']['spacing']['padding'] )
	? (array) $attributes['style']['spacing']['padding']
	: array();
if ( ! empty( $block_padding ) ) {
	$padding_parts = array();
	foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
		if ( ! empty( $block_padding[ $side ] ) ) {
			$padding_parts[] = 'padding-' . $side . ': ' . esc_attr( $resolve_wp_value( (string) $block_padding[ $side ] ) );
		}
	}
	$panel_body_style = implode( '; ', $padding_parts );
}

// Extra CSS classes added via the "Additional CSS class(es)" field.
$extra_class = ! empty( $attributes['className'] ) ? ' ' . esc_attr( $attributes['className'] ) : '';

// ============================================================================
// Explicitly enqueue panel styles.
//
// This block outputs nothing inline (all HTML is buffered to wp_footer via the
// portal pattern), so WordPress's automatic block-style detection skips it.
// We enqueue here to guarantee the CSS is present on any page that contains the
// mobile-nav template part. The panel's JS (Interactivity store) is enqueued as
// the shared @aggressive-apparel/navigation-panel-store module in
// includes/Blocks/class-navigation-functions.php.
// ============================================================================

// Block styles are registered by register_block_type_from_metadata() at init.
// The auto-generated handle follows WordPress's naming convention.
wp_enqueue_style( 'aggressive-apparel-navigation-panel-style' );

// ============================================================================
// Generate IDs
// ============================================================================

$panel_id     = aggressive_apparel_get_nav_panel_id( $panel_slug );
$announcer_id = aggressive_apparel_get_nav_panel_announcer_id( $panel_slug );

// ============================================================================
// Seed the panel store state
// ============================================================================

if ( function_exists( 'wp_interactivity_state' ) ) {
	$current_state = wp_interactivity_state( 'aggressive-apparel/navigation-panel' );
	$panels_state  = $current_state['_panels'] ?? array();

	$panels_state[ $panel_slug ] = array(
		'isOpen'          => false,
		'activeSubmenuId' => null,
		'drillStack'      => array(),
	);

	wp_interactivity_state(
		'aggressive-apparel/navigation-panel',
		array( '_panels' => $panels_state )
	);
}

// ============================================================================
// Separate inner block content into menu items, panel header/footer, utility
// ============================================================================

$menu_items_html      = '';
$utility_html         = '';
$panel_header_html    = '';
$panel_footer_html    = '';
$panel_header_classes = '';
$panel_header_style   = '';
$panel_footer_classes = '';
$panel_footer_style   = '';

if ( ! empty( $content ) ) {
	$dom     = new DOMDocument();
	$wrapped = '<div id="aa-nav-panel-parse-root">' . $content . '</div>';
	@$dom->loadHTML( '<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

	$root = $dom->getElementById( 'aa-nav-panel-parse-root' );
	if ( $root ) {
		foreach ( $root->childNodes as $node ) {
			if ( XML_ELEMENT_NODE !== $node->nodeType ) {
				continue;
			}

			$html_fragment = $dom->saveHTML( $node );
			if ( false === $html_fragment ) {
				continue;
			}

			$node_class = $node instanceof DOMElement ? $node->getAttribute( 'class' ) : '';

			if (
				'li' === $node->nodeName
				&& $node instanceof DOMElement
				&& (
					str_contains( $node_class, 'wp-block-aggressive-apparel-nav-link' )
					|| str_contains( $node_class, 'wp-block-aggressive-apparel-nav-submenu' )
				)
			) {
				$menu_items_html .= $html_fragment;
			} elseif ( $node instanceof DOMElement && str_contains( $node_class, 'wp-block-aggressive-apparel-nav-panel-header' ) ) {
				$panel_header_classes = $node_class;
				$panel_header_style   = $node->getAttribute( 'style' );
				$inner                = '';
				foreach ( $node->childNodes as $child ) {
					$child_html = $dom->saveHTML( $child );
					if ( false !== $child_html ) {
						$inner .= $child_html;
					}
				}
				$panel_header_html = $inner;
			} elseif ( $node instanceof DOMElement && str_contains( $node_class, 'wp-block-aggressive-apparel-nav-panel-footer' ) ) {
				$panel_footer_classes = $node_class;
				$panel_footer_style   = $node->getAttribute( 'style' );
				$inner                = '';
				foreach ( $node->childNodes as $child ) {
					$child_html = $dom->saveHTML( $child );
					if ( false !== $child_html ) {
						$inner .= $child_html;
					}
				}
				$panel_footer_html = $inner;
			} else {
				$utility_html .= $html_fragment;
			}
		}
	}
}

// ============================================================================
// Build panel inline styles + classes
// ============================================================================

$panel_classes = array(
	'aa-nav__panel',
	'aa-nav__panel--' . sanitize_html_class( $position ),
	'aa-nav__panel--' . sanitize_html_class( $animation_style ),
);

if ( 'fullscreen' === $menu_style ) {
	$panel_classes[] = 'aa-nav--fullscreen';
}

if ( '' !== $extra_class ) {
	$panel_classes[] = ltrim( $extra_class );
}

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
if ( $panel_font_size ) {
	$panel_style_parts[] = sprintf( 'font-size: %s', esc_attr( $panel_font_size ) );
}
if ( $panel_hover_color ) {
	$panel_style_parts[] = sprintf( '--panel-link-hover-color: %s', esc_attr( $panel_hover_color ) );
}
if ( $panel_hover_bg ) {
	$panel_style_parts[] = sprintf( '--panel-link-hover-bg: %s', esc_attr( $panel_hover_bg ) );
}
// Always emit the overlay background so the configured color and opacity are
// applied even when both are at their defaults (no color + 50% opacity). The
// CSS default may differ from the block attribute default, so we must always
// write an explicit value.
$ov_color            = ! empty( $overlay_color ) ? $overlay_color : '#000000';
$panel_style_parts[] = sprintf(
	'--panel-overlay-bg: color-mix(in srgb, %s %s%%, transparent)',
	esc_attr( $ov_color ),
	esc_attr( (string) (int) $overlay_opacity )
);
if ( $indicator_color ) {
	$panel_style_parts[] = sprintf( '--indicator-color: %s', esc_attr( $indicator_color ) );
}

$panel_inline_style = implode( '; ', $panel_style_parts ) . ';';

// ============================================================================
// Build overlay + close button + menu
// ============================================================================

$overlay_html = '';
if ( $show_overlay ) {
	$overlay_html = '<div class="aa-nav__panel-overlay" data-wp-on--click="actions.close" aria-hidden="true" role="presentation"></div>';
}

$close_button_html = sprintf(
	'<button type="button" class="aa-nav__panel-close" aria-label="%s" data-wp-on--click="actions.close"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg></button>',
	esc_attr__( 'Close menu', 'aggressive-apparel' )
);

// Panel header: optional user content left of the close button.
$panel_header_content_html = '';
if ( ! empty( $panel_header_html ) ) {
	$panel_header_content_html = '<div class="aa-nav__panel-header-content">' . $panel_header_html . '</div>';
}

$panel_header_class = trim( 'aa-nav__panel-header ' . $panel_header_classes );
$panel_header_attr  = sprintf( ' class="%s"', esc_attr( $panel_header_class ) );
if ( '' !== $panel_header_style ) {
	$panel_header_attr .= sprintf( ' style="%s"', esc_attr( $panel_header_style ) );
}

// Panel footer: only rendered when nav-panel-footer has inner content.
$panel_footer_section_html = '';
if ( ! empty( $panel_footer_html ) ) {
	$panel_footer_class = trim( 'aa-nav__panel-footer ' . $panel_footer_classes );
	$panel_footer_attr  = sprintf( ' class="%s"', esc_attr( $panel_footer_class ) );
	if ( '' !== $panel_footer_style ) {
		$panel_footer_attr .= sprintf( ' style="%s"', esc_attr( $panel_footer_style ) );
	}
	$panel_footer_section_html = '<div' . $panel_footer_attr . '>' . $panel_footer_html . '</div>';
}

$utility_section_html = '';
if ( ! empty( $utility_html ) ) {
	$utility_section_html = '<div class="aa-nav__panel-utility">' . $utility_html . '</div>';
}

// Panel content: header + scrollable body (utility + menu) + optional footer.
// Padding from the Dimensions sidebar is applied to the body (scrollable area)
// rather than the panel shell so it doesn't squeeze the header / close button.
$panel_body_attr    = $panel_body_style ? ' style="' . $panel_body_style . '"' : '';
$panel_content_html = sprintf(
	'<div%s>%s%s</div><div class="aa-nav__panel-body"%s>%s<ul class="aa-nav__panel-menu" role="menu" aria-orientation="vertical" data-wp-on--keydown="callbacks.onArrowKey">%s</ul></div>%s',
	aggressive_apparel_trusted_html( $panel_header_attr ),
	aggressive_apparel_trusted_html( $panel_header_content_html ),
	aggressive_apparel_trusted_html( $close_button_html ),
	aggressive_apparel_trusted_html( $panel_body_attr ),
	aggressive_apparel_trusted_html( $utility_section_html ),
	aggressive_apparel_trusted_html( $menu_items_html ),
	aggressive_apparel_trusted_html( $panel_footer_section_html )
);

// Screen reader announcer (lives inside the portaled panel wrapper).
$announcer_html = sprintf(
	'<div id="%s" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>',
	esc_attr( $announcer_id )
);

// ============================================================================
// Build the panel. The overlay is a SIBLING of the panel, not a child, because
// the panel uses transform (slide/push/reveal) which creates a containing block
// that would trap the position:fixed overlay.
// ============================================================================

$panel_html = sprintf(
	'%s%s<div id="%s" class="%s" style="%s" role="dialog" aria-modal="true" aria-label="%s" aria-hidden="true" inert data-panel-slug="%s" data-animation-style="%s" data-position="%s" data-wp-bind--aria-hidden="!state.isOpen" data-wp-bind--inert="!state.isOpen" data-wp-class--is-open="state.isOpen" data-wp-class--has-drill-stack="callbacks.hasDrillHistory"><div class="aa-nav__panel-content">%s</div></div>',
	aggressive_apparel_trusted_html( $announcer_html ),
	aggressive_apparel_trusted_html( $overlay_html ),
	esc_attr( $panel_id ),
	esc_attr( implode( ' ', $panel_classes ) ),
	aggressive_apparel_trusted_html( $panel_inline_style ),
	esc_attr__( 'Navigation menu', 'aggressive-apparel' ),
	esc_attr( $panel_slug ),
	esc_attr( $animation_style ),
	esc_attr( $position ),
	aggressive_apparel_trusted_html( $panel_content_html )
);

// ============================================================================
// Buffer the panel for wp_footer output (portal pattern)
// ============================================================================

if ( function_exists( 'aggressive_apparel_buffer_nav_panel_html' ) ) {
	aggressive_apparel_buffer_nav_panel_html( $panel_slug, $panel_html );
}
