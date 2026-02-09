<?php
/**
 * Free Shipping Bar Class
 *
 * Displays a progress bar toward the free-shipping threshold in the cart
 * and updates it via WooCommerce AJAX cart fragments.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\WooCommerce;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Free Shipping Bar
 *
 * @since 1.17.0
 */
class Free_Shipping_Bar {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_fragment' ) );
		add_action( 'woocommerce_before_cart_totals', array( $this, 'render' ) );
		add_action( 'woocommerce_widget_shopping_cart_before_buttons', array( $this, 'render' ) );
	}

	/**
	 * Enqueue styles on cart/checkout and pages with mini-cart.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		$css_file = AGGRESSIVE_APPAREL_DIR . '/build/styles/woocommerce/free-shipping-bar.css';
		if ( ! file_exists( $css_file ) ) {
			return;
		}

		wp_enqueue_style(
			'aggressive-apparel-free-shipping-bar',
			AGGRESSIVE_APPAREL_URI . '/build/styles/woocommerce/free-shipping-bar.css',
			array(),
			(string) filemtime( $css_file ),
		);
	}

	/**
	 * Add the progress bar as a cart fragment for AJAX updates.
	 *
	 * @param array $fragments Existing fragments.
	 * @return array Modified fragments.
	 */
	public function cart_fragment( array $fragments ): array {
		ob_start();
		try {
			$this->render();
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Intentionally silent; buffer cleaned by ob_get_clean.
			unset( $e );
		}
		$html = (string) ob_get_clean();

		$fragments['.aggressive-apparel-shipping-bar'] = $html;

		return $fragments;
	}

	/**
	 * Render the progress bar markup.
	 *
	 * @return void
	 */
	public function render(): void {
		$threshold = $this->get_threshold();

		if ( $threshold <= 0 ) {
			// No free shipping configured — output an empty shell for fragments.
			echo '<div class="aggressive-apparel-shipping-bar"></div>';
			return;
		}

		$cart_total = $this->get_cart_total();
		$remaining  = max( 0, $threshold - $cart_total );
		$percent    = min( 100, ( $cart_total / $threshold ) * 100 );
		$complete   = $remaining <= 0;

		$modifier = $complete ? ' aggressive-apparel-shipping-bar--complete' : '';

		echo '<div class="aggressive-apparel-shipping-bar' . esc_attr( $modifier ) . '">';
		echo '<div class="aggressive-apparel-shipping-bar__track" role="progressbar" aria-valuenow="' . esc_attr( (string) round( $percent, 1 ) ) . '" aria-valuemin="0" aria-valuemax="100" aria-label="' . esc_attr__( 'Free shipping progress', 'aggressive-apparel' ) . '">';
		echo '<div class="aggressive-apparel-shipping-bar__progress" style="width:' . esc_attr( round( $percent, 1 ) . '%' ) . '"></div>';
		echo '</div>';
		echo '<p class="aggressive-apparel-shipping-bar__message">';

		if ( $complete ) {
			echo esc_html__( "You've unlocked free shipping!", 'aggressive-apparel' );
		} else {
			printf(
				/* translators: %s: formatted remaining amount for free shipping. */
				esc_html__( "You're %s away from free shipping!", 'aggressive-apparel' ),
				wp_kses_post( wc_price( $remaining ) ),
			);
		}

		echo '</p>';
		echo '</div>';
	}

	/**
	 * Find the lowest free-shipping minimum amount across all shipping zones.
	 *
	 * @return float Threshold amount or 0 if none configured.
	 */
	private function get_threshold(): float {
		$threshold = (float) apply_filters( 'aggressive_apparel_free_shipping_threshold', 0.0 );
		if ( $threshold > 0 ) {
			return $threshold;
		}

		// Auto-detect from WooCommerce shipping methods.
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return 0.0;
		}

		$zones = \WC_Shipping_Zones::get_zones();
		// Include the "rest of world" zone.
		$zones[] = array( 'zone_id' => 0 );

		$min_amount = 0.0;

		foreach ( $zones as $zone_data ) {
			$zone    = new \WC_Shipping_Zone( $zone_data['zone_id'] );
			$methods = $zone->get_shipping_methods( true );

			foreach ( $methods as $method ) {
				if ( 'free_shipping' === $method->id ) {
					$requires = $method->get_option( 'requires', '' );
					if ( in_array( $requires, array( 'min_amount', 'either', 'both' ), true ) ) {
						$amount = (float) $method->get_option( 'min_amount', 0 );
						if ( $amount > 0 && ( 0.0 === $min_amount || $amount < $min_amount ) ) {
							$min_amount = $amount;
						}
					}
				}
			}
		}

		return $min_amount;
	}

	/**
	 * Get the current cart total (excluding taxes and shipping).
	 *
	 * @return float
	 */
	private function get_cart_total(): float {
		if ( ! function_exists( 'WC' ) || ! \WC()->cart ) {
			return 0.0;
		}

		return (float) \WC()->cart->get_displayed_subtotal();
	}
}
