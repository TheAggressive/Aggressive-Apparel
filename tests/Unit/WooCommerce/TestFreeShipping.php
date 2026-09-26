<?php
/**
 * Free shipping helper tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Free_Shipping;
use Aggressive_Apparel\WooCommerce\Free_Shipping_Rules;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Free_Shipping
 * @covers \Aggressive_Apparel\WooCommerce\Free_Shipping_Rules
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
	 * @param string     $country          ISO country code.
	 * @param float|null $min_amount       Free-shipping minimum; null adds no method.
	 * @param string     $requires         WooCommerce `requires` setting.
	 * @param string     $ignore_discounts 'yes' | 'no'.
	 * @return void
	 */
	private function create_zone( string $country, ?float $min_amount, string $requires = 'min_amount', string $ignore_discounts = 'no' ): void {
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
				'requires'         => $requires,
				'min_amount'       => (string) $min_amount,
				'ignore_discounts' => $ignore_discounts,
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
	 * Boot an empty session cart holding one product at the given price.
	 *
	 * @param float $price Regular price (store currency, tax-exclusive).
	 * @return void
	 */
	private function cart_with_product( float $price ): void {
		if ( ! WC()->cart ) {
			WC()->frontend_includes();
			WC()->initialize_session();
			WC()->initialize_cart();
		}

		WC()->cart->empty_cart();

		$product = new \WC_Product_Simple();
		$product->set_name( 'Free shipping fixture' );
		$product->set_regular_price( (string) $price );
		$product->save();

		WC()->cart->add_to_cart( $product->get_id() );
		WC()->cart->calculate_totals();
	}

	/**
	 * Create and apply a coupon.
	 *
	 * @param bool  $free_shipping Whether the coupon grants free shipping.
	 * @param float $amount        Fixed cart discount.
	 * @return void
	 */
	private function apply_coupon( bool $free_shipping, float $amount = 0.0 ): void {
		$code   = 'aa-fs-' . wp_generate_password( 6, false );
		$coupon = new \WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'fixed_cart' );
		$coupon->set_amount( $amount );
		$coupon->set_free_shipping( $free_shipping );
		$coupon->save();

		WC()->cart->apply_coupon( $code );
		WC()->cart->calculate_totals();
		wc_clear_notices();
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

		$data = Free_Shipping::get_store_api_cart_data();

		$this->assertSame( 7500, $data['threshold'] );
		$this->assertSame( 1.0, $data['rate'] );
		$this->assertIsInt( $data['subtotal'] );
	}

	/**
	 * The payload reports the rate and converts store-currency overrides.
	 */
	public function test_store_api_cart_data_converts_store_currency_overrides(): void {
		add_filter( 'aggressive_apparel_free_shipping_currency_rate', static fn() => 0.79 );
		add_filter( 'aggressive_apparel_free_shipping_threshold', static fn() => 100.0 );

		$data = Free_Shipping::get_store_api_cart_data();

		$this->assertSame( 0.79, $data['rate'] );
		$this->assertSame( 7900, $data['threshold'] );
	}

	/**
	 * "Minimum AND coupon" can't be earned by spending alone.
	 */
	public function test_both_method_counts_only_with_free_shipping_coupon(): void {
		$this->create_zone( 'US', 50.0, 'both' );
		$this->cart_with_product( 60.0 );

		$this->assertSame( 0.0, Free_Shipping::get_threshold() );

		$this->apply_coupon( true );

		$this->assertSame( 50.0, Free_Shipping::get_threshold() );
	}

	/**
	 * "Minimum OR coupon" is unlocked by the coupon below the minimum.
	 */
	public function test_either_method_is_unlocked_by_coupon(): void {
		$this->create_zone( 'US', 100.0, 'either' );
		$this->cart_with_product( 60.0 );

		$before = Free_Shipping::get_cart_progress();
		$this->assertNotNull( $before );
		$this->assertFalse( $before['complete'] );

		$this->apply_coupon( true );

		$after = Free_Shipping::get_cart_progress();
		$this->assertNotNull( $after );
		$this->assertTrue( $after['complete'] );
		$this->assertTrue( Free_Shipping::get_store_api_cart_data()['unlocked'] );
	}

	/**
	 * Qualifying subtotal subtracts discounts unless the method ignores them.
	 */
	public function test_qualifying_subtotal_respects_ignore_discounts(): void {
		$this->create_zone( 'US', 100.0 );
		$this->cart_with_product( 120.0 );
		$this->apply_coupon( false, 30.0 );

		$this->assertSame( 9000, Free_Shipping::get_store_api_cart_data()['subtotal'] );

		Free_Shipping::flush_threshold_cache();
		update_option( 'woocommerce_free_shipping_' . $this->first_free_shipping_instance() . '_settings', array_merge( $this->first_free_shipping_settings(), array( 'ignore_discounts' => 'yes' ) ) );
		Free_Shipping::flush_threshold_cache();

		$this->assertSame( 12000, Free_Shipping::get_store_api_cart_data()['subtotal'] );
	}

	/**
	 * Tax-inclusive display compares the tax-inclusive subtotal, like WooCommerce.
	 */
	public function test_qualifying_subtotal_includes_tax_when_displayed_with_tax(): void {
		$this->enable_twenty_percent_tax();

		$this->create_zone( 'US', 100.0 );
		$this->cart_with_product( 100.0 );

		$this->assertSame( 12000, Free_Shipping::get_store_api_cart_data()['subtotal'] );
	}

	/**
	 * Boundary scenarios for the parity contract.
	 *
	 * @return array<string, array{0: float, 1: string, 2: float, 3: float, 4: bool, 5: bool}>
	 *   [minimum, ignore_discounts, price, coupon discount, tax-inclusive display, WooCommerce verdict]
	 */
	public static function parity_scenarios(): array {
		return array(
			'one cent below the minimum'         => array( 100.0, 'no', 99.99, 0.0, false, false ),
			'exactly the minimum'                => array( 100.0, 'no', 100.0, 0.0, false, true ),
			'discount drops below the minimum'   => array( 100.0, 'no', 120.0, 30.0, false, false ),
			'discount ignored by the method'     => array( 100.0, 'yes', 120.0, 30.0, false, true ),
			'tax-inclusive display crosses'      => array( 100.0, 'no', 90.0, 0.0, true, true ),
			'tax-exclusive display stays below'  => array( 100.0, 'no', 90.0, 0.0, false, false ),
			'tax-inclusive discount stays below' => array( 100.0, 'no', 100.0, 20.0, true, false ),
		);
	}

	/**
	 * Parity contract: the displayed progress must agree with WooCommerce.
	 *
	 * `complete` comes from WC_Shipping_Free_Shipping::is_available(); the
	 * threshold/subtotal arithmetic is the theme's. If a WooCommerce release
	 * changes how the minimum is compared, these fail instead of the bar
	 * silently disagreeing with checkout.
	 *
	 * @dataProvider parity_scenarios
	 *
	 * @param float  $minimum          Free-shipping minimum.
	 * @param string $ignore_discounts Method setting.
	 * @param float  $price            Product price (tax-exclusive).
	 * @param float  $discount         Fixed cart coupon amount (0 = none).
	 * @param bool   $tax_inclusive    Display cart prices including 20% tax.
	 * @param bool   $expected         WooCommerce's expected verdict.
	 */
	public function test_progress_agrees_with_woocommerce_verdict( float $minimum, string $ignore_discounts, float $price, float $discount, bool $tax_inclusive, bool $expected ): void {
		if ( $tax_inclusive ) {
			$this->enable_twenty_percent_tax();
		}

		$this->create_zone( 'US', $minimum, 'min_amount', $ignore_discounts );
		$this->cart_with_product( $price );
		if ( $discount > 0 ) {
			$this->apply_coupon( false, $discount );
		}

		$state = Free_Shipping_Rules::resolve();

		$this->assertSame( $expected, $state['complete'], 'WooCommerce verdict' );
		$this->assertSame(
			$state['complete'],
			$state['subtotal'] >= $state['threshold'],
			sprintf( 'Theme arithmetic (%.2f of %.2f) disagrees with WooCommerce', $state['subtotal'], $state['threshold'] )
		);
	}

	/**
	 * A coupon-only method unlocks free shipping via WooCommerce's verdict.
	 */
	public function test_coupon_only_method_unlocks_via_woocommerce(): void {
		$this->create_zone( 'US', 100.0 );
		$this->create_zone_method( 'US', 'coupon' );
		$this->cart_with_product( 20.0 );
		$this->apply_coupon( true );

		$progress = Free_Shipping::get_cart_progress();

		$this->assertNotNull( $progress );
		$this->assertTrue( $progress['complete'] );
	}

	/**
	 * While WooCommerce says "not yet", the copy never claims 0.00 remaining.
	 */
	public function test_incomplete_progress_never_shows_zero_remaining(): void {
		$this->create_zone( 'US', 50.0, 'both' );
		$this->cart_with_product( 60.0 );
		add_filter( 'aggressive_apparel_free_shipping_threshold', static fn() => 0.0 );

		// Custom threshold met arithmetically counts as complete…
		$custom = Free_Shipping::get_cart_progress( 40.0 );
		$this->assertNotNull( $custom );
		$this->assertTrue( $custom['complete'] );

		// …but a zone threshold waits for WooCommerce (no coupon yet): hidden.
		$zone = Free_Shipping::get_cart_progress();
		$this->assertNotNull( $zone );
		$this->assertSame( 0.0, $zone['threshold'] );
	}

	/**
	 * Enable a 20% US tax rate with tax-inclusive cart display.
	 *
	 * @return void
	 */
	private function enable_twenty_percent_tax(): void {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_display_cart', 'incl' );
		\WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => 'US',
				'tax_rate'          => '20.0000',
				'tax_rate_name'     => 'Tax',
				'tax_rate_priority' => '1',
				'tax_rate_compound' => '0',
				'tax_rate_shipping' => '1',
				'tax_rate_order'    => '1',
				'tax_rate_class'    => '',
			)
		);
	}

	/**
	 * Add a free-shipping method with a given `requires` to the country's zone.
	 *
	 * @param string $country  ISO country code of an existing zone.
	 * @param string $requires WooCommerce `requires` setting.
	 * @return void
	 */
	private function create_zone_method( string $country, string $requires ): void {
		$zone        = \WC_Shipping_Zones::get_zone_matching_package(
			array(
				'destination' => array(
					'country'  => $country,
					'state'    => '',
					'postcode' => '',
				),
			)
		);
		$instance_id = $zone->add_shipping_method( 'free_shipping' );
		update_option(
			'woocommerce_free_shipping_' . $instance_id . '_settings',
			array(
				'title'    => 'Free shipping (coupon)',
				'requires' => $requires,
			)
		);
		Free_Shipping::flush_threshold_cache();
	}

	/**
	 * The WooPayments integration reads the selected currency rate.
	 */
	public function test_woopayments_rate_reads_selected_currency(): void {
		$multi_currency = new class() {
			/**
			 * @return object
			 */
			public function get_selected_currency(): object {
				return new class() {
					/**
					 * @return float
					 */
					public function get_rate(): float {
						return 0.79;
					}
				};
			}
		};

		$this->assertSame( 0.79, Free_Shipping_Rules::get_woopayments_rate( $multi_currency ) );
	}

	/**
	 * An unrecognised WooPayments shape yields null instead of a guess.
	 */
	public function test_woopayments_rate_rejects_unknown_shape(): void {
		$this->assertNull( Free_Shipping_Rules::get_woopayments_rate( null ) );
		$this->assertNull( Free_Shipping_Rules::get_woopayments_rate( new \stdClass() ) );
	}

	/**
	 * Instance id of the most recently created free-shipping method.
	 *
	 * @return int
	 */
	private function first_free_shipping_instance(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = 'free_shipping' ORDER BY instance_id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Settings of that method.
	 *
	 * @return array<string, mixed>
	 */
	private function first_free_shipping_settings(): array {
		return (array) get_option( 'woocommerce_free_shipping_' . $this->first_free_shipping_instance() . '_settings', array() );
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
