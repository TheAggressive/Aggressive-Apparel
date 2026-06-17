<?php
/**
 * Navigation Trigger Block Render
 *
 * A standalone hamburger/toggle button placed inside the navigation block.
 * Visible on mobile, hidden on desktop via CSS. Toggles the navigation-panel
 * identified by panelSlug through the shared
 * aggressive-apparel/navigation-panel Interactivity store.
 *
 * @package Aggressive_Apparel
 *
 * @var array    $attributes Block attributes.
 * @var WP_Block $block      Block instance.
 */

declare(strict_types=1);

// ============================================================================
// Extract attributes
// ============================================================================

$icon_style  = $attributes['iconStyle'] ?? 'hamburger';
$anim_type   = $attributes['animationType'] ?? 'to-x';
$label       = $attributes['label'] ?? __( 'Menu', 'aggressive-apparel' );
$show_label  = $attributes['showLabel'] ?? false;
$panel_slug  = ! empty( $attributes['panelSlug'] ) ? (string) $attributes['panelSlug'] : 'mobile-nav';

// ============================================================================
// Generate IDs
// ============================================================================

$panel_id   = aggressive_apparel_get_nav_panel_id( $panel_slug );
$trigger_id = aggressive_apparel_get_nav_trigger_id( $panel_slug );

// ============================================================================
// Seed the panel store state so the trigger's bindings have an initial value
// even if the panel block renders later in the page (or in a different part).
// ============================================================================

if ( function_exists( 'wp_interactivity_state' ) ) {
	$current_state = wp_interactivity_state( 'aggressive-apparel/navigation-panel' );
	$panels_state  = $current_state['_panels'] ?? array();

	if ( ! isset( $panels_state[ $panel_slug ] ) ) {
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
}

// ============================================================================
// Build the icon HTML
// ============================================================================

if ( 'dots' === $icon_style ) {
	$icon_html = sprintf(
		'<span class="aa-nav-trigger__icon aa-nav-trigger__icon--dots">%s</span>',
		str_repeat( '<span class="aa-nav-trigger__dot"></span>', 3 )
	);
} else {
	$icon_html = sprintf(
		'<span class="aa-nav-trigger__icon">%s</span>',
		str_repeat( '<span class="aa-nav-trigger__bar"></span>', 3 )
	);
}

$label_html = '';
if ( $show_label ) {
	$label_html = sprintf(
		'<span class="aa-nav-trigger__label">%s</span>',
		esc_html( $label )
	);
}

// ============================================================================
// Build the button
// ============================================================================

$context = wp_json_encode( array( 'panelSlug' => $panel_slug ), JSON_HEX_TAG | JSON_HEX_AMP );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'id'                          => $trigger_id,
		'class'                       => implode(
			' ',
			array(
				'aa-nav-trigger',
				'aa-nav-trigger--' . sanitize_html_class( $icon_style ),
				'aa-nav-trigger--anim-' . sanitize_html_class( $anim_type ),
			)
		),
		'type'                        => 'button',
		'aria-expanded'               => 'false',
		'aria-controls'               => $panel_id,
		'aria-label'                  => esc_attr( $label ),
		'data-wp-interactive'         => 'aggressive-apparel/navigation-panel',
		'data-wp-context'             => $context ? $context : '{}',
		'data-wp-on--click'           => 'actions.toggle',
		'data-wp-bind--aria-expanded' => 'state.isOpen',
		'data-wp-class--is-active'    => 'state.isOpen',
	)
);

printf(
	'<button %s>%s%s</button>',
	$wrapper_attributes, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes.
	$icon_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe HTML.
	$label_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
);
