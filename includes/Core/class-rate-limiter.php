<?php
/**
 * Rate Limiter
 *
 * A small, reusable per-IP throttle for public endpoints. Shared by the search
 * and load-more REST endpoints so the limiting logic lives in one place.
 *
 * @package Aggressive_Apparel
 */

declare( strict_types=1 );

namespace Aggressive_Apparel\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Fixed-window, per-IP rate limiter backed by transients.
 */
class Rate_Limiter {

	/**
	 * Whether the current request is within the allowed rate for a scope.
	 *
	 * Logged-in users and clients whose IP can't be determined are never
	 * limited. The counter is keyed by scope + hashed IP (via the
	 * Cloudflare-aware Client_IP helper), so different endpoints throttle
	 * independently.
	 *
	 * @param string $scope  Short identifier for the limited action (e.g. 'search').
	 * @param int    $max    Maximum requests allowed within the window.
	 * @param int    $window Window length in seconds.
	 * @return bool True when the request is allowed, false when it should be throttled.
	 */
	public static function allow( string $scope, int $max, int $window ): bool {
		if ( is_user_logged_in() ) {
			return true;
		}

		$ip = Client_IP::get();
		if ( '' === $ip ) {
			return true;
		}

		$key   = 'aa_rl_' . sanitize_key( $scope ) . '_' . hash( 'sha256', $ip );
		$count = (int) get_transient( $key );

		if ( $count >= max( 1, $max ) ) {
			return false;
		}

		set_transient( $key, $count + 1, max( 1, $window ) );

		return true;
	}
}
