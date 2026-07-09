/**
 * Hero Carousel — shared selectors and timing constants.
 *
 * @package Aggressive_Apparel
 */

export const ROOT_SELECTOR = '.wp-block-aggressive-apparel-hero-carousel';
export const SLIDE_SELECTOR = '.aa-hero__slide';
export const TRACK_SELECTOR = '.aa-hero__track';
export const COVER_BG_SELECTOR = '.wp-block-cover__image-background';

/** Cover media that receives background-motion transforms. */
export const MOTION_MEDIA_SELECTOR =
  '.wp-block-cover__image-background, .wp-block-cover__video-background';

/** Pause after manual navigation before autoplay may resume, per WCAG intent. */
export const RESUME_DELAY_MS = 400;

/** Debounce before a manual scroll settles into an active slide index. */
export const SCROLL_IDLE_MS = 90;

/** Fraction of the carousel that must be on-screen for autoplay to run. */
export const VISIBILITY_THRESHOLD = 0.25;

/** Marker for cloned edge slides used by seamless slide-mode looping. */
export const CLONE_ATTR = 'data-aa-hero-clone';

/** Suppress background-motion re-entrance after a seamless wrap snap. */
export const SETTLE_CLASS = 'aa-hero__slide--settle';

/**
 * Slide is leaving: keep Cover media at the last animated transform instead
 * of snapping back when `.is-active` (and its motion animation) is removed.
 */
export const MOTION_HOLD_CLASS = 'aa-hero__slide--motion-hold';

/** Fallback settle delay when `scrollend` never fires (ms). */
export const LOOP_SETTLE_MS = 1200;
