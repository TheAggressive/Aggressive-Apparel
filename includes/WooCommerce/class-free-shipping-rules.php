<?php
/**
 * Free-shipping rule resolution for the current customer.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the customer's free-shipping state from WooCommerce.
 *
 * Upgrade-safety contract: whether free shipping applies is WooCommerce's own
 * verdict — WC_Shipping_Free_Shipping::is_available(), including its filters
 * and anything multi-currency plugins change — never a re-implementation.
 * Only the *progress amount* (threshold and qualifying subtotal) is derived
 * here, and TestFreeShipping's parity cases pin it to WooCommerce's verdict so
 * a behaviour change in a WooCommerce release fails CI instead of drifting.
 */
final class Free_Shipping_Rules {

	/**
	 * `requires` values gated on a minimum order amount.
	 *
	 * @var list<string>
	 */
	private const MIN_AMOUNT_REQUIREMENTS = array( 'min_amount', 'either', 'both' );

	/**
	 * Request-local memo of each zone's enabled free-shipping methods, keyed by "zone id|currency".
	 *
	 * @var array<string, list<\WC_Shipping_Method>>
	 */
	private static array $zone_methods = array();

	/**
	 * Diagnostics already logged this request.
	 *
	 * @var array<string, true>
	 */
	private static array $logged = array();

	/**
	 * Drop request-local memos.
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$zone_methods = array();
	}

	/**
	 * Resolve threshold, qualifying subtotal and completion for the customer.
	 *
	 * Precedence for the threshold: block custom threshold, then the
	 * `aggressive_apparel_free_shipping_threshold` filter (both store
	 * currency, converted), then the customer's zone. Zone thresholds are
	 * complete only on WooCommerce's verdict; store-currency overrides describe
	 * free shipping configured outside WooCommerce's method, so reaching them
	 * also counts.
	 *
	 * @param float $store_threshold Block override in store currency; 0 uses the filter or zone.
	 * @return array{threshold: float, subtotal: float, complete: bool}
	 */
	public static function resolve( float $store_threshold = 0.0 ): array {
		$package   = self::get_customer_package();
		$methods   = self::get_zone_methods( $package );
		$candidate = self::get_threshold_candidate( $methods );
		$subtotal  = self::get_qualifying_subtotal( $candidate['ignore_discounts'] );
		$unlocked  = self::is_unlocked_by_woocommerce( $methods, $package );

		if ( $store_threshold <= 0 ) {
			$store_threshold = self::get_filtered_threshold();
		}

		if ( $store_threshold > 0 ) {
			$threshold = self::to_active_currency( $store_threshold );

			return array(
				'threshold' => $threshold,
				'subtotal'  => $subtotal,
				'complete'  => $unlocked || $subtotal >= $threshold,
			);
		}

		return array(
			'threshold' => $candidate['amount'],
			'subtotal'  => $subtotal,
			'complete'  => $unlocked,
		);
	}

	/**
	 * Global threshold override from the filter (store currency).
	 *
	 * @return float
	 */
	public static function get_filtered_threshold(): float {
		return max( 0.0, (float) apply_filters( 'aggressive_apparel_free_shipping_threshold', 0.0 ) );
	}

	/**
	 * Lowest configured (store-currency) minimum across every zone.
	 *
	 * Raw settings; only answers "is free shipping offered anywhere".
	 *
	 * @return float
	 */
	public static function get_lowest_configured_minimum(): float {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return 0.0;
		}

		$zones   = \WC_Shipping_Zones::get_zones();
		$zones[] = array( 'zone_id' => 0 );
		$min     = 0.0;

		foreach ( $zones as $zone_data ) {
			$zone = new \WC_Shipping_Zone( $zone_data['zone_id'] );

			foreach ( $zone->get_shipping_methods( true ) as $method ) {
				if ( 'free_shipping' !== $method->id || ! in_array( self::get_requires( $method ), self::MIN_AMOUNT_REQUIREMENTS, true ) ) {
					continue;
				}

				$amount = (float) $method->get_option( 'min_amount', 0 );
				if ( $amount > 0 && ( 0.0 === $min || $amount < $min ) ) {
					$min = $amount;
				}
			}
		}

		return $min;
	}

	/**
	 * Store-currency → active-currency multiplier.
	 *
	 * Supports WooPayments multi-currency out of the box; other currency
	 * switchers hook `aggressive_apparel_free_shipping_currency_rate`.
	 *
	 * @return float
	 */
	public static function get_currency_rate(): float {
		$rate = 1.0;

		if ( function_exists( 'WC_Payments_Multi_Currency' ) ) {
			$wcpay_rate = self::get_woopayments_rate( \WC_Payments_Multi_Currency() );

			if ( null === $wcpay_rate ) {
				self::log_once(
					'woopayments_rate',
					'Free shipping: WooPayments multi-currency is active but its currency rate could not be read; custom free-shipping thresholds are shown unconverted.'
				);
			} else {
				$rate = $wcpay_rate;
			}
		}

		/**
		 * Filter the store-currency → active-currency rate for free-shipping amounts.
		 *
		 * @param float $rate Multiplier; 1.0 when no currency switcher is active.
		 */
		$rate = (float) apply_filters( 'aggressive_apparel_free_shipping_currency_rate', $rate );

		return $rate > 0 ? $rate : 1.0;
	}

	/**
	 * Active-currency rate from a WooPayments MultiCurrency instance.
	 *
	 * Public WooPayments methods, but not a formal API — so the shape is
	 * checked, and null (logged, rate 1.0) is returned instead of guessing.
	 *
	 * @param mixed $multi_currency Result of WC_Payments_Multi_Currency().
	 * @return float|null Rate, or null when the shape is unrecognised.
	 */
	public static function get_woopayments_rate( mixed $multi_currency ): ?float {
		if ( ! is_object( $multi_currency ) || ! method_exists( $multi_currency, 'get_selected_currency' ) ) {
			return null;
		}

		$currency = $multi_currency->get_selected_currency();
		if ( ! is_object( $currency ) || ! method_exists( $currency, 'get_rate' ) ) {
			return null;
		}

		$rate = (float) $currency->get_rate();

		return $rate > 0 ? $rate : null;
	}

	/**
	 * Whether any enabled free-shipping method in the zone applies right now.
	 *
	 * @param \WC_Shipping_Method[] $methods Zone free-shipping methods.
	 * @param array<string, mixed>  $package Customer shipping package.
	 * @return bool
	 */
	private static function is_unlocked_by_woocommerce( array $methods, array $package ): bool {
		if ( ! function_exists( 'WC' ) || ! \WC()->cart ) {
			return false;
		}

		foreach ( $methods as $method ) {
			if ( $method->is_available( $package ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Lowest minimum the customer can reach by spending, with its discount rule.
	 *
	 * "Minimum AND coupon" methods only count once a free-shipping coupon is
	 * applied — spending alone can't earn them. Amounts are read from
	 * `$method->min_amount`, the value is_available() tests (and multi-currency
	 * plugins convert).
	 *
	 * @param \WC_Shipping_Method[] $methods Zone free-shipping methods.
	 * @return array{amount: float, ignore_discounts: bool}
	 */
	private static function get_threshold_candidate( array $methods ): array {
		$best       = array(
			'amount'           => 0.0,
			'ignore_discounts' => false,
		);
		$has_coupon = null;

		foreach ( $methods as $method ) {
			$requires = self::get_requires( $method );
			if ( ! in_array( $requires, self::MIN_AMOUNT_REQUIREMENTS, true ) ) {
				continue;
			}

			if ( 'both' === $requires ) {
				$has_coupon ??= self::cart_has_free_shipping_coupon();
				if ( ! $has_coupon ) {
					continue;
				}
			}

			$amount = self::get_min_amount( $method );
			if ( $amount > 0 && ( 0.0 === $best['amount'] || $amount < $best['amount'] ) ) {
				$best = array(
					'amount'           => $amount,
					'ignore_discounts' => 'yes' === self::get_setting( $method, 'ignore_discounts' ),
				);
			}
		}

		return $best;
	}

	/**
	 * Enabled free-shipping methods of the zone matching the package.
	 *
	 * @param array<string, mixed> $package Customer shipping package.
	 * @return list<\WC_Shipping_Method>
	 */
	private static function get_zone_methods( array $package ): array {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return array();
		}

		$zone     = \WC_Shipping_Zones::get_zone_matching_package( $package );
		$currency = function_exists( 'get_woocommerce_currency' ) ? \get_woocommerce_currency() : '';
		$memo_key = $zone->get_id() . '|' . $currency;

		if ( ! isset( self::$zone_methods[ $memo_key ] ) ) {
			self::$zone_methods[ $memo_key ] = array_values(
				array_filter(
					$zone->get_shipping_methods( true ),
					static fn( $method ): bool => 'free_shipping' === $method->id
				)
			);
		}

		return self::$zone_methods[ $memo_key ];
	}

	/**
	 * The package WooCommerce rates at checkout, falling back to the customer
	 * (or default, possibly geolocated) location when there is no cart.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_customer_package(): array {
		$package = array();

		if ( function_exists( 'WC' ) && \WC()->cart ) {
			$packages = \WC()->cart->get_shipping_packages();
			$first    = reset( $packages );
			$package  = is_array( $first ) ? $first : array();
		}

		$destination = isset( $package['destination'] ) && is_array( $package['destination'] ) ? $package['destination'] : array();
		if ( '' === (string) ( $destination['country'] ?? '' ) ) {
			$package['destination'] = self::get_customer_destination();
		}

		if ( ! isset( $package['contents'] ) ) {
			$package['contents'] = array();
		}

		return $package;
	}

	/**
	 * Customer shipping destination, or the store default location.
	 *
	 * @return array{country: string, state: string, postcode: string, city: string}
	 */
	private static function get_customer_destination(): array {
		$customer = function_exists( 'WC' ) ? \WC()->customer : null;

		if ( $customer instanceof \WC_Customer && '' !== $customer->get_shipping_country() ) {
			return array(
				'country'  => $customer->get_shipping_country(),
				'state'    => $customer->get_shipping_state(),
				'postcode' => $customer->get_shipping_postcode(),
				'city'     => $customer->get_shipping_city(),
			);
		}

		$default = function_exists( 'wc_get_customer_default_location' ) ? wc_get_customer_default_location() : array();

		return array(
			'country'  => (string) ( $default['country'] ?? '' ),
			'state'    => (string) ( $default['state'] ?? '' ),
			'postcode' => '',
			'city'     => '',
		);
	}

	/**
	 * Cart amount WooCommerce compares against the minimum.
	 *
	 * Mirrors WC_Shipping_Free_Shipping::is_available() for display only:
	 * displayed subtotal (tax-inclusive when prices display with tax), less
	 * discounts unless the method ignores them. Parity-tested.
	 *
	 * @param bool $ignore_discounts Whether the method ignores coupons.
	 * @return float
	 */
	private static function get_qualifying_subtotal( bool $ignore_discounts ): float {
		if ( ! function_exists( 'WC' ) || ! \WC()->cart ) {
			return 0.0;
		}

		$cart  = \WC()->cart;
		$total = (float) $cart->get_displayed_subtotal();

		if ( ! $ignore_discounts ) {
			$total -= (float) $cart->get_discount_total();
			if ( $cart->display_prices_including_tax() ) {
				$total -= (float) $cart->get_discount_tax();
			}
		}

		return round( $total, wc_get_price_decimals() );
	}

	/**
	 * Whether a valid free-shipping coupon is applied.
	 *
	 * @return bool
	 */
	private static function cart_has_free_shipping_coupon(): bool {
		if ( ! function_exists( 'WC' ) || ! \WC()->cart ) {
			return false;
		}

		$cart      = \WC()->cart;
		$discounts = new \WC_Discounts( $cart );

		foreach ( $cart->get_coupons() as $coupon ) {
			if ( $coupon->get_free_shipping() && true === $discounts->is_coupon_valid( $coupon ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Minimum amount as the method will test it.
	 *
	 * @param \WC_Shipping_Method $method Free-shipping method.
	 * @return float
	 */
	private static function get_min_amount( \WC_Shipping_Method $method ): float {
		$configured = (float) $method->get_option( 'min_amount', 0 );
		$amount     = property_exists( $method, 'min_amount' ) ? (float) $method->min_amount : $configured;

		// A currency switcher is active but the minimum was not converted:
		// checkout will test the raw amount too, so show it — and say so.
		if ( $amount > 0 && abs( $amount - $configured ) < 0.000001 && abs( self::get_currency_rate() - 1.0 ) > 0.005 ) {
			self::log_once(
				'unconverted_min_amount',
				'Free shipping: the free-shipping minimum is not converted to the active currency, so checkout compares a converted cart against the store-currency minimum. Check your currency switcher\'s shipping settings.'
			);
		}

		return $amount;
	}

	/**
	 * The method's `requires` setting.
	 *
	 * @param \WC_Shipping_Method $method Free-shipping method.
	 * @return string
	 */
	private static function get_requires( \WC_Shipping_Method $method ): string {
		return self::get_setting( $method, 'requires' );
	}

	/**
	 * A method setting, preferring the runtime property WooCommerce reads.
	 *
	 * @param \WC_Shipping_Method $method Shipping method.
	 * @param string              $key    Setting key.
	 * @return string
	 */
	private static function get_setting( \WC_Shipping_Method $method, string $key ): string {
		$value = property_exists( $method, $key ) ? $method->{$key} : $method->get_option( $key, '' );

		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Convert a store-currency amount to the active currency.
	 *
	 * @param float $amount Store-currency amount.
	 * @return float
	 */
	private static function to_active_currency( float $amount ): float {
		return round( $amount * self::get_currency_rate(), wc_get_price_decimals() );
	}

	/**
	 * Log a diagnostic once per request (WP_DEBUG only).
	 *
	 * @param string $key     Dedup key.
	 * @param string $message Message.
	 * @return void
	 */
	private static function log_once( string $key, string $message ): void {
		if ( isset( self::$logged[ $key ] ) ) {
			return;
		}

		self::$logged[ $key ] = true;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			aggressive_apparel_debug_log( $message );
		}
	}
}
