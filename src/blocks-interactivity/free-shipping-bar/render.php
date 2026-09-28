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
use Aggressive_Apparel\WooCommerce\Free_Shipping_Message;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'WC' ) ) {
	return;
}

$custom_threshold = isset( $attributes['customThreshold'] ) ? (float) $attributes['customThreshold'] : 0.0;
$progress         = Free_Shipping::get_cart_progress( $custom_threshold );

if ( null === $progress ) {
	return;
}

$threshold = $progress['threshold'];
$percent   = $progress['percent'];
$complete  = $progress['complete'];

Free_Shipping_Message::register_segments_state( 'aggressive-apparel/free-shipping-bar' );

$context = (string) wp_json_encode(
	array_merge(
		array(
			'threshold'       => $threshold,
			'customThreshold' => $custom_threshold,
			'cartTotal'       => $progress['cart_total'],
			'percent'         => $percent,
			'remaining'       => $progress['remaining'],
			'complete'        => $complete,
			'restBase'        => esc_url_raw( rest_url( 'wc/store/v1' ) ),
		),
		Free_Shipping_Message::block_context( $attributes ),
		Free_Shipping::get_currency_context()
	)
);

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                => 'aggressive-apparel-shipping-bar' . ( $complete ? ' aggressive-apparel-shipping-bar--complete' : '' ),
		'data-wp-interactive'  => 'aggressive-apparel/free-shipping-bar',
		'data-wp-context'      => $context,
		'data-wp-init'         => 'callbacks.init',
		'data-wp-class--aggressive-apparel-shipping-bar--complete' => 'state.isComplete',
		'data-wp-bind--hidden' => '!context.threshold',
		'hidden'               => $threshold > 0 ? null : 'hidden',
	)
);
?>
<div 
<?php
echo wp_kses(
	$wrapper_attrs,
	array(
		'class'                => array(),
		'id'                   => array(),
		'style'                => array(),
		'data-wp-interactive'  => array(),
		'data-wp-context'      => array(),
		'data-wp-init'         => array(),
		'data-wp-class--aggressive-apparel-shipping-bar--complete' => array(),
		'data-wp-bind--hidden' => array(),
		'hidden'               => array(),
	)
);
?>
>
	<div
		class="aggressive-apparel-shipping-bar__track"
		role="progressbar"
		aria-valuenow="<?php echo esc_attr( (string) round( $percent, 1 ) ); ?>"
		data-wp-bind--aria-valuenow="state.progressValue"
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
	<p class="aggressive-apparel-shipping-bar__message" aria-live="polite" aria-atomic="true"><template data-wp-each--segment="state.segments" data-wp-each-key="context.segment.key"><span data-wp-class--aggressive-apparel-shipping-bar__amount="context.segment.amount" data-wp-class--aggressive-apparel-shipping-bar__emphasis="context.segment.emphasis" data-wp-text="context.segment.text"></span></template></p>
</div>
