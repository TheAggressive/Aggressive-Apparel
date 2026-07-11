/**
 * Debug adapter for the Parallax block.
 *
 * Thin wrapper over the shared scroll-debug controller (see
 * `blocks-interactivity/debug-shared/`). Loaded via dynamic import ONLY
 * when a block has Debug Mode enabled, so visitors never download any
 * of it in production.
 *
 * Accuracy: the shared controller probes with the block's EFFECTIVE
 * rootMargin — including the pre-activation buffer the engine adds in
 * `getObserverRootMargin()` — and draws both the configured boundary
 * and the buffered observer boundary when they differ.
 *
 * @package Aggressive Apparel
 */

import {
  createScrollDebugController,
  getEffectiveThreshold,
  getStrings,
} from '../../debug-shared';
import type { ParallaxInstance } from '../engine';
import { getObserverRootMargin, getVisibilityThreshold } from '../utils';

export interface DebugController {
  /** Fed by the production observer: pre-thresholded activation state. */
  onIntersection: (ratio: number, isIntersecting: boolean) => void;
  /** Fed by the frame engine: scroll progress 0..1. */
  onFrame: (progress: number) => void;
  destroy: () => void;
}

export const createDebugController = (
  instance: ParallaxInstance
): DebugController => {
  const ctx = instance.ctx;
  const strings = getStrings();
  const effectiveRootMargin = getObserverRootMargin(
    ctx.detectionBoundary,
    ctx.activationBuffer ?? 20,
    window.innerHeight
  );

  const controller = createScrollDebugController({
    id: ctx.id ?? `parallax_${Date.now()}`,
    namespace: 'parallax',
    title: strings.titleParallax,
    element: instance.root,
    configuredBoundary: ctx.detectionBoundary,
    effectiveRootMargin,
    // The observer stashes the threshold it actually runs with (incl.
    // the tall-element auto-cap) on the context; the fallback only
    // covers the debug module resolving before observeInstance() ran.
    threshold:
      ctx.effectiveThreshold ??
      getEffectiveThreshold(
        getVisibilityThreshold(ctx.visibilityTrigger),
        instance.root.offsetHeight,
        window.innerHeight,
        effectiveRootMargin
      ),
    trackProgress: true,
    engine: {
      label: strings.engineLabel,
      active: strings.engineActive,
      idle: strings.engineIdle,
    },
  });

  return {
    onIntersection: (_ratio, isIntersecting) =>
      controller.setEngineState(isIntersecting),
    onFrame: progress => controller.setProgress(progress),
    destroy: () => controller.destroy(),
  };
};
