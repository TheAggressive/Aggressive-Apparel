/**
 * Timing and threshold constants for the directional step (paged) controller.
 */

/** Same-direction coast stream after a land (gap + max from land). */
export const COAST_GAP_MS = 70;
export const COAST_MAX_MS = 220;

/** Minimum |deltaY| (px) to count as a deliberate wheel notch / flick. */
export const WHEEL_INTENT_PX = 8;

/** Minimum touch travel (px) that counts as a deliberate swipe. */
export const TOUCH_STEP_THRESHOLD_PX = 16;

/** Inclusive band around the pinned scroll range (px). Keep tight for exit. */
export const RANGE_SLACK_PX = 4;

/**
 * Wheel capture hysteresis (px beyond the pin range). Bind before we own the
 * range so the entry flick is not missed; unbind further out so far-page
 * scrolling pays no non-passive wheel tax.
 */
export const WHEEL_LISTEN_ENTER_PX = 200;
export const WHEEL_LISTEN_EXIT_PX = 320;

/** Drift past a stop before clamping (avoids scroll↔clamp feedback). */
export const CLAMP_DRIFT_PX = 2;

/** Already-on-stop tolerance for "no tween needed". */
export const STOP_EPSILON_PX = 1;

export const REDUCED_MOTION_QUERY = '(prefers-reduced-motion: reduce)';
