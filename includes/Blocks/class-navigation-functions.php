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
// Eagerly enqueue navigation-panel and child block assets on wp_enqueue_scripts.
//
// The navigation-panel block outputs nothing inline (it portals to wp_footer),
// so WordPress's block-style auto-detection never marks it as "used" on the
// page. Styles enqueued from render.php also miss wp_head because template
// parts in the body render after wp_head fires. Enqueueing here (priority 11,
// after block style registration at priority 10) guarantees the panel CSS and
// view module are in wp_head on every page that has the navigation-panel block
// registered — which is always the case for this theme.
//
// Accordion and drilldown styles are included here for the same reason: their
// blocks render inside the portaled panel, so WordPress never auto-detects them
// as "in use" during the main template pass.
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

		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module( '@aggressive-apparel/navigation-panel/view' );
		}
	},
	11
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

// ============================================================================
// Navigation Panel block (navigation-panel / navigation-trigger)
// ============================================================================
// The mobile panel is now its own block (aggressive-apparel/navigation-panel)
// placed in the Mobile Navigation template part and opened by a trigger block
// (aggressive-apparel/navigation-trigger). State is keyed by panelSlug and the
// panel is portaled to wp_footer to escape ancestor stacking contexts.

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
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static CSS literal, no user input.
		echo '<style id="aa-nav-panel-critical">'
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
			. '@media(prefers-reduced-motion:reduce){.aa-nav__panel{transition:none}.aa-nav__panel-overlay{transition:none}}'
			. '</style>';

		// Also output a late <link> if the full stylesheet wasn't already printed
		// in wp_head (covers the case where wp_enqueue_style() fired too late).
		$style_handle = 'aggressive-apparel-navigation-panel-style';
		if ( ! wp_style_is( $style_handle, 'done' ) ) {
			$registered = wp_styles()->registered;
			if ( isset( $registered[ $style_handle ] ) && ! empty( $registered[ $style_handle ]->src ) ) {
				// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Late fallback: wp_head already fired so wp_enqueue_style cannot output a <link> tag.
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
			$panel_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Panel HTML already escaped in render.php.
		);
	}
}
