<?php
/**
 * Content Width Class
 *
 * Handles content width configuration
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content Width Class
 *
 * Sets and manages the content width for embeds and images.
 *
 * @since 1.0.0
 */
class Content_Width {

	/**
	 * Default content width in pixels
	 *
	 * @var int
	 */
	private $default_width = 1200;

	/**
	 * Constructor
	 *
	 * @param int $width Optional. Custom content width.
	 */
	public function __construct( $width = 0 ) {
		if ( $width > 0 ) {
			$this->default_width = $width;
		}
	}

	/**
	 * Initialize content width
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'after_setup_theme', array( $this, 'set_content_width' ), 0 );
	}

	/**
	 * Set content width
	 *
	 * @return void
	 */
	public function set_content_width() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		$GLOBALS['content_width'] = apply_filters( 'aggressive_apparel_content_width', $this->default_width );
	}
}
