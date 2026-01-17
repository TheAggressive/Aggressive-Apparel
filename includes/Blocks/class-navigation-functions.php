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

/**
 * Build the navigation context array for Interactivity API.
 *
 * Creates a consistent context structure used by navigation, menu-toggle,
 * navigation-panel, and other navigation child blocks.
 *
 * @param string $nav_id     The navigation instance ID.
 * @param int    $breakpoint The mobile breakpoint in pixels.
 * @param string $open_on    How submenus open: 'hover' or 'click'.
 * @return array The context array for Interactivity API.
 */
function aggressive_apparel_build_nav_context( string $nav_id, int $breakpoint = 1024, string $open_on = 'hover' ): array {
	return array(
		'navId'           => $nav_id,
		'breakpoint'      => $breakpoint,
		'openOn'          => $open_on,
		'isOpen'          => false,
		'isMobile'        => false,
		'activeSubmenuId' => null,
		'drillStack'      => array(),
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
 * Get navigation ID from block context or global fallback.
 *
 * Child blocks need access to the parent navigation's ID for state sharing.
 * This function checks block context first, then falls back to the global
 * runtime ID set by the parent navigation block.
 *
 * @param WP_Block $block The block instance.
 * @return string The navigation ID.
 */
function aggressive_apparel_get_nav_id_from_context( WP_Block $block ): string {
	global $aggressive_apparel_current_nav_id;

	// Try block context first (for saved navId attribute).
	$nav_id = $block->context['aggressive-apparel/navigationId'] ?? '';

	// Fall back to global runtime navId from parent navigation.
	if ( empty( $nav_id ) && ! empty( $aggressive_apparel_current_nav_id ) ) {
		$nav_id = $aggressive_apparel_current_nav_id;
	}

	return $nav_id;
}

/**
 * Get navigation breakpoint from block context or global fallback.
 *
 * @param WP_Block $block         The block instance.
 * @param int      $default_value Default breakpoint if not found.
 * @return int The breakpoint in pixels.
 */
function aggressive_apparel_get_nav_breakpoint_from_context( WP_Block $block, int $default_value = 1024 ): int {
	global $aggressive_apparel_current_nav_breakpoint;

	// Try block context first.
	$breakpoint = $block->context['aggressive-apparel/navigationBreakpoint'] ?? null;

	// Fall back to global runtime breakpoint.
	if ( null === $breakpoint && ! empty( $aggressive_apparel_current_nav_breakpoint ) ) {
		$breakpoint = $aggressive_apparel_current_nav_breakpoint;
	}

	return (int) ( $breakpoint ?? $default_value );
}

/**
 * Build panel ID from navigation ID.
 *
 * @param string $nav_id The navigation instance ID.
 * @return string The panel ID.
 */
function aggressive_apparel_get_panel_id( string $nav_id ): string {
	return $nav_id ? $nav_id . '-panel' : 'navigation-panel';
}

/**
 * Build menu toggle ID from navigation ID.
 *
 * @param string $nav_id The navigation instance ID.
 * @return string The toggle ID, or empty string if nav_id is empty.
 */
function aggressive_apparel_get_toggle_id( string $nav_id ): string {
	return $nav_id ? 'menu-toggle-' . $nav_id : '';
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
