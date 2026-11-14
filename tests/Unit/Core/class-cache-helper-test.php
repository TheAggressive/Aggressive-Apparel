<?php
/**
 * Tests for the Cache_Helper read-through transient wrapper.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\Core;

use Aggressive_Apparel\Core\Cache_Helper;
use WP_Error;
use WP_UnitTestCase;

/**
 * Tests for the Cache_Helper class.
 *
 * @covers \Aggressive_Apparel\Core\Cache_Helper
 */
class Cache_Helper_Test extends WP_UnitTestCase {

	private const KEY = 'aa_cache_helper_test';

	/**
	 * Builder calls, so each test can count rebuilds.
	 *
	 * @var int
	 */
	private int $builds = 0;

	/**
	 * Reset the transient and the counter.
	 */
	public function set_up(): void {
		parent::set_up();
		delete_transient( self::KEY );
		$this->builds = 0;
	}

	/**
	 * A builder that counts its calls and returns the given value.
	 *
	 * @param mixed $value Value to build.
	 * @return callable(): mixed
	 */
	private function builder( mixed $value ): callable {
		return function () use ( $value ) {
			++$this->builds;
			return $value;
		};
	}

	/**
	 * The first call builds and stores; the second reads the transient.
	 */
	public function test_builds_once_then_serves_the_transient(): void {
		$this->assertSame( array( 'a' ), Cache_Helper::remember( self::KEY, HOUR_IN_SECONDS, $this->builder( array( 'a' ) ) ) );
		$this->assertSame( array( 'a' ), Cache_Helper::remember( self::KEY, HOUR_IN_SECONDS, $this->builder( array( 'b' ) ) ) );
		$this->assertSame( 1, $this->builds );
	}

	/**
	 * A cached value the validator rejects is rebuilt and replaced.
	 */
	public function test_rebuilds_when_the_validator_rejects_the_cached_shape(): void {
		set_transient( self::KEY, 'stale-string', HOUR_IN_SECONDS );

		$value = Cache_Helper::remember( self::KEY, HOUR_IN_SECONDS, $this->builder( array( 'fresh' ) ), 'is_array' );

		$this->assertSame( array( 'fresh' ), $value );
		$this->assertSame( array( 'fresh' ), get_transient( self::KEY ) );
	}

	/**
	 * A value the store predicate rejects is returned but not cached, so the
	 * next call retries instead of serving the failure for the whole TTL.
	 */
	public function test_returns_but_never_stores_a_rejected_value(): void {
		$error     = new WP_Error( 'down', 'API unavailable' );
		$not_error = static fn( $value ): bool => ! is_wp_error( $value );

		$this->assertSame( $error, Cache_Helper::remember( self::KEY, HOUR_IN_SECONDS, $this->builder( $error ), null, $not_error ) );
		$this->assertFalse( get_transient( self::KEY ) );

		$this->assertSame( array( 'ok' ), Cache_Helper::remember( self::KEY, HOUR_IN_SECONDS, $this->builder( array( 'ok' ) ), null, $not_error ) );
		$this->assertSame( 2, $this->builds );
		$this->assertSame( array( 'ok' ), get_transient( self::KEY ) );
	}
}
