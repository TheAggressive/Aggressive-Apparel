<?php
/**
 * Free Shipping Message Block — Server Render.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

use Aggressive_Apparel\Blocks\Icon_Block;
use Aggressive_Apparel\WooCommerce\Free_Shipping;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) ) {
	return;
}

$custom_threshold = isset( $attributes['customThreshold'] ) ? (float) $attributes['customThreshold'] : 0.0;
$progress         = Free_Shipping::get_cart_progress( $custom_threshold );

if ( null === $progress ) {
	return;
}

$emphasis_text = sanitize_text_field( $attributes['emphasisText'] ?? 'FREE Shipping' );
$prefix_icon   = sanitize_key( $attributes['prefixIcon'] ?? '' );
$suffix_icon   = sanitize_key( $attributes['suffixIcon'] ?? '' );
$icon_size     = Icon_Block::sanitize_size( $attributes['iconSize'] ?? 24 );
$remaining     = $progress['remaining'];
$complete      = $progress['complete'];
$message       = Free_Shipping::format_message( $remaining, $emphasis_text, $complete );

$currency_code   = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
$currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $currency_code ) : '$';

$context = (string) wp_json_encode(
	array(
		'threshold'         => $progress['threshold'],
		'cartTotal'         => $progress['cart_total'],
		'remaining'         => $remaining,
		'complete'          => $complete,
		'restBase'          => esc_url_raw( rest_url( 'wc/store/v1' ) ),
		'currencyPrefix'    => $currency_symbol,
		'currencySuffix'    => '',
		'currencyMinorUnit' => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
		'emphasisText'      => $emphasis_text,
		'i18n'              => Free_Shipping::get_message_i18n(),
	)
);

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'               => 'aggressive-apparel-free-shipping-message',
		'data-wp-interactive' => 'aggressive-apparel/free-shipping-message',
		'data-wp-context'     => $context,
		'data-wp-init'        => 'callbacks.init',
	)
);

$prefix_markup = '' !== $prefix_icon ? Icon_Block::render_svg( $prefix_icon, $icon_size ) : '';
$suffix_markup = '' !== $suffix_icon ? Icon_Block::render_svg( $suffix_icon, $icon_size ) : '';
$icon_style    = sprintf( 'width:%1$dpx;height:%1$dpx;', $icon_size );
?>
<span <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes(). ?>>
	<?php if ( '' !== $prefix_markup ) : ?>
	<span class="aggressive-apparel-free-shipping-message__icon aggressive-apparel-free-shipping-message__icon--prefix aggressive-apparel-icon__svg-wrap" style="<?php echo esc_attr( $icon_style ); ?>" aria-hidden="true">
		<?php echo $prefix_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted theme SVG. ?>
	</span>
	<?php endif; ?>

	<span class="aggressive-apparel-free-shipping-message__text" aria-live="polite" aria-atomic="true" data-wp-text="state.message">
		<?php echo esc_html( $message ); ?>
	</span>

	<?php if ( '' !== $suffix_markup ) : ?>
	<span class="aggressive-apparel-free-shipping-message__icon aggressive-apparel-free-shipping-message__icon--suffix aggressive-apparel-icon__svg-wrap" style="<?php echo esc_attr( $icon_style ); ?>" aria-hidden="true">
		<?php echo $suffix_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted theme SVG. ?>
	</span>
	<?php endif; ?>
</span>
