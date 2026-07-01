<?php
/**
 * Scripts Class
 *
 * Handles frontend JavaScript enqueuing.
 *
 * @package Aggressive_Apparel
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Assets;

use Aggressive_Apparel\WooCommerce\Feature_Settings;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scripts Class
 *
 * Central entry point for theme-wide frontend scripts. Feature-specific
 * bundles should stay in their own classes.
 *
 * @since 1.0.0
 */
class Scripts {

	/**
	 * Initialize scripts.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$this->enqueue_cursor();

		/**
		 * Fires after core theme scripts are enqueued.
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_enqueue_scripts' );
	}

	/**
	 * Enqueue the custom cursor script module when the feature is enabled.
	 *
	 * @return void
	 */
	private function enqueue_cursor(): void {
		if ( ! function_exists( 'wp_register_script_module' ) ) {
			return;
		}

		if ( ! class_exists( Feature_Settings::class ) ) {
			return;
		}

		if ( ! Feature_Settings::is_enabled( 'custom_cursor' ) ) {
			return;
		}

		wp_enqueue_script_module(
			'@aggressive-apparel/cursor',
			AGGRESSIVE_APPAREL_URI . '/build/interactivity/cursor.js',
			array(),
			AGGRESSIVE_APPAREL_VERSION,
		);
	}
}
