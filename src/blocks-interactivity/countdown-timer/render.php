<?php
/**
 * Sale Countdown Timer Block — Server Render.
 *
 * Renders a live countdown for a configured end date or product sale end date.
 * The Interactivity API `startTicker` callback ticks every second client-side.
 *
 * Nothing is output when:
 *   - No manual end date is configured and no product sale end date is available.
 *   - The resolved end date is in the past or invalid.
 *
 * @see https://github.com/WordPress/gutenberg/blob/trunk/docs/reference-guides/block-api/block-metadata.md#render
 *
 * @package Aggressive_Apparel
 */

defined( 'ABSPATH' ) || exit;

$end_ts          = 0;
$manual_end_date = ! empty( $attributes['endDateTime'] ) ? trim( (string) $attributes['endDateTime'] ) : '';

if ( '' !== $manual_end_date ) {
	try {
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$end_ts   = ( new DateTimeImmutable( $manual_end_date, $timezone ) )->getTimestamp();
	} catch ( Exception $e ) {
		$end_ts = 0;
	}
}

if ( $end_ts <= 0 && function_exists( 'wc_get_product' ) ) {
	$product_id = get_the_ID();
	if ( isset( $block ) && ! empty( $block->context['postId'] ) ) {
		$product_id = (int) $block->context['postId'];
	}

	$product = wc_get_product( $product_id );
	if ( $product && $product->is_on_sale() ) {
		$end_date = $product->get_date_on_sale_to();
		if ( $end_date ) {
			$end_ts = $end_date->getTimestamp();
		}
	}
}

if ( $end_ts <= 0 ) {
	return;
}

$diff = $end_ts - time();

if ( $diff <= 0 ) {
	return;
}

$days    = (int) floor( $diff / DAY_IN_SECONDS );
$hours   = (int) floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );
$minutes = (int) floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );
$seconds = $diff % MINUTE_IN_SECONDS;

$drop_page_url        = ! empty( $attributes['dropPageUrl'] ) ? esc_url( $attributes['dropPageUrl'] ) : '';
$display_style        = ! empty( $attributes['displayStyle'] ) ? sanitize_key( (string) $attributes['displayStyle'] ) : 'inline';
$valid_display_styles = array();

if (
	isset( $block ) &&
	isset( $block->block_type->attributes['displayStyle']['enum'] ) &&
	is_array( $block->block_type->attributes['displayStyle']['enum'] )
) {
	$valid_display_styles = array_map(
		'sanitize_key',
		$block->block_type->attributes['displayStyle']['enum']
	);
}

if ( empty( $valid_display_styles ) ) {
	$registered_block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'aggressive-apparel/countdown-timer' );

	if (
		$registered_block_type &&
		isset( $registered_block_type->attributes['displayStyle']['enum'] ) &&
		is_array( $registered_block_type->attributes['displayStyle']['enum'] )
	) {
		$valid_display_styles = array_map(
			'sanitize_key',
			$registered_block_type->attributes['displayStyle']['enum']
		);
	}
}

if ( empty( $valid_display_styles ) ) {
	$block_metadata_path = __DIR__ . '/block.json';

	if ( is_readable( $block_metadata_path ) ) {
		$block_metadata = wp_json_file_decode(
			$block_metadata_path,
			array( 'associative' => true )
		);

		if (
			is_array( $block_metadata ) &&
			isset( $block_metadata['attributes']['displayStyle']['enum'] ) &&
			is_array( $block_metadata['attributes']['displayStyle']['enum'] )
		) {
			$valid_display_styles = array_map(
				'sanitize_key',
				$block_metadata['attributes']['displayStyle']['enum']
			);
		}
	}
}

if ( empty( $valid_display_styles ) ) {
	$valid_display_styles = array(
		'inline',
		'compact',
		'minimal',
		'card',
		'chips',
		'banner',
		'strip',
		'pill',
		'boxed',
		'outline',
		'editorial',
		'magazine',
		'hero',
		'hero-center',
		'hero-split',
		'hero-panel',
	);
}

$color_style_variables = array(
	'saleLabelColor'   => '--aa-countdown-label-color',
	'timeValueColor'   => '--aa-countdown-value-color',
	'unitLabelColor'   => '--aa-countdown-unit-color',
	'timerBorderColor' => '--aa-countdown-border-color',
);
$color_style           = '';

foreach ( $color_style_variables as $attribute_name => $css_variable ) {
	$color_value = ! empty( $attributes[ $attribute_name ] ) ? trim( (string) $attributes[ $attribute_name ] ) : '';

	if ( '' === $color_value ) {
		continue;
	}

	if ( preg_match( '/^var:preset\|color\|([a-z0-9_-]+)$/i', $color_value, $matches ) ) {
		$color_value = 'var(--wp--preset--color--' . sanitize_key( $matches[1] ) . ')';
	} elseif ( preg_match( '/^[a-z0-9_-]+$/i', $color_value ) ) {
		$color_value = 'var(--wp--preset--color--' . sanitize_key( $color_value ) . ')';
	}

	$color_style .= $css_variable . ':' . $color_value . ';';
}

$color_style = safecss_filter_attr( $color_style );

if (
	! in_array(
		$display_style,
		$valid_display_styles,
		true
	)
) {
	$display_style = 'inline';
}

$context            = (string) wp_json_encode(
	array(
		'endTs'       => $end_ts,
		'days'        => $days,
		'hours'       => $hours,
		'minutes'     => $minutes,
		'seconds'     => $seconds,
		'dropPageUrl' => $drop_page_url,
	)
);
$wrapper_attributes = array(
	'class'               => 'aggressive-apparel-countdown aggressive-apparel-countdown--' . $display_style,
	'data-wp-interactive' => 'aggressive-apparel/countdown-timer',
	'data-wp-context'     => $context,
	'data-wp-init'        => 'callbacks.startTicker',
);

if ( '' !== $color_style ) {
	$wrapper_attributes['style'] = $color_style;
}
?>
<div
	<?php
	echo get_block_wrapper_attributes( $wrapper_attributes );
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
