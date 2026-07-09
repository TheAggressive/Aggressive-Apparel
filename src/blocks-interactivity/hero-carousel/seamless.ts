/**
 * Hero Carousel — seamless slide-mode loop helpers.
 *
 * Edge clones + programmatic-scroll guarding so wrap animations don't reverse
 * through the strip or restart the autoplay progress pill mid-fill.
 *
 * @package Aggressive_Apparel
 */

import { CLONE_ATTR, LOOP_SETTLE_MS, SETTLE_CLASS } from './constants';
import {
  logicalToPhysical,
  physicalToLogical,
  scrollLeftForIndex,
  seamlessPhysicalTarget,
} from './logic';

/** Clone a slide for the seamless track, with eager media and muted video. */
export function prepareClone(
  source: HTMLElement,
  kind: 'first' | 'last'
): HTMLElement {
  const clone = source.cloneNode(true) as HTMLElement;
  clone.setAttribute(CLONE_ATTR, kind);
  clone.removeAttribute('data-wp-context');
  clone.classList.remove('is-active', SETTLE_CLASS);
  clone.setAttribute('aria-hidden', 'true');
  clone.inert = true;

  clone.querySelectorAll('img').forEach(img => {
    img.loading = 'eager';
    img.setAttribute('fetchpriority', 'high');
    img.removeAttribute('decoding');
  });

  clone.querySelectorAll('video').forEach(video => {
    video.autoplay = false;
    video.muted = true;
    video.playsInline = true;
    video.pause();
    video.removeAttribute('autoplay');
    try {
      video.currentTime = 0;
    } catch {
      // Some browsers reject seeks before metadata is ready.
    }
  });

  return clone;
}

/**
 * Clone last/first onto the track edges. Returns the width used for the
 * initial align, or 0 when cloning was skipped.
 */
export function mountEdgeClones(
  track: HTMLElement,
  slides: HTMLElement[]
): number {
  if (slides.length < 2) return 0;

  track.querySelectorAll(`[${CLONE_ATTR}]`).forEach(node => node.remove());

  const first = slides[0];
  const last = slides[slides.length - 1];
  if (!first || !last) return 0;

  track.prepend(prepareClone(last, 'last'));
  track.append(prepareClone(first, 'first'));

  return track.clientWidth || 1;
}

/** Enable/disable native scroll-snap (must be off during the clone teleport). */
export function setScrollSnap(
  track: HTMLElement | null,
  enabled: boolean
): void {
  if (!track) return;
  track.style.scrollSnapType = enabled ? '' : 'none';
}

export function clearSettle(slides: HTMLElement[]): void {
  for (const slide of slides) {
    slide.classList.remove(SETTLE_CLASS);
  }
}

export function removeEdgeClones(track: HTMLElement | null): void {
  track?.querySelectorAll(`[${CLONE_ATTR}]`).forEach(node => node.remove());
}

/**
 * Guards engine-driven `scrollTo` so the scroll handler does not treat
 * autoplay / arrow navigation as a user swipe.
 *
 * `begin()` arms a pending flag; the next scroll-idle settle consumes it.
 * A long safety timeout covers `scrollTo` that never fires a scroll event
 * (already at the target). No short clear timer — smooth scroll often lasts
 * longer than 800ms and a premature clear restarted the progress pill.
 */
export class ProgrammaticScrollGuard {
  private pending = false;
  private scrollIdle = 0;
  private safetyTimer = 0;

  begin(): void {
    this.pending = true;
    window.clearTimeout(this.scrollIdle);
    window.clearTimeout(this.safetyTimer);
    // Longer than a typical smooth scroll; clears if no scroll event arrives.
    this.safetyTimer = window.setTimeout(() => {
      this.pending = false;
    }, 2000);
  }

  /**
   * Debounced scroll settle. `onIdle(wasProgrammatic)` runs after the track
   * stops scrolling.
   */
  consumeAfterIdle(
    onIdle: (wasProgrammatic: boolean) => void,
    idleMs: number
  ): void {
    window.clearTimeout(this.scrollIdle);
    this.scrollIdle = window.setTimeout(() => {
      this.scrollIdle = 0;
      const wasProgrammatic = this.pending;
      this.pending = false;
      window.clearTimeout(this.safetyTimer);
      onIdle(wasProgrammatic);
    }, idleMs);
  }

  destroy(): void {
    window.clearTimeout(this.scrollIdle);
    window.clearTimeout(this.safetyTimer);
    this.scrollIdle = 0;
  }
}

export interface SeamlessWrapHost {
  track: HTMLElement;
  slides: HTMLElement[];
  count: number;
  isRtl: boolean;
  setDisplayIndex: (index: number) => void;
  setIndices: (index: number) => void;
  emitChange: (index: number) => void;
  onWrapStart: () => void;
  onWrapEnd: () => void;
  scrollGuard: ProgrammaticScrollGuard;
}

/**
 * Drive a seamless wrap: scroll to the edge clone, then teleport to the real
 * slide and commit the logical index.
 */
export function startSeamlessWrap(
  host: SeamlessWrapHost,
  from: number,
  target: number,
  reduce: boolean
): { settleTimer: number; cancel: () => void } | null {
  const wrapPhysical = seamlessPhysicalTarget(from, target, host.count, true);
  if (wrapPhysical === null) return null;

  const width = host.track.clientWidth || 1;
  const cloneAttr = from === host.count - 1 ? 'first' : 'last';
  const clone = host.track.querySelector<HTMLElement>(
    `[${CLONE_ATTR}="${cloneAttr}"]`
  );

  host.onWrapStart();
  setScrollSnap(host.track, false);
  host.setDisplayIndex(target);
  clone?.classList.add('is-active');

  host.scrollGuard.begin();
  host.track.scrollTo({
    left: scrollLeftForIndex(wrapPhysical, width, host.isRtl),
    behavior: reduce ? 'auto' : 'smooth',
  });

  if (reduce) {
    finishLoopSnap(host, target, clone);
    return { settleTimer: 0, cancel: () => undefined };
  }

  return scheduleLoopSnap(host, target, clone);
}

function scheduleLoopSnap(
  host: SeamlessWrapHost,
  logical: number,
  clone: HTMLElement | null
): { settleTimer: number; cancel: () => void } {
  let settled = false;
  let settleTimer = 0;

  const finish = (): void => {
    if (settled) return;
    settled = true;
    host.track.removeEventListener('scrollend', onScrollEnd);
    window.clearTimeout(settleTimer);
    finishLoopSnap(host, logical, clone);
  };

  const onScrollEnd = (): void => {
    finish();
  };

  host.track.addEventListener('scrollend', onScrollEnd, { once: true });
  settleTimer = window.setTimeout(finish, LOOP_SETTLE_MS);

  return {
    settleTimer,
    cancel: () => {
      settled = true;
      host.track.removeEventListener('scrollend', onScrollEnd);
      window.clearTimeout(settleTimer);
    },
  };
}

/** Teleport from clone → real and commit the logical active index. */
export function finishLoopSnap(
  host: SeamlessWrapHost,
  logical: number,
  clone: HTMLElement | null
): void {
  const real = host.slides[logical] ?? null;
  const width = host.track.clientWidth || 1;

  host.scrollGuard.begin();
  host.track.scrollTo({
    left: scrollLeftForIndex(
      logicalToPhysical(logical, host.count),
      width,
      host.isRtl
    ),
    behavior: 'auto',
  });

  clone?.classList.remove('is-active');

  // Suppress background-motion re-entrance on the real slide for this dwell.
  // Content stays hidden on clones and plays entrance fresh when is-active.
  clearSettle(host.slides);
  real?.classList.add(SETTLE_CLASS);

  host.setIndices(logical);
  host.emitChange(logical);
  setScrollSnap(host.track, true);
  host.onWrapEnd();
}

/** Instantly replace a clone position with its matching real slide. */
export function snapFromClone(host: SeamlessWrapHost, physical: number): void {
  const logical = physicalToLogical(physical, host.count);
  const cloneAttr = physical === 0 ? 'last' : 'first';
  const clone = host.track.querySelector<HTMLElement>(
    `[${CLONE_ATTR}="${cloneAttr}"]`
  );
  host.onWrapStart();
  setScrollSnap(host.track, false);
  host.setDisplayIndex(logical);
  finishLoopSnap(host, logical, clone);
}
