/**
 * Intersection Observer for the Parallax block.
 *
 * Its single job is activating/deactivating an instance in the shared
 * frame engine (plus feeding the optional debug controller). All motion
 * math lives in the engine.
 *
 * @package Aggressive Apparel
 */

import { setInstanceActive, type ParallaxInstance } from './engine';
import {
  getObserverRootMargin,
  getObserverThreshold,
  getValidIntersectionRatio,
  getVisibilityThreshold,
} from './utils';

/** Debug hook receiving raw observer data; loaded lazily in debug mode. */
export type IntersectionDebugHook = (
  ratio: number,
  isIntersecting: boolean
) => void;

/**
 * Create and start the observer for a parallax instance.
 */
export const observeInstance = (
  instance: ParallaxInstance,
  onDebugEntry?: IntersectionDebugHook
): IntersectionObserver => {
  const { ctx } = instance;

  const observer = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        const ratio = getValidIntersectionRatio(entry.intersectionRatio, 0);
        const threshold = getVisibilityThreshold(ctx.visibilityTrigger);
        const isIntersecting =
          entry.isIntersecting && ratio >= Math.min(threshold, 0.99);

        ctx.intersectionRatio = ratio;
        ctx.isIntersecting = isIntersecting;

        setInstanceActive(instance, isIntersecting);
        onDebugEntry?.(ratio, isIntersecting);
      });
    },
    {
      threshold: getObserverThreshold(ctx.visibilityTrigger),
      rootMargin: getObserverRootMargin(ctx.detectionBoundary),
    }
  );

  observer.observe(instance.root);
  return observer;
};
