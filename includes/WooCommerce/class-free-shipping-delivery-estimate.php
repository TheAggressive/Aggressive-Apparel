<?php
/**
 * Carry Printful's delivery estimate onto the free-shipping rate.
 *
 * @package Aggressive_Apparel
 * @since 1.186.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * Appends a Printful rate's "(Estimated delivery: …)" suffix to free shipping.
 *
 * With "Hide shipping rates when free shipping is available" on, a qualifying
 * cart keeps only free shipping, so Printful's rates (the only ones that
 * carry a delivery estimate, in their label) disappear with their estimate.
 * This copies the estimate from Printful's Standard rate, else its cheapest,
 * so the shopper still sees "Free shipping (Estimated delivery: Oct 12–13)".
 *
 * Runs just before Free_Shipping_Rate_Hiding (PHP_INT_MAX), and core 11.3.0+
 * hides after the whole filter, so the Printful rates are still present.
 */
class Free_Shipping_Delivery_Estimate {

	/**
	 * Method-id prefix of Printful rates (`printful_shipping_<RATE ID>`).
	 *
	 * @var string
	 */
	private const PRINTFUL_PREFIX = 'printful_shipping_';

	/**
	 * Printful's standard service; its estimate matches free shipping best.
	 *
	 * @var string
	 */
	private const PRINTFUL_STANDARD = 'printful_shipping_STANDARD';

	/**
	 * Register the rates filter.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'woocommerce_package_rates', array( self::class, 'label_free_shipping' ), PHP_INT_MAX - 1 );
	}

	/**
	 * Append the Printful estimate to each free-shipping rate's label.
	 *
	 * @param mixed $rates Package rates, keyed by rate id.
	 * @return mixed
	 */
	public static function label_free_shipping( $rates ) {
		if ( ! is_array( $rates ) ) {
			return $rates;
		}

		$estimate = self::printful_estimate( $rates );
		if ( '' === $estimate ) {
			return $rates;
		}

		foreach ( $rates as $rate ) {
			if ( $rate instanceof \WC_Shipping_Rate && 'free_shipping' === $rate->get_method_id() && ! str_ends_with( $rate->get_label(), $estimate ) ) {
				$rate->set_label( $rate->get_label() . ' ' . $estimate );
			}
		}

		return $rates;
	}

	/**
	 * The trailing parenthetical of the best Printful rate, or ''.
	 *
	 * @param array<mixed> $rates Package rates.
	 * @return string
	 */
	private static function printful_estimate( array $rates ): string {
		$best = null;

		foreach ( $rates as $rate ) {
			if ( ! $rate instanceof \WC_Shipping_Rate || ! str_starts_with( $rate->get_method_id(), self::PRINTFUL_PREFIX ) ) {
				continue;
			}

			if ( self::PRINTFUL_STANDARD === $rate->get_method_id() ) {
				$best = $rate;
				break;
			}

			if ( null === $best || (float) $rate->get_cost() < (float) $best->get_cost() ) {
				$best = $rate;
			}
		}

		if ( null === $best || ! preg_match( '/\([^()]+\)\s*$/u', $best->get_label(), $match ) ) {
			return '';
		}

		return trim( $match[0] );
	}
}
