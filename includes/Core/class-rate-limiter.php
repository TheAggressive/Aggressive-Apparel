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
 * Fixed-window, per-IP rate limiter backed by atomic object-cache counters
 * when available, with a transient fallback for local/shared-hosting installs.
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
	 * The window is aligned to the wall clock and the TTL tracks only its
	 * remaining time. A continuously active client therefore receives a fresh
	 * bucket on schedule instead of extending the window with every request.
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

		$max    = max( 1, $max );
		$window = max( 1, $window );
		$scope  = sanitize_key( $scope );
		$hash   = hash( 'sha256', $ip );
		$now    = time();
		$reset  = ( intdiv( $now, $window ) + 1 ) * $window;
		$ttl    = max( 1, $reset - $now );

		// VIP and other persistent object-cache platforms provide atomic add/incr,
		// avoiding lost updates when many requests from one client arrive at once.
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
			$cache_key = $scope . ':' . $hash . ':' . intdiv( $now, $window );
			$group     = 'aggressive-apparel-rate-limits';
			// TTL is the time left in the current window, not a fixed constant, so a
			// window longer than the old hardcoded lease can't expire mid-window and
			// reset the counter early (which would allow ~window/lease x max requests).
			// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- A fixed-window rate-limit bucket must expire exactly at the window boundary ($ttl), which is intentionally short for small windows.
			wp_cache_add( $cache_key, 0, $group, $ttl );
			$count = wp_cache_incr( $cache_key, 1, $group );
			if ( false !== $count ) {
				return $count <= $max;
			}
		}

		$key = 'aa_rl_' . $scope . '_' . $hash;

		$bucket = get_transient( $key );

		// Start a fresh window when none is active or the current one has elapsed.
		if ( ! is_array( $bucket ) || ! isset( $bucket['count'], $bucket['reset'] ) || $bucket['reset'] <= $now ) {
			$bucket = array(
				'count' => 0,
				'reset' => $reset,
			);
		}

		if ( (int) $bucket['count'] >= $max ) {
			return false;
		}

		++$bucket['count'];

		// TTL is the time left in the current window, so the counter resets on
		// schedule instead of from the most recent request.
		set_transient( $key, $bucket, max( 1, (int) $bucket['reset'] - $now ) );

		return true;
	}
}
