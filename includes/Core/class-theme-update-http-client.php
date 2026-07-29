<?php
/**
 * Theme Update HTTP Client
 *
 * @package Aggressive_Apparel\Core
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes bounded, SSRF-safe HTTP requests for theme update services.
 */
final class Theme_Update_Http_Client {

	/**
	 * Fetch a remote updater resource with VIP circuit breaking when available.
	 *
	 * @param string               $url  HTTPS URL.
	 * @param array<string, mixed> $args WordPress HTTP arguments.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function get( string $url, array $args = array() ): array|\WP_Error {
		$timeout         = min( 3, max( 1, (int) ( $args['timeout'] ?? 3 ) ) );
		$args['timeout'] = $timeout;

		if ( function_exists( 'vip_safe_wp_remote_get' ) ) {
			$response = \vip_safe_wp_remote_get( $url, false, 3, $timeout, 20, $args );

			return is_array( $response ) ? $response : new \WP_Error( 'remote_request_failed' );
		}

		return wp_safe_remote_get( $url, $args );
	}
}
