<?php
/**
 * WooPayments Stripe Elements appearance integration.
 *
 * Enqueues the theme appearance script and clears WooPayments localStorage
 * cache so Stripe Elements match block checkout form styling.
 *
 * @package Aggressive_Apparel
 * @since 1.16.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooPayments appearance integration.
 *
 * @since 1.16.0
 */
class Wcpay_Appearance {

	/**
	 * Script handle for WooPayments appearance customization.
	 */
	private const WCPAY_APPEARANCE_HANDLE = 'aggressive-apparel-wcpay-appearance';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_head', array( $this, 'print_wcpay_appearance_bootstrap' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_wcpay_appearance' ), 5 );
	}

	/**
	 * Clear WooPayments appearance cache before WooPayments reads localStorage.
	 *
	 * Inline in head so it executes before WooPayments reads localStorage.
	 *
	 * @return void
	 */
	public function print_wcpay_appearance_bootstrap(): void {
		if ( ! $this->should_enqueue_wcpay_appearance() ) {
			return;
		}

		echo aggressive_apparel_trusted_html( '<script>(function(){try{Object.keys(localStorage).filter(function(k){return k.indexOf("wcpay_appearance_")===0;}).forEach(function(k){localStorage.removeItem(k);});}catch(e){}})();</script>' ) . "\n";
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
		if ( ! $this->should_enqueue_wcpay_appearance() ) {
			return;
		}

		$src         = 'build/scripts/wcpay-appearance';
		$asset_data  = Asset_Loader::get_asset_data( $src );
		$script_path = AGGRESSIVE_APPAREL_DIR . '/' . $src . '.js';

		if ( ! file_exists( $script_path ) ) {
			return;
		}

		wp_enqueue_script(
			self::WCPAY_APPEARANCE_HANDLE,
			aggressive_apparel_asset_uri( $src . '.js' ),
			$asset_data['dependencies'],
			$asset_data['version'],
			true
		);
	}

	/**
	 * Whether the WooPayments appearance script should load.
	 *
	 * @return bool
	 */
	private function should_enqueue_wcpay_appearance(): bool {
		return function_exists( 'is_checkout' ) && is_checkout();
	}
}
