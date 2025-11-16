<?php
/**
 * PHPUnit Bootstrap for WordPress Theme Tests
 *
 * This file loads the WordPress test environment and prepares the theme for testing.
 * When running in wp-env, WordPress core files are available.
 *
 * @package Aggressive_Apparel
 */

// Get WordPress test suite directory
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php\n";
	echo "Please set WP_TESTS_DIR environment variable or run tests in wp-env.\n";
	exit( 1 );
}

// Composer autoloader MUST be loaded first
if ( file_exists( dirname( __DIR__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/autoload.php';
}

// Load theme autoloader before WordPress bootstraps
require_once dirname( __DIR__ ) . '/includes/class-autoloader.php';

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the theme being tested
 */
function _aggressive_apparel_manually_load_environment() {
	// Initialize autoloader
	new Aggressive_Apparel\Autoloader();

	// Get theme directory path
	$theme_dir = dirname( __DIR__ ); // Go up from tests/ to theme root

	// Define theme constants (normally done in functions.php)
	if ( ! defined( 'AGGRESSIVE_APPAREL_VERSION' ) ) {
		define( 'AGGRESSIVE_APPAREL_VERSION', wp_get_theme()->get( 'Version' ) );
		define( 'AGGRESSIVE_APPAREL_DIR', $theme_dir );
		define( 'AGGRESSIVE_APPAREL_URI', get_template_directory_uri() );
	}

	// Load theme helper functions (normally done in functions.php)
	if ( file_exists( $theme_dir . '/includes/helpers.php' ) ) {
		require_once $theme_dir . '/includes/helpers.php';
	}

	// Load the theme
	switch_theme( 'aggressive-apparel' );

	// Initialize theme support early (before wp_loaded)
	$theme_support = new Aggressive_Apparel\Core\Theme_Support();
	$theme_support->init();

	// Initialize theme components (similar to functions.php)
	if ( function_exists( 'aggressive_apparel_init' ) ) {
		aggressive_apparel_init();
	}

	// If WooCommerce is needed, activate it
	if ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
		// Activate WooCommerce plugin
		$plugins = get_option( 'active_plugins', array() );
		if ( ! in_array( 'woocommerce/woocommerce.php', $plugins, true ) ) {
			$plugins[] = 'woocommerce/woocommerce.php';
			update_option( 'active_plugins', $plugins );
		}
	}
}

tests_add_filter( 'muplugins_loaded', '_aggressive_apparel_manually_load_environment' );

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';
