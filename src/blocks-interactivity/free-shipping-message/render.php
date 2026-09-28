<?php
/**
 * Free Shipping Message Block — Server Render.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

use Aggressive_Apparel\Blocks\Icon_Block;
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

$prefix_icon = sanitize_key( $attributes['prefixIcon'] ?? '' );
$suffix_icon = sanitize_key( $attributes['suffixIcon'] ?? '' );
$icon_size   = Icon_Block::sanitize_size( $attributes['iconSize'] ?? 24 );

Free_Shipping_Message::register_segments_state( 'aggressive-apparel/free-shipping-message' );

$context = (string) wp_json_encode(
	array_merge(
		array(
			'threshold'       => $progress['threshold'],
			'customThreshold' => $custom_threshold,
			'cartTotal'       => $progress['cart_total'],
			'remaining'       => $progress['remaining'],
			'complete'        => $progress['complete'],
			'restBase'        => esc_url_raw( rest_url( 'wc/store/v1' ) ),
		),
		Free_Shipping_Message::block_context( $attributes ),
		Free_Shipping::get_currency_context()
	)
);

$prefix_markup = Icon_Block::render_wrapped_svg(
	$prefix_icon,
	$icon_size,
	array(
		'class' => 'aggressive-apparel-free-shipping-message__icon aggressive-apparel-free-shipping-message__icon--prefix',
	)
);
$suffix_markup = Icon_Block::render_wrapped_svg(
	$suffix_icon,
	$icon_size,
	array(
		'class' => 'aggressive-apparel-free-shipping-message__icon aggressive-apparel-free-shipping-message__icon--suffix',
	)
);
?>
<span
	<?php
	echo get_block_wrapper_attributes(
		array(
			'class'                => 'aggressive-apparel-free-shipping-message',
			'data-wp-interactive'  => 'aggressive-apparel/free-shipping-message',
			'data-wp-context'      => $context,
			'data-wp-init'         => 'callbacks.init',
			'data-wp-bind--hidden' => '!context.threshold',
			'hidden'               => $progress['threshold'] > 0 ? null : 'hidden',
		)
	);
	?>
>
	<?php if ( '' !== $prefix_markup ) : ?>
		<?php echo aggressive_apparel_trusted_html( $prefix_markup ); ?>
	<?php endif; ?>

	<span class="aggressive-apparel-free-shipping-message__text" aria-live="polite" aria-atomic="true"><template data-wp-each--segment="state.segments" data-wp-each-key="context.segment.key"><span data-wp-class--aggressive-apparel-free-shipping-message__amount="context.segment.amount" data-wp-class--aggressive-apparel-free-shipping-message__emphasis="context.segment.emphasis" data-wp-text="context.segment.text"></span></template></span>

	<?php if ( '' !== $suffix_markup ) : ?>
		<?php echo aggressive_apparel_trusted_html( $suffix_markup ); ?>
	<?php endif; ?>
</span>
