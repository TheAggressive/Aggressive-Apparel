<?php
/**
 * Free shipping threshold and cart progress helpers.
 *
 * @package Aggressive_Apparel
 * @since 1.123.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

use Aggressive_Apparel\Core\Cache_Helper;

/**
 * Reads WooCommerce free-shipping settings and cart progress.
 *
 * Thresholds are per customer: the shipping zone matching the customer's
 * location decides the amount, in the customer's active currency. Server
 * HTML only carries a first-paint guess (a full-page cache serves the priming
 * visitor's copy), so the live value rides on the Store API cart response and
 * the blocks rehydrate from it.
 */
class Free_Shipping {

	/**
	 * Store API cart extension namespace (mirrored in cart-data.ts).
	 *
	 * @var string
	 */
	public const STORE_API_NAMESPACE = 'aggressive-apparel/free-shipping';

	/**
	 * Transient key for the store-wide "any zone offers a minimum" scan.
	 *
	 * @var string
	 */
	private const TRANSIENT_KEY = 'aggressive_apparel_free_shipping_threshold';

	/**
	 * Cache duration for zone scans (shipping settings change rarely).
	 *
	 * @var int
	 */
	private const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * Request-local memo of the store-wide scan.
	 *
	 * @var float|null
	 */
	private static ?float $cached_threshold = null;

	/**
	 * Request-local memo of per-zone rules, keyed by "zone id|currency".
	 *
	 * @var array<string, array{threshold: float, ignore_discounts: bool}>
	 */
	private static array $zone_rules = array();

	/**
	 * Register shipping-settings invalidation hooks and the cart extension.
	 *
	 * Called from Bootstrap when WooCommerce is active.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'woocommerce_after_shipping_zone_object_save', array( self::class, 'flush_threshold_cache' ) );
		add_action( 'woocommerce_delete_shipping_zone', array( self::class, 'flush_threshold_cache' ) );
		add_action( 'woocommerce_shipping_zone_method_added', array( self::class, 'flush_threshold_cache' ) );
		add_action( 'woocommerce_shipping_zone_method_deleted', array( self::class, 'flush_threshold_cache' ) );
		add_action( 'woocommerce_shipping_zone_method_status_toggled', array( self::class, 'flush_threshold_cache' ) );
		add_action( 'update_option_woocommerce_free_shipping_settings', array( self::class, 'flush_threshold_cache' ) );
		add_action( 'rest_api_init', array( self::class, 'register_store_api_extension' ) );
	}

	/**
	 * Drop request + persistent threshold caches.
	 *
	 * @return void
	 */
	public static function flush_threshold_cache(): void {
		self::$cached_threshold = null;
		self::$zone_rules       = array();
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Expose the customer's threshold on the Store API `cart` endpoint.
	 *
	 * The free-shipping blocks already fetch the cart on load, so this costs
	 * no extra request and corrects cached first-paint HTML.
	 *
	 * @return void
	 */
	public static function register_store_api_extension(): void {
		Store_Api_Extension::register_cart_data(
			self::STORE_API_NAMESPACE,
			array( self::class, 'get_store_api_cart_data' )
		);
	}

	/**
	 * Cart extension payload, in Store API minor units.
	 *
	 * `rate` converts store-currency amounts (block custom thresholds) to the
	 * active currency client-side, since the cart request can't know which
	 * block instances are on the page.
	 *
	 * @return array{threshold: int, subtotal: int, rate: float}
	 */
	public static function get_store_api_cart_data(): array {
		$rule   = self::get_customer_rule();
		$factor = 10 ** wc_get_price_decimals();

		return array(
			'threshold' => (int) round( $rule['threshold'] * $factor ),
			'subtotal'  => (int) round( self::get_qualifying_subtotal( $rule['ignore_discounts'] ) * $factor ),
			'rate'      => self::get_currency_rate(),
		);
	}

	/**
	 * Resolve the customer's free-shipping threshold in the active currency.
	 *
	 * @param float $custom_threshold Block override in store currency; 0 uses the filter or zone.
	 * @return float Threshold amount, or 0 when the customer's zone has none.
	 */
	public static function get_threshold( float $custom_threshold = 0.0 ): float {
		return self::get_customer_rule( $custom_threshold )['threshold'];
	}

	/**
	 * Whether free shipping is offered anywhere (decides if blocks render).
	 *
	 * Deliberately customer-independent: server HTML is cacheable, so a block
	 * must not vanish for everyone because the priming visitor's zone had no
	 * threshold. Per-customer visibility is handled client-side.
	 *
	 * @param float $custom_threshold Block override; > 0 always offers.
	 * @return bool
	 */
	public static function is_offered( float $custom_threshold = 0.0 ): bool {
		if ( $custom_threshold > 0 || self::get_filtered_threshold() > 0 ) {
			return true;
		}

		if ( null === self::$cached_threshold ) {
			/**
			 * Lowest store-currency minimum across all zones, from transient or rebuild.
			 *
			 * @var float $detected
			 */
			$detected = Cache_Helper::remember(
				self::TRANSIENT_KEY,
				self::CACHE_TTL,
				static fn(): float => self::detect_threshold_from_zones(),
				static fn( $cached ): bool => is_numeric( $cached )
			);

			self::$cached_threshold = (float) $detected;
		}

		return self::$cached_threshold > 0;
	}

	/**
	 * Resolve the threshold and discount rule for the current customer.
	 *
	 * Precedence: block custom threshold, then the filter, then the customer's
	 * zone. Zone amounts come from `$method->min_amount` — the value WooCommerce
	 * itself tests in WC_Shipping_Free_Shipping::is_available(), which
	 * multi-currency plugins convert — so "unlocked" matches checkout exactly.
	 *
	 * @param float $custom_threshold Block override in store currency.
	 * @return array{threshold: float, ignore_discounts: bool}
	 */
	public static function get_customer_rule( float $custom_threshold = 0.0 ): array {
		$zone_rule = self::get_zone_rule();

		if ( $custom_threshold <= 0 ) {
			$custom_threshold = self::get_filtered_threshold();
		}

		if ( $custom_threshold > 0 ) {
			return array(
				'threshold'        => self::to_active_currency( $custom_threshold ),
				'ignore_discounts' => $zone_rule['ignore_discounts'],
			);
		}

		return $zone_rule;
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
			$multi_currency = \WC_Payments_Multi_Currency();
			$currency       = is_object( $multi_currency ) && method_exists( $multi_currency, 'get_selected_currency' )
				? $multi_currency->get_selected_currency()
				: null;

			if ( is_object( $currency ) && method_exists( $currency, 'get_rate' ) ) {
				$rate = (float) $currency->get_rate();
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
	 * Global threshold override from the filter (store currency).
	 *
	 * @return float
	 */
	private static function get_filtered_threshold(): float {
		return max( 0.0, (float) apply_filters( 'aggressive_apparel_free_shipping_threshold', 0.0 ) );
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
	 * Free-shipping rule of the zone matching the customer's shipping location.
	 *
	 * @return array{threshold: float, ignore_discounts: bool}
	 */
	private static function get_zone_rule(): array {
		$none = array(
			'threshold'        => 0.0,
			'ignore_discounts' => false,
		);

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $none;
		}

		$zone     = \WC_Shipping_Zones::get_zone_matching_package( array( 'destination' => self::get_customer_destination() ) );
		$currency = function_exists( 'get_woocommerce_currency' ) ? \get_woocommerce_currency() : '';
		$memo_key = $zone->get_id() . '|' . $currency;

		if ( isset( self::$zone_rules[ $memo_key ] ) ) {
			return self::$zone_rules[ $memo_key ];
		}

		$rule = $none;
		foreach ( $zone->get_shipping_methods( true ) as $method ) {
			if ( 'free_shipping' !== $method->id || ! self::requires_min_amount( $method ) ) {
				continue;
			}

			$amount = property_exists( $method, 'min_amount' ) ? (float) $method->min_amount : 0.0;
			if ( $amount > 0 && ( 0.0 === $rule['threshold'] || $amount < $rule['threshold'] ) ) {
				$rule = array(
					'threshold'        => $amount,
					'ignore_discounts' => property_exists( $method, 'ignore_discounts' ) && 'yes' === $method->ignore_discounts,
				);
			}
		}

		self::$zone_rules[ $memo_key ] = $rule;
		return $rule;
	}

	/**
	 * Customer shipping destination, falling back to the store default location
	 * (which is geolocated when WooCommerce is configured to).
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
	 * Mirrors WC_Shipping_Free_Shipping::is_available(): displayed subtotal
	 * (tax-inclusive when prices display with tax), less discounts unless the
	 * method ignores them.
	 *
	 * @param bool $ignore_discounts Whether the zone's method ignores coupons.
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
	 * Whether a free-shipping method is gated on a minimum order amount.
	 *
	 * @param \WC_Shipping_Method $method Shipping method.
	 * @return bool
	 */
	private static function requires_min_amount( \WC_Shipping_Method $method ): bool {
		$requires = property_exists( $method, 'requires' ) ? $method->requires : $method->get_option( 'requires', '' );

		return in_array( $requires, array( 'min_amount', 'either', 'both' ), true );
	}

	/**
	 * Walk WooCommerce shipping zones for the lowest free-shipping min amount.
	 *
	 * Raw store-currency options; only used to decide whether free shipping
	 * is offered anywhere, never shown to a customer.
	 *
	 * @return float
	 */
	private static function detect_threshold_from_zones(): float {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return 0.0;
		}

		$zones   = \WC_Shipping_Zones::get_zones();
		$zones[] = array( 'zone_id' => 0 );
		$min     = 0.0;

		foreach ( $zones as $zone_data ) {
			$zone    = new \WC_Shipping_Zone( $zone_data['zone_id'] );
			$methods = $zone->get_shipping_methods( true );

			foreach ( $methods as $method ) {
				if ( 'free_shipping' !== $method->id || ! self::requires_min_amount( $method ) ) {
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
	 * Cart progress toward the customer's free-shipping threshold.
	 *
	 * `threshold` is 0 when free shipping is offered somewhere but not in the
	 * customer's zone — blocks render hidden so the client can reveal them.
	 *
	 * @param float $custom_threshold Block override in store currency; 0 uses filter/zone.
	 * @return array{
	 *     threshold: float,
	 *     cart_total: float,
	 *     remaining: float,
	 *     percent: float,
	 *     complete: bool
	 * }|null Null when free shipping is not offered anywhere.
	 */
	public static function get_cart_progress( float $custom_threshold = 0.0 ): ?array {
		if ( ! function_exists( 'WC' ) || ! \WC()->cart || ! self::is_offered( $custom_threshold ) ) {
			return null;
		}

		$rule       = self::get_customer_rule( $custom_threshold );
		$threshold  = $rule['threshold'];
		$cart_total = self::get_qualifying_subtotal( $rule['ignore_discounts'] );

		if ( $threshold <= 0 ) {
			return array(
				'threshold'  => 0.0,
				'cart_total' => $cart_total,
				'remaining'  => 0.0,
				'percent'    => 0.0,
				'complete'   => false,
			);
		}

		$remaining = max( 0.0, $threshold - $cart_total );

		return array(
			'threshold'  => $threshold,
			'cart_total' => $cart_total,
			'remaining'  => $remaining,
			'percent'    => min( 100.0, ( $cart_total / $threshold ) * 100 ),
			'complete'   => $remaining <= 0,
		);
	}

	/**
	 * Currency formatting for the blocks' client-side amounts.
	 *
	 * Mirrors the Store API currency fields so SSR context and cart responses
	 * format identically (symbol position, separators, decimals).
	 *
	 * @return array{
	 *     currencyPrefix: string,
	 *     currencySuffix: string,
	 *     currencyMinorUnit: int,
	 *     currencyDecimalSeparator: string,
	 *     currencyThousandSeparator: string
	 * }
	 */
	public static function get_currency_context(): array {
		$symbol   = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES );
		$position = (string) get_option( 'woocommerce_currency_pos', 'left' );
		$prefix   = '';
		$suffix   = '';

		switch ( $position ) {
			case 'left_space':
				$prefix = $symbol . ' ';
				break;
			case 'right':
				$suffix = $symbol;
				break;
			case 'right_space':
				$suffix = ' ' . $symbol;
				break;
			default:
				$prefix = $symbol;
		}

		return array(
			'currencyPrefix'            => $prefix,
			'currencySuffix'            => $suffix,
			'currencyMinorUnit'         => wc_get_price_decimals(),
			'currencyDecimalSeparator'  => wc_get_price_decimal_separator(),
			'currencyThousandSeparator' => wc_get_price_thousand_separator(),
		);
	}

	/**
	 * Translatable message templates for the free-shipping message block.
	 *
	 * Edit strings here — they are injected into `data-wp-context` for live JS
	 * updates and used by format_message() for the initial server render.
	 *
	 * Filter: aggressive_apparel_free_shipping_message_i18n
	 *
	 * @return array{
	 *     progressDefault: string,
	 *     progressCustom: string,
	 *     unlockedDefault: string,
	 *     unlockedCustom: string
	 * }
	 */
	public static function get_message_i18n(): array {
		$templates = array(
			/* translators: %s: formatted amount still needed for free shipping. */
			'progressDefault' => __( '%s Away from FREE Shipping!', 'aggressive-apparel' ),
			/* translators: 1: formatted amount, 2: emphasized free shipping phrase. */
			'progressCustom'  => __( '%1$s Away from %2$s!', 'aggressive-apparel' ),
			'unlockedDefault' => __( 'FREE Shipping UNLOCKED!', 'aggressive-apparel' ),
			/* translators: %s: emphasized free shipping phrase. */
			'unlockedCustom'  => __( '%s UNLOCKED!', 'aggressive-apparel' ),
		);

		/**
		 * Filter free-shipping message templates (SSR + live JS).
		 *
		 * @param array $templates Message template strings with %s placeholders.
		 */
		$filtered = (array) apply_filters( 'aggressive_apparel_free_shipping_message_i18n', $templates );

		return array(
			'progressDefault' => (string) ( $filtered['progressDefault'] ?? $templates['progressDefault'] ),
			'progressCustom'  => (string) ( $filtered['progressCustom'] ?? $templates['progressCustom'] ),
			'unlockedDefault' => (string) ( $filtered['unlockedDefault'] ?? $templates['unlockedDefault'] ),
			'unlockedCustom'  => (string) ( $filtered['unlockedCustom'] ?? $templates['unlockedCustom'] ),
		);
	}

	/**
	 * Translatable strings for the free-shipping progress bar block.
	 *
	 * Filter: aggressive_apparel_free_shipping_bar_message_i18n
	 *
	 * @return array{ progress: string, complete: string }
	 */
	public static function get_bar_message_i18n(): array {
		$templates = array(
			/* translators: %s: formatted amount still needed for free shipping. */
			'progress' => __( '%s Away from FREE Shipping!', 'aggressive-apparel' ),
			'complete' => __( 'FREE Shipping UNLOCKED!', 'aggressive-apparel' ),
		);

		/**
		 * Filter free-shipping bar message templates (SSR + live JS).
		 *
		 * @param array $templates Message template strings with %s placeholders.
		 */
		$filtered = (array) apply_filters( 'aggressive_apparel_free_shipping_bar_message_i18n', $templates );

		return array(
			'progress' => (string) ( $filtered['progress'] ?? $templates['progress'] ),
			'complete' => (string) ( $filtered['complete'] ?? $templates['complete'] ),
		);
	}

	/**
	 * Format the free-shipping message for the front end.
	 *
	 * Uses the same templates as get_message_i18n() — edit copy there, not here.
	 *
	 * @param float  $remaining    Amount still needed.
	 * @param string $emphasis     Phrase emphasized in the message (e.g. FREE Shipping).
	 * @param bool   $complete     Whether the threshold has been met.
	 * @return string
	 */
	public static function format_message( float $remaining, string $emphasis, bool $complete ): string {
		$emphasis = trim( $emphasis );
		$i18n     = self::get_message_i18n();

		if ( $complete ) {
			if ( self::is_default_emphasis( $emphasis ) ) {
				return $i18n['unlockedDefault'];
			}

			return sprintf( $i18n['unlockedCustom'], $emphasis );
		}

		$formatted_amount = self::format_remaining_amount( $remaining );

		if ( self::is_default_emphasis( $emphasis ) ) {
			return sprintf( $i18n['progressDefault'], $formatted_amount );
		}

		return sprintf( $i18n['progressCustom'], $formatted_amount, $emphasis );
	}

	/**
	 * Format the free-shipping bar message for the front end.
	 *
	 * @param float $remaining Amount still needed.
	 * @param bool  $complete  Whether the threshold has been met.
	 * @return string
	 */
	public static function format_bar_message( float $remaining, bool $complete ): string {
		$i18n = self::get_bar_message_i18n();

		if ( $complete ) {
			return $i18n['complete'];
		}

		return sprintf( $i18n['progress'], self::format_remaining_amount( $remaining ) );
	}

	/**
	 * Format a remaining amount using WooCommerce price formatting when available.
	 *
	 * @param float $remaining Amount still needed.
	 * @return string
	 */
	private static function format_remaining_amount( float $remaining ): string {
		if ( function_exists( 'wc_price' ) ) {
			return wp_strip_all_tags( wc_price( $remaining ) );
		}

		return number_format_i18n( $remaining, 2 );
	}

	/**
	 * Whether the emphasis phrase is the default free-shipping label.
	 *
	 * @param string $emphasis Emphasis phrase from block settings.
	 * @return bool
	 */
	private static function is_default_emphasis( string $emphasis ): bool {
		if ( '' === $emphasis ) {
			return true;
		}

		return 'freeshipping' === strtolower( str_replace( ' ', '', $emphasis ) );
	}
}
