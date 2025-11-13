<?php
/**
 * PHPStan Bootstrap
 *
 * Defines constants and loads stubs for static analysis
 *
 * @package Aggressive_Apparel
 */

// Define WordPress constants that PHPStan needs
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

if ( ! defined( 'WP_DEBUG_LOG' ) ) {
	define( 'WP_DEBUG_LOG', true );
}

if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
	define( 'WP_DEBUG_DISPLAY', false );
}

// Theme constants
if ( ! defined( 'AGGRESSIVE_APPAREL_VERSION' ) ) {
	define( 'AGGRESSIVE_APPAREL_VERSION', '0.0.1' );
}

if ( ! defined( 'AGGRESSIVE_APPAREL_DIR' ) ) {
	define( 'AGGRESSIVE_APPAREL_DIR', dirname( __DIR__ ) );
}

if ( ! defined( 'AGGRESSIVE_APPAREL_URI' ) ) {
	define( 'AGGRESSIVE_APPAREL_URI', 'http://localhost/wp-content/themes/aggressive-apparel' );
}

// WooCommerce constants
if ( ! defined( 'WC_ABSPATH' ) ) {
	define( 'WC_ABSPATH', ABSPATH . 'wp-content/plugins/woocommerce/' );
}
