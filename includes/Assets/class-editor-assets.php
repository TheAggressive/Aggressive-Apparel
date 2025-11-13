<?php
/**
 * Editor Assets Class
 *
 * Handles block editor asset enqueuing
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
 * Editor Assets Class
 *
 * Responsible for enqueuing editor-specific assets.
 *
 * @since 1.0.0
 */
class Editor_Assets {

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
	public function __construct( $version = '1.0.0' ) {
		$this->version = $version;
	}

	/**
	 * Initialize editor assets
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ), 10 );
	}

	/**
	 * Enqueue block editor assets
	 *
	 * @return void
	 */
	public function enqueue_editor_assets() {
		$theme_uri = get_template_directory_uri();

		// Editor stylesheet.
		wp_enqueue_style(
			'aggressive-apparel-editor-style',
			$theme_uri . '/build/styles/editor-style.css',
			array( 'wp-edit-blocks' ),
			$this->version,
			'all'
		);

		// Editor script.
		wp_enqueue_script(
			'aggressive-apparel-editor',
			$theme_uri . '/build/scripts/editor.js',
			array( 'wp-blocks', 'wp-dom-ready', 'wp-edit-post' ),
			$this->version,
			true
		);

		/**
		 * Hook: After editor assets enqueued
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_enqueue_editor_assets' );
	}
}
