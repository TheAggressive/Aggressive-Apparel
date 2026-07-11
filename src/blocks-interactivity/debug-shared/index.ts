/**
 * Shared scroll-debug tooling — public entry point.
 *
 * Used by the Parallax and Animate On Scroll debug adapters. This code
 * is only ever loaded through each block's dynamic `import()` when
 * Debug Mode is enabled, so it never ships to production visitors.
 *
 * @package Aggressive Apparel
 */

export {
  createScrollDebugController,
  rootMarginToBoundary,
  type EngineRowConfig,
  type ScrollDebugController,
  type ScrollDebugOptions,
} from './controller';

export type { DebugBoundary, IntersectionPhase, PerfSnapshot } from './types';

export {
  boundaryToRootMargin,
  getIntersectionPhase,
  getMaxReachableRatio,
  getRootBoxHeight,
  getValidIntersectionRatio,
  getVisibilityThreshold,
  invertValue,
  PHASE_LABELS,
} from './utils';
