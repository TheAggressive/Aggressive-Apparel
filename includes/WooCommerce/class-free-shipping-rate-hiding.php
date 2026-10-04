<?php
/**
 * Stand-in for WooCommerce's "Hide shipping rates when free shipping is
 * available" setting until the core fix ships.
 *
 * @package Aggressive_Apparel
 * @since 1.186.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

/**
 * Applies the core hide-when-free setting safely on WooCommerce < 11.3.0.
 *
 * Before 11.3.0, core's hiding loop looks up each rate's method by
 * `$rate->method_id` with no null guard, so a rate whose method isn't
 * registered under that id (e.g. one produced by a shipping plugin) fatals
 * with `supports() on null`. It also runs before `woocommerce_package_rates`,
 * so a plugin that later removes free shipping leaves no rates at all
 * (woocommerce/woocommerce#63300). Both are fixed by woocommerce/woocommerce#68707.
 *
 * While the merchant's checkbox is on, this keeps core's loop from running
 * (the option reads 'no' only between rate collection and the rates filter)
 * and applies the same rule after every other `woocommerce_package_rates`
 * callback, which is the 11.3.0 ordering. On 11.3.0+ it registers nothing,
 * so core takes over with the same setting. Delete once the theme requires
 * WooCommerce 11.3.0.
 */
class Free_Shipping_Rate_Hiding {

	/**
	 * Core's setting (WooCommerce → Settings → Shipping).
	 *
	 * @var string
	 */
	public const OPTION = 'woocommerce_shipping_hide_rates_when_free';

	/**
	 * First WooCommerce release carrying the core fix.
	 *
	 * @var string
	 */
	public const FIXED_IN = '11.3.0';

	/**
	 * Register hooks when the running WooCommerce still has the bug.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( ! self::is_needed() ) {
			add_action( 'admin_notices', array( self::class, 'render_removal_notice' ) );
			return;
		}

		add_action( 'woocommerce_before_get_rates_for_package', array( self::class, 'suppress_core' ) );
		add_filter( 'woocommerce_package_rates', array( self::class, 'restore_core' ), PHP_INT_MIN );
		add_filter( 'woocommerce_package_rates', array( self::class, 'hide_paid_rates' ), PHP_INT_MAX );
	}

	/**
	 * Whether the running WooCommerce predates the core fix.
	 *
	 * @return bool
	 */
	public static function is_needed(): bool {
		return defined( 'WC_VERSION' ) && version_compare( (string) WC_VERSION, self::FIXED_IN, '<' );
	}

	/**
	 * Remind admins to delete this class once core carries the fix.
	 *
	 * Developer-facing, so deliberately untranslated. Limited to the
	 * dashboard, Plugins and WooCommerce settings screens.
	 *
	 * @return void
	 */
	public static function render_removal_notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! current_user_can( 'manage_options' ) || null === $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'woocommerce_page_wc-settings' ), true ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>Aggressive Apparel:</strong> WooCommerce %1$s fixes "Hide shipping rates when free shipping is available", so the theme\'s stand-in is now inactive. Delete <code>%2$s</code>, its <code>Free_Shipping_Rate_Hiding::init()</code> call in <code>%3$s</code>, and <code>%4$s</code>.</p></div>',
			esc_html( (string) WC_VERSION ),
			'includes/WooCommerce/class-free-shipping-rate-hiding.php',
			'includes/class-bootstrap.php',
			'tests/Unit/WooCommerce/TestFreeShippingRateHiding.php'
		);
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
