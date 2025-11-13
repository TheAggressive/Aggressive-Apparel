<?php
/**
 * Aggressive Apparel Theme Functions
 *
 * Main functions file that bootstraps the theme
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define theme constants.
define( 'AGGRESSIVE_APPAREL_VERSION', wp_get_theme()->get( 'Version' ) );
define( 'AGGRESSIVE_APPAREL_DIR', get_template_directory() );
define( 'AGGRESSIVE_APPAREL_URI', get_template_directory_uri() );

/**
 * Autoloader
 */
require_once AGGRESSIVE_APPAREL_DIR . '/includes/class-autoloader.php';
new Aggressive_Apparel\Autoloader();

/**
 * Initialize theme
 *
 * Bootstrap all theme components following Single Responsibility Principle.
 * Each component is focused on one specific task.
 */
function aggressive_apparel_init() {
	// Initialize the Bootstrap class (handles all component initialization).
	Aggressive_Apparel\Bootstrap::get_instance();

	/**
	 * Hook: After theme initialization
	 *
	 * @since 1.0.0
	 */
	do_action( 'aggressive_apparel_init' );
}
add_action( 'after_setup_theme', 'aggressive_apparel_init', 5 );

/**
 * Load theme helper functions
 */
if ( file_exists( AGGRESSIVE_APPAREL_DIR . '/includes/helpers.php' ) ) {
	require_once AGGRESSIVE_APPAREL_DIR . '/includes/helpers.php';
}
