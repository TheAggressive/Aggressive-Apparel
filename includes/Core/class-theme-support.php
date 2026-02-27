<?php
/**
 * Theme Support Class
 *
 * Handles WordPress theme support features registration
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme Support Class
 *
 * Registers all WordPress theme supports following WordPress best practices.
 *
 * @since 1.0.0
 */
class Theme_Support {

	/**
	 * Initialize theme support
	 *
	 * @return void
	 */
	public function init() {
		$this->register_theme_support();
	}

	/**
	 * Register theme support features
	 *
	 * @return void
	 */
	public function register_theme_support() {
		// Add default posts and comments RSS feed links to head.
		add_theme_support( 'automatic-feed-links' );

		// Let WordPress manage the document title.
		add_theme_support( 'title-tag' );

		// Enable support for Post Thumbnails on posts and pages.
		add_theme_support( 'post-thumbnails' );

		// Add support for responsive embedded content.
		add_theme_support( 'responsive-embeds' );

		// Add support for experimental link color control.
		add_theme_support( 'experimental-link-color' );

		// Add support for Block Styles.
		add_theme_support( 'wp-block-styles' );

		// Add support for full and wide align images.
		add_theme_support( 'align-wide' );

		// Add support for custom line height controls.
		add_theme_support( 'custom-line-height' );

		// Add support for custom units.
		add_theme_support( 'custom-units' );

		// Add support for custom spacing.
		add_theme_support( 'custom-spacing' );

		// Add support for HTML5 markup.
		add_theme_support(
			'html5',
			array(
				'comment-list',
				'comment-form',
				'search-form',
				'gallery',
				'caption',
				'style',
				'script',
			)
		);

		// Remove core block patterns.
		remove_theme_support( 'core-block-patterns' );

		/**
		 * Hook: After theme support registration
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_theme_support' );
	}
}
