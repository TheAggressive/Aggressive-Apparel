<?php
/**
 * Essential Helper Functions
 *
 * Only the most commonly used helper functions
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get asset URI
 *
 * @param string $path Asset path relative to theme root.
 * @return string Full asset URI.
 */
function aggressive_apparel_asset_uri( $path ) {
	return AGGRESSIVE_APPAREL_URI . '/' . ltrim( $path, '/' );
}

/**
 * Get asset path
 *
 * @param string $path Asset path relative to theme root.
 * @return string Full asset path.
 */
function aggressive_apparel_asset_path( $path ) {
	return AGGRESSIVE_APPAREL_DIR . '/' . ltrim( $path, '/' );
}

/**
 * Get trusted theme SVG icon markup.
 *
 * Thin wrapper around Icons::get() so PHPCS can treat the return value as
 * auto-escaped (class methods cannot be registered in customAutoEscapedFunctions).
 *
 * @param string               $icon  Icon name.
 * @param array<string, mixed> $attrs Optional SVG attributes.
 * @return string SVG markup or empty string if icon not found.
 */
function aggressive_apparel_get_icon( string $icon, array $attrs = array() ): string {
	return \Aggressive_Apparel\Core\Icons::get( $icon, $attrs );
}

/**
 * Echo a trusted theme SVG icon.
 *
 * @param string               $icon  Icon name.
 * @param array<string, mixed> $attrs Optional SVG attributes.
 */
function aggressive_apparel_render_icon( string $icon, array $attrs = array() ): void {
	echo aggressive_apparel_get_icon( $icon, $attrs );
}

/**
 * Read a local theme file from an absolute path under the theme directory.
 *
 * Prefer WordPress APIs (wp_json_file_decode, get_block_template) when possible.
 * This helper is for plain text/HTML theme files where no dedicated API exists.
 *
 * @param string $absolute_path Absolute filesystem path.
 * @return string|false File contents, or false on failure / path escape.
 */
function aggressive_apparel_read_theme_file( string $absolute_path ): string|false {
	$theme_root = wp_normalize_path( (string) get_template_directory() );
	$normalized = wp_normalize_path( $absolute_path );

	if ( ! str_starts_with( $normalized, $theme_root . '/' ) && $normalized !== $theme_root ) {
		return false;
	}

	if ( ! is_readable( $normalized ) ) {
		return false;
	}

	// Local theme asset read; WP_Filesystem is unnecessary for trusted theme paths.
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded to theme directory.
	$contents = file_get_contents( $normalized );

	return is_string( $contents ) ? $contents : false;
}

/**
 * Auto-detect the lowest free-shipping threshold from WooCommerce shipping zones.
 *
 * @return float Threshold in store currency, or 0 when none configured.
 */
function aggressive_apparel_free_shipping_threshold(): float {
	return \Aggressive_Apparel\WooCommerce\Free_Shipping::get_threshold();
}
