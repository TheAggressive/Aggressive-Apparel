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

use Aggressive_Apparel\Core\Icons;

defined( 'ABSPATH' ) || exit;

$item_width = ! empty( $attributes['itemWidth'] ) ? (string) $attributes['itemWidth'] : '60vw';
if ( ! preg_match( '/^\d+(?:\.\d+)?(?:px|vw|%)$/', $item_width ) ) {
	$item_width = '60vw';
}

$speed = ! empty( $attributes['speed'] ) ? (float) $attributes['speed'] : 1.0;
$speed = min( 3.0, max( 0.5, $speed ) );

$show_progress = ! empty( $attributes['showProgress'] );

$snap_to_next = ! empty( $attributes['snapToNext'] );

$swipe_hint_style = $attributes['swipeHintStyle'] ?? 'cue';
if ( ! in_array( $swipe_hint_style, array( 'off', 'cue', 'label', 'badge' ), true ) ) {
	$swipe_hint_style = 'cue';
}

// Where the section pins (and horizontal scroll begins) relative to the
// viewport on desktop: top | center | bottom. Drives --aa-hscroll-sticky-top
// and --aa-hscroll-pin-height via the modifier class in style.css.
$activation = $attributes['activation'] ?? 'top';
if ( ! in_array( $activation, array( 'top', 'center', 'bottom' ), true ) ) {
	$activation = 'top';
}

// Desktop behaviour: 'pinned' scroll-jacks vertical scroll into horizontal
// movement; 'inline' uses the native swipe/snap carousel (no pinning) on
// desktop too. Touch always uses native snap regardless.
$desktop_behavior = $attributes['desktopBehavior'] ?? 'pinned';
if ( ! in_array( $desktop_behavior, array( 'pinned', 'inline' ), true ) ) {
	$desktop_behavior = 'pinned';
}

$classes = 'aa-hscroll aa-hscroll--' . $activation;
if ( 'inline' === $desktop_behavior ) {
	$classes .= ' aa-hscroll--inline';
}
if ( $snap_to_next ) {
	$classes .= ' aa-hscroll--snap-next';
}

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'                => $classes,
		'aria-roledescription' => 'carousel',
		'aria-label'           => __( 'Scrolling gallery', 'aggressive-apparel' ),
		'data-wp-interactive'  => 'aggressive-apparel/horizontal-scroll',
		'data-wp-context'      => wp_json_encode(
			array(
				'itemWidth'       => $item_width,
				'speed'           => $speed,
				'progress'        => 0,
				'desktopBehavior' => $desktop_behavior,
				'snapToNext'      => $snap_to_next,
				'swipeHintStyle'  => $swipe_hint_style,
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
		<?php if ( 'off' !== $swipe_hint_style ) : ?>
		<div
			class="aa-hscroll__swipe-hint aa-hscroll__swipe-hint--<?php echo esc_attr( $swipe_hint_style ); ?>"
			aria-hidden="true"
			hidden
		>
			<?php if ( 'label' === $swipe_hint_style ) : ?>
			<span class="aa-hscroll__swipe-hint-label">
				<?php esc_html_e( 'Swipe', 'aggressive-apparel' ); ?>
			</span>
			<?php endif; ?>
			<span class="aa-hscroll__swipe-hint-icon">
				<?php
				$chevron_markup  = Icons::get(
					'chevron-right',
					array(
						'width'  => 36,
						'height' => 36,
					)
				);
				$chevron_classes = array(
					'aa-hscroll__swipe-hint-chevron',
					'aa-hscroll__swipe-hint-chevron aa-hscroll__swipe-hint-chevron--trail',
				);
				foreach ( $chevron_classes as $chevron_class ) :
					?>
				<span class="<?php echo esc_attr( $chevron_class ); ?>">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns trusted SVG.
					echo $chevron_markup;
					?>
				</span>
					<?php
				endforeach;
				?>
			</span>
		</div>
		<?php endif; ?>
	</div>
	<div
		class="aa-hscroll__live-region"
		aria-live="polite"
		aria-atomic="true"
	></div>
</section>
