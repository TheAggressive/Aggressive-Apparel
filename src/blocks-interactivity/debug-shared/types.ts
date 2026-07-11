/**
 * Shared types for the scroll-debug tooling used by the Parallax and
 * Animate On Scroll blocks.
 *
 * @package Aggressive Apparel
 */

/** rootMargin-style boundary, one CSS length/percentage per side. */
export interface DebugBoundary {
  top: string;
  right: string;
  bottom: string;
  left: string;
}

/**
 * Lifecycle phase of the observed element relative to its trigger:
 * - `waiting`     — outside the detection boundary
 * - `approaching` — inside the boundary but below the entry threshold
 * - `active`      — at or past the entry threshold
 */
export type IntersectionPhase = 'waiting' | 'approaching' | 'active';

export type PerformanceStatus = 'good' | 'lag' | 'jitter' | 'poor';

export interface PerfSnapshot {
  status: PerformanceStatus;
  /** Rolling average frame time in ms. */
  averageFrameTime: number;
  /** Rolling average frames per second. */
  fps: number;
}
