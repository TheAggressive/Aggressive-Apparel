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

declare(strict_types=1);

use Aggressive_Apparel\WooCommerce\Free_Shipping;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_price' ) ) {
	return;
}

$custom_threshold = isset( $attributes['customThreshold'] ) ? (float) $attributes['customThreshold'] : 0.0;
$progress         = Free_Shipping::get_cart_progress( $custom_threshold );

if ( null === $progress ) {
	return;
}

$threshold  = $progress['threshold'];
$cart_total = $progress['cart_total'];
$remaining  = $progress['remaining'];
$percent    = $progress['percent'];
$complete   = $progress['complete'];
$message    = Free_Shipping::format_bar_message( $remaining, $complete );

$currency_code   = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $currency_code ) : '$';

$context = (string) wp_json_encode(
	array(
		'threshold'         => $threshold,
		'cartTotal'         => $cart_total,
		'percent'           => $percent,
		'remaining'         => $remaining,
		'complete'          => $complete,
		'restBase'          => esc_url_raw( rest_url( 'wc/store/v1' ) ),
		'currencyPrefix'    => $currency_symbol,
		'currencySuffix'    => '',
		'currencyMinorUnit' => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
		'i18n'              => Free_Shipping::get_bar_message_i18n(),
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
		<?php echo esc_html( $message ); ?>
	</p>
</div>
