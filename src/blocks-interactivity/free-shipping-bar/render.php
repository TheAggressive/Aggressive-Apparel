<?php
/**
 * Free Shipping Bar Block — Server Render.
 *
 * Renders the progress bar with Interactivity API context so view.js
 * can update it live when items are added or removed from the cart.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive_Apparel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_price' ) ) {
	return;
}

$custom_threshold = isset( $attributes['customThreshold'] ) ? (float) $attributes['customThreshold'] : 0.0;
$threshold        = $custom_threshold > 0 ? $custom_threshold : aggressive_apparel_free_shipping_threshold();

if ( $threshold <= 0 ) {
	return;
}

$cart_total = ( function_exists( 'WC' ) && \WC()->cart ) ? (float) \WC()->cart->get_displayed_subtotal() : 0.0;
$remaining  = max( 0.0, $threshold - $cart_total );
$percent    = min( 100.0, ( $cart_total / $threshold ) * 100 );
$complete   = $remaining <= 0;

$context = (string) wp_json_encode(
	array(
		'threshold' => $threshold,
		'cartTotal' => $cart_total,
		'percent'   => $percent,
		'remaining' => $remaining,
		'complete'  => $complete,
		'restBase'  => esc_url_raw( rest_url( 'wc/store/v1' ) ),
	)
);

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'aggressive-apparel-shipping-bar' . ( $complete ? ' aggressive-apparel-shipping-bar--complete' : '' ),
		'data-wp-interactive' => 'aggressive-apparel/free-shipping-bar',
		'data-wp-context'     => $context,
		'data-wp-init'        => 'callbacks.init',
		'data-wp-class--aggressive-apparel-shipping-bar--complete' => 'state.isComplete',
	)
);
?>
<div 
<?php
echo wp_kses(
	$wrapper_attrs,
	array(
		'class'               => array(),
		'id'                  => array(),
		'style'               => array(),
		'data-wp-interactive' => array(),
		'data-wp-context'     => array(),
		'data-wp-init'        => array(),
		'data-wp-class--aggressive-apparel-shipping-bar--complete' => array(),
	)
);
?>
>
	<div
		class="aggressive-apparel-shipping-bar__track"
		role="progressbar"
		aria-valuenow="<?php echo esc_attr( (string) round( $percent, 1 ) ); ?>"
		aria-valuemin="0"
		aria-valuemax="100"
		aria-label="<?php esc_attr_e( 'Free shipping progress', 'aggressive-apparel' ); ?>"
	>
		<div
			class="aggressive-apparel-shipping-bar__progress"
			style="width:<?php echo esc_attr( round( $percent, 1 ) . '%' ); ?>"
			data-wp-style--width="state.progressWidth"
		></div>
	</div>
	<p class="aggressive-apparel-shipping-bar__message" data-wp-text="state.message">
		<?php
		if ( $complete ) {
			echo esc_html__( "You've unlocked free shipping!", 'aggressive-apparel' );
		} else {
			printf(
				/* translators: %s: formatted remaining amount for free shipping. */
				esc_html__( "You're %s away from free shipping!", 'aggressive-apparel' ),
				wp_kses_post( wc_price( $remaining ) ),
			);
		}
		?>
	</p>
</div>
<?php

/**
 * Auto-detect the lowest free-shipping threshold from WooCommerce shipping zones.
 *
 * @return float Threshold in store currency, or 0 when none configured.
 */
function aggressive_apparel_free_shipping_threshold(): float {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}

	$threshold = (float) apply_filters( 'aggressive_apparel_free_shipping_threshold', 0.0 );
	if ( $threshold > 0 ) {
		$cached = $threshold;
		return $cached;
	}

	if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
		$cached = 0.0;
		return $cached;
	}

	$zones   = \WC_Shipping_Zones::get_zones();
	$zones[] = array( 'zone_id' => 0 );
	$min     = 0.0;

	foreach ( $zones as $zone_data ) {
		$zone    = new \WC_Shipping_Zone( $zone_data['zone_id'] );
		$methods = $zone->get_shipping_methods( true );

		foreach ( $methods as $method ) {
			if ( 'free_shipping' === $method->id ) {
				$requires = $method->get_option( 'requires', '' );
				if ( in_array( $requires, array( 'min_amount', 'either', 'both' ), true ) ) {
					$amount = (float) $method->get_option( 'min_amount', 0 );
					if ( $amount > 0 && ( 0.0 === $min || $amount < $min ) ) {
						$min = $amount;
					}
				}
			}
		}
	}

	$cached = $min;
	return $cached;
}
