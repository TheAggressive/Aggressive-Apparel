<?php
/**
 * Horizontal Scroll Block — Server Render.
 *
 * Outputs a scroll-sentinel wrapper (tall, claims vertical space) and an
 * inner sticky viewport with a horizontally scrollable track. On desktop
 * the track is driven by JS; on touch devices CSS scroll-snap takes over.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    InnerBlocks HTML.
 * @var WP_Block $block      Block instance.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$item_width = ! empty( $attributes['itemWidth'] ) ? (string) $attributes['itemWidth'] : '60vw';
if ( ! preg_match( '/^\d+(?:\.\d+)?(?:px|vw|%)$/', $item_width ) ) {
	$item_width = '60vw';
}

$speed = ! empty( $attributes['speed'] ) ? (float) $attributes['speed'] : 1.0;
$speed = min( 3.0, max( 0.5, $speed ) );

$show_progress = ! empty( $attributes['showProgress'] );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                => 'aa-hscroll',
		'aria-roledescription' => 'carousel',
		'aria-label'           => __( 'Scrolling gallery', 'aggressive-apparel' ),
		'data-wp-interactive'  => 'aggressive-apparel/horizontal-scroll',
		'data-wp-context'      => wp_json_encode(
			array(
				'itemWidth' => $item_width,
				'speed'     => $speed,
				'progress'  => 0,
			)
		),
		'data-wp-init'         => 'callbacks.init',
		'style'                => sprintf(
			'--aa-hscroll-item-width: %s; --aa-hscroll-speed: %s;',
			esc_attr( $item_width ),
			esc_attr( (string) $speed )
		),
	)
);
?>
<section <?php echo wp_kses_post( $wrapper_attrs ); ?>>
	<div class="aa-hscroll__viewport" data-aa-hscroll>
		<div class="aa-hscroll__track">
			<?php echo wp_kses_post( $content ); ?>
		</div>
		<?php if ( $show_progress ) : ?>
		<div
			class="aa-hscroll__progress"
			role="progressbar"
			aria-label="<?php esc_attr_e( 'Scroll progress', 'aggressive-apparel' ); ?>"
			aria-valuemin="0"
			aria-valuemax="100"
			data-wp-bind--aria-valuenow="context.progress"
		>
			<div
				class="aa-hscroll__progress-bar"
				data-wp-bind--style="callbacks.progressStyle"
			></div>
		</div>
		<?php endif; ?>
	</div>
</section>
