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
	 * Handle for the global theme script bundle.
	 *
	 * @var string
	 */
	public const HANDLE = 'aggressive-apparel-main';

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
		$this->enqueue_core_scripts();

		/**
		 * Fires after core theme scripts are enqueued.
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_enqueue_scripts' );
	}

	/**
	 * Enqueue shared frontend scripts.
	 *
	 * @return void
	 */
	private function enqueue_core_scripts(): void {
		Asset_Loader::enqueue_script(
			self::HANDLE,
			'build/scripts/main',
			array( 'wp-dom-ready' )
		);

		$this->localize_theme_data( self::HANDLE );
	}

	/**
	 * Attach global theme data to a script handle.
	 *
	 * @param string $handle Script handle.
	 * @return void
	 */
	private function localize_theme_data( string $handle ): void {
		wp_localize_script(
			$handle,
			'aggressiveApparelData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'aggressive_apparel_nonce' ),
				'restUrl' => esc_url_raw( rest_url() ),
			)
		);
	}
}
