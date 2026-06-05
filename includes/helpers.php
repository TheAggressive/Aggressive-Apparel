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
