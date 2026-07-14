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

$speed = ! empty( $attributes['speed'] ) ? (float) $attributes['speed'] : 1.5;
$speed = min( 3.0, max( 0.5, $speed ) );

$show_progress = ! empty( $attributes['showProgress'] );

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

// Desktop behaviour: 'pinned' maps native vertical progress to horizontal
// movement; 'inline' uses the native swipe/snap carousel (no pinning).
// Touch always uses native snap regardless.
$desktop_behavior = $attributes['desktopBehavior'] ?? 'pinned';
if ( ! in_array( $desktop_behavior, array( 'pinned', 'inline' ), true ) ) {
	$desktop_behavior = 'pinned';
}

// Scroll behavior: 'paged' = one deliberate gesture advances one slide (the
// input-locked "step" glide); anything else = continuous "scrub". Legacy
// 'proximity' values fall through to scrub.
$snap_behavior = $attributes['snapBehavior'] ?? 'off';
if ( ! in_array( $snap_behavior, array( 'off', 'proximity', 'paged' ), true ) ) {
	$snap_behavior = 'off';
}

// Accessible name for the carousel region. Falls back to a generic label;
// authors should set a unique one when a page has more than one gallery.
$aria_label = ! empty( $attributes['ariaLabel'] )
	? (string) $attributes['ariaLabel']
	: __( 'Scrolling gallery', 'aggressive-apparel' );

$classes = 'aa-hscroll aa-hscroll--' . $activation;
if ( 'inline' === $desktop_behavior ) {
	$classes .= ' aa-hscroll--inline';
}
?>
<section
	<?php
	echo get_block_wrapper_attributes(
		array(
			'class'                => $classes,
			'aria-roledescription' => 'carousel',
			'aria-label'           => $aria_label,
			'data-wp-interactive'  => 'aggressive-apparel/horizontal-scroll',
			'data-wp-context'      => wp_json_encode(
				array(
					'speed'           => $speed,
					'progress'        => 0,
					'desktopBehavior' => $desktop_behavior,
					'snapBehavior'    => $snap_behavior,
					'swipeHintStyle'  => $swipe_hint_style,
					'i18n'            => array(
						/* translators: 1: current slide number, 2: total slide count. Announced by screen readers. */
						'slideAnnouncement' => __( 'Slide %1$s of %2$s', 'aggressive-apparel' ),
						/* translators: 1: current slide number, 2: total slide count. Per-slide aria-label. */
						'slideLabel'        => __( '%1$s of %2$s', 'aggressive-apparel' ),
					),
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
>
	<div class="aa-hscroll__range">
		<div class="aa-hscroll__viewport" data-aa-hscroll>
			<div class="aa-hscroll__track">
				<?php echo aggressive_apparel_trusted_html( $content ); ?>
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
					$chevron_classes = array(
						'aa-hscroll__swipe-hint-chevron',
						'aa-hscroll__swipe-hint-chevron aa-hscroll__swipe-hint-chevron--trail',
					);
					foreach ( $chevron_classes as $chevron_class ) :
						?>
					<span class="<?php echo esc_attr( $chevron_class ); ?>">
						<?php
						aggressive_apparel_render_icon(
							'chevron-right',
							array(
								'width'  => 36,
								'height' => 36,
							)
						);
						?>
					</span>
						<?php
					endforeach;
					?>
				</span>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<div
		class="aa-hscroll__live-region"
		aria-live="polite"
		aria-atomic="true"
	></div>
</section>
