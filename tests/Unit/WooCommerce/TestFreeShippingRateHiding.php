<?php
/**
 * Hide-when-free stand-in tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Free_Shipping_Rate_Hiding;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Free_Shipping_Rate_Hiding
 */
class TestFreeShippingRateHiding extends WP_UnitTestCase {

	/**
	 * @return void
	 */
	public function tearDown(): void {
		Free_Shipping_Rate_Hiding::restore_core( array() );
		delete_option( Free_Shipping_Rate_Hiding::OPTION );
		parent::tearDown();
	}

	/**
	 * Build a rates array keyed by rate id, as core passes it.
	 *
	 * @param array<int, array{0: string, 1: string}> $specs [method_id, cost] pairs.
	 * @return array<string, \WC_Shipping_Rate>
	 */
	private function rates( array $specs ): array {
		$rates = array();
		foreach ( $specs as $i => $spec ) {
			$rate                     = new \WC_Shipping_Rate( $spec[0] . ':' . ( $i + 1 ), $spec[0], $spec[1], array(), $spec[0], $i + 1 );
			$rates[ $rate->get_id() ] = $rate;
		}
		return $rates;
	}

	/**
	 * @return void
	 */
	public function test_keeps_free_and_pickup_and_tolerates_unregistered_methods(): void {
		update_option( Free_Shipping_Rate_Hiding::OPTION, 'yes' );

		$rates = $this->rates(
			array(
				array( 'flat_rate', '9.35' ),
				array( 'plugin_live_rate', '12.00' ),
				array( 'free_shipping', '0' ),
				array( 'local_pickup', '0' ),
			)
		);

		$this->assertSame( array( 'free_shipping:3', 'local_pickup:4' ), array_keys( Free_Shipping_Rate_Hiding::hide_paid_rates( $rates ) ) );
	}

	/**
	 * @return void
	 */
	public function test_leaves_rates_alone_without_free_shipping_or_when_off(): void {
		$rates = $this->rates( array( array( 'flat_rate', '9.35' ), array( 'free_shipping', '0' ) ) );
		$this->assertSame( $rates, Free_Shipping_Rate_Hiding::hide_paid_rates( $rates ) );

		update_option( Free_Shipping_Rate_Hiding::OPTION, 'yes' );
		$paid = $this->rates( array( array( 'flat_rate', '9.35' ) ) );
		$this->assertSame( $paid, Free_Shipping_Rate_Hiding::hide_paid_rates( $paid ) );
	}

	/**
	 * @return void
	 */
	public function test_core_sees_setting_off_only_until_the_rates_filter(): void {
		update_option( Free_Shipping_Rate_Hiding::OPTION, 'yes' );

		Free_Shipping_Rate_Hiding::suppress_core();
		$this->assertSame( 'no', get_option( Free_Shipping_Rate_Hiding::OPTION ) );

		Free_Shipping_Rate_Hiding::restore_core( array() );
		$this->assertSame( 'yes', get_option( Free_Shipping_Rate_Hiding::OPTION ) );
	}

	/**
	 * Core's own calculation with a plugin-style rate whose method id isn't
	 * registered: the case that fatals in core before 11.3.0.
	 *
	 * @return void
	 */
	public function test_core_calculation_with_unregistered_rate_method(): void {
		if ( ! Free_Shipping_Rate_Hiding::is_needed() ) {
			$this->markTestSkipped( 'Core carries the fix; see test_stand_in_is_deleted_once_core_fix_ships.' );
		}

		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( 'Rate hiding' );
		$zone->add_location( 'ZA', 'country' );
		$zone->save();
		$zone->add_shipping_method( 'free_shipping' );

		// A zone method registered under no method id, as some shipping plugins do.
		$inject = static function ( array $methods ): array {
			$methods[999] = new class( 999 ) extends \WC_Shipping_Method {
				/**
				 * @param int $instance_id Instance id.
				 */
				public function __construct( $instance_id = 0 ) {
					$this->id          = 'plugin_live_rate';
					$this->instance_id = absint( $instance_id );
					$this->enabled     = 'yes';
					$this->supports    = array( 'shipping-zones' );
				}

				/**
				 * @param array<string, mixed> $package Package.
				 * @return void
				 */
				public function calculate_shipping( $package = array() ): void {
					$this->add_rate(
						array(
							'label' => 'Flat Rate (Estimated delivery)',
							'cost'  => '9.35',
						)
					);
				}
			};
			return $methods;
		};
		add_filter( 'woocommerce_shipping_zone_shipping_methods', $inject );
		update_option( Free_Shipping_Rate_Hiding::OPTION, 'yes' );
		$this->assertNotFalse( has_filter( 'woocommerce_package_rates', array( Free_Shipping_Rate_Hiding::class, 'hide_paid_rates' ) ), 'Bootstrap registers the stand-in.' );

		if ( null === WC()->session ) {
			WC()->initialize_session();
		}

		$package = array(
			'contents'        => array(),
			'contents_cost'   => 100,
			'applied_coupons' => array(),
			'user'            => array( 'ID' => 0 ),
			'destination'     => array(
				'country'   => 'ZA',
				'state'     => '',
				'postcode'  => '',
				'city'      => '',
				'address'   => '',
				'address_1' => '',
				'address_2' => '',
			),
			'cart_subtotal'   => 100,
		);

		try {
			$result = WC()->shipping()->calculate_shipping_for_package( $package, 'rate-hiding-test' );
		} finally {
			remove_filter( 'woocommerce_shipping_zone_shipping_methods', $inject );
		}

		$methods = array_map( static fn( $rate ) => $rate->get_method_id(), array_values( $result['rates'] ) );
		$this->assertSame( array( 'free_shipping' ), $methods );
		$this->assertSame( 'yes', get_option( Free_Shipping_Rate_Hiding::OPTION ) );
	}

	/**
	 * @return void
	 */
	public function test_stand_in_is_deleted_once_core_fix_ships(): void {
		$this->assertTrue(
			Free_Shipping_Rate_Hiding::is_needed(),
			sprintf(
				'WooCommerce %s carries the hide-when-free fix (woocommerce/woocommerce#68707). Delete Free_Shipping_Rate_Hiding, its Bootstrap init() call, and this test file.',
				WC_VERSION
			)
		);
	}

	/**
	 * @return void
	 */
	public function test_removal_notice_is_admin_only_on_listed_screens(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		set_current_screen( 'dashboard' );

		ob_start();
		Free_Shipping_Rate_Hiding::render_removal_notice();
		$this->assertStringContainsString( 'class-free-shipping-rate-hiding.php', (string) ob_get_clean() );

		set_current_screen( 'edit-post' );
		ob_start();
		Free_Shipping_Rate_Hiding::render_removal_notice();
		$this->assertSame( '', ob_get_clean() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		set_current_screen( 'dashboard' );
		ob_start();
		Free_Shipping_Rate_Hiding::render_removal_notice();
		$this->assertSame( '', ob_get_clean() );
	}
}
