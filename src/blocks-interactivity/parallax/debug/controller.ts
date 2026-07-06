/**
 * Debug controller for the Parallax block.
 *
 * This module (plus the overlay/panel helpers it pulls in) is loaded via a
 * dynamic import ONLY when a block has Debug Mode enabled, so visitors
 * never download any of it in production.
 *
 * @package Aggressive Apparel
 */

import type { ParallaxInstance } from '../engine';
import type { ParallaxContext, ParallaxState } from '../types';
import {
  activeDebugBlocks,
  removeDebugOverlays,
  updateDebugContainerPosition,
  updateDebugPanel,
  updateDetectionBoundary,
  updateVisibilityTriggerLine,
  updateZoneVisualization,
} from './index';

export interface DebugController {
  onIntersection: (ratio: number, isIntersecting: boolean) => void;
  onFrame: (progress: number) => void;
  destroy: () => void;
}

const PERFORMANCE = {
  TARGET_FRAME_TIME: 16.67,
  MAX_FRAME_TIME: 33.33,
  FRAME_TIME_WINDOW: 60,
  LAG_THRESHOLD: 0.2,
  JITTER_THRESHOLD: 5,
  UPDATE_INTERVAL_MS: 200,
} as const;

/** Build the plain state object the overlay/panel helpers expect. */
const createDebugState = (
  ctx: ParallaxContext,
  ref: HTMLElement
): ParallaxState => ({
  isIntersecting: false,
  intersectionRatio: 0,
  mouseX: 0.5,
  mouseY: 0.5,
  hasInitialized: true,
  debugMode: true,
  elementRef: ref,
  previousRatio: 0,
  previousTop: ref.getBoundingClientRect().top,
  previousScrollY: window.scrollY,
  entryHeight: ref.offsetHeight,
  ctx,
  resizeTimeout: null,
  scrollDirection: 'down',
  velocity: 0,
  lastScrollTime: 0,
  performanceStatus: 'good',
  frameTimes: [],
  frameTimeIndex: 0,
  lastFrameTime: 0,
  lastPerformanceUpdate: 0,
  averageFrameTime: 0,
  lagCount: 0,
  jitterVariance: 0,
  debugElements: {},
});

const updatePerformanceStatus = (state: ParallaxState, now: number): void => {
  if (state.lastFrameTime > 0) {
    const frameTime = now - state.lastFrameTime;
    state.frameTimes[state.frameTimeIndex] = frameTime;
    state.frameTimeIndex =
      (state.frameTimeIndex + 1) % PERFORMANCE.FRAME_TIME_WINDOW;

    const bufferSize =
      state.frameTimes.length === PERFORMANCE.FRAME_TIME_WINDOW
        ? PERFORMANCE.FRAME_TIME_WINDOW
        : state.frameTimeIndex;

    if (bufferSize > 0) {
      let sum = 0;
      let lagCount = 0;
      for (let i = 0; i < bufferSize; i++) {
        sum += state.frameTimes[i];
        if (state.frameTimes[i] > PERFORMANCE.TARGET_FRAME_TIME) {
          lagCount++;
        }
      }
      state.averageFrameTime = sum / bufferSize;
      state.lagCount = lagCount;

      if (bufferSize >= 10) {
        let varianceSum = 0;
        for (let i = 0; i < bufferSize; i++) {
          const diff = state.frameTimes[i] - state.averageFrameTime;
          varianceSum += diff * diff;
        }
        state.jitterVariance = Math.sqrt(varianceSum / bufferSize);
      }

      const lagRatio = state.lagCount / bufferSize;
      const hasSevereLag = state.averageFrameTime > PERFORMANCE.MAX_FRAME_TIME;
      const hasHighLag = lagRatio > PERFORMANCE.LAG_THRESHOLD;
      const hasJitter = state.jitterVariance > PERFORMANCE.JITTER_THRESHOLD;

      if (hasSevereLag || (hasHighLag && hasJitter)) {
        state.performanceStatus = 'poor';
      } else if (hasHighLag) {
        state.performanceStatus = 'lag';
      } else if (hasJitter) {
        state.performanceStatus = 'jitter';
      } else {
        state.performanceStatus = 'good';
      }
    }
  }
  state.lastFrameTime = now;
};

/**
 * Wire the debug overlays, floating panel, and performance monitor for a
 * single parallax instance.
 */
export const createDebugController = (
  instance: ParallaxInstance
): DebugController => {
  const ctx = instance.ctx;
  const ref = instance.root;
  const state = createDebugState(ctx, ref);

  if (ctx.id) {
    activeDebugBlocks.add(ctx.id);
  }

  if (ctx.detectionBoundary) {
    updateDetectionBoundary(ctx.detectionBoundary);
  }
  updateDebugContainerPosition(state);

  let positionRafId: number | null = null;
  const updatePosition = (): void => {
    if (positionRafId !== null) {
      return;
    }
    positionRafId = requestAnimationFrame(() => {
      positionRafId = null;
      state.entryHeight = ref.offsetHeight;
      if (ctx.detectionBoundary) {
        updateDetectionBoundary(ctx.detectionBoundary);
      }
      updateDebugContainerPosition(state);
    });
  };

  window.addEventListener('scroll', updatePosition, { passive: true });
  window.addEventListener('resize', updatePosition, { passive: true });

  // Performance monitor: samples frame times while debug mode is active.
  let perfRafId: number | null = null;
  let lastPanelUpdate = 0;
  const perfTick = (now: number): void => {
    updatePerformanceStatus(state, now);
    if (now >= lastPanelUpdate + PERFORMANCE.UPDATE_INTERVAL_MS) {
      lastPanelUpdate = now;
      updateDebugPanel(state, state.isIntersecting);
    }
    perfRafId = requestAnimationFrame(perfTick);
  };
  perfRafId = requestAnimationFrame(perfTick);

  return {
    onIntersection: (ratio, isIntersecting) => {
      state.intersectionRatio = ratio;
      state.isIntersecting = isIntersecting;
      updateDebugPanel(state, isIntersecting);
      updateZoneVisualization(state, ratio, isIntersecting);
      updateVisibilityTriggerLine(state, ratio, isIntersecting);
    },
    onFrame: progress => {
      const scrollY = window.scrollY;
      state.scrollDirection = scrollY >= state.previousScrollY ? 'down' : 'up';
      state.previousScrollY = scrollY;
      state.previousRatio = progress;
    },
    destroy: () => {
      window.removeEventListener('scroll', updatePosition);
      window.removeEventListener('resize', updatePosition);
      if (positionRafId !== null) {
        cancelAnimationFrame(positionRafId);
      }
      if (perfRafId !== null) {
        cancelAnimationFrame(perfRafId);
      }
      if (ctx.id) {
        removeDebugOverlays(ctx.id);
      }
    },
  };
};
