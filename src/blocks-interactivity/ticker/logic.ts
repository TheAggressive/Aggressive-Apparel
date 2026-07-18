/**
 * Ticker block — pure helpers (safe to unit-test without the DOM runtime).
 *
 * @package Aggressive_Apparel
 */

import { DEFAULT_TICKER_SPEED } from './constants';

export interface TickerAnimationState {
  isDestroyed: boolean;
  isIntersecting: boolean;
  isDocumentVisible: boolean;
  reducedMotion: boolean;
  isPaused: boolean;
  pxPerMs: number;
}

export interface TickerPauseState {
  /** Manual pause from the play/pause control (or reduced-motion lock). */
  isPaused: boolean;
  /** Temporary hold from hover / keyboard focus. */
  isHeld: boolean;
  /** Reduced-motion lock — animation must not run; control stays disabled. */
  motionLocked: boolean;
}

/**
 * Pixels per millisecond for one full content-loop at the given speed.
 *
 * Speed is measured in seconds to scroll one `.ticker__content` width, so loop
 * duration stays consistent regardless of viewport width.
 */
export function getTickerPxPerMs(
  loopWidth: number,
  speedSeconds: number
): number {
  if (loopWidth <= 0 || speedSeconds <= 0) {
    return 0;
  }

  return loopWidth / (speedSeconds * 1000);
}

/** Parse loop duration from `data-ticker-speed`. */
export function parseTickerDataSpeed(
  value: string | undefined,
  fallback = DEFAULT_TICKER_SPEED
): number {
  const parsed = Number.parseFloat(value ?? '');
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

/** Whether the marquee scrolls right based on `data-ticker-direction`. */
export function isTickerReverseDirection(
  direction: string | undefined
): boolean {
  return direction === 'right';
}

/**
 * Manual pause, hover/focus hold, or reduced-motion lock all freeze motion.
 */
export function isEffectivelyPaused(state: TickerPauseState): boolean {
  return state.isPaused || state.isHeld || state.motionLocked;
}

/**
 * Whether a ticker should consume animation frames right now.
 */
export function shouldAnimateTicker(state: TickerAnimationState): boolean {
  return (
    !state.isDestroyed &&
    state.isIntersecting &&
    state.isDocumentVisible &&
    !state.reducedMotion &&
    !state.isPaused &&
    state.pxPerMs > 0
  );
}

/**
 * Pick `value` when it is in `allowed`, otherwise `fallback`.
 */
export function pickAllowed<T extends string>(
  value: string | undefined,
  allowed: readonly T[],
  fallback: T
): T {
  if (value !== undefined && (allowed as readonly string[]).includes(value)) {
    return value as T;
  }

  return fallback;
}

/** Whether `target` is (or is inside) the ticker play/pause control. */
export function isTickerPauseControl(target: EventTarget | null): boolean {
  return target instanceof Element && Boolean(target.closest('.ticker__pause'));
}

/**
 * Resolve the marquee content's computed text color for the pause control.
 * Prefers the first painted child so inner-block color classes win.
 */
export function resolveTickerControlColor(content: HTMLElement | null): string {
  if (!content) {
    return '';
  }

  const sample =
    content.querySelector<HTMLElement>(
      'p, span, a, .aggressive-apparel-free-shipping-message, li'
    ) ?? content;

  return getComputedStyle(sample).color;
}
