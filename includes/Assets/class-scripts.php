<?php
/**
 * Scripts Class
 *
 * Handles frontend JavaScript enqueuing
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
 * Scripts Class
 *
 * Responsible for enqueuing all frontend JavaScript files.
 *
 * @since 1.0.0
 */
class Scripts {

	/**
	 * Initialize scripts
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_assets' ), 10 );
	}

	/**
	 * Enqueue frontend scripts
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		// Main theme script.
		Asset_Loader::enqueue_script(
			'aggressive-apparel-main',
			'build/scripts/main'
		);

		// Localize script data.
		wp_localize_script(
			'aggressive-apparel-main',
			'aggressiveApparelData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aggressive_apparel_nonce' ),
			)
		);

		/**
		 * Hook: After scripts enqueued
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_enqueue_scripts' );
	}

	/**
	 * Enqueue block assets
	 *
	 * @return void
	 */
	public function enqueue_block_assets() {
	}
}
