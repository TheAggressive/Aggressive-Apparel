<?php
/**
 * Front-End jQuery Optimizer Class
 *
 * Reduces jQuery's front-end footprint on this block theme. jQuery only reaches
 * the front end via classic WooCommerce here — see {@see Legacy_Asset_Trim},
 * which already removes the classic jQuery script chain on non-commerce pages.
 * On the commerce pages where jQuery does load, this class:
 *
 *   1. Drops `jquery-migrate` from jQuery's dependency chain. Migrate is a shim
 *      for deprecated jQuery APIs that modern WooCommerce and this (vanilla-TS)
 *      theme do not use — removing it saves a request and ~10 KB.
 *   2. Applies the WordPress 6.3 `defer` loading strategy to the classic
 *      WooCommerce scripts that ride on jQuery, so they no longer block
 *      rendering.
 *
 * jQuery itself stays blocking. The strategy API keeps a handle blocking only
 * for dependents that *declare* it, and plugins use the `jQuery` global
 * without declaring it (WooPayments' product-page payment messaging did,
 * failing with "jQuery is not defined" on every product page).
 * Deferring jQuery cannot be made safe against scripts it cannot see.
 *
 * The admin is never touched — wp-color-picker and other admin UIs depend on
 * jQuery and jquery-migrate.
 *
 * @package Aggressive_Apparel
 * @since 1.152.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-End jQuery Optimizer
 *
 * @since 1.152.0
 */
class Jquery_Optimizer {

	/**
	 * Front-end script handles to load with the `defer` strategy: the classic
	 * WooCommerce scripts that depend on jQuery (mirrors
	 * {@see Legacy_Asset_Trim} handles). They declare jQuery, and nothing else
	 * runs before them, so deferring them is safe.
	 *
	 * @var array<int, string>
	 */
	public const DEFER_HANDLES = array(
		'wc-add-to-cart',
		'woocommerce',
		'wc-cart-fragments',
	);

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Drop jquery-migrate from the dependency chain (front end only).
		add_action( 'wp_default_scripts', array( $this, 'remove_migrate' ) );

		// Apply the defer strategy after WooCommerce enqueues its assets (10).
		add_action( 'wp_enqueue_scripts', array( $this, 'defer_scripts' ), 99 );
	}

	/**
	 * Remove jquery-migrate from jQuery's dependencies on the front end.
	 *
	 * `wp_default_scripts` fires for both admin and front-end requests, so guard
	 * against admin to keep the dashboard's full jQuery bundle intact.
	 *
	 * @param \WP_Scripts $scripts Core scripts registry.
	 * @return void
	 */
	public function remove_migrate( \WP_Scripts $scripts ): void {
		if ( is_admin() ) {
			return;
		}

		$jquery = $scripts->registered['jquery'] ?? null;
		if ( $jquery instanceof \_WP_Dependency ) {
			$jquery->deps = array_values( array_diff( $jquery->deps, array( 'jquery-migrate' ) ) );
		}
	}

	/**
	 * Mark the classic WooCommerce scripts as `defer`.
	 *
	 * Runs at priority 99 so WooCommerce (priority 10) has already registered
	 * and enqueued its scripts. Only touches handles that are actually
	 * registered on the current request.
	 *
	 * @return void
	 */
	public function defer_scripts(): void {
		if ( is_admin() ) {
			return;
		}

		foreach ( self::DEFER_HANDLES as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_script_add_data( $handle, 'strategy', 'defer' );
			}
		}
	}
}
