<?php
/**
 * Contract test for the WooCommerce internals Back_In_Stock_Native uses.
 *
 * @package Aggressive_Apparel\Tests\Unit\WooCommerce
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Back_In_Stock_Native;
use Automattic\WooCommerce\Internal\StockNotifications\Emails\EmailActionController;
use Automattic\WooCommerce\Internal\StockNotifications\Frontend\SignupRateLimiter;
use Automattic\WooCommerce\Internal\StockNotifications\Frontend\SignupService;

/**
 * WooCommerce's `Internal` namespace carries no compatibility promise. The
 * adapter's whole surface is listed in stubs/woocommerce-stock-notifications.php;
 * this checks every class, method and constant there against the pinned
 * WooCommerce, so an upstream rename fails CI rather than the storefront.
 */
class TestBackInStockNativeContract extends BackInStockTestCase {

	/**
	 * Classes, methods and constants the stub declares.
	 *
	 * @return array<string, array{methods: array<string, bool>, constants: array<string, string>}>
	 */
	private static function stub_surface(): array {
		$source = (string) file_get_contents( dirname( __DIR__, 3 ) . '/stubs/woocommerce-stock-notifications.php' );
		$tokens = token_get_all( $source );

		$surface   = array();
		$namespace = '';
		$class     = '';
		$static    = false;
		$count     = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) ) {
				continue;
			}
			if ( T_NAMESPACE === $token[0] ) {
				$namespace = trim( (string) $tokens[ $i + 2 ][1] );
			} elseif ( T_CLASS === $token[0] ) {
				$class             = $namespace . '\\' . $tokens[ $i + 2 ][1];
				$surface[ $class ] = array(
					'methods'   => array(),
					'constants' => array(),
				);
			} elseif ( T_STATIC === $token[0] ) {
				$static = true;
			} elseif ( T_FUNCTION === $token[0] ) {
				$surface[ $class ]['methods'][ (string) $tokens[ $i + 2 ][1] ] = $static;
				$static = false;
			} elseif ( T_CONST === $token[0] ) {
				$name  = (string) $tokens[ $i + 2 ][1];
				$value = '';
				for ( $j = $i + 3; $j < $count && T_CONSTANT_ENCAPSED_STRING !== ( $tokens[ $j ][0] ?? null ); $j++ ) {
					continue;
				}
				if ( $j < $count ) {
					$value = trim( (string) $tokens[ $j ][1], '\'"' );
				}
				$surface[ $class ]['constants'][ $name ] = $value;
			}
		}

		return $surface;
	}

	/**
	 * The pinned WooCommerce is new enough for the adapter.
	 *
	 * @return void
	 */
	public function test_pinned_woocommerce_is_supported(): void {
		$this->assertTrue( Back_In_Stock_Native::is_supported(), 'CI pins WooCommerce ' . WC_VERSION . '; the adapter needs ' . Back_In_Stock_Native::MIN_WC_VERSION . '+.' );
	}

	/**
	 * Every stubbed class, method and constant exists as declared.
	 *
	 * @return void
	 */
	public function test_every_stubbed_symbol_exists(): void {
		$surface = self::stub_surface();
		$this->assertNotEmpty( $surface );

		foreach ( $surface as $class => $members ) {
			$this->assertTrue( class_exists( $class ), "{$class} is gone." );
			$reflection = new \ReflectionClass( $class );

			foreach ( $members['methods'] as $method => $is_static ) {
				$this->assertTrue( $reflection->hasMethod( $method ), "{$class}::{$method}() is gone." );
				$this->assertSame( $is_static, $reflection->getMethod( $method )->isStatic(), "{$class}::{$method}() changed static-ness." );
				$this->assertTrue( $reflection->getMethod( $method )->isPublic(), "{$class}::{$method}() is no longer public." );
			}
			foreach ( $members['constants'] as $constant => $value ) {
				$this->assertTrue( $reflection->hasConstant( $constant ), "{$class}::{$constant} is gone." );
				$this->assertSame( $value, $reflection->getConstant( $constant ), "{$class}::{$constant} changed value." );
			}
		}
	}

	/**
	 * The adapter's own copies of WooCommerce's codes, link actions, email IDs
	 * and filter name still match WooCommerce.
	 *
	 * @return void
	 */
	public function test_mirrored_values_match_woocommerce(): void {
		$this->assertSame( SignupService::SIGNUP_SUCCESS, Back_In_Stock_Native::SUCCESS );
		$this->assertSame( SignupService::SIGNUP_SUCCESS_DOUBLE_OPT_IN, Back_In_Stock_Native::SUCCESS_DOUBLE_OPT_IN );
		$this->assertSame( SignupService::SIGNUP_ALREADY_JOINED, Back_In_Stock_Native::ALREADY_JOINED );
		$this->assertSame( SignupService::SIGNUP_ALREADY_JOINED_DOUBLE_OPT_IN, Back_In_Stock_Native::ALREADY_JOINED_DOUBLE_OPT_IN );
		$this->assertSame( array( SignupService::ERROR_RATE_LIMITED, SignupService::ERROR_INVALID_EMAIL, SignupService::ERROR_REQUIRES_ACCOUNT, SignupService::ERROR_INVALID_PRODUCT, SignupService::ERROR_INVALID_REQUEST ), array( 'rate_limited', 'invalid_email', 'requires_account', 'invalid_product', 'invalid_request' ), 'Back_In_Stock::handle_subscribe() maps these error codes.' );
		$this->assertSame( array( EmailActionController::ACTION_VERIFY, EmailActionController::ACTION_UNSUBSCRIBE ), Back_In_Stock_Native::LINK_ACTIONS );

		$emails = WC()->mailer()->get_emails();
		$ids    = array();
		foreach ( array( 'WC_Email_Customer_Stock_Notification', 'WC_Email_Customer_Stock_Notification_Verified' ) as $key ) {
			$this->assertArrayHasKey( $key, $emails, "WooCommerce no longer registers {$key}." );
			$ids[] = $emails[ $key ]->id;
		}
		$this->assertSame( Back_In_Stock_Native::UNSUBSCRIBE_EMAIL_IDS, $ids );

		$limiter = (string) file_get_contents( (string) ( new \ReflectionClass( SignupRateLimiter::class ) )->getFileName() );
		$this->assertStringContainsString( "'woocommerce_customer_stock_notifications_signup_rate_limit_options'", $limiter );
		$this->assertStringContainsString( "'client_delay'", $limiter );
	}
}
