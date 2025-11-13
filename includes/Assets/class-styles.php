<?php
/**
 * Styles Class
 *
 * Handles frontend stylesheet enqueuing
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

namespace Aggressive_Apparel\Assets;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Styles Class
 *
 * Responsible for enqueuing all frontend stylesheets.
 *
 * @since 1.0.0
 */
class Styles {

	/**
	 * Theme version
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor
	 *
	 * @param string $version Theme version.
	 */
	public function __construct( $version = null ) {
		$this->version = $version ? $version : \wp_get_theme()->get( 'Version' );
	}

	/**
	 * Initialize styles
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
	}

	/**
	 * Enqueue frontend styles
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		$theme_uri = get_template_directory_uri();

		// Main theme stylesheet.
		wp_enqueue_style(
			'aggressive-apparel-main',
			$theme_uri . '/build/styles/main.css',
			array(),
			$this->version,
			'all'
		);

		// Navigation styles (conditional).
		if ( has_nav_menu( 'primary' ) ) {
			wp_enqueue_style(
				'aggressive-apparel-navigation',
				$theme_uri . '/build/styles/navigation.css',
				array( 'aggressive-apparel-main' ),
				$this->version,
				'all'
			);
		}

		// Block styles.
		wp_enqueue_style(
			'aggressive-apparel-blocks',
			$theme_uri . '/build/styles/blocks.css',
			array( 'aggressive-apparel-main' ),
			$this->version,
			'all'
		);

		/**
		 * Hook: After styles enqueued
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_enqueue_styles' );
	}
}
