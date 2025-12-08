/**
 * Intersection Observer logic for the Parallax block
 *
 * @package Aggressive Apparel
 */

import { applyParallaxTransformsDirect } from '../transforms';
import { ParallaxActions, ParallaxContext, ParallaxState } from '../types';
import {
  ParallaxLogger,
  calculateProgressWithinBoundary,
  getObserverRootMargin,
  getObserverThreshold,
  getValidIntersectionRatio,
  getVisibilityThreshold,
} from '../utils';

/**
 * Create IntersectionObserver with extracted logic
 */
export const createIntersectionObserver = (
  ctx: ParallaxContext,
  ref: HTMLElement,
  state: ParallaxState,
  actions: ParallaxActions
): IntersectionObserver => {
  const observer = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        try {
          // Update intersection ratio
          const validRatio = getValidIntersectionRatio(
            entry.intersectionRatio,
            0
          );
          ctx.intersectionRatio = validRatio;

          // Update isIntersecting based on visibility trigger
          const threshold = getVisibilityThreshold(ctx.visibilityTrigger);
          const previousIsIntersecting = ctx.isIntersecting ?? false;
          ctx.isIntersecting = entry.intersectionRatio >= threshold;

          // Update state for Interactivity API reactivity (safe updates)
          state.intersectionRatio = validRatio;
          state.isIntersecting = ctx.isIntersecting;

          // Sync state when debug mode is enabled
          if (ctx.debugMode) {
            state.ctx = ctx;
            state.elementRef = ref;
            state.ctx.intersectionRatio = ctx.intersectionRatio;

            // Update debug overlays
            actions.updateInfoPanel(ctx.isIntersecting);
            actions.updateZoneVisualization(
              entry.intersectionRatio,
              ctx.isIntersecting
            );
            actions.updateVisibilityTriggerLine(
              entry.intersectionRatio,
              ctx.isIntersecting
            );
          }

          // Calculate scroll progress using stateless viewport logic
          // This ensures precise start/end points based on Detection Boundary
          const { progress } = calculateProgressWithinBoundary(
            ref,
            ctx.detectionBoundary,
            ctx.visibilityTrigger
          );


          ctx.scrollProgress = progress;

          // Apply transforms
          // We apply transforms if:
          // 1. Element is intersecting
          // 2. Element JUST left intersection (to freeze at 0 or 1)
          if (ctx.isIntersecting || previousIsIntersecting) {
            applyParallaxTransformsDirect(ctx, ref);
          }
        } catch (error) {
          ParallaxLogger.error('Error in IntersectionObserver callback:', {
            error,
            entry: entry,
          });
        }
      });
    },
    {
      threshold: getObserverThreshold(ctx.visibilityTrigger),
      rootMargin: getObserverRootMargin(ctx.detectionBoundary),
    }
  );

  return observer;
};

/**
 * Initialize IntersectionObserver
 */
export const initializeIntersectionObserver = (
  ctx: ParallaxContext,
  ref: HTMLElement,
  state: ParallaxState,
  actions: ParallaxActions
): IntersectionObserver => {
  const observer = createIntersectionObserver(ctx, ref, state, actions);
  observer.observe(ref);
  return observer;
};
