import { isScrollInPinnedRange } from '../logic';
import { WHEEL_LISTEN_ENTER_PX, WHEEL_LISTEN_EXIT_PX } from './step-constants';

/**
 * Whether non-passive window wheel capture should be bound.
 * Always on while owning/animating; otherwise near the pin range with hysteresis.
 */
export function shouldListenWindowWheel(params: {
  destroyed: boolean;
  animating: boolean;
  owning: boolean;
  windowWheelBound: boolean;
  scrollY: number;
  scrollStart: number;
  scrollDistance: number;
}): boolean {
  const {
    destroyed,
    animating,
    owning,
    windowWheelBound,
    scrollY,
    scrollStart,
    scrollDistance,
  } = params;

  if (destroyed) return false;
  if (animating || owning) return true;

  return isScrollInPinnedRange({
    scrollY,
    scrollStart,
    scrollDistance,
    slackPx: windowWheelBound ? WHEEL_LISTEN_EXIT_PX : WHEEL_LISTEN_ENTER_PX,
  });
}

/** Normalize wheel deltaY into approximate CSS pixels for intent checks. */
export function normalizeWheelDeltaY(
  event: WheelEvent,
  innerHeight: number
): number {
  if (event.deltaMode === WheelEvent.DOM_DELTA_LINE) {
    return event.deltaY * 16;
  }
  if (event.deltaMode === WheelEvent.DOM_DELTA_PAGE) {
    return event.deltaY * innerHeight;
  }
  return event.deltaY;
}
