<?php
/**
 * Request Cache
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-request memoisation for derived data (parsed icon definitions, scanned
 * block content, resolved settings) — the WordPress way: a non-persistent
 * object-cache group. Lookups are in-process array reads; with a persistent
 * object cache the group still never leaves the request. Unlike a static
 * property, it is invalidated with the rest of the object cache (WordPress
 * tests flush it between tests), and writers can drop a key when the data it
 * derives from changes.
 */
final class Request_Cache {

	/**
	 * Object-cache group, registered as non-persistent.
	 */
	public const GROUP = 'aggressive-apparel-request';

	/**
	 * Mark the group request-only. Call once, early (Bootstrap does).
	 *
	 * @return void
	 */
	public static function register(): void {
		wp_cache_add_non_persistent_groups( array( self::GROUP ) );
	}

	/**
	 * Return the cached value for `$key`, computing and storing it on a miss.
	 * Falsy results (null, false, empty arrays) are cached too.
	 *
	 * @template T
	 * @param string        $key     Cache key, namespaced by the caller ("icons:slugs").
	 * @param callable(): T $compute Produces the value on a miss.
	 * @return T
	 */
	public static function remember( string $key, callable $compute ): mixed {
		$found = false;
		$value = wp_cache_get( $key, self::GROUP, false, $found );
		if ( $found ) {
			return $value;
		}

		$value = $compute();
		wp_cache_set( $key, $value, self::GROUP );

		return $value;
	}

	/**
	 * Store a value.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public static function set( string $key, mixed $value ): void {
		wp_cache_set( $key, $value, self::GROUP );
	}

	/**
	 * Read a value, or `$fallback` when it is not cached.
	 *
	 * @param string $key      Cache key.
	 * @param mixed  $fallback Returned on a miss.
	 * @return mixed
	 */
	public static function get( string $key, mixed $fallback = null ): mixed {
		$found = false;
		$value = wp_cache_get( $key, self::GROUP, false, $found );

		return $found ? $value : $fallback;
	}

	/**
	 * Drop a key (its source data changed).
	 *
	 * @param string $key Cache key.
	 * @return void
	 */
	public static function forget( string $key ): void {
		wp_cache_delete( $key, self::GROUP );
	}
}
