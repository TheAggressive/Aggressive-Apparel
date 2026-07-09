/**
 * Freeze background-motion mid-flight when a slide deactivates.
 *
 * Motion keyframes are gated on `.is-active`. Removing that class drops the
 * animation (and its `forwards` fill), so the media snaps to identity —
 * e.g. zoom-in looks like a rapid zoom-out right before the next slide.
 * Capture the live computed transform/filter/clip-path, then pin them with
 * inline styles until the slide is active again.
 *
 * Applies to every transition mode (slide / fade / crossfade) because all
 * of them clear `.is-active` through the shared index update path.
 *
 * @package Aggressive_Apparel
 */

import { MOTION_HOLD_CLASS, MOTION_MEDIA_SELECTOR } from './constants';

const HELD_PROPS = ['transform', 'filter', 'clip-path'] as const;

function mediaElements(slide: HTMLElement): HTMLElement[] {
  return Array.from(slide.querySelectorAll<HTMLElement>(MOTION_MEDIA_SELECTOR));
}

/**
 * Pin each Cover media element at its current animated transform.
 */
export function holdMotion(slide: HTMLElement): void {
  for (const media of mediaElements(slide)) {
    const computed = getComputedStyle(media);
    for (const prop of HELD_PROPS) {
      const value = computed.getPropertyValue(prop);
      if (value && value !== 'none') {
        media.style.setProperty(prop, value);
      }
    }
  }
  slide.classList.add(MOTION_HOLD_CLASS);
}

/**
 * Clear a previous hold so the next `.is-active` dwell can play motion from
 * the keyframe start again.
 */
export function releaseMotion(slide: HTMLElement): void {
  slide.classList.remove(MOTION_HOLD_CLASS);
  for (const media of mediaElements(slide)) {
    for (const prop of HELD_PROPS) {
      media.style.removeProperty(prop);
    }
  }
}

export function clearMotionHolds(slides: HTMLElement[]): void {
  for (const slide of slides) {
    releaseMotion(slide);
  }
}
