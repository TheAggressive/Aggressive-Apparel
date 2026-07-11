/**
 * Intersection Observer for the Parallax block.
 *
 * Its single job is activating/deactivating an instance in the shared
 * frame engine (plus feeding the optional debug controller). All motion
 * math lives in the engine.
 *
 * @package Aggressive Apparel
 */

import { getEffectiveThreshold } from '../debug-shared/utils';
import {
  refreshInstanceGeometry,
  setInstanceActive,
  type ParallaxInstance,
} from './engine';
import {
  getObserverRootMargin,
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

  const rootMargin = getObserverRootMargin(
    ctx.detectionBoundary,
    ctx.activationBuffer ?? 20,
    window.innerHeight
  );
  // Elements taller than the root box can never reach the configured
  // ratio — observe at the reachable effective threshold instead
  // (computed once at init from the element's initial height).
  const threshold = getEffectiveThreshold(
    getVisibilityThreshold(ctx.visibilityTrigger),
    instance.root.offsetHeight,
    window.innerHeight,
    rootMargin
  );
  // Stashed so the (async-loaded) debug adapter shows the exact value
  // the production observer runs with — no re-derivation drift.
  ctx.effectiveThreshold = threshold;

  const observer = new IntersectionObserver(
    entries => {
      entries.forEach(entry => {
        // Free layout-true rect: keep the engine's cached geometry honest.
        refreshInstanceGeometry(instance, entry.boundingClientRect);

        const ratio = getValidIntersectionRatio(entry.intersectionRatio, 0);
        const isIntersecting = entry.isIntersecting && ratio >= threshold;

        ctx.intersectionRatio = ratio;
        ctx.isIntersecting = isIntersecting;

        setInstanceActive(instance, isIntersecting);
        onDebugEntry?.(ratio, isIntersecting);
      });
    },
    { threshold: [threshold], rootMargin }
  );

  observer.observe(instance.root);
  return observer;
};
