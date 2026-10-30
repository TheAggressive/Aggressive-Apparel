<?php
/**
 * Front-End jQuery Optimizer Class
 *
 * Reduces jQuery's front-end footprint on this block theme. jQuery only reaches
 * the front end via classic WooCommerce here — see {@see Legacy_Asset_Trim},
 * which already removes the classic jQuery script chain on non-commerce pages.
 * On the commerce pages where jQuery does load, this class drops
 * `jquery-migrate` from jQuery's dependency chain. Migrate is a shim for
 * deprecated jQuery APIs that modern WooCommerce and this (vanilla-TS) theme
 * do not use — removing it saves a request and ~10 KB. WooCommerce already
 * registers its own jQuery scripts with the `defer` strategy.
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
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Drop jquery-migrate from the dependency chain (front end only).
		add_action( 'wp_default_scripts', array( $this, 'remove_migrate' ) );
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
}
