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
 * Free-shipping progress for the blocks.
 *
 * Thresholds are per customer (zone + active currency), resolved by
 * Free_Shipping_Rules. Server HTML only carries a first-paint value (a
 * full-page cache serves the priming visitor's copy), so the live value rides
 * on the Store API cart response and the blocks rehydrate from it.
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
		Free_Shipping_Rules::flush();
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Expose the customer's threshold on the Store API `cart` endpoint.
	 *
	 * The free-shipping blocks already fetch the cart when it holds items, so
	 * this costs no extra request and corrects cached first-paint HTML.
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
	 * `unlocked` is WooCommerce's own verdict that free shipping applies.
	 * `rate` converts store-currency amounts (block custom thresholds) to the
	 * active currency client-side, since the cart request can't know which
	 * block instances are on the page.
	 *
	 * @return array{threshold: int, subtotal: int, rate: float, unlocked: bool}
	 */
	public static function get_store_api_cart_data(): array {
		$state  = Free_Shipping_Rules::resolve();
		$factor = 10 ** wc_get_price_decimals();

		return array(
			'threshold' => (int) round( $state['threshold'] * $factor ),
			'subtotal'  => (int) round( $state['subtotal'] * $factor ),
			'rate'      => Free_Shipping_Rules::get_currency_rate(),
			'unlocked'  => $state['complete'],
		);
	}

	/**
	 * Resolve the customer's free-shipping threshold in the active currency.
	 *
	 * @param float $custom_threshold Block override in store currency; 0 uses the filter or zone.
	 * @return float Threshold amount, or 0 when the customer's zone has none.
	 */
	public static function get_threshold( float $custom_threshold = 0.0 ): float {
		return Free_Shipping_Rules::resolve( $custom_threshold )['threshold'];
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
		if ( $custom_threshold > 0 || Free_Shipping_Rules::get_filtered_threshold() > 0 ) {
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
				static fn(): float => Free_Shipping_Rules::get_lowest_configured_minimum(),
				static fn( $cached ): bool => is_numeric( $cached )
			);

			self::$cached_threshold = (float) $detected;
		}

		return self::$cached_threshold > 0;
	}

	/**
	 * Cart progress toward the customer's free-shipping threshold.
	 *
	 * `threshold` is 0 when free shipping is offered somewhere but not in the
	 * customer's zone — blocks render hidden so the client can reveal them.
	 * `complete` is WooCommerce's verdict; while it is false, at least one
	 * minor unit is shown as remaining so the copy never claims "0.00 away".
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

		$state     = Free_Shipping_Rules::resolve( $custom_threshold );
		$threshold = $state['threshold'];
		$subtotal  = $state['subtotal'];

		if ( $threshold <= 0 ) {
			return array(
				'threshold'  => 0.0,
				'cart_total' => $subtotal,
				'remaining'  => 0.0,
				'percent'    => 0.0,
				'complete'   => false,
			);
		}

		$complete  = $state['complete'];
		$min_unit  = 1 / ( 10 ** wc_get_price_decimals() );
		$remaining = $complete ? 0.0 : max( $min_unit, $threshold - $subtotal );

		return array(
			'threshold'  => $threshold,
			'cart_total' => $subtotal,
			'remaining'  => $remaining,
			'percent'    => $complete ? 100.0 : min( 100.0, ( $subtotal / $threshold ) * 100 ),
			'complete'   => $complete,
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
}
