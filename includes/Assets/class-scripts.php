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
		/**
		 * Fires after core theme scripts are enqueued.
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_enqueue_scripts' );
	}
}
