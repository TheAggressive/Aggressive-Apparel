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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		$this->enqueue_core_scripts();
		$this->enqueue_payment_appearance();

		/**
		 * Fires after core theme scripts are enqueued.
		 *
		 * @since 1.0.0
		 */
		do_action( 'aggressive_apparel_after_enqueue_scripts' );
	}

	/**
	 * Tune the WooPayments / Stripe Payment Element appearance on checkout.
	 *
	 * The card fields render in a cross-origin Stripe iframe; WooPayments copies
	 * the theme's computed form styles into the Element's appearance. This filter
	 * script trims that (smaller labels, brand focus colour) so the card field
	 * matches the rest of the form. Checkout only.
	 *
	 * @return void
	 */
	private function enqueue_payment_appearance(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}

		// Loaded in the head, non-deferred (NOT via Asset_Loader, which forces
		// footer + defer): the appearance filter must be registered before
		// WooPayments builds and applies it, or our override never runs.
		$asset_file = aggressive_apparel_asset_path( 'build/scripts/checkout/payment-appearance.asset.php' );
		$asset      = file_exists( $asset_file )
			? require $asset_file
			: array(
				'dependencies' => array( 'wp-hooks' ),
				'version'      => AGGRESSIVE_APPAREL_VERSION,
			);

		wp_enqueue_script(
			'aggressive-apparel-payment-appearance',
			aggressive_apparel_asset_uri( 'build/scripts/checkout/payment-appearance.js' ),
			$asset['dependencies'] ?? array( 'wp-hooks' ),
			$asset['version'] ?? AGGRESSIVE_APPAREL_VERSION,
			array( 'in_footer' => false )
		);
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
