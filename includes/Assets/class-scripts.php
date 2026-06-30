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
		add_action( 'wp_head', array( $this, 'print_wcpay_appearance_bootstrap' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_wcpay_appearance' ), 5 );
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
		$this->enqueue_cursor();
	}

	/**
	 * Clear WooPayments appearance cache before any checkout scripts run.
	 *
	 * Inline in head so it executes before WooPayments reads localStorage.
	 *
	 * @return void
	 */
	public function print_wcpay_appearance_bootstrap(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		echo '<script>(function(){try{Object.keys(localStorage).filter(function(k){return k.indexOf("wcpay_appearance_")===0;}).forEach(function(k){localStorage.removeItem(k);});}catch(e){}})();</script>' . "\n";
	}

	/**
	 * Enqueue WooPayments Stripe Elements appearance overrides on checkout.
	 *
	 * Registers the `wcpay_elements_appearance` listener before WooPayments
	 * initializes checkout (priority 5, footer, no defer).
	 *
	 * @return void
	 */
	public function enqueue_wcpay_appearance(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		$src         = 'build/scripts/wcpay-appearance';
		$asset_data  = Asset_Loader::get_asset_data( $src );
		$script_path = AGGRESSIVE_APPAREL_DIR . '/' . $src . '.js';

		if ( ! file_exists( $script_path ) ) {
			return;
		}

		wp_enqueue_script(
			'aggressive-apparel-wcpay-appearance',
			aggressive_apparel_asset_uri( $src . '.js' ),
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);
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
