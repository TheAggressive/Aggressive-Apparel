<?php
/**
 * Sale Countdown Timer Block — Server Render.
 *
 * Renders a live countdown for a product whose sale ends at a scheduled date.
 * The Interactivity API `startTicker` callback ticks every second client-side.
 *
 * Nothing is output when:
 *   - No product is in context.
 *   - The product is not on sale.
 *   - The sale end date is in the past or unset.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive_Apparel
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wc_get_product' ) ) {
	return;
}

$product = wc_get_product( get_the_ID() );
if ( ! $product || ! $product->is_on_sale() ) {
	return;
}

$end_date = $product->get_date_on_sale_to();
if ( ! $end_date ) {
	return;
}

$end_ts = $end_date->getTimestamp();
$diff   = $end_ts - time();

if ( $diff <= 0 ) {
	return;
}

$days    = (int) floor( $diff / DAY_IN_SECONDS );
$hours   = (int) floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
$minutes = (int) floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
$seconds = $diff % MINUTE_IN_SECONDS;

$drop_page_url = ! empty( $attributes['dropPageUrl'] ) ? esc_url( $attributes['dropPageUrl'] ) : '';

$context = (string) wp_json_encode(
	array(
		'endTs'       => $end_ts,
		'days'        => $days,
		'hours'       => $hours,
		'minutes'     => $minutes,
		'seconds'     => $seconds,
		'dropPageUrl' => $drop_page_url,
	)
);
?>
<div
	<?php
	echo get_block_wrapper_attributes(
		array(
			'class'               => 'aggressive-apparel-countdown',
			'data-wp-interactive' => 'aggressive-apparel/countdown-timer',
			'data-wp-context'     => $context,
			'data-wp-init'        => 'callbacks.startTicker',
		)
	);
	?>
>
	<span class="aggressive-apparel-countdown__label">
		<?php esc_html_e( 'Sale ends in', 'aggressive-apparel' ); ?>
	</span>
	<span class="aggressive-apparel-countdown__segment">
		<span class="aggressive-apparel-countdown__value" data-wp-text="context.days"><?php echo esc_html( (string) $days ); ?></span>
		<span class="aggressive-apparel-countdown__unit"><?php esc_html_e( 'd', 'aggressive-apparel' ); ?></span>
	</span>
	<span class="aggressive-apparel-countdown__segment">
		<span class="aggressive-apparel-countdown__value" data-wp-text="context.hours"><?php echo esc_html( (string) $hours ); ?></span>
		<span class="aggressive-apparel-countdown__unit"><?php esc_html_e( 'h', 'aggressive-apparel' ); ?></span>
	</span>
	<span class="aggressive-apparel-countdown__segment">
		<span class="aggressive-apparel-countdown__value" data-wp-text="context.minutes"><?php echo esc_html( (string) $minutes ); ?></span>
		<span class="aggressive-apparel-countdown__unit"><?php esc_html_e( 'm', 'aggressive-apparel' ); ?></span>
	</span>
	<span class="aggressive-apparel-countdown__segment">
		<span class="aggressive-apparel-countdown__value" data-wp-text="context.seconds"><?php echo esc_html( (string) $seconds ); ?></span>
		<span class="aggressive-apparel-countdown__unit"><?php esc_html_e( 's', 'aggressive-apparel' ); ?></span>
	</span>
</div>
