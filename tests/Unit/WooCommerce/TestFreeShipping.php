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
		update_option( 'woocommerce_default_customer_address', 'base' );
		$this->set_customer_country( 'US' );
		Free_Shipping::flush_threshold_cache();
	}

	/**
	 * @return void
	 */
	public function tearDown(): void {
		Free_Shipping::flush_threshold_cache();
		remove_all_filters( 'aggressive_apparel_free_shipping_threshold' );
		remove_all_filters( 'aggressive_apparel_free_shipping_currency_rate' );
		parent::tearDown();
	}

	/**
	 * Create a country zone, optionally with a min-amount free-shipping method.
	 *
	 * @param string     $country    ISO country code.
	 * @param float|null $min_amount Free-shipping minimum; null adds no method.
	 * @return void
	 */
	private function create_zone( string $country, ?float $min_amount ): void {
		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( $country );
		$zone->add_location( $country, 'country' );
		$zone->save();

		if ( null === $min_amount ) {
			$zone->add_shipping_method( 'flat_rate' );
			return;
		}

		$instance_id = $zone->add_shipping_method( 'free_shipping' );
		update_option(
			'woocommerce_free_shipping_' . $instance_id . '_settings',
			array(
				'title'            => 'Free shipping',
				'requires'         => 'min_amount',
				'min_amount'       => (string) $min_amount,
				'ignore_discounts' => 'no',
			)
		);
		Free_Shipping::flush_threshold_cache();
	}

	/**
	 * Point the customer's shipping location (or default location) at a country.
	 *
	 * @param string $country ISO country code.
	 * @return void
	 */
	private function set_customer_country( string $country ): void {
		update_option( 'woocommerce_default_country', $country );

		if ( WC()->customer instanceof \WC_Customer ) {
			WC()->customer->set_shipping_country( $country );
			WC()->customer->set_shipping_state( '' );
		}
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
		$this->assertNotFalse(
			has_action( 'rest_api_init', array( Free_Shipping::class, 'register_store_api_extension' ) )
		);
	}

	/**
	 * The threshold must come from the customer's zone, not the lowest overall.
	 */
	public function test_get_threshold_resolves_customer_zone(): void {
		$this->create_zone( 'US', 75.0 );
		$this->create_zone( 'GB', 150.0 );

		$this->set_customer_country( 'GB' );
		$this->assertSame( 150.0, Free_Shipping::get_threshold() );

		$this->set_customer_country( 'US' );
		$this->assertSame( 75.0, Free_Shipping::get_threshold() );
	}

	/**
	 * A zone without free shipping yields 0 while the store still offers it.
	 */
	public function test_zone_without_free_shipping_is_hidden_not_absent(): void {
		$this->create_zone( 'US', 75.0 );
		$this->create_zone( 'AU', null );
		$this->set_customer_country( 'AU' );

		$this->assertSame( 0.0, Free_Shipping::get_threshold() );
		$this->assertTrue( Free_Shipping::is_offered() );
	}

	/**
	 * The store-wide scan should persist across requests via a transient.
	 */
	public function test_is_offered_persists_zone_scan_in_transient(): void {
		$this->create_zone( 'US', 75.0 );

		$this->assertTrue( Free_Shipping::is_offered() );
		$this->assertSame( 75.0, (float) get_transient( self::TRANSIENT_KEY ) );
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
		$this->assertTrue( Free_Shipping::is_offered() );
		$this->assertFalse( get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * Flush should drop both request memo and transient.
	 */
	public function test_flush_threshold_cache_deletes_transient(): void {
		Free_Shipping::is_offered();
		$this->assertNotFalse( get_transient( self::TRANSIENT_KEY ) );

		Free_Shipping::flush_threshold_cache();

		$this->assertFalse( get_transient( self::TRANSIENT_KEY ) );
	}

	/**
	 * Custom block threshold should bypass auto-detect.
	 */
	public function test_get_threshold_custom_override(): void {
		$this->assertSame( 99.0, Free_Shipping::get_threshold( 99.0 ) );
	}

	/**
	 * Store-currency overrides convert to the active currency.
	 */
	public function test_custom_threshold_converts_with_currency_rate(): void {
		add_filter( 'aggressive_apparel_free_shipping_currency_rate', static fn() => 0.9234 );

		$this->assertSame( 92.34, Free_Shipping::get_threshold( 100.0 ) );
	}

	/**
	 * Cart extension payload uses Store API minor units.
	 */
	public function test_store_api_cart_data_uses_minor_units(): void {
		$this->create_zone( 'US', 75.0 );
		add_filter( 'aggressive_apparel_free_shipping_currency_rate', static fn() => 0.79 );

		$data = Free_Shipping::get_store_api_cart_data();

		$this->assertSame( 7500, $data['threshold'] );
		$this->assertSame( 0.79, $data['rate'] );
		$this->assertIsInt( $data['subtotal'] );
	}

	/**
	 * Currency context mirrors the Store API symbol position.
	 */
	public function test_currency_context_honours_symbol_position(): void {
		update_option( 'woocommerce_currency_pos', 'right_space' );

		$context = Free_Shipping::get_currency_context();

		$this->assertSame( '', $context['currencyPrefix'] );
		$this->assertStringStartsWith( ' ', $context['currencySuffix'] );
		$this->assertArrayHasKey( 'currencyDecimalSeparator', $context );
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
