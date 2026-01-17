<?php
/**
 * Navigation Panel Block Render
 *
 * Panels are rendered via wp_footer to ensure they're outside .wp-site-blocks
 * for proper push/reveal animations that move the page content.
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Inner block content.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

// Explicitly enqueue block assets since we're using the portal pattern.
// WordPress normally auto-enqueues when block output is detected, but since we
// store the HTML for wp_footer output, we need to manually ensure assets load.
$block_name = 'aggressive-apparel/navigation-panel';

// Enqueue the block's frontend styles.
// Try the WordPress-generated handle first, then fallback to direct enqueue.
$style_handle = generate_block_asset_handle( $block_name, 'style' );
if ( wp_style_is( $style_handle, 'registered' ) ) {
	wp_enqueue_style( $style_handle );
} else {
	// Fallback: directly enqueue the CSS file.
	$style_path = get_template_directory() . '/build/blocks-interactivity/navigation-panel/style-index.css';
	$style_url  = get_template_directory_uri() . '/build/blocks-interactivity/navigation-panel/style-index.css';
	if ( file_exists( $style_path ) ) {
		wp_enqueue_style(
			'aggressive-apparel-navigation-panel-style',
			$style_url,
			array(),
			filemtime( $style_path )
		);
	}
}

// Enqueue the view script module (for Interactivity API).
// Script modules use a different API than regular scripts in WordPress 6.5+.
$view_script_module_id = generate_block_asset_handle( $block_name, 'viewScriptModule' );
if ( function_exists( 'wp_enqueue_script_module' ) ) {
	wp_enqueue_script_module( $view_script_module_id );
}

$position          = $attributes['position'] ?? 'right';
$animation_style   = $attributes['animationStyle'] ?? 'slide';
$width             = $attributes['width'] ?? 'min(320px, 85vw)';
$show_overlay      = $attributes['showOverlay'] ?? true;
$show_close_button = $attributes['showCloseButton'] ?? true;

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

// Inline styles ensure panel is hidden even if CSS fails to load.
// The stylesheet will override these when loaded via higher specificity.
$inline_styles = sprintf(
	'--panel-width: %s; visibility: hidden; opacity: 0; pointer-events: none;',
	esc_attr( $width )
);

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

// Close button markup.
$close_button_html = '';
if ( $show_close_button ) {
	$close_button_html = sprintf(
		'<button class="wp-block-aggressive-apparel-navigation-panel__close" type="button" aria-label="%s" data-wp-on--click="actions.close">
			<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<line x1="18" y1="6" x2="6" y2="18"></line>
				<line x1="6" y1="6" x2="18" y2="18"></line>
			</svg>
		</button>',
		esc_attr__( 'Close menu', 'aggressive-apparel' )
	);
}

// Drill-down back button (hidden by default, shown via CSS when drill stack has items).
$back_button_html = sprintf(
	'<button class="wp-block-aggressive-apparel-navigation-panel__back" type="button" aria-label="%s" data-wp-on--click="actions.drillBack" data-wp-bind--hidden="!callbacks.hasDrillHistory">
		<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
			<line x1="19" y1="12" x2="5" y2="12"></line>
			<polyline points="12 19 5 12 12 5"></polyline>
		</svg>
		<span>%s</span>
	</button>',
	esc_attr__( 'Go back', 'aggressive-apparel' ),
	esc_html__( 'Back', 'aggressive-apparel' )
);

// Build the panel HTML.
$panel_html = sprintf(
	'<div %s>%s<div class="wp-block-aggressive-apparel-navigation-panel__content">
		<div class="wp-block-aggressive-apparel-navigation-panel__header">%s%s</div>
		<div class="wp-block-aggressive-apparel-navigation-panel__body">%s</div>
	</div></div>',
	$wrapper_attributes,
	$overlay_html,
	$back_button_html,
	$close_button_html,
	$content
);

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

			// Print the stylesheet since wp_head has already run.
			// We use wp_print_styles to output any enqueued styles that haven't been printed yet.
			$style_handle = generate_block_asset_handle( 'aggressive-apparel/navigation-panel', 'style' );
			if ( wp_style_is( $style_handle, 'enqueued' ) && ! wp_style_is( $style_handle, 'done' ) ) {
				wp_print_styles( array( $style_handle ) );
			} elseif ( ! wp_style_is( $style_handle, 'done' ) ) {
				// Fallback: register and print the stylesheet via wp_enqueue_style.
				$style_url  = get_template_directory_uri() . '/build/blocks-interactivity/navigation-panel/style-index.css';
				$style_path = get_template_directory() . '/build/blocks-interactivity/navigation-panel/style-index.css';
				if ( file_exists( $style_path ) ) {
					wp_register_style(
						'aggressive-apparel-navigation-panel-footer-style',
						$style_url,
						array(),
						(string) filemtime( $style_path )
					);
					wp_print_styles( array( 'aggressive-apparel-navigation-panel-footer-style' ) );
				}
			}

			// Render all registered panels at the end of body.
			foreach ( $aggressive_apparel_navigation_panels as $panel_html ) {
				echo $panel_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped.
			}
		},
		5 // Priority 5 to render before most other footer content.
	);
}
