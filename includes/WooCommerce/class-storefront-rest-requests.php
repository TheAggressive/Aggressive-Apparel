<?php
/**
 * Storefront REST Requests
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the theme's shopper-facing REST routes like storefront pages.
 *
 * WooCommerce loads the shopper's session, cart and customer for pages but not
 * for REST requests, so prices rendered by these routes could be computed for
 * a different shopper than the page that requests them. WooPayments'
 * cache-optimized multi-currency, for one, renders client-side price skeletons
 * without a session but loads its converter only on session-less pages, so a
 * shopper with a cart would see blank prices in search results.
 *
 * Opting these routes out of WooCommerce's REST detection (it runs before
 * `init`, where the session is loaded) makes every pricing plugin see the
 * same shopper — currency, taxes, session — as on the page.
 */
final class Storefront_Rest_Requests {

	/** Theme routes that render prices for the current shopper. */
	private const ROUTES = array(
		'/aggressive-apparel/v1/search',
	);

	/**
	 * Register hooks. Must run before `init`.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_is_rest_api_request', array( $this, 'boot_as_storefront' ) );
	}

	/**
	 * WooCommerce's REST detection, minus the theme's storefront routes.
	 *
	 * @param bool $is_rest_api_request Whether WooCommerce treats the request as REST.
	 * @return bool
	 */
	public function boot_as_storefront( $is_rest_api_request ): bool {
		return (bool) $is_rest_api_request && ! self::targets_storefront_route();
	}

	/**
	 * Whether the request targets one of the routes. Runs before WordPress
	 * parses the REST route, so it reads the raw request.
	 */
	private static function targets_storefront_route(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing check only.
		$rest_route = isset( $_GET['rest_route'] ) && is_string( $_GET['rest_route'] ) ? sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ) : '';
		if ( '' !== $rest_route ) {
			return in_array( untrailingslashit( $rest_route ), self::ROUTES, true );
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );

		foreach ( self::ROUTES as $route ) {
			if ( str_ends_with( $path, '/' . rest_get_url_prefix() . $route ) ) {
				return true;
			}
		}

		return false;
	}
}
