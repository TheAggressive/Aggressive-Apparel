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
 */
class Free_Shipping {

	/**
	 * Transient key for the zone-detected threshold.
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
	 * Request-local memo (avoids repeat transient hits in one request).
	 *
	 * @var float|null
	 */
	private static ?float $cached_threshold = null;

	/**
	 * Register shipping-settings invalidation hooks.
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
	}

	/**
	 * Drop request + persistent threshold caches.
	 *
	 * @return void
	 */
	public static function flush_threshold_cache(): void {
		self::$cached_threshold = null;
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Resolve the free-shipping threshold in store currency.
	 *
	 * Filter overrides and block custom thresholds are not persisted — only
	 * the WooCommerce zone scan is cached across requests.
	 *
	 * @param float $custom_threshold Block override; 0 uses auto-detect/filter.
	 * @return float Threshold amount, or 0 when none configured.
	 */
	public static function get_threshold( float $custom_threshold = 0.0 ): float {
		if ( $custom_threshold > 0 ) {
			return $custom_threshold;
		}

		if ( null !== self::$cached_threshold ) {
			return self::$cached_threshold;
		}

		$threshold = (float) apply_filters( 'aggressive_apparel_free_shipping_threshold', 0.0 );
		if ( $threshold > 0 ) {
			self::$cached_threshold = $threshold;
			return self::$cached_threshold;
		}

		/**
		 * Zone-detected threshold from transient or rebuild.
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
		return self::$cached_threshold;
	}

	/**
	 * Walk WooCommerce shipping zones for the lowest free-shipping min amount.
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
				if ( 'free_shipping' !== $method->id ) {
					continue;
				}

				$requires = $method->get_option( 'requires', '' );
				if ( ! in_array( $requires, array( 'min_amount', 'either', 'both' ), true ) ) {
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
	 * Cart progress toward a free-shipping threshold.
	 *
	 * @param float $custom_threshold Block override; 0 uses auto-detect/filter.
	 * @return array{
	 *     threshold: float,
	 *     cart_total: float,
	 *     remaining: float,
	 *     percent: float,
	 *     complete: bool
	 * }|null Null when no threshold is configured.
	 */
	public static function get_cart_progress( float $custom_threshold = 0.0 ): ?array {
		if ( ! function_exists( 'WC' ) || ! \WC()->cart ) { // @phpstan-ignore booleanNot.alwaysFalse
			return null;
		}

		$threshold = self::get_threshold( $custom_threshold );
		if ( $threshold <= 0 ) {
			return null;
		}

		$cart_total = (float) \WC()->cart->get_displayed_subtotal();
		$remaining  = max( 0.0, $threshold - $cart_total );
		$percent    = min( 100.0, ( $cart_total / $threshold ) * 100 );

		return array(
			'threshold'  => $threshold,
			'cart_total' => $cart_total,
			'remaining'  => $remaining,
			'percent'    => $percent,
			'complete'   => $remaining <= 0,
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
