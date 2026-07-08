<?php
/**
 * Back in stock rate limiter tests.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Back_In_Stock;
use ReflectionClass;
use WP_UnitTestCase;

/**
 * Covers the back-in-stock abuse controls without exercising AJAX output.
 */
class TestBackInStockRateLimiter extends WP_UnitTestCase {

	/**
	 * Previous REMOTE_ADDR value.
	 *
	 * @var string
	 */
	private string $previous_remote_addr = '';

	/**
	 * Service under test.
	 *
	 * @var Back_In_Stock
	 */
	private Back_In_Stock $back_in_stock;

	/**
	 * Configure strict limits for each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->back_in_stock       = new Back_In_Stock();
		$this->previous_remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		wp_set_current_user( 0 );

		add_filter( 'aggressive_apparel_back_in_stock_rate_limit_max_attempts', static fn(): int => 1 );
		add_filter( 'aggressive_apparel_back_in_stock_rate_limit_window', static fn(): int => 60 );
	}

	/**
	 * Restore globals.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		if ( '' === $this->previous_remote_addr ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->previous_remote_addr;
		}

		remove_all_filters( 'aggressive_apparel_back_in_stock_rate_limit_max_attempts' );
		remove_all_filters( 'aggressive_apparel_back_in_stock_rate_limit_window' );
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Invoke the private limiter method.
	 *
	 * @param string $email Submitted email.
	 * @return bool Whether the attempt is limited.
	 */
	private function is_rate_limited( string $email ): bool {
		$reflection = new ReflectionClass( Back_In_Stock::class );
		$method     = $reflection->getMethod( 'is_rate_limited' );
		$method->setAccessible( true );

		return (bool) $method->invoke( $this->back_in_stock, $email );
	}

	/**
	 * Repeated anonymous attempts from the same IP are limited by the shared limiter.
	 *
	 * @return void
	 */
	public function test_ip_attempts_use_shared_rate_limiter(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.80';

		$this->assertFalse( $this->is_rate_limited( 'not-an-email' ) );
		$this->assertTrue( $this->is_rate_limited( 'not-an-email' ) );
	}

	/**
	 * A single email remains limited even when the requester rotates IPs.
	 *
	 * @return void
	 */
	public function test_email_attempts_remain_limited_across_ips(): void {
		$email = 'stock-' . wp_generate_uuid4() . '@example.com';

		$_SERVER['REMOTE_ADDR'] = '203.0.113.81';
		$this->assertFalse( $this->is_rate_limited( $email ) );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.82';
		$this->assertTrue( $this->is_rate_limited( $email ) );
	}
}
