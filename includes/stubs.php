<?php
/**
 * WooCommerce Function Stubs
 *
 * Provides function definitions for IDE/IntelliSense when WooCommerce is not loaded.
 * These are only for development - the actual functions are provided by WooCommerce.
 *
 * @package Aggressive_Apparel
 */

// Prevent loading if WooCommerce is active.
if ( class_exists( 'WooCommerce', false ) ) {
	return;
}

/**
 * Check if current page is product tag archive
 *
 * @return bool
 */
if ( ! function_exists( 'is_product_tag' ) ) {
	function is_product_tag(): bool {
		return false;
	}
}

/**
 * Check if current page is product category archive
 *
 * @return bool
 */
if ( ! function_exists( 'is_product_category' ) ) {
	function is_product_category(): bool {
		return false;
	}
}

/**
 * Check if current page is shop page
 *
 * @return bool
 */
if ( ! function_exists( 'is_shop' ) ) {
	function is_shop(): bool {
		return false;
	}
}

/**
 * Check if current page is single product
 *
 * @return bool
 */
if ( ! function_exists( 'is_product' ) ) {
	function is_product(): bool {
		return false;
	}
}

/**
 * Check if sidebar is active
 *
 * @param string $sidebar_id
 * @return bool
 */
if ( ! function_exists( 'is_active_sidebar' ) ) {
	function is_active_sidebar( string $sidebar_id ): bool {
		return false;
	}
}
