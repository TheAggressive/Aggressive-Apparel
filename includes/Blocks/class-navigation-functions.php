<?php
/**
 * Navigation Block Shared Functions
 *
 * Utility functions shared across all navigation block render files.
 * This reduces duplication and ensures consistent behavior.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ============================================================================
// Eagerly enqueue navigation-panel and child block styles on wp_enqueue_scripts.
//
// The navigation-panel block outputs nothing inline (it portals to wp_footer),
// so WordPress's block-style auto-detection never marks it as "used" on the
// page. Styles enqueued from render.php also miss wp_head because template
// parts in the body render after wp_head fires. Enqueueing here (priority 11,
// after block style registration at priority 10) guarantees the panel CSS is in
// wp_head on every page that has the navigation-panel block registered — which
// is always the case for this theme.
//
// Accordion and drilldown styles are included here for the same reason: their
// blocks render inside the portaled panel, so WordPress never auto-detects them
// as "in use" during the main template pass.
//
// The panel's JavaScript (Interactivity store) is loaded via the shared
// @aggressive-apparel/navigation-panel-store module registered below, not a
// per-block view module.
// ============================================================================
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! function_exists( 'WP_Block_Type_Registry' ) && ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return;
		}

		if ( ! \WP_Block_Type_Registry::get_instance()->is_registered( 'aggressive-apparel/navigation-panel' ) ) {
			return;
		}

		wp_enqueue_style( 'aggressive-apparel-navigation-panel-style' );
		wp_enqueue_style( 'aggressive-apparel-nav-submenu-accordion-style' );
		wp_enqueue_style( 'aggressive-apparel-nav-submenu-drilldown-style' );
	},
	11
);

// ============================================================================
// Enqueue the navigation Interactivity stores as shared, standalone script
// modules — ONE per subsystem instead of bundling the store into every block's
// view module (which previously shipped the ~32KB store 2–3× per page).
//
// The blocks have no view module: their render.php emits the data-wp-* directives
// and `supports.interactivity` loads the @wordpress/interactivity runtime, while
// these store modules (enqueued directly as <script type="module">) call store()
// to register the namespace before the runtime hydrates. This mirrors how the
// WooCommerce interactivity features load their stores.
//
// Gated on each block type being registered (always true for this theme — the
// desktop nav is in the header, the panel is portaled to the footer).
// ============================================================================
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! function_exists( 'wp_register_script_module' ) || ! function_exists( 'wp_enqueue_script_module' ) ) {
			return;
		}
		if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return;
		}

		wp_register_script_module(
			'@aggressive-apparel/navigation-store',
			AGGRESSIVE_APPAREL_URI . '/build/interactivity/navigation-store.js',
			array( '@wordpress/interactivity' ),
			AGGRESSIVE_APPAREL_VERSION,
		);
		wp_register_script_module(
			'@aggressive-apparel/navigation-panel-store',
			AGGRESSIVE_APPAREL_URI . '/build/interactivity/navigation-panel-store.js',
			array( '@wordpress/interactivity' ),
			AGGRESSIVE_APPAREL_VERSION,
		);

		$registry = \WP_Block_Type_Registry::get_instance();

		if ( $registry->is_registered( 'aggressive-apparel/navigation' ) ) {
			wp_enqueue_script_module( '@aggressive-apparel/navigation-store' );
		}
		if ( $registry->is_registered( 'aggressive-apparel/navigation-panel' ) ) {
			wp_enqueue_script_module( '@aggressive-apparel/navigation-panel-store' );
		}
	},
	5
);

/**
 * Build the navigation context array for Interactivity API.
 *
 * Creates a consistent context structure used by the navigation block
 * and its child blocks (nav-link, nav-submenu).
 *
 * @param string $nav_id     The navigation instance ID.
 * @param int    $breakpoint The mobile breakpoint in pixels.
 * @param string $open_on    How submenus open: 'hover' or 'click'.
 * @return array The context array for Interactivity API.
 */
function aggressive_apparel_build_nav_context( string $nav_id, int $breakpoint = 1024, string $open_on = 'hover' ): array {
	// Context holds immutable config only. Mutable state (isOpen, isMobile,
	// activeSubmenuId, drillStack) lives in the global store state._panels[navId],
	// shared between the <nav> and the portaled panel outside .wp-site-blocks.
	return array(
		'navId'      => $nav_id,
		'breakpoint' => $breakpoint,
		'openOn'     => $open_on,
	);
}

/**
 * Encode context array as JSON for data-wp-context attribute.
 *
 * Uses JSON_HEX_TAG | JSON_HEX_AMP for safe embedding in HTML attributes.
 *
 * @param array $context The context array.
 * @return string JSON-encoded context string.
 */
function aggressive_apparel_encode_nav_context( array $context ): string {
	$json = wp_json_encode( $context, JSON_HEX_TAG | JSON_HEX_AMP );
	return $json ? $json : '{}';
}

/**
 * Build announcer ID from navigation ID.
 *
 * @param string $nav_id The navigation instance ID.
 * @return string The announcer ID.
 */
function aggressive_apparel_get_announcer_id( string $nav_id ): string {
	return $nav_id ? 'navigation-announcer-' . $nav_id : 'navigation-announcer';
}

/**
 * Mark Nav Link children of mega menus as region content.
 *
 * Block context normally comes from saved parent attributes. Older mega-menu
 * blocks predate the internal isMegaMenu attribute, so this render-time
 * fallback guarantees valid markup without requiring users to re-save them.
 *
 * @param array<string, mixed> $context      Available block context.
 * @param array<string, mixed> $parsed_block Parsed block data.
 * @param WP_Block|null        $parent_block Parent block instance.
 * @return array<string, mixed> Filtered block context.
 */
function aggressive_apparel_add_mega_nav_link_context( array $context, array $parsed_block, ?WP_Block $parent_block ): array {
	if (
		'aggressive-apparel/nav-link' === ( $parsed_block['blockName'] ?? '' )
		&& $parent_block instanceof WP_Block
		&& 'aggressive-apparel/nav-submenu-mega' === $parent_block->name
	) {
		$context['aggressive-apparel/isMegaMenu'] = true;
	}

	return $context;
}
add_filter( 'render_block_context', 'aggressive_apparel_add_mega_nav_link_context', 10, 3 );

/**
 * Reset the request-scoped navigation DOM ID registry.
 *
 * @return void
 */
function aggressive_apparel_reset_navigation_render_ids(): void {
	$GLOBALS['aggressive_apparel_navigation_render_ids'] = array();
}

/**
 * Reserve a unique DOM ID for a rendered navigation component.
 *
 * Saved block attributes may be duplicated by the editor or copied between
 * templates. A request-scoped registry prevents ambiguous aria-controls
 * relationships in the rendered document.
 *
 * @param string $requested_id Requested saved ID.
 * @param string $prefix       Prefix used when no valid saved ID is available.
 * @return string Unique DOM ID.
 */
function aggressive_apparel_reserve_navigation_dom_id( string $requested_id, string $prefix ): string {
	if ( ! isset( $GLOBALS['aggressive_apparel_navigation_render_ids'] ) || ! is_array( $GLOBALS['aggressive_apparel_navigation_render_ids'] ) ) {
		aggressive_apparel_reset_navigation_render_ids();
	}

	$base_id = sanitize_html_class( $requested_id );
	if ( '' === $base_id ) {
		$base_id = wp_unique_id( sanitize_html_class( $prefix ) );
	}

	$candidate = $base_id;
	$suffix    = 2;

	while ( isset( $GLOBALS['aggressive_apparel_navigation_render_ids'][ $candidate ] ) ) {
		$candidate = $base_id . '-' . $suffix;
		++$suffix;
	}

	$GLOBALS['aggressive_apparel_navigation_render_ids'][ $candidate ] = true;

	return $candidate;
}

/**
 * Render an automatic link to the submenu parent destination.
 *
 * Submenu triggers use their primary click to disclose content, so a visible
 * destination link keeps a configured parent URL reachable.
 *
 * @param string $url          Parent destination URL.
 * @param string $label        Parent navigation label.
 * @param string $class_name   CSS class applied to the anchor.
 * @param bool   $as_menu_item Whether to wrap the anchor in a menu item.
 * @return string Rendered link or an empty string when no URL is configured.
 */
function aggressive_apparel_get_nav_view_all_link( string $url, string $label, string $class_name, bool $as_menu_item = true ): string {
	$url = trim( $url );
	if ( '' === $url ) {
		return '';
	}

	/* translators: %s: navigation submenu label. */
	$link_label = sprintf( __( 'View all in %s', 'aggressive-apparel' ), $label );
	$role       = $as_menu_item ? ' role="menuitem"' : '';
	$anchor     = sprintf(
		'<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
		esc_attr( $class_name ),
		esc_url( $url ),
		$role,
		esc_html( $link_label )
	);

	if ( ! $as_menu_item ) {
		return $anchor;
	}

	return '<li role="none">' . $anchor . '</li>';
}

/**
 * Parse rendered inner-block HTML into its top-level nodes.
 *
 * @param string $content Rendered inner-block content.
 * @return list<array{node: DOMNode, html: string, classes: list<string>}>|null
 *     Top-level nodes in source order, or null when the markup can't be parsed.
 */
function aggressive_apparel_parse_nav_fragment( string $content ): ?array {
	$previous = libxml_use_internal_errors( true );
	$document = new DOMDocument();
	$loaded   = $document->loadHTML(
		'<?xml encoding="utf-8" ?><div id="aa-nav-panel-parser-root">' . $content . '</div>',
		LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
	);
	$root     = $loaded ? $document->getElementById( 'aa-nav-panel-parser-root' ) : null;

	$nodes = array();
	if ( $root ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
		foreach ( $root->childNodes as $child ) {
			$html = $document->saveHTML( $child );
			if ( false === $html ) {
				continue;
			}

			$classes = array();
			if ( $child instanceof DOMElement ) {
				$classes = preg_split( '/\s+/', trim( $child->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY );
				$classes = false === $classes ? array() : $classes;
			}

			$nodes[] = array(
				'node'    => $child,
				'html'    => $html,
				'classes' => $classes,
			);
		}
	}

	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	return $root ? $nodes : null;
}

/**
 * Get the serialized inner HTML of a parsed node.
 *
 * @param DOMNode $node Parsed node.
 * @return string Inner HTML.
 */
function aggressive_apparel_nav_node_inner_html( DOMNode $node ): string {
	$html = '';
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
	foreach ( $node->childNodes as $child ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
		$inner = $node->ownerDocument?->saveHTML( $child );
		if ( is_string( $inner ) ) {
			$html .= $inner;
		}
	}
	return $html;
}

/**
 * Whether a parsed node is a navigation menu item (nav-link / nav-submenu `<li>`).
 *
 * @param array{node: DOMNode, html: string, classes: list<string>} $node Parsed node.
 * @return bool
 */
function aggressive_apparel_is_nav_menu_item( array $node ): bool {
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
	if ( 'li' !== strtolower( $node['node']->nodeName ) ) {
		return false;
	}

	foreach ( $node['classes'] as $class_name ) {
		if ( 'wp-block-aggressive-apparel-nav-link' === $class_name || str_starts_with( $class_name, 'wp-block-aggressive-apparel-nav-submenu' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Add a parsed node to an ordered list of menu / content sections.
 *
 * Menu items join a menu section and anything else a content section;
 * consecutive nodes of the same kind share one section.
 *
 * @param list<array{type: string, html: string}>                   $sections Sections, appended in place.
 * @param array{node: DOMNode, html: string, classes: list<string>} $node     Parsed node.
 */
function aggressive_apparel_append_nav_section( array &$sections, array $node ): void {
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API property.
	if ( XML_ELEMENT_NODE !== $node['node']->nodeType && '' === trim( $node['html'] ) ) {
		return;
	}

	$type = aggressive_apparel_is_nav_menu_item( $node ) ? 'menu' : 'content';

	$last = array_key_last( $sections );
	if ( null !== $last && $type === $sections[ $last ]['type'] ) {
		$sections[ $last ]['html'] .= $node['html'];
		return;
	}

	$sections[] = array(
		'type' => $type,
		'html' => $node['html'],
	);
}

/**
 * Split rendered navigation children into ordered menu / content sections.
 *
 * @param string $content Rendered inner-block content.
 * @return list<array{type: string, html: string}> Sections in source order.
 */
function aggressive_apparel_split_nav_sections( string $content ): array {
	if ( '' === trim( $content ) ) {
		return array();
	}

	$nodes = aggressive_apparel_parse_nav_fragment( $content );
	if ( null === $nodes ) {
		return array(
			array(
				'type' => 'content',
				'html' => $content,
			),
		);
	}

	$sections = array();
	foreach ( $nodes as $node ) {
		aggressive_apparel_append_nav_section( $sections, $node );
	}
	return $sections;
}

/**
 * Prepend menu items (e.g. a "View all" link) to the leading menu section.
 *
 * Starts a new leading menu section when the first section is content. An
 * empty submenu still yields one empty menu section so it keeps its list.
 *
 * @param list<array{type: string, html: string}> $sections Ordered sections.
 * @param string                                  $html     Menu item HTML.
 * @return list<array{type: string, html: string}>
 */
function aggressive_apparel_prepend_nav_menu_html( array $sections, string $html ): array {
	if ( '' === $html && ! empty( $sections ) ) {
		return $sections;
	}

	if ( isset( $sections[0] ) && 'menu' === $sections[0]['type'] ) {
		$sections[0]['html'] = $html . $sections[0]['html'];
		return $sections;
	}

	array_unshift(
		$sections,
		array(
			'type' => 'menu',
			'html' => $html,
		)
	);
	return $sections;
}

/**
 * Render ordered menu / content sections.
 *
 * @param list<array{type: string, html: string}> $sections      Ordered sections.
 * @param string                                  $menu_open_tag Opening `<ul …>` tag for each menu section.
 * @param string                                  $content_class Class for each content section wrapper.
 * @return string Rendered HTML.
 */
function aggressive_apparel_render_nav_sections( array $sections, string $menu_open_tag, string $content_class ): string {
	$html = '';
	foreach ( $sections as $section ) {
		$html .= 'menu' === $section['type']
			? $menu_open_tag . $section['html'] . '</ul>'
			: '<div class="' . esc_attr( $content_class ) . '">' . $section['html'] . '</div>';
	}
	return $html;
}

/**
 * Render a panel submenu's links and content blocks in editor order.
 *
 * @param string $content       Rendered inner blocks.
 * @param string $view_all_html "View all" menu item that leads the first list.
 * @param string $label         Submenu label, the lists' accessible name.
 * @param string $base_class    Block class; lists get `__panel-inner`, content `__panel-section`.
 * @return string Rendered HTML.
 */
function aggressive_apparel_render_nav_submenu_sections( string $content, string $view_all_html, string $label, string $base_class ): string {
	return aggressive_apparel_render_nav_sections(
		aggressive_apparel_prepend_nav_menu_html( aggressive_apparel_split_nav_sections( $content ), $view_all_html ),
		sprintf( '<ul class="%s__panel-inner" role="menu" aria-label="%s">', esc_attr( $base_class ), esc_attr( $label ) ),
		$base_class . '__panel-section'
	);
}

/**
 * Resolve a block's Block spacing (blockGap) value to CSS.
 *
 * The navigation blocks render custom markup without layout support, so core
 * never prints their gap. Render callbacks expose it as a custom property.
 *
 * @param array<string, mixed> $attributes Block attributes.
 * @return string CSS value, or '' when unset or unsafe.
 */
function aggressive_apparel_get_nav_block_gap( array $attributes ): string {
	$gap = $attributes['style']['spacing']['blockGap'] ?? '';
	if ( is_array( $gap ) ) {
		$gap = $gap['top'] ?? '';
	}
	$gap = aggressive_apparel_safe_css_value( $gap );

	return (string) preg_replace( '/^var:preset\|([^|]+)\|(.+)$/', 'var(--wp--preset--$1--$2)', $gap );
}

/**
 * A single CSS value safe to interpolate into an inline `style` declaration.
 *
 * Rejects anything that could end the declaration or open a new one (`;`,
 * braces, quotes), so a block attribute can't smuggle extra properties in.
 *
 * @param mixed $value Candidate value, e.g. `min(320px, 85vw)`.
 * @return string Trimmed value, or '' when unsafe.
 */
function aggressive_apparel_safe_css_value( $value ): string {
	if ( ! is_string( $value ) || ! preg_match( '/^[\w\s().,%+*\/|:-]+$/', $value ) ) {
		return '';
	}

	return trim( $value );
}

/**
 * Partition navigation-panel children into semantic regions.
 *
 * Header and footer slot wrappers are extracted into their dedicated regions.
 * Everything else becomes ordered menu / content sections (see
 * aggressive_apparel_append_nav_section()), so the front end keeps the order
 * the blocks have in the editor.
 *
 * @param string $content Rendered inner-block content.
 * @return array{
 *     sections: list<array{type: string, html: string}>,
 *     panel_header_html: string,
 *     panel_footer_html: string,
 *     panel_header_classes: string,
 *     panel_header_style: string,
 *     panel_footer_classes: string,
 *     panel_footer_style: string
 * }
 */
function aggressive_apparel_partition_nav_panel_content( string $content ): array {
	$parts = array(
		'sections'             => array(),
		'panel_header_html'    => '',
		'panel_footer_html'    => '',
		'panel_header_classes' => '',
		'panel_header_style'   => '',
		'panel_footer_classes' => '',
		'panel_footer_style'   => '',
	);

	if ( '' === trim( $content ) ) {
		return $parts;
	}

	$nodes = aggressive_apparel_parse_nav_fragment( $content );
	if ( null === $nodes ) {
		$parts['sections'][] = array(
			'type' => 'content',
			'html' => $content,
		);
		return $parts;
	}

	foreach ( $nodes as $node ) {
		$element = $node['node'];

		if ( $element instanceof DOMElement && in_array( 'wp-block-aggressive-apparel-nav-panel-header', $node['classes'], true ) ) {
			$parts['panel_header_classes'] = implode( ' ', $node['classes'] );
			$parts['panel_header_style']   = $element->getAttribute( 'style' );
			$parts['panel_header_html']   .= aggressive_apparel_nav_node_inner_html( $element );
			continue;
		}

		if ( $element instanceof DOMElement && in_array( 'wp-block-aggressive-apparel-nav-panel-footer', $node['classes'], true ) ) {
			$parts['panel_footer_classes'] = implode( ' ', $node['classes'] );
			$parts['panel_footer_style']   = $element->getAttribute( 'style' );
			$parts['panel_footer_html']   .= aggressive_apparel_nav_node_inner_html( $element );
			continue;
		}

		aggressive_apparel_append_nav_section( $parts['sections'], $node );
	}

	return $parts;
}

// ============================================================================
// Navigation Panel block (navigation-panel / navigation-trigger)
// ============================================================================
// The mobile panel is now its own block (aggressive-apparel/navigation-panel)
// placed in the Mobile Navigation template part and opened by a trigger block
// (aggressive-apparel/navigation-trigger). State is keyed by panelSlug and the
// panel is portaled to wp_footer to escape ancestor stacking contexts.

/**
 * Auto-render the mobile navigation panel template part.
 *
 * Called from the desktop navigation block's render so the mobile panel is
 * placed on the frontend automatically — no manually positioned
 * `core/template-part` block required. The part holds an
 * aggressive-apparel/navigation-panel block which buffers itself to wp_footer
 * via the portal pattern, so this emits nothing inline (we discard do_blocks'
 * empty return).
 *
 * Idempotent per request: the same part slug is only rendered once even when
 * several navigation blocks share it. Output is additionally deduplicated by
 * panel slug in aggressive_apparel_buffer_nav_panel_html(), so a manually
 * placed template part with the same panel never double-prints.
 *
 * @param string $part_slug Template part slug (e.g. 'mobile-nav').
 */
function aggressive_apparel_render_mobile_nav_part( string $part_slug ): void {
	$part_slug = sanitize_title( $part_slug );
	if ( '' === $part_slug ) {
		return;
	}

	static $rendered = array();
	if ( isset( $rendered[ $part_slug ] ) ) {
		return;
	}
	$rendered[ $part_slug ] = true;

	if ( ! function_exists( 'get_block_template' ) ) {
		return;
	}

	$template_part = get_block_template( get_stylesheet() . '//' . $part_slug, 'wp_template_part' );
	if ( $template_part instanceof WP_Block_Template && ! empty( $template_part->content ) ) {
		// Renders the navigation-panel block inside, which buffers itself to
		// wp_footer. The returned string is empty (portal pattern), so discard it.
		do_blocks( $template_part->content );
	}
}

/**
 * Build the panel element ID from a panel slug.
 *
 * @param string $panel_slug The panel slug.
 * @return string The panel element ID.
 */
function aggressive_apparel_get_nav_panel_id( string $panel_slug ): string {
	return sanitize_html_class( $panel_slug ) . '-panel';
}

/**
 * Build the trigger button ID from a panel slug.
 *
 * @param string $panel_slug The panel slug.
 * @return string The trigger button ID.
 */
function aggressive_apparel_get_nav_trigger_id( string $panel_slug ): string {
	return 'nav-trigger-' . sanitize_html_class( $panel_slug );
}

/**
 * Build the announcer element ID for a panel slug.
 *
 * @param string $panel_slug The panel slug.
 * @return string The announcer element ID.
 */
function aggressive_apparel_get_nav_panel_announcer_id( string $panel_slug ): string {
	return sanitize_html_class( $panel_slug ) . '-announcer';
}

/**
 * Buffer navigation-panel HTML for output via wp_footer.
 *
 * The panel is portaled outside .wp-site-blocks so position: fixed is not
 * trapped by ancestor stacking contexts. Each call stores panel HTML keyed by
 * panel slug and registers the flush hook once.
 *
 * @param string $panel_slug The panel slug.
 * @param string $panel_html The fully-built panel HTML.
 */
function aggressive_apparel_buffer_nav_panel_html( string $panel_slug, string $panel_html ): void {
	static $panels = array();
	static $hooked = false;

	$panels[ $panel_slug ] = $panel_html;

	if ( ! $hooked ) {
		$hooked = true;
		add_action(
			'wp_footer',
			static function () use ( &$panels ) {
				aggressive_apparel_flush_nav_panel_blocks( $panels );
			},
			5
		);
	}
}

/**
 * Flush all buffered navigation-panel blocks in wp_footer.
 *
 * Outputs each panel inside a portal wrapper bound to the
 * aggressive-apparel/navigation-panel store, keyed by panelSlug.
 *
 * @param array $panels Associative array of panel_slug => panel_html.
 */
function aggressive_apparel_flush_nav_panel_blocks( array $panels ): void {
	// Output critical CSS once before the first portal. This guarantees the panel
	// is fixed-positioned and off-screen regardless of whether the external
	// stylesheet loaded (the portal block outputs nothing inline so WordPress's
	// block-style auto-detection can miss it, and styles enqueued from render.php
	// miss wp_head because the template part renders after it fires).
	static $critical_css_output = false;
	if ( ! $critical_css_output ) {
		$critical_css_output = true;
		echo aggressive_apparel_trusted_html(
			'<style id="aa-nav-panel-critical">'
			. '.aa-nav-panel__portal{display:contents}'
			. '.aa-nav__panel{position:fixed!important;top:0;bottom:0;z-index:100000;overflow:hidden;'
			. 'width:var(--panel-width,min(320px,85vw));pointer-events:none;'
			. 'background:var(--panel-bg,var(--wp--preset--color--surface-elevated,#fff));'
			. 'color:var(--panel-color,var(--wp--preset--color--foreground,#111));'
			. 'transition:transform .35s cubic-bezier(.22,1,.36,1),opacity .35s cubic-bezier(.22,1,.36,1)}'
			// Full-width on phones (≤640 px). Tablets (641–1023 px) get the configured --panel-width.
			. '@media(max-width:640px){.aa-nav__panel{width:100%!important}}'
			. 'body.admin-bar .aa-nav__panel{top:var(--wp-admin--admin-bar--height,32px)}'
			. '@media(max-width:782px){body.admin-bar .aa-nav__panel{top:46px}}'
			. '.aa-nav__panel--right{right:0;transform:translateX(100%)}'
			. '.aa-nav__panel--left{left:0;transform:translateX(-100%)}'
			. '.aa-nav__panel--fade{left:0;right:0;transform:none;opacity:0}'
			. '.aa-nav__panel--slide.is-open,.aa-nav__panel--push.is-open,.aa-nav__panel--reveal.is-open{transform:translateX(0);pointer-events:auto}'
			. '.aa-nav__panel--fade.is-open{opacity:1;pointer-events:auto}'
			. '.aa-nav__panel-content{display:flex;flex-direction:column;height:100%;overflow:hidden}'
			. '.aa-nav__panel-header{display:flex;align-items:center;flex-shrink:0;padding:.75rem 1rem}'
			. '.aa-nav__panel-header-content{flex:1;min-width:0}'
			. '.aa-nav__panel-close{display:flex;align-items:center;justify-content:center;margin-left:auto;'
			. 'width:44px;height:44px;background:none;border:none;cursor:pointer;padding:0}'
			. '.aa-nav__panel-body{flex:1;overflow:hidden auto}'
			. '.aa-nav__panel-overlay{position:fixed;inset:0;opacity:0;pointer-events:none;z-index:99999;'
			. 'transition:opacity .35s cubic-bezier(.22,1,.36,1)}'
			. '.aa-nav-panel__portal.is-open .aa-nav__panel-overlay{opacity:1;pointer-events:auto}'
			. 'body.has-search-modal-open .aa-nav__panel-overlay,'
			. 'body.has-search-modal-open .aa-nav__panel.is-open{pointer-events:none!important}'
			. '@media(prefers-reduced-motion:reduce){.aa-nav__panel{transition:none}.aa-nav__panel-overlay{transition:none}}'
			. '</style>'
		);

		// Also output a late <link> if the full stylesheet wasn't already printed
		// in wp_head (covers the case where wp_enqueue_style() fired too late).
		$style_handle = 'aggressive-apparel-navigation-panel-style';
		if ( ! wp_style_is( $style_handle, 'done' ) ) {
			$registered = wp_styles()->registered;
			if ( isset( $registered[ $style_handle ] ) && ! empty( $registered[ $style_handle ]->src ) ) {
				// Late fallback: wp_head already fired, so enqueue cannot print a link.
				printf(
					'<link rel="stylesheet" id="%s-css" href="%s">',
					esc_attr( $style_handle ),
					esc_url( $registered[ $style_handle ]->src )
				);
			}
		}
	}

	foreach ( $panels as $panel_slug => $panel_html ) {
		$context = wp_json_encode( array( 'panelSlug' => $panel_slug ), JSON_HEX_TAG | JSON_HEX_AMP );

		// The `hidden` attribute is removed by `callbacks.initPanel` once the
		// Interactivity API boots. This prevents the panel from showing as
		// in-flow content when the CSS hasn't loaded yet (e.g. first paint).
		printf(
			'<div class="aa-nav-panel__portal" hidden data-wp-interactive="aggressive-apparel/navigation-panel" data-wp-context=\'%s\' data-wp-init="callbacks.initPanel" data-wp-class--is-open="state.isOpen" data-wp-on-window--keydown="callbacks.onEscape">%s</div>',
			esc_attr( $context ? $context : '{}' ),
			aggressive_apparel_trusted_html( $panel_html )
		);
	}
}
