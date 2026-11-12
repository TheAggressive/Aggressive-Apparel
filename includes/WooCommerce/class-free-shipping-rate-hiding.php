<?php
/**
 * Stand-in for WooCommerce's "Hide shipping rates when free shipping is
 * available" setting until a core fix ships.
 *
 * @package Aggressive_Apparel
 * @since 1.186.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * Applies the core hide-when-free setting safely.
 *
 * Core's hiding loop looks up each rate's method by `$rate->method_id` with
 * no null guard, so a rate whose method isn't registered under that id (e.g.
 * Printful's `printful_shipping_*` rates) fatals with `supports() on null`,
 * which 500s every Store API cart request with shippable items. It also runs
 * before `woocommerce_package_rates`, so a plugin that later removes free
 * shipping leaves no rates at all (woocommerce/woocommerce#63300).
 *
 * The fix (woocommerce/woocommerce#68707) was milestoned for 11.3.0 but
 * reverted before release (#68854); 11.3.0 still ships the unguarded loop.
 * So this is not version-gated: it runs on every WooCommerce, and
 * TestFreeShippingRateHiding checks core's actual behavior to say when it
 * can go. On a core that is fixed, it is harmless: core then hides after the
 * rates filter with the setting already restored, which is a no-op on rates
 * this has already reduced.
 *
 * While the merchant's checkbox is on, this keeps core's loop from running
 * (the option reads 'no' only between rate collection and the rates filter)
 * and applies the same rule after every other `woocommerce_package_rates`
 * callback.
 */
class Free_Shipping_Rate_Hiding {

	/**
	 * Core's setting (WooCommerce → Settings → Shipping).
	 *
	 * @var string
	 */
	public const OPTION = 'woocommerce_shipping_hide_rates_when_free';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'woocommerce_before_get_rates_for_package', array( self::class, 'suppress_core' ) );
		add_filter( 'woocommerce_package_rates', array( self::class, 'restore_core' ), PHP_INT_MIN );
		add_filter( 'woocommerce_package_rates', array( self::class, 'hide_paid_rates' ), PHP_INT_MAX );
	}

	/**
	 * Make core's hiding loop read the setting as off for this calculation.
	 *
	 * Fires per method inside the rate loop, i.e. after the stored-rates
	 * check and before core's hiding block.
	 *
	 * @return void
	 */
	public static function suppress_core(): void {
		add_filter( 'pre_option_' . self::OPTION, array( self::class, 'option_off' ) );
	}

	/**
	 * Short-circuit value for the suppressed option.
	 *
	 * @return string
	 */
	public static function option_off(): string {
		return 'no';
	}

	/**
	 * Drop the suppression once core's hiding block has been skipped.
	 *
	 * @param mixed $rates Package rates.
	 * @return mixed
	 */
	public static function restore_core( $rates ) {
		remove_filter( 'pre_option_' . self::OPTION, array( self::class, 'option_off' ) );
		return $rates;
	}

	/**
	 * Keep only free shipping and local pickup when free shipping is offered.
	 *
	 * @param mixed $rates Package rates, keyed by rate id.
	 * @return mixed
	 */
	public static function hide_paid_rates( $rates ) {
		if ( ! is_array( $rates ) || 'yes' !== get_option( self::OPTION, 'no' ) ) {
			return $rates;
		}

		$free   = array();
		$pickup = array();

		foreach ( $rates as $key => $rate ) {
			if ( ! $rate instanceof \WC_Shipping_Rate ) {
				continue;
			}

			if ( 'free_shipping' === $rate->get_method_id() ) {
				$free[ $key ] = $rate;
				continue;
			}

			if ( self::is_pickup( $rate->get_method_id() ) ) {
				$pickup[ $key ] = $rate;
			}
		}

		return empty( $free ) ? $rates : $free + $pickup;
	}

	/**
	 * Whether a rate's method is local pickup, tolerating unregistered ids.
	 *
	 * @param string $method_id Rate method id.
	 * @return bool
	 */
	private static function is_pickup( string $method_id ): bool {
		if ( 'local_pickup' === $method_id ) {
			return true;
		}

		$method = WC()->shipping()->get_shipping_methods()[ $method_id ] ?? null;

		return $method instanceof \WC_Shipping_Method && $method->supports( 'local-pickup' );
	}
}
