<?php
/**
 * Free-shipping delivery estimate tests.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Tests\Unit\WooCommerce;

use Aggressive_Apparel\WooCommerce\Free_Shipping_Delivery_Estimate;
use WP_UnitTestCase;

/**
 * @covers \Aggressive_Apparel\WooCommerce\Free_Shipping_Delivery_Estimate
 */
class TestFreeShippingDeliveryEstimate extends WP_UnitTestCase {

	/**
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( 'woocommerce_shipping_hide_rates_when_free' );
		parent::tearDown();
	}

	/**
	 * Build a rates array keyed by rate id, as core passes it.
	 *
	 * @param array<int, array{0: string, 1: string, 2: string}> $specs [method_id, label, cost] triples.
	 * @return array<string, \WC_Shipping_Rate>
	 */
	private function rates( array $specs ): array {
		$rates = array();
		foreach ( $specs as $i => $spec ) {
			$rate                     = new \WC_Shipping_Rate( $spec[0] . ':' . ( $i + 1 ), $spec[1], $spec[2], array(), $spec[0], $i + 1 );
			$rates[ $rate->get_id() ] = $rate;
		}
		return $rates;
	}

	/**
	 * @return void
	 */
	public function test_prefers_printful_standard_estimate(): void {
		$rates = $this->rates(
			array(
				array( 'printful_shipping_PRIORITY', 'Priority (Estimated delivery: Oct 8–9)', '4.00' ),
				array( 'printful_shipping_STANDARD', 'Flat Rate (Estimated delivery: Oct 12–13)', '9.35' ),
				array( 'free_shipping', 'Free shipping', '0' ),
			)
		);

		$labelled = Free_Shipping_Delivery_Estimate::label_free_shipping( $rates );

		$this->assertSame( 'Free shipping (Estimated delivery: Oct 12–13)', $labelled['free_shipping:3']->get_label() );
		$this->assertSame( 'Flat Rate (Estimated delivery: Oct 12–13)', $labelled['printful_shipping_STANDARD:2']->get_label() );
	}

	/**
	 * @return void
	 */
	public function test_falls_back_to_cheapest_printful_rate(): void {
		$rates = $this->rates(
			array(
				array( 'printful_shipping_EXPRESS', 'Express (Estimated delivery: Oct 6–7)', '19.00' ),
				array( 'printful_shipping_ECONOMY', 'Economy (Estimated delivery: Oct 14–18)', '6.50' ),
				array( 'free_shipping', 'Free shipping', '0' ),
			)
		);

		$labelled = Free_Shipping_Delivery_Estimate::label_free_shipping( $rates );

		$this->assertSame( 'Free shipping (Estimated delivery: Oct 14–18)', $labelled['free_shipping:3']->get_label() );
	}

	/**
	 * @return void
	 */
	public function test_leaves_labels_alone_without_an_estimate_and_is_idempotent(): void {
		$plain = $this->rates(
			array(
				array( 'flat_rate', 'Flat rate (Estimated delivery: Oct 12–13)', '9.35' ),
				array( 'printful_shipping_STANDARD', 'Flat Rate', '9.35' ),
				array( 'free_shipping', 'Free shipping', '0' ),
			)
		);
		$this->assertSame( 'Free shipping', Free_Shipping_Delivery_Estimate::label_free_shipping( $plain )['free_shipping:3']->get_label() );

		$rates = $this->rates(
			array(
				array( 'printful_shipping_STANDARD', 'Flat Rate (Estimated delivery: Oct 12–13)', '9.35' ),
				array( 'free_shipping', 'Free shipping', '0' ),
			)
		);
		Free_Shipping_Delivery_Estimate::label_free_shipping( $rates );
		$this->assertSame( 'Free shipping (Estimated delivery: Oct 12–13)', Free_Shipping_Delivery_Estimate::label_free_shipping( $rates )['free_shipping:2']->get_label() );
	}

	/**
	 * Core's own calculation with a Printful-style rate and hide-when-free on:
	 * the estimate must survive the paid rate being hidden.
	 *
	 * @return void
	 */
	public function test_estimate_survives_hide_when_free(): void {
		$this->assertNotFalse( has_filter( 'woocommerce_package_rates', array( Free_Shipping_Delivery_Estimate::class, 'label_free_shipping' ) ), 'Bootstrap registers the filter.' );

		$zone = new \WC_Shipping_Zone();
		$zone->set_zone_name( 'Delivery estimate' );
		$zone->add_location( 'ZA', 'country' );
		$zone->save();
		$zone->add_shipping_method( 'free_shipping' );

		// Printful rates carry method ids like printful_shipping_STANDARD.
		$inject = static function ( array $methods ): array {
			$methods[998] = new class( 998 ) extends \WC_Shipping_Method {
				/**
				 * @param int $instance_id Instance id.
				 */
				public function __construct( $instance_id = 0 ) {
					$this->id          = 'printful_shipping_STANDARD';
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
							'label' => 'Flat Rate (Estimated delivery: Oct 12–13)',
							'cost'  => '9.35',
						)
					);
				}
			};
			return $methods;
		};
		add_filter( 'woocommerce_shipping_zone_shipping_methods', $inject );
		update_option( 'woocommerce_shipping_hide_rates_when_free', 'yes' );

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
			$result = WC()->shipping()->calculate_shipping_for_package( $package, 'delivery-estimate-test' );
		} finally {
			remove_filter( 'woocommerce_shipping_zone_shipping_methods', $inject );
		}

		$labels = array_map( static fn( $rate ) => $rate->get_label(), array_values( $result['rates'] ) );
		$this->assertSame( array( 'Free shipping (Estimated delivery: Oct 12–13)' ), $labels );
	}
}
