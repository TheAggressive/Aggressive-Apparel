<?php
/**
 * Plugin Name: wp-env Cron Loopback
 * Description: Routes WP-Cron requests to the WordPress container instead of the host-only wp-env port.
 *
 * @package Aggressive_Apparel
 */

add_filter(
	'cron_request',
	static function ( array $request ): array {
		$path  = wp_parse_url( $request['url'], PHP_URL_PATH );
		$query = wp_parse_url( $request['url'], PHP_URL_QUERY );

		if ( ! $path ) {
			$path = '/wp-cron.php';
		}

		$request['url'] = 'http://wordpress' . $path;

		if ( $query ) {
			$request['url'] .= '?' . $query;
		}

		return $request;
	}
);
