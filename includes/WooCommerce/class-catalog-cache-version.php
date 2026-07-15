<?php
/**
 * Catalog Cache Version
 *
 * @package Aggressive_Apparel
 * @since 1.66.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Provides constant-time invalidation for catalog-derived caches.
 *
 * Cached facets and rendered product fragments include this version in their
 * keys. Incrementing one small, non-autoloaded option invalidates every prior
 * permutation without scanning or deleting individual cache entries.
 *
 * @since 1.66.0
 */
final class Catalog_Cache_Version {

	/** Version option shared by catalog-derived caches. */
	private const OPTION = 'aa_pf_facets_version';

	/**
	 * Whether this request already advanced the version.
	 *
	 * @var bool
	 */
	private bool $bumped = false;

	/**
	 * Return the current catalog cache version.
	 *
	 * @return int
	 */
	public function current(): int {
		return max( 1, (int) get_option( self::OPTION, 1 ) );
	}

	/**
	 * Invalidate all versioned catalog caches once per request.
	 *
	 * @return void
	 */
	public function bump(): void {
		if ( $this->bumped ) {
			return;
		}

		$this->bumped = true;
		update_option( self::OPTION, $this->current() + 1, false );
	}
}
