<?php
/**
 * Navigation Panel Block Render
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

// Panels are rendered via wp_footer to ensure they're outside .wp-site-blocks
// for proper push/reveal animations that move the page content.

// Explicitly enqueue block assets since we're using the portal pattern.
// WordPress normally auto-enqueues when block output is detected, but since we
// store the HTML for wp_footer output, we need to manually ensure assets load.
// This includes the panel's own styles AND all child block styles.

// Helper function to enqueue a block's frontend styles.
// @param string $block_name Full block name (e.g., 'aggressive-apparel/panel-header').
$enqueue_block_style = static function ( string $block_name ): void {
	$style_handle = generate_block_asset_handle( $block_name, 'style' );
	if ( wp_style_is( $style_handle, 'registered' ) ) {
		wp_enqueue_style( $style_handle );
		return;
	}

	// Fallback: directly enqueue the CSS file.
	$block_slug = str_replace( 'aggressive-apparel/', '', $block_name );
	$style_path = get_template_directory() . '/build/blocks-interactivity/' . $block_slug . '/style-index.css';
	$style_url  = get_template_directory_uri() . '/build/blocks-interactivity/' . $block_slug . '/style-index.css';
	if ( file_exists( $style_path ) ) {
		wp_enqueue_style(
			'aggressive-apparel-' . $block_slug . '-style',
			$style_url,
			array(),
			filemtime( $style_path )
		);
	}
};

// Enqueue styles for the navigation-panel and all its child blocks.
// Child blocks need explicit enqueuing because the portal pattern means
// WordPress doesn't detect them during normal content rendering.
$panel_blocks = array(
	'aggressive-apparel/navigation-panel',
	'aggressive-apparel/panel-header',
	'aggressive-apparel/panel-body',
	'aggressive-apparel/panel-footer',
	'aggressive-apparel/panel-close-button',
);

foreach ( $panel_blocks as $panel_block_name ) {
	$enqueue_block_style( $panel_block_name );
}

// Enqueue the view script module (for Interactivity API).
// Script modules use a different API than regular scripts in WordPress 6.5+.
$view_script_module_id = generate_block_asset_handle( 'aggressive-apparel/navigation-panel', 'viewScriptModule' );
if ( function_exists( 'wp_enqueue_script_module' ) ) {
	wp_enqueue_script_module( $view_script_module_id );
}

$position            = $attributes['position'] ?? 'right';
$animation_style     = $attributes['animationStyle'] ?? 'slide';
$width               = $attributes['width'] ?? 'min(320px, 85vw)';
$show_overlay        = $attributes['showOverlay'] ?? true;
$link_color          = $attributes['linkColor'] ?? '';
$link_hover_color    = $attributes['linkHoverColor'] ?? '';
$link_bg_color       = $attributes['linkBackgroundColor'] ?? '';
$link_hover_bg_color = $attributes['linkHoverBackgroundColor'] ?? '';

// Get navigation ID and breakpoint from context using shared functions.
$nav_id     = aggressive_apparel_get_nav_id_from_context( $block );
$breakpoint = aggressive_apparel_get_nav_breakpoint_from_context( $block );

// Build panel ID using shared function.
$panel_id = aggressive_apparel_get_panel_id( $nav_id );

// Build context for Interactivity API using shared function.
$context = aggressive_apparel_encode_nav_context(
	aggressive_apparel_build_nav_context( $nav_id, $breakpoint )
);

// Build class list.
$classes = array(
	'wp-block-aggressive-apparel-navigation-panel',
	'wp-block-aggressive-apparel-navigation-panel--' . sanitize_html_class( $position ),
	'wp-block-aggressive-apparel-navigation-panel--' . sanitize_html_class( $animation_style ),
);

// Build inline styles with CSS variables.
// The panel is positioned off-screen via CSS transform when closed,
// so we don't need visibility:hidden which would break animations.
$style_parts = array(
	sprintf( '--panel-width: %s', esc_attr( $width ) ),
	'pointer-events: none',
);

// Add link color variables if set (for panel-body nav items).
if ( $link_color ) {
	$style_parts[] = sprintf( '--panel-link-color: %s', esc_attr( $link_color ) );
}
if ( $link_hover_color ) {
	$style_parts[] = sprintf( '--panel-link-hover-color: %s', esc_attr( $link_hover_color ) );
}
if ( $link_bg_color ) {
	$style_parts[] = sprintf( '--panel-link-bg: %s', esc_attr( $link_bg_color ) );
}
if ( $link_hover_bg_color ) {
	$style_parts[] = sprintf( '--panel-link-hover-bg: %s', esc_attr( $link_hover_bg_color ) );
}

$inline_styles = implode( '; ', $style_parts ) . ';';

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                                  => implode( ' ', $classes ),
		'style'                                  => $inline_styles,
		'id'                                     => esc_attr( $panel_id ),
		'role'                                   => 'dialog',
		'aria-modal'                             => 'true',
		'aria-label'                             => esc_attr__( 'Navigation menu', 'aggressive-apparel' ),
		'aria-hidden'                            => 'true',
		'data-wp-interactive'                    => 'aggressive-apparel/navigation',
		'data-wp-context'                        => $context,
		'data-nav-id'                            => esc_attr( $nav_id ),
		'data-animation-style'                   => esc_attr( $animation_style ),
		'data-position'                          => esc_attr( $position ),
		'data-wp-bind--aria-hidden'              => '!state.isOpen',
		'data-wp-class--is-open'                 => 'state.isOpen',
		'data-wp-class--has-drill-stack'         => 'callbacks.hasDrillHistory',
		'data-wp-watch'                          => 'callbacks.watchPanelState',
		'data-wp-init'                           => 'callbacks.initPanel',
		'data-wp-on-window--aa-nav-state-change' => 'callbacks.onStateChange',
	)
);

// Overlay markup.
$overlay_html = '';
if ( $show_overlay ) {
	$overlay_html = '<div class="wp-block-aggressive-apparel-navigation-panel__overlay" data-wp-on--click="actions.close" aria-hidden="true" role="presentation"></div>';
}

// Build the panel HTML.
// The content comes from InnerBlocks which includes the header, body, and footer groups.
$panel_html = sprintf(
	'<div %s>%s<div class="wp-block-aggressive-apparel-navigation-panel__content">%s</div></div>',
	$wrapper_attributes,
	$overlay_html,
	$content
);

// Note: We intentionally DO NOT call wp_interactivity_process_directives() here.
// The inner blocks ($content) have already been processed during normal block rendering.
// Processing the full panel HTML again would double-process those directives.
// The panel's wrapper directives are handled client-side via the onStateChange callback,
// which listens for the aa-nav-state-change custom event dispatched by the navigation block.

// Register the panel with the global registry.
// Panels are rendered via wp_footer to ensure they're outside .wp-site-blocks.
global $aggressive_apparel_navigation_panels;
if ( ! is_array( $aggressive_apparel_navigation_panels ) ) {
	$aggressive_apparel_navigation_panels = array();
}
$aggressive_apparel_navigation_panels[ $panel_id ] = $panel_html;

// Register the wp_footer action to render all panels (only once).
global $aggressive_apparel_panels_footer_registered;
if ( ! $aggressive_apparel_panels_footer_registered ) {
	$aggressive_apparel_panels_footer_registered = true;

	add_action(
		'wp_footer',
		static function () {
			global $aggressive_apparel_navigation_panels;

			if ( empty( $aggressive_apparel_navigation_panels ) || ! is_array( $aggressive_apparel_navigation_panels ) ) {
				return;
			}

			// Print stylesheets for navigation-panel and all child blocks since wp_head has already run.
			// We use wp_print_styles to output any enqueued styles that haven't been printed yet.
			$panel_blocks = array(
				'aggressive-apparel/navigation-panel',
				'aggressive-apparel/panel-header',
				'aggressive-apparel/panel-body',
				'aggressive-apparel/panel-footer',
				'aggressive-apparel/panel-close-button',
			);

			$styles_to_print = array();

			foreach ( $panel_blocks as $block_name ) {
				$style_handle = generate_block_asset_handle( $block_name, 'style' );

				if ( wp_style_is( $style_handle, 'enqueued' ) && ! wp_style_is( $style_handle, 'done' ) ) {
					$styles_to_print[] = $style_handle;
				} elseif ( ! wp_style_is( $style_handle, 'done' ) ) {
					// Fallback: register the stylesheet directly if not already done.
					$block_slug = str_replace( 'aggressive-apparel/', '', $block_name );
					$style_url  = get_template_directory_uri() . '/build/blocks-interactivity/' . $block_slug . '/style-index.css';
					$style_path = get_template_directory() . '/build/blocks-interactivity/' . $block_slug . '/style-index.css';

					if ( file_exists( $style_path ) ) {
						$fallback_handle = 'aggressive-apparel-' . $block_slug . '-footer-style';
						wp_register_style(
							$fallback_handle,
							$style_url,
							array(),
							(string) filemtime( $style_path )
						);
						$styles_to_print[] = $fallback_handle;
					}
				}
			}

			if ( ! empty( $styles_to_print ) ) {
				wp_print_styles( $styles_to_print );
			}

			// Render all registered panels at the end of body.
			foreach ( $aggressive_apparel_navigation_panels as $panel_html ) {
				echo $panel_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped.
			}
		},
		5 // Priority 5 to render before most other footer content.
	);
}
