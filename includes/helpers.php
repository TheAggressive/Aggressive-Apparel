<?php
/**
 * Essential Helper Functions
 *
 * Only the most commonly used helper functions
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get theme instance (Bootstrap)
 *
 * @return Aggressive_Apparel\Bootstrap
 */
function aggressive_apparel_theme() {
	return Aggressive_Apparel\Bootstrap::get_instance();
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
 * Check if WooCommerce is active
 *
 * @return bool
 */
function aggressive_apparel_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}
