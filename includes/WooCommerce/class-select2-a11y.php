<?php
/**
 * Accessibility fix-up for WooCommerce's selectWoo.
 *
 * @package Aggressive_Apparel
 * @since 1.186.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Assets\Asset_Loader;

/**
 * Loads build/scripts/select2-a11y wherever WooCommerce's selectWoo loads
 * (address forms, country/state fields), to drop its nameless nested
 * textbox role. See src/scripts/select2-a11y.ts.
 */
class Select2_A11y {

	/**
	 * Script handle of WooCommerce's selectWoo.
	 *
	 * @var string
	 */
	private const SELECTWOO = 'selectWoo';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Late, so WooCommerce has already queued selectWoo (directly or as a
		// dependency of its country-select script).
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ), 100 );
	}

	/**
	 * Enqueue the fix-up when selectWoo is on the page.
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		if ( ! wp_script_is( self::SELECTWOO, 'enqueued' ) ) {
			return;
		}

		Asset_Loader::enqueue_script( 'aggressive-apparel-select2-a11y', 'build/scripts/select2-a11y', array( self::SELECTWOO ) );
	}
}
