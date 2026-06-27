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
	 * The window is fixed: its reset time is pinned when the first request of a
	 * window arrives, and the transient TTL tracks the *remaining* time. This
	 * means the counter decays on a real clock rather than being pushed forward
	 * by each request — a continuously active client isn't falsely throttled for
	 * never going idle a full window.
	 *
	 * Note: the read-then-write is not atomic, so under heavy concurrency the
	 * limiter fails open (slightly undercounts). That's an acceptable trade-off
	 * for a defensive throttle backed by transients.
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
		$key    = 'aa_rl_' . sanitize_key( $scope ) . '_' . hash( 'sha256', $ip );
		$now    = time();

		$bucket = get_transient( $key );

		// Start a fresh window when none is active or the current one has elapsed.
		if ( ! is_array( $bucket ) || ! isset( $bucket['count'], $bucket['reset'] ) || $bucket['reset'] <= $now ) {
			$bucket = array(
				'count' => 0,
				'reset' => $now + $window,
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
