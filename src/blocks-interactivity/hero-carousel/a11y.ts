/**
 * Hero Carousel — accessibility + deep-link helpers.
 *
 * Kept out of the controller so announcement / hash sync / focus retention
 * stay unit-testable without spinning up the full Interactivity store.
 *
 * @package Aggressive_Apparel
 */

import { CLONE_ATTR } from './constants';
import { slideHash } from './logic';

/**
 * Update the polite live region. Suppressed while autoplay is actively
 * advancing so AT is not flooded every dwell.
 */
export function announceSlide(
  announcer: HTMLElement | null,
  template: string,
  index: number,
  count: number,
  suppress: boolean
): void {
  if (!announcer || suppress) return;
  announcer.textContent = template
    .replace('%1$s', String(index + 1))
    .replace('%2$s', String(count));
}

/** Reflect the active slide in the URL hash when deep linking is enabled. */
export function syncDeepLinkHash(
  carouselId: string,
  index: number,
  enabled: boolean
): void {
  if (!enabled || !carouselId) return;
  const hash = slideHash(carouselId, index);
  if (window.location.hash !== hash) {
    window.history.replaceState(null, '', hash);
  }
}

/**
 * True when keyboard focus is on a real slide inside this carousel
 * (not chrome, not an edge clone).
 */
export function isSlideFocused(
  root: HTMLElement,
  active: Element | null = document.activeElement
): boolean {
  return (
    active instanceof HTMLElement &&
    active.classList.contains('aa-hero__slide') &&
    !active.hasAttribute(CLONE_ATTR) &&
    root.contains(active)
  );
}

/**
 * Move focus to a slide after Interactivity applies inert/tabindex.
 * Double rAF waits for directive binds to land first.
 */
export function focusSlide(slide: HTMLElement | null | undefined): void {
  if (!slide) return;
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      slide.focus({ preventScroll: true });
    });
  });
}
