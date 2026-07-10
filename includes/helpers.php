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
 * Mark HTML as trusted for escaped output (PHPCS EscapeOutput).
 *
 * Use only when the markup is already safe:
 * - InnerBlocks `$content` from the block editor
 * - Strings built with esc_html / esc_attr / esc_url in the same scope
 * - Theme SVG from Icons / Icon_Block
 * - Static theme chrome (critical CSS, announcer shells)
 *
 * Registered in phpcs.xml.dist as customAutoEscapedFunctions so call sites
 * do not need phpcs:ignore. Never pass unsanitized request/user input.
 *
 * @param string $html Already-escaped or otherwise trusted HTML.
 * @return string Same HTML, safe to echo/printf under WPCS EscapeOutput.
 */
function aggressive_apparel_trusted_html( string $html ): string {
	return $html;
}

/**
 * Product rating marks markup (brand icon fill).
 *
 * Thin wrapper so PHPCS can treat Rating::stars() as auto-escaped.
 *
 * @param float $rating Average rating 0–5.
 * @return string Accessible rating HTML.
 */
function aggressive_apparel_rating_stars( float $rating ): string {
	return \Aggressive_Apparel\WooCommerce\Rating::stars( $rating );
}

/**
 * Brand / library icon SVG for the icon block.
 *
 * @param string    $slug Icon slug.
 * @param int|float $size Pixel size.
 * @return string SVG markup or empty string.
 */
function aggressive_apparel_icon_block_svg( string $slug, int|float $size = 48 ): string {
	return \Aggressive_Apparel\Blocks\Icon_Block::render_svg( $slug, $size );
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

/**
 * Write a theme log line (single allowed error_log sink for PHPCS).
 *
 * Callers decide when to invoke (e.g. WP_DEBUG). Do not pass secrets.
 *
 * @param string               $message Log message.
 * @param array<string, mixed> $context Optional structured context.
 */
function aggressive_apparel_debug_log( string $message, array $context = array() ): void {
	$line = '[Aggressive Apparel] ' . $message;
	if ( array() !== $context ) {
		$encoded = wp_json_encode( $context );
		if ( is_string( $encoded ) ) {
			$line .= ' ' . $encoded;
		}
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Single allowed debug sink.
	error_log( $line );
}

/**
 * Read and unslash a POST value for a dedicated sanitizer.
 *
 * PHPCS cannot see custom sanitize_* methods on the next line; this helper
 * owns the one InputNotSanitized exception. Always pass the return value
 * through a sanitizer before persistence or output.
 *
 * @param string $key POST key.
 * @return mixed|null Unslashed value, or null when unset.
 */
function aggressive_apparel_unslash_post( string $key ): mixed {
	if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Caller verifies nonce before use.
		return null;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing -- Caller sanitizes and verifies nonce.
	return wp_unslash( $_POST[ $key ] );
}
