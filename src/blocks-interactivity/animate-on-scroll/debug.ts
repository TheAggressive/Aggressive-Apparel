/**
 * Debug adapter for the Animate On Scroll block.
 *
 * Thin wrapper over the shared scroll-debug controller (see
 * `blocks-interactivity/debug-shared/`). Loaded via dynamic import ONLY
 * when a block has Debug Mode enabled — production visitors never
 * download this module.
 *
 * The shared controller runs its own dense-threshold probe with the
 * same rootMargin as the production observer, so the live ratio/phase
 * in the panel is accurate between the block's sparse threshold events.
 *
 * @package Aggressive Apparel
 */

import {
  boundaryToRootMargin,
  createScrollDebugController,
  getVisibilityThreshold,
  type DebugBoundary,
} from '../debug-shared';

export type { DebugBoundary };

export interface DebugContextData {
  id: string;
  detectionBoundary: DebugBoundary;
  visibilityTrigger: number | string;
  reverseOnScrollBack?: boolean;
}

export interface AosDebugController {
  /**
   * Feed block-logic state after each production observer event.
   * `isVisible` is the store's post-logic visibility (ctx.isVisible).
   */
  onEntry: (
    ratio: number,
    isIntersecting: boolean,
    isVisible?: boolean
  ) => void;
  destroy: () => void;
}

export const createDebugController = (
  ctx: DebugContextData,
  ref: HTMLElement
): AosDebugController => {
  const threshold = getVisibilityThreshold(ctx.visibilityTrigger);

  const controller = createScrollDebugController({
    id: ctx.id,
    namespace: 'animate-on-scroll',
    title: 'Animate On Scroll Debug',
    element: ref,
    configuredBoundary: ctx.detectionBoundary,
    // AOS passes the configured boundary straight through to its
    // observer, so configured === effective here.
    effectiveRootMargin: boundaryToRootMargin(ctx.detectionBoundary),
    threshold,
    exitThreshold: ctx.reverseOnScrollBack ? threshold / 2 : undefined,
    engine: { label: 'Animation', active: 'Shown', idle: 'Hidden' },
    info: [
      {
        label: 'Reverse on scroll back',
        value: ctx.reverseOnScrollBack ? 'Yes' : 'No',
      },
    ],
  });

  return {
    onEntry: (_ratio, _isIntersecting, isVisible) => {
      if (typeof isVisible === 'boolean') {
        controller.setEngineState(isVisible);
      }
    },
    destroy: () => controller.destroy(),
  };
};
