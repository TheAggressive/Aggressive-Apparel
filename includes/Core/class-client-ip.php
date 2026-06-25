<?php
/**
 * Client IP resolution
 *
 * Resolves the real visitor IP for rate-limiting / abuse-prevention, correctly
 * handling Cloudflare (and other reverse proxies via filter).
 *
 * Behind Cloudflare, `REMOTE_ADDR` is a Cloudflare edge IP, so every visitor
 * would share one identity. Cloudflare forwards the real client IP in the
 * `CF-Connecting-IP` header — but that header is only trustworthy when the
 * request actually arrived from a Cloudflare edge IP. We therefore validate
 * `REMOTE_ADDR` against Cloudflare's published ranges before trusting it, which
 * stops an attacker hitting the origin directly from spoofing the header to
 * dodge a per-IP limit.
 *
 * @package Aggressive_Apparel
 */

declare( strict_types=1 );

namespace Aggressive_Apparel\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Static helper for resolving the originating client IP.
 */
class Client_IP {

	/**
	 * Cloudflare edge IP ranges (CIDR).
	 *
	 * Source: https://www.cloudflare.com/ips-v4 and /ips-v6. These change very
	 * rarely; override via the `aggressive_apparel_cloudflare_ip_ranges` filter
	 * if Cloudflare updates them before this list does.
	 *
	 * @var array<int, string>
	 */
	private const CLOUDFLARE_RANGES = array(
		// IPv4.
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		// IPv6.
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/**
	 * Resolve the originating client IP.
	 *
	 * @return string A valid IP, or '' when none could be determined.
	 */
	public static function get(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';
		$remote = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';

		$ip = $remote;

		// Trust Cloudflare's connecting-IP header only when the request actually
		// arrived from a Cloudflare edge IP.
		if (
			'' !== $remote
			&& isset( $_SERVER['HTTP_CF_CONNECTING_IP'] )
			&& self::is_cloudflare( $remote )
		) {
			$cf = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
				$ip = $cf;
			}
		}

		/**
		 * Filter the resolved client IP.
		 *
		 * Lets sites behind a different proxy/CDN supply their own logic.
		 *
		 * @param string $ip     Resolved IP ('' when unknown).
		 * @param string $remote Raw validated REMOTE_ADDR.
		 */
		return (string) apply_filters( 'aggressive_apparel_client_ip', $ip, $remote );
	}

	/**
	 * Whether an IP falls within any Cloudflare edge range.
	 *
	 * @param string $ip Validated IP address.
	 * @return bool
	 */
	private static function is_cloudflare( string $ip ): bool {
		/**
		 * Filter the Cloudflare IP ranges used to validate the CF-Connecting-IP header.
		 *
		 * @param array<int, string> $ranges CIDR ranges.
		 */
		$ranges = (array) apply_filters( 'aggressive_apparel_cloudflare_ip_ranges', self::CLOUDFLARE_RANGES );

		foreach ( $ranges as $range ) {
			if ( self::ip_in_range( $ip, (string) $range ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Test whether an IP is inside a CIDR range (IPv4 or IPv6).
	 *
	 * @param string $ip   Validated IP address.
	 * @param string $cidr CIDR range, e.g. "104.16.0.0/13".
	 * @return bool
	 */
	private static function ip_in_range( string $ip, string $cidr ): bool {
		if ( ! str_contains( $cidr, '/' ) ) {
			return false;
		}

		list( $subnet, $bits_raw ) = explode( '/', $cidr, 2 );
		$bits                      = (int) $bits_raw;

		$ip_bin     = inet_pton( $ip );
		$subnet_bin = inet_pton( $subnet );

		// Reject invalid values and v4-vs-v6 mismatches (different byte lengths).
		if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
			return false;
		}

		$whole_bytes    = intdiv( $bits, 8 );
		$remaining_bits = $bits % 8;

		if ( $whole_bytes > 0 && strncmp( $ip_bin, $subnet_bin, $whole_bytes ) !== 0 ) {
			return false;
		}

		if ( $remaining_bits > 0 ) {
			$mask = chr( ( 0xff << ( 8 - $remaining_bits ) ) & 0xff );
			return ( ord( $ip_bin[ $whole_bytes ] ) & ord( $mask ) ) === ( ord( $subnet_bin[ $whole_bytes ] ) & ord( $mask ) );
		}

		return true;
	}
}
