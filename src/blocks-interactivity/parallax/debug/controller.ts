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

import { createScrollDebugController } from '../../debug-shared';
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

  const controller = createScrollDebugController({
    id: ctx.id ?? `parallax_${Date.now()}`,
    namespace: 'parallax',
    title: 'Parallax Debug',
    element: instance.root,
    configuredBoundary: ctx.detectionBoundary,
    effectiveRootMargin: getObserverRootMargin(ctx.detectionBoundary),
    threshold: getVisibilityThreshold(ctx.visibilityTrigger),
    trackProgress: true,
    engine: { label: 'Engine', active: 'Active', idle: 'Idle' },
  });

  return {
    onIntersection: (_ratio, isIntersecting) =>
      controller.setEngineState(isIntersecting),
    onFrame: progress => controller.setProgress(progress),
    destroy: () => controller.destroy(),
  };
};
