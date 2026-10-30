<?php
/**
 * Public endpoint throttles must not be bypassed with an ordinary account.
 *
 * @package Aggressive_Apparel
 */

namespace Aggressive_Apparel\Tests\Security;

use Aggressive_Apparel\Core\Rate_Limiter;
use WP_UnitTestCase;

/** Rate limits across shopper and management roles. */
class TestRateLimiterRoles extends WP_UnitTestCase {

	/** Self-service account creation must not grant unlimited public requests. */
	public function test_shopper_accounts_remain_rate_limited(): void {
		$ip = static fn( $value, $key ): string => 'REMOTE_ADDR' === $key ? '198.51.100.77' : (string) $value;
		add_filter( 'aggressive_apparel_server_value', $ip, 10, 2 );
		try {
			foreach ( array( 'subscriber', 'customer' ) as $role ) {
				$id = self::factory()->user->create( array( 'role' => $role ) );
				wp_set_current_user( $id );
				$this->assertTrue( Rate_Limiter::allow( 'role_' . $id, 1, 60 ) );
				$this->assertFalse( Rate_Limiter::allow( 'role_' . $id, 1, 60 ), $role );
			}
			wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
			$this->assertTrue( Rate_Limiter::allow( 'staff', 1, 60 ) );
			$this->assertTrue( Rate_Limiter::allow( 'staff', 1, 60 ) );
		} finally {
			remove_filter( 'aggressive_apparel_server_value', $ip, 10 );
			wp_set_current_user( 0 );
		}
	}
}
