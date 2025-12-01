/**
 * Debug Observer logic for the Parallax block
 *
 * @package Aggressive Apparel
 */

import { ParallaxActions, ParallaxContext, ParallaxState } from '../types';
import { getScrollY } from '../utils';

/**
 * Initialize element state helper
 */
export const initializeElementState = (
  ctx: ParallaxContext,
  ref: HTMLElement,
  state: ParallaxState
): void => {
  state.ctx = ctx;
  state.elementRef = ref;
  state.entryHeight = ref.offsetHeight;

  // Initialize previousTop and previousScrollY for scroll direction detection
  const initialRect = ref.getBoundingClientRect();
  state.previousTop = initialRect.top;
  state.previousScrollY = getScrollY();
};

/**
 * Setup debug event listeners for scroll and resize
 */
export const setupDebugEventListeners = (
  ctx: ParallaxContext,
  elementRef: HTMLElement,
  state: ParallaxState,
  actions: ParallaxActions
): (() => void) => {
  let scrollUpdateTimeout: number | null = null;

  const updateDebugPosition = () => {
    if (
      ctx.debugMode &&
      // @ts-ignore - id might be missing in types but present in context
      (state.ctx.id === ctx.id || true) &&
      scrollUpdateTimeout === null
    ) {
      scrollUpdateTimeout = requestAnimationFrame(() => {
        state.ctx = ctx;
        state.elementRef = elementRef;
        if (state.elementRef) {
          state.entryHeight = state.elementRef.offsetHeight;
        }
        // Update debug overlays position
        actions.updateDetectionBoundary();
        actions.updateDebugContainerPosition();
        // Visual updates only happen in observer callback with fresh data
        scrollUpdateTimeout = null;
      });
    }
  };

  window.addEventListener('scroll', updateDebugPosition, { passive: true });
  window.addEventListener('resize', updateDebugPosition, { passive: true });

  // Return cleanup function
  return () => {
    window.removeEventListener('scroll', updateDebugPosition);
    window.removeEventListener('resize', updateDebugPosition);
  };
};
