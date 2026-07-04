<?php
/**
 * Image Loading Class
 *
 * Applies loading optimization (lazy-loading, fetchpriority, async decoding)
 * to images rendered directly inside block templates.
 *
 * Core skips the `template` context in wp_get_loading_optimization_attributes()
 * on the assumption that body images arrive through `the_content` (which is
 * processed granularly with in-the-loop viewport counting). Pages built
 * directly in the site editor — like the home template — bypass
 * `the_content`, so their images ship with no `loading`, `fetchpriority`, or
 * `decoding` attributes at all: the LCP hero gets no priority hint and
 * below-fold images are never lazy-loaded. Core's counting heuristics cannot
 * be reused directly either, because outside the main loop every image is
 * classified as below the viewport (everything would lazy-load, including
 * the hero). This class mirrors core's rules with its own template-pass
 * counter instead.
 *
 * @package Aggressive_Apparel
 * @since 1.132.0
 */

declare(strict_types=1);

namespace Aggressive_Apparel\Core;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image Loading Class
 *
 * @since 1.132.0
 */
class Image_Loading {

	/**
	 * Minimum width × height for a fetchpriority="high" candidate.
	 *
	 * Matches WordPress core's threshold in
	 * wp_get_loading_optimization_attributes().
	 *
	 * @var int
	 */
	private const HIGH_PRIORITY_MIN_AREA = 50000;

	/**
	 * Number of raw template images seen this request.
	 *
	 * @var int
	 */
	private int $image_count = 0;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'wp_content_img_tag', array( $this, 'optimize_template_image' ), 10, 2 );
	}

	/**
	 * Add loading optimization attributes to unprocessed template images.
	 *
	 * Images that already went through a granular core pass (`the_content`,
	 * `template_part_*`) carry `decoding="async"` — those are skipped so
	 * already-eager images are never re-evaluated into lazy-loading. Only
	 * images core left untouched (raw template body images) are processed:
	 * the first N (core's omit threshold, default 3) stay eager and the
	 * first sufficiently large one gets `fetchpriority="high"` (coordinated
	 * with core via wp_high_priority_element_flag()); the rest lazy-load.
	 *
	 * @param string $image   Full img tag markup.
	 * @param string $context The filter context.
	 * @return string Modified img tag markup.
	 */
	public function optimize_template_image( $image, $context ): string {
		if ( ! is_string( $image ) || '' === $image ) {
			return (string) $image;
		}

		if ( 'template' !== $context || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $image;
		}

		// Already processed in a granular context, or author-decided.
		if (
			str_contains( $image, ' decoding=' ) ||
			str_contains( $image, ' loading=' ) ||
			str_contains( $image, ' fetchpriority=' )
		) {
			return $image;
		}

		$processor = new \WP_HTML_Tag_Processor( $image );

		if ( ! $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
			return $image;
		}

		// Interactivity-bound images (e.g. mini-cart item templates) are
		// placeholders, not real resources — leave them alone.
		$src = $processor->get_attribute( 'src' );
		if ( ! is_string( $src ) || '' === $src || str_starts_with( $src, 'state.' ) || str_starts_with( $src, 'context.' ) ) {
			return $image;
		}

		++$this->image_count;

		$processor->set_attribute( 'decoding', 'async' );

		if ( $this->image_count <= $this->omit_lazy_threshold() ) {
			// Above-the-fold budget: keep eager; flag the first large image
			// as the likely LCP element.
			if ( $this->should_assign_high_priority( $processor ) ) {
				$processor->set_attribute( 'fetchpriority', 'high' );
			}
		} else {
			$processor->set_attribute( 'loading', 'lazy' );
		}

		return $processor->get_updated_html();
	}

	/**
	 * How many leading template images skip lazy-loading.
	 *
	 * Uses core's threshold (and its filter) so template images follow the
	 * same tuning as content images.
	 *
	 * @return int
	 */
	private function omit_lazy_threshold(): int {
		if ( function_exists( 'wp_omit_loading_attr_threshold' ) ) {
			return (int) wp_omit_loading_attr_threshold();
		}

		return 3;
	}

	/**
	 * Whether this image should carry fetchpriority="high".
	 *
	 * Mirrors core: only one element per page (shared flag), and only images
	 * large enough to plausibly be the LCP element.
	 *
	 * @param \WP_HTML_Tag_Processor $processor Positioned on the IMG tag.
	 * @return bool True when the high-priority flag was consumed.
	 */
	private function should_assign_high_priority( \WP_HTML_Tag_Processor $processor ): bool {
		if ( ! function_exists( 'wp_high_priority_element_flag' ) || ! wp_high_priority_element_flag() ) {
			return false;
		}

		$width  = (int) $processor->get_attribute( 'width' );
		$height = (int) $processor->get_attribute( 'height' );

		if ( $width <= 0 || $height <= 0 || ( $width * $height ) < self::HIGH_PRIORITY_MIN_AREA ) {
			return false;
		}

		// Consume the shared flag so neither core nor this class assigns a
		// second high-priority image on the same page.
		wp_high_priority_element_flag( false );

		return true;
	}
}
