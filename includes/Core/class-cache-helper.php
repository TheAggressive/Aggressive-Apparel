<?php
/**
 * Cache Helper Class
 *
 * Small wrapper around the WordPress Transients API providing a
 * read-through "remember" primitive to remove repeated
 * get_transient / validate / set_transient boilerplate.
 *
 * @package Aggressive_Apparel
 * @since 1.16.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

/**
 * Cache Helper Class
 *
 * @since 1.16.0
 */
class Cache_Helper {

	/**
	 * Return a cached value or build, cache, and return it.
	 *
	 * Reads a transient. When the cached value is considered valid it is
	 * returned as-is; otherwise the builder is invoked, its result is stored
	 * under the given key for the given TTL, and that result is returned.
	 *
	 * When no validator is supplied the default freshness check is a strict
	 * `false !== $cached` (mirroring the Transients API "not found" sentinel).
	 * Supply a validator for value-shape checks such as `is_array` or
	 * `is_string`.
	 *
	 * @param string        $key      Transient key.
	 * @param int           $ttl      Time to live in seconds.
	 * @param callable      $builder  Callback returning the fresh value.
	 * @param callable|null $is_valid Optional validator for the cached value.
	 * @return mixed The cached or freshly built value.
	 */
	public static function remember( string $key, int $ttl, callable $builder, ?callable $is_valid = null ) {
		$cached = get_transient( $key );

		$fresh = null !== $is_valid ? (bool) $is_valid( $cached ) : ( false !== $cached );

		if ( $fresh ) {
			return $cached;
		}

		$value = $builder();
		set_transient( $key, $value, $ttl );

		return $value;
	}
}
