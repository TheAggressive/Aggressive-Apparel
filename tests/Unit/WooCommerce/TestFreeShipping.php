<?php
/**
 * Free shipping helper tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Free_Shipping;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Free_Shipping
 */
class TestFreeShipping extends WP_UnitTestCase {

	/**
	 * Zone-scan transient key (mirrors Free_Shipping::TRANSIENT_KEY).
	 *
	 * @var string
	 */
	private const TRANSIENT_KEY = 'aggressive_apparel_free_shipping_threshold';

	/**
	 * Reset request + persistent threshold caches between tests.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		Free_Shipping::flush_threshold_cache();
	}

	/**
	 * @return void
	 */
	public function tearDown(): void {
		Free_Shipping::flush_threshold_cache();
		remove_all_filters( 'aggressive_apparel_free_shipping_threshold' );
		parent::tearDown();
	}

	/**
	 * Shipping-settings hooks should clear the threshold cache.
	 */
	public function test_init_registers_invalidation_hooks(): void {
		Free_Shipping::init();

		$this->assertNotFalse(
			has_action( 'woocommerce_after_shipping_zone_object_save', array( Free_Shipping::class, 'flush_threshold_cache' ) )
		);
		$this->assertNotFalse(
			has_action( 'woocommerce_shipping_zone_method_added', array( Free_Shipping::class, 'flush_threshold_cache' ) )
		);
		$this->assertNotFalse(
			has_action( 'update_option_woocommerce_free_shipping_settings', array( Free_Shipping::class, 'flush_threshold_cache' ) )
		);
	}

	/**
	 * Zone detection should persist across calls via a transient.
	 */
	public function test_get_threshold_persists_zone_scan_in_transient(): void {
		$threshold = Free_Shipping::get_threshold();

		$this->assertIsFloat( $threshold );
		$this->assertNotFalse( get_transient( self::TRANSIENT_KEY ) );
		$this->assertSame( $threshold, (float) get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * Filter overrides must not be written into the zone transient.
	 */
	public function test_filter_override_does_not_pollute_transient(): void {
		add_filter(
			'aggressive_apparel_free_shipping_threshold',
			static fn() => 125.0
		);

		$this->assertSame( 125.0, Free_Shipping::get_threshold() );
		$this->assertFalse( get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * Flush should drop both request memo and transient.
	 */
	public function test_flush_threshold_cache_deletes_transient(): void {
		Free_Shipping::get_threshold();
		$this->assertNotFalse( get_transient( self::TRANSIENT_KEY ) );

		Free_Shipping::flush_threshold_cache();

		$this->assertFalse( get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * Threshold filter should override zone detection.
	 */
	public function test_get_threshold_uses_filter(): void {
		add_filter(
			'aggressive_apparel_free_shipping_threshold',
			static fn() => 125.0
		);

		$this->assertSame( 125.0, Free_Shipping::get_threshold() );
	}

	/**
	 * Custom block threshold should bypass auto-detect.
	 */
	public function test_get_threshold_custom_override(): void {
		$this->assertSame( 99.0, Free_Shipping::get_threshold( 99.0 ) );
	}

	/**
	 * Message formatting should include emphasis text.
	 */
	public function test_format_message_incomplete(): void {
		$message = Free_Shipping::format_message( 150.0, 'FREE Shipping', false );

		$this->assertStringContainsString( '150', $message );
		$this->assertStringContainsString( 'Away from FREE Shipping!', $message );
	}

	/**
	 * Complete message should use unlocked copy.
	 */
	public function test_format_message_complete(): void {
		$message = Free_Shipping::format_message( 0.0, 'FREE Shipping', true );

		$this->assertSame( 'FREE Shipping UNLOCKED!', $message );
	}

	/**
	 * Custom emphasis should appear in both message states.
	 */
	public function test_format_message_custom_emphasis(): void {
		$incomplete = Free_Shipping::format_message( 25.0, 'FREE Express', false );
		$complete   = Free_Shipping::format_message( 0.0, 'FREE Express', true );

		$this->assertStringContainsString( 'Away from FREE Express!', $incomplete );
		$this->assertSame( 'FREE Express UNLOCKED!', $complete );
	}

	/**
	 * Message i18n templates should be available for live JS updates.
	 */
	public function test_get_message_i18n(): void {
		$i18n = Free_Shipping::get_message_i18n();

		$this->assertArrayHasKey( 'progressDefault', $i18n );
		$this->assertArrayHasKey( 'progressCustom', $i18n );
		$this->assertArrayHasKey( 'unlockedDefault', $i18n );
		$this->assertArrayHasKey( 'unlockedCustom', $i18n );
		$this->assertStringContainsString( '%s', $i18n['progressDefault'] );
	}

	/**
	 * Bar message formatting should use translatable templates.
	 */
	public function test_format_bar_message(): void {
		$incomplete = Free_Shipping::format_bar_message( 25.0, false );
		$complete   = Free_Shipping::format_bar_message( 0.0, true );

		$this->assertStringContainsString( '25', $incomplete );
		$this->assertStringContainsString( 'Away from FREE Shipping!', $incomplete );
		$this->assertSame( 'FREE Shipping UNLOCKED!', $complete );
	}
}
