<?php
/**
 * Hero Carousel Block — Server Render.
 *
 * Slides are core/cover inner blocks rendered individually so each can be
 * wrapped in a slide shell carrying its own Interactivity context (index),
 * ARIA slide semantics, and a deterministic Ken Burns variant class.
 *
 * Progressive enhancement: slide 1 renders fully visible with zero JS
 * (static hero fallback); its image is forced eager + fetchpriority="high"
 * for LCP while all later slides lazy-load.
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    InnerBlocks HTML (unused — slides render individually).
 * @var WP_Block $block      Block instance.
 *
 * @package Aggressive_Apparel
 */

declare(strict_types=1);

use Aggressive_Apparel\Core\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * Constrain an attribute to an allowed set, falling back to a default.
 *
 * @param mixed    $value    Candidate value.
 * @param string[] $allowed  Allowed values.
 * @param string   $fallback Default when $value is not allowed.
 * @return string
 */
$hero_enum = static function ( $value, array $allowed, string $fallback ): string {
	return in_array( $value, $allowed, true ) ? (string) $value : $fallback;
};

$hero_transition = $hero_enum( $attributes['transition'] ?? 'slide', array( 'slide', 'fade', 'crossfade' ), 'slide' );

$hero_min_height = (string) ( $attributes['minHeight'] ?? '85svh' );
if ( ! preg_match( '/^\d+(?:\.\d+)?(?:px|rem|em|vh|svh|lvh|dvh|%)$/', $hero_min_height ) ) {
	$hero_min_height = '85svh';
}

$hero_autoplay       = ! empty( $attributes['autoplay'] );
$hero_autoplay_speed = isset( $attributes['autoplaySpeed'] ) ? (int) $attributes['autoplaySpeed'] : 6000;
$hero_autoplay_speed = max( 1000, $hero_autoplay_speed );
$hero_loop           = ! isset( $attributes['loop'] ) || (bool) $attributes['loop'];
$hero_pause_hover    = ! isset( $attributes['pauseOnHover'] ) || (bool) $attributes['pauseOnHover'];

$hero_transition_ms = isset( $attributes['transitionMs'] ) ? (int) $attributes['transitionMs'] : 700;
$hero_transition_ms = min( 3000, max( 100, $hero_transition_ms ) );

$hero_show_arrows = ! isset( $attributes['showArrows'] ) || (bool) $attributes['showArrows'];
$hero_arrow_pos   = $hero_enum( $attributes['arrowPosition'] ?? 'edges', array( 'edges', 'bottom' ), 'edges' );

$hero_pagination = $hero_enum(
	$attributes['pagination'] ?? 'dots',
	array( 'dots', 'lines', 'numbers', 'fraction', 'thumbnails', 'none' ),
	'dots'
);

$hero_show_progress = ! isset( $attributes['showProgress'] ) || (bool) $attributes['showProgress'];
$hero_deep_link     = ! empty( $attributes['deepLink'] );

$hero_ken_burns = $hero_enum(
	$attributes['kenBurns'] ?? 'alternate',
	array( 'none', 'zoom-in', 'zoom-out', 'alternate', 'random' ),
	'alternate'
);

$hero_kb_duration = isset( $attributes['kenBurnsDuration'] ) ? (float) $attributes['kenBurnsDuration'] : 12.0;
$hero_kb_duration = min( 60.0, max( 4.0, $hero_kb_duration ) );

$hero_content_anim = $hero_enum( $attributes['contentAnimation'] ?? 'fade-up', array( 'none', 'fade-up', 'clip', 'blur' ), 'fade-up' );

/**
 * Ken Burns variant for a slide. Variants are rendered into slide classes
 * server-side (the client never recomputes them), so this is the single
 * source of truth for `alternate`/`random` assignment.
 *
 * @param string $mode  Ken Burns mode.
 * @param int    $index Zero-based slide index.
 * @return string|null 'zoom-in' | 'zoom-out' | null.
 */
$hero_kb_variant = static function ( string $mode, int $index ): ?string {
	switch ( $mode ) {
		case 'zoom-in':
		case 'zoom-out':
			return $mode;
		case 'alternate':
			return 0 === $index % 2 ? 'zoom-in' : 'zoom-out';
		case 'random':
			// Deterministic per index (uint32 wrap, >> 13 folds high bits into
			// the parity so it isn't just index % 2).
			$hashed = ( ( ( $index + 1 ) * 2654435761 ) % 4294967296 ) >> 13;
			return 0 === ( $hashed & 1 ) ? 'zoom-in' : 'zoom-out';
		default:
			return null;
	}
};

/**
 * Whether a slide is inside its scheduled visibility window.
 *
 * `aaHeroStart` / `aaHeroEnd` are `datetime-local` strings interpreted in the
 * site timezone. Either bound is optional; a missing/unparseable bound is
 * treated as open. NOTE: this is evaluated at render time, so a full-page
 * cache can serve a slide past its window until the cache is purged.
 *
 * @param array             $attrs Cover block attributes.
 * @param DateTimeImmutable $now   Current site-timezone moment.
 * @return bool
 */
$hero_in_window = static function ( array $attrs, DateTimeImmutable $now ): bool {
	$tz    = wp_timezone();
	$start = empty( $attrs['aaHeroStart'] ) ? false : date_create_immutable( (string) $attrs['aaHeroStart'], $tz );
	$end   = empty( $attrs['aaHeroEnd'] ) ? false : date_create_immutable( (string) $attrs['aaHeroEnd'], $tz );
	if ( $start instanceof DateTimeImmutable && $now < $start ) {
		return false;
	}
	if ( $end instanceof DateTimeImmutable && $now >= $end ) {
		return false;
	}
	return true;
};

// Collect the cover inner blocks (skipping any outside their schedule) so we
// can wrap each rendered slide.
$hero_inner_blocks = $block->parsed_block['innerBlocks'] ?? array();
$hero_now          = current_datetime();
$hero_slides       = array();
foreach ( $hero_inner_blocks as $hero_inner_block ) {
	if ( 'core/cover' !== ( $hero_inner_block['blockName'] ?? '' ) ) {
		continue;
	}
	if ( ! $hero_in_window( $hero_inner_block['attrs'] ?? array(), $hero_now ) ) {
		continue;
	}
	$hero_slides[] = $hero_inner_block;
}

$hero_count = count( $hero_slides );
if ( 0 === $hero_count ) {
	return;
}

$hero_root_classes = array(
	'aa-hero',
	'aa-hero--' . $hero_transition,
	'aa-hero--arrows-' . $hero_arrow_pos,
);
if ( 'none' !== $hero_content_anim ) {
	$hero_root_classes[] = 'aa-hero--content-' . $hero_content_anim;
}
if ( $hero_autoplay ) {
	$hero_root_classes[] = 'is-playing';
}

// Chrome theming — attribute colors flow through CSS custom properties;
// defaults in style.css bind to the adaptive --aa-* palette.
$hero_style_parts = array(
	sprintf( '--aa-hero-min-height: %s;', esc_attr( $hero_min_height ) ),
	sprintf( '--aa-hero-transition-ms: %dms;', $hero_transition_ms ),
	sprintf( '--aa-hero-kb-duration: %ss;', esc_attr( (string) $hero_kb_duration ) ),
	sprintf( '--aa-hero-autoplay-ms: %dms;', $hero_autoplay_speed ),
);
$hero_color_vars  = array(
	'arrowColor'     => '--aa-hero-arrow-color',
	'arrowBg'        => '--aa-hero-arrow-bg',
	'dotColor'       => '--aa-hero-dot-color',
	'dotActiveColor' => '--aa-hero-dot-active-color',
);
foreach ( $hero_color_vars as $hero_attr_key => $hero_css_var ) {
	if ( ! empty( $attributes[ $hero_attr_key ] ) ) {
		$hero_style_parts[] = sprintf( '%s: %s;', $hero_css_var, esc_attr( (string) $attributes[ $hero_attr_key ] ) );
	}
}

$hero_wrapper_attrs = array(
	'class'                => implode( ' ', $hero_root_classes ),
	'role'                 => 'region',
	'aria-roledescription' => 'carousel',
	'aria-label'           => __( 'Hero slideshow', 'aggressive-apparel' ),
	'style'                => implode( ' ', $hero_style_parts ),
	'data-wp-interactive'  => 'aggressive-apparel/hero-carousel',
	'data-wp-context'      => (string) wp_json_encode(
		array(
			'activeIndex'   => 0,
			'slideIndex'    => 0,
			'isPlaying'     => $hero_autoplay,
			'isPaused'      => false,
			'autoplay'      => $hero_autoplay,
			'autoplaySpeed' => $hero_autoplay_speed,
			'loop'          => $hero_loop,
			'pauseOnHover'  => $hero_pause_hover,
			'count'         => $hero_count,
			'transition'    => $hero_transition,
			'deepLink'      => $hero_deep_link,
			'i18n'          => array(
				'play'  => __( 'Play slideshow', 'aggressive-apparel' ),
				'pause' => __( 'Pause slideshow', 'aggressive-apparel' ),
				/* translators: 1: current slide number, 2: total slide count. Announced by screen readers. */
				'slide' => __( 'Slide %1$s of %2$s', 'aggressive-apparel' ),
			),
		)
	),
	'data-wp-init'         => 'callbacks.init',
	'data-wp-on--keydown'  => 'actions.handleKeydown',
	'data-wp-on--focusin'  => 'actions.pauseFocus',
	'data-wp-on--focusout' => 'actions.resumeFocus',
);
if ( $hero_autoplay && $hero_pause_hover ) {
	$hero_wrapper_attrs['data-wp-on--mouseenter'] = 'actions.pause';
	$hero_wrapper_attrs['data-wp-on--mouseleave'] = 'actions.resume';
}

$hero_wrapper = get_block_wrapper_attributes( $hero_wrapper_attrs );

/**
 * Tune image loading inside a rendered slide for LCP.
 *
 * Slide 1 is the likely LCP element: force eager + fetchpriority="high".
 * Later slides are off-screen at load: force lazy.
 *
 * @param string $html  Rendered cover HTML.
 * @param bool   $first Whether this is the first slide.
 * @return string
 */
$hero_tune_images = static function ( string $html, bool $first ): string {
	if ( ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
		return $html;
	}
	$processor = new \WP_HTML_Tag_Processor( $html );
	while ( $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
		if ( $first ) {
			$processor->remove_attribute( 'loading' );
			$processor->set_attribute( 'fetchpriority', 'high' );
			$processor->set_attribute( 'decoding', 'async' );
		} else {
			$processor->set_attribute( 'loading', 'lazy' );
			$processor->set_attribute( 'decoding', 'async' );
		}
	}
	return $processor->get_updated_html();
};

/**
 * Build a decorative thumbnail for the thumbnails pagination style.
 *
 * Prefers the Cover's attachment (sized `thumbnail`), falls back to its raw
 * media URL, then to a color swatch for media-less (color-only) slides.
 *
 * @param array $cover_attrs Cover block attributes.
 * @return string Escaped thumbnail markup.
 */
$hero_slide_thumb = static function ( array $cover_attrs ): string {
	if ( ! empty( $cover_attrs['id'] ) ) {
		$img = wp_get_attachment_image(
			(int) $cover_attrs['id'],
			'thumbnail',
			false,
			array(
				'class'   => 'aa-hero__thumb',
				'alt'     => '',
				'loading' => 'lazy',
			)
		);
		if ( '' !== $img ) {
			return $img;
		}
	}
	if ( ! empty( $cover_attrs['url'] ) ) {
		return sprintf(
			'<img class="aa-hero__thumb" src="%s" alt="" loading="lazy" decoding="async" />',
			esc_url( (string) $cover_attrs['url'] )
		);
	}
	$swatch = $cover_attrs['customOverlayColor'] ?? '';
	return sprintf(
		'<span class="aa-hero__thumb aa-hero__thumb--swatch"%s></span>',
		$swatch ? ' style="background:' . esc_attr( (string) $swatch ) . '"' : ''
	);
};
?>
<section <?php echo $hero_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes(). ?>>
	<div class="aa-hero__viewport">
		<div class="aa-hero__track">
			<?php foreach ( $hero_slides as $hero_index => $hero_slide_block ) : ?>
				<?php
				$hero_slide_classes = array( 'aa-hero__slide' );
				if ( 0 === $hero_index ) {
					$hero_slide_classes[] = 'is-active';
				}
				$hero_variant = $hero_kb_variant( $hero_ken_burns, $hero_index );
				// Per-slide override (from the Cover's "Hero Slide" panel) wins
				// over the carousel-level Ken Burns mode when set.
				$hero_slide_kb = $hero_slide_block['attrs']['aaHeroKenBurns'] ?? '';
				if ( in_array( $hero_slide_kb, array( 'none', 'zoom-in', 'zoom-out' ), true ) ) {
					$hero_variant = 'none' === $hero_slide_kb ? null : $hero_slide_kb;
				}
				if ( null !== $hero_variant ) {
					$hero_slide_classes[] = 'aa-hero__slide--kb-' . $hero_variant;
				}
				$hero_slide_html = render_block( $hero_slide_block );
				$hero_slide_html = $hero_tune_images( $hero_slide_html, 0 === $hero_index );

				// Pre-hydration a11y for stacked modes: only slide 1 is reachable.
				$hero_is_hidden = 'slide' !== $hero_transition && 0 !== $hero_index;
				?>
				<div
					class="<?php echo esc_attr( implode( ' ', $hero_slide_classes ) ); ?>"
					role="group"
					aria-roledescription="slide"
					<?php /* translators: 1: current slide number, 2: total slide count. */ ?>
					aria-label="<?php echo esc_attr( sprintf( __( '%1$s of %2$s', 'aggressive-apparel' ), $hero_index + 1, $hero_count ) ); ?>"
					<?php echo $hero_is_hidden ? 'inert aria-hidden="true"' : ''; ?>
					data-wp-context='<?php echo esc_attr( (string) wp_json_encode( array( 'slideIndex' => $hero_index ) ) ); ?>'
					data-wp-class--is-active="state.isActiveSlide"
					data-wp-bind--inert="state.slideInert"
					data-wp-bind--aria-hidden="state.ariaHiddenSlide"
				>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by render_block(); covers may contain media/embeds wp_kses_post() would strip.
					echo $hero_slide_html;
					?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $hero_show_arrows && $hero_count > 1 ) : ?>
			<div class="aa-hero__arrows aa-hero__arrows--<?php echo esc_attr( $hero_arrow_pos ); ?>">
				<button
					type="button"
					class="aa-hero__arrow aa-hero__arrow--prev"
					aria-label="<?php esc_attr_e( 'Previous slide', 'aggressive-apparel' ); ?>"
					data-wp-on--click="actions.prev"
					data-wp-bind--disabled="state.prevDisabled"
				>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns trusted SVG.
					echo Icons::get(
						'chevron-left',
						array(
							'width'  => 24,
							'height' => 24,
						)
					);
					?>
				</button>
				<button
					type="button"
					class="aa-hero__arrow aa-hero__arrow--next"
					aria-label="<?php esc_attr_e( 'Next slide', 'aggressive-apparel' ); ?>"
					data-wp-on--click="actions.next"
					data-wp-bind--disabled="state.nextDisabled"
				>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns trusted SVG.
					echo Icons::get(
						'chevron-right',
						array(
							'width'  => 24,
							'height' => 24,
						)
					);
					?>
				</button>
			</div>
		<?php endif; ?>

		<?php if ( ( 'none' !== $hero_pagination || $hero_autoplay ) && $hero_count > 1 ) : ?>
			<div class="aa-hero__bar">
				<?php if ( 'fraction' === $hero_pagination ) : ?>
					<span class="aa-hero__fraction" data-wp-text="state.fraction" aria-hidden="true">1 / <?php echo (int) $hero_count; ?></span>
				<?php elseif ( 'none' !== $hero_pagination ) : ?>
					<div class="aa-hero__pagination aa-hero__pagination--<?php echo esc_attr( $hero_pagination ); ?><?php echo $hero_show_progress && $hero_autoplay ? ' aa-hero__pagination--progress' : ''; ?>" role="group" aria-label="<?php esc_attr_e( 'Choose slide', 'aggressive-apparel' ); ?>">
						<?php for ( $hero_dot = 0; $hero_dot < $hero_count; $hero_dot++ ) : ?>
							<button
								type="button"
								class="aa-hero__dot"
								<?php /* translators: %s: slide number. */ ?>
								aria-label="<?php echo esc_attr( sprintf( __( 'Go to slide %s', 'aggressive-apparel' ), $hero_dot + 1 ) ); ?>"
								<?php echo 0 === $hero_dot ? 'aria-current="true"' : ''; ?>
								data-wp-context='<?php echo esc_attr( (string) wp_json_encode( array( 'slideIndex' => $hero_dot ) ) ); ?>'
								data-wp-on--click="actions.goTo"
								data-wp-bind--aria-current="state.ariaCurrentDot"
							>
								<?php if ( 'numbers' === $hero_pagination ) : ?>
									<span class="aa-hero__dot-number"><?php echo (int) ( $hero_dot + 1 ); ?></span>
								<?php elseif ( 'thumbnails' === $hero_pagination ) : ?>
									<?php
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in $hero_slide_thumb().
									echo $hero_slide_thumb( $hero_slides[ $hero_dot ]['attrs'] ?? array() );
									?>
								<?php endif; ?>
							</button>
						<?php endfor; ?>
					</div>
				<?php endif; ?>

				<?php if ( $hero_autoplay ) : ?>
					<button
						type="button"
						class="aa-hero__play"
						aria-label="<?php esc_attr_e( 'Pause slideshow', 'aggressive-apparel' ); ?>"
						data-wp-on--click="actions.togglePlay"
						data-wp-bind--aria-label="state.playLabel"
						data-wp-class--is-playing="state.isPlayingClass"
					>
						<span class="aa-hero__play-icon aa-hero__play-icon--play">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns trusted SVG.
							echo Icons::get(
								'play',
								array(
									'width'  => 16,
									'height' => 16,
								)
							);
							?>
						</span>
						<span class="aa-hero__play-icon aa-hero__play-icon--pause">
							<?php
							// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icons::get() returns trusted SVG.
							echo Icons::get(
								'pause',
								array(
									'width'  => 16,
									'height' => 16,
								)
							);
							?>
						</span>
					</button>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<div
		class="aa-hero__live"
		aria-atomic="true"
		aria-live="polite"
		data-wp-bind--aria-live="state.ariaLive"
	></div>
</section>
