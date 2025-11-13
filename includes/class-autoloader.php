<?php
/**
 * Autoloader Class
 *
 * Automatically loads classes for the theme
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

namespace Aggressive_Apparel;

/**
 * Autoloader Class
 *
 * Handles PSR-4 autoloading for theme classes.
 * Note: No ABSPATH check here as this needs to work in various contexts (WordPress, CLI, tests)
 *
 * @since 1.0.0
 */
class Autoloader {

	/**
	 * Theme namespace
	 *
	 * @var string
	 */
	private $namespace = 'Aggressive_Apparel';

	/**
	 * Base directory for classes
	 *
	 * @var string
	 */
	private $base_dir;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Use dirname to get the includes directory path reliably.
		$this->base_dir = __DIR__ . '/';
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Autoload classes
	 *
	 * Supports both flat structure (includes/class-*.php) and
	 * namespaced structure (includes/Namespace/class-*.php).
	 *
	 * @param string $class_name The fully-qualified class name.
	 * @return void
	 */
	public function autoload( $class_name ) {
		// Check if the class uses the namespace prefix.
		$len = strlen( $this->namespace );
		if ( strncmp( $this->namespace, $class_name, $len ) !== 0 ) {
			return;
		}

		// Get the relative class name and remove leading backslash.
		$relative_class = substr( $class_name, $len );
		$relative_class = ltrim( $relative_class, '\\' );

		// Split the class name into parts (for subdirectories).
		$parts     = explode( '\\', $relative_class );
		$classname = array_pop( $parts );

		// Convert namespace parts to directory structure.
		$subdirs = implode( '/', $parts );
		if ( ! empty( $subdirs ) ) {
			$subdirs .= '/';
		}

		// Convert class name to file name (WordPress style).
		// Example: Theme_Support becomes class-theme-support.php
		// Example: Core\Theme_Support becomes Core/class-theme-support.php.
		$filename = 'class-' . str_replace( '_', '-', strtolower( $classname ) ) . '.php';
		$file     = $this->base_dir . $subdirs . $filename;

		// If the file exists, require it.
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
