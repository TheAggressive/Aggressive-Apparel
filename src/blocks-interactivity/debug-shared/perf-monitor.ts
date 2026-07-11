/**
 * Frame-time sampler for the shared scroll-debug tooling.
 *
 * Runs its own requestAnimationFrame loop while active and reports a
 * rolling health snapshot (status + average frame time + fps) at a fixed
 * interval, so panel DOM writes stay cheap.
 *
 * @package Aggressive Apparel
 */

import type { PerfSnapshot, PerformanceStatus } from './types';

export const PERFORMANCE = {
  /** 60fps budget. */
  TARGET_FRAME_TIME: 16.67,
  /** Below 30fps counts as severe lag. */
  MAX_FRAME_TIME: 33.33,
  /** Rolling window size (frames). */
  FRAME_TIME_WINDOW: 60,
  /** Fraction of over-budget frames that counts as lag. */
  LAG_THRESHOLD: 0.2,
  /** Frame-time standard deviation (ms) that counts as jitter. */
  JITTER_THRESHOLD: 5,
  /** How often the snapshot callback fires (ms). */
  UPDATE_INTERVAL_MS: 200,
} as const;

export interface PerfMonitor {
  stop: () => void;
}

/**
 * Start sampling frame times immediately; call `stop()` to cancel.
 */
export const createPerfMonitor = (
  onSnapshot: (snapshot: PerfSnapshot) => void
): PerfMonitor => {
  const frameTimes: number[] = [];
  let frameTimeIndex = 0;
  let lastFrameTime = 0;
  let lastReport = 0;
  let rafId: number | null = null;

  const tick = (now: number): void => {
    if (lastFrameTime > 0) {
      frameTimes[frameTimeIndex] = now - lastFrameTime;
      frameTimeIndex = (frameTimeIndex + 1) % PERFORMANCE.FRAME_TIME_WINDOW;

      const bufferSize =
        frameTimes.length === PERFORMANCE.FRAME_TIME_WINDOW
          ? PERFORMANCE.FRAME_TIME_WINDOW
          : frameTimeIndex;

      if (
        bufferSize >= 10 &&
        now >= lastReport + PERFORMANCE.UPDATE_INTERVAL_MS
      ) {
        lastReport = now;

        let sum = 0;
        let lagCount = 0;
        for (let i = 0; i < bufferSize; i++) {
          sum += frameTimes[i];
          if (frameTimes[i] > PERFORMANCE.TARGET_FRAME_TIME) {
            lagCount++;
          }
        }
        const average = sum / bufferSize;

        let varianceSum = 0;
        for (let i = 0; i < bufferSize; i++) {
          const diff = frameTimes[i] - average;
          varianceSum += diff * diff;
        }
        const jitter = Math.sqrt(varianceSum / bufferSize);

        const hasSevereLag = average > PERFORMANCE.MAX_FRAME_TIME;
        const hasHighLag = lagCount / bufferSize > PERFORMANCE.LAG_THRESHOLD;
        const hasJitter = jitter > PERFORMANCE.JITTER_THRESHOLD;

        let status: PerformanceStatus = 'good';
        if (hasSevereLag || (hasHighLag && hasJitter)) {
          status = 'poor';
        } else if (hasHighLag) {
          status = 'lag';
        } else if (hasJitter) {
          status = 'jitter';
        }

        onSnapshot({
          status,
          averageFrameTime: average,
          fps: average > 0 ? 1000 / average : 0,
        });
      }
    }
    lastFrameTime = now;
    rafId = requestAnimationFrame(tick);
  };

  rafId = requestAnimationFrame(tick);

  return {
    stop: () => {
      if (rafId !== null) {
        cancelAnimationFrame(rafId);
        rafId = null;
      }
    },
  };
};
