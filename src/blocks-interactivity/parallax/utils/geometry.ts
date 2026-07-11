/**
 * Viewport geometry helpers for the Parallax block: detection-zone
 * progress plus the IntersectionObserver threshold/rootMargin inputs.
 *
 * @package Aggressive Apparel
 */

import { DetectionBoundary } from '../types';

/**
 * Layout-immune geometry snapshot for progress computation. Measured
 * rarely (init, resize, observer events); per-frame progress is then
 * pure arithmetic against scrollY — no offset-chain walks or boundary
 * string parsing inside the frame loop.
 */
export interface ProgressGeometry {
  /** Document-space top via the offsetTop chain (transform-immune). */
  docTop: number;
  elementHeight: number;
  boundaryTopPx: number;
  boundaryBottomPx: number;
}

const parseBoundaryValue = (
  value: string,
  viewportHeight: number,
  viewportWidth: number,
  dimension: 'height' | 'width' = 'height'
): number => {
  if (!value || value === '0%' || value === '0px') return 0;

  const match = value.match(/(-?\d+\.?\d*)(px|%)/);
  if (!match) return 0;

  const num = parseFloat(match[1]);
  if (match[2] === 'px') {
    return num;
  }
  // Percentages are relative to the viewport dimension.
  return (
    (num / 100) * (dimension === 'height' ? viewportHeight : viewportWidth)
  );
};

/**
 * Measure the geometry needed for progress. The only layout-reading
 * code on the progress path — call it sparingly.
 */
export const measureProgressGeometry = (
  element: HTMLElement,
  detectionBoundary: DetectionBoundary
): ProgressGeometry => {
  // Accumulate offsetTop up the tree for a transform-immune baseline.
  let docTop = 0;
  let el: HTMLElement | null = element;
  while (el) {
    docTop += el.offsetTop;
    el = el.offsetParent as HTMLElement;
  }

  const windowHeight = window.innerHeight;
  const windowWidth = window.innerWidth;

  return {
    docTop,
    elementHeight: element.offsetHeight,
    boundaryTopPx: parseBoundaryValue(
      detectionBoundary.top || '0%',
      windowHeight,
      windowWidth
    ),
    boundaryBottomPx: parseBoundaryValue(
      detectionBoundary.bottom || '0%',
      windowHeight,
      windowWidth
    ),
  };
};

/**
 * Progress within the Detection Boundary from cached geometry — pure
 * math, safe to run every frame. Returns 0 when the element enters the
 * zone, 1 when it exits the opposite side; aligns with visibilityTrigger
 * so progress is 0 at the trigger point.
 */
export const progressFromGeometry = (
  geom: ProgressGeometry,
  scrollY: number,
  windowHeight: number,
  visibilityTrigger: number = 0
): { progress: number; isWithinBoundary: boolean } => {
  const originalTop = geom.docTop - scrollY;

  // Animation starts when the element is [visibilityTrigger] visible, so
  // the start line sits higher in the viewport by this amount.
  const triggerOffset = geom.elementHeight * visibilityTrigger;
  const zoneBottom = windowHeight + geom.boundaryBottomPx - triggerOffset;
  const zoneTop = 0 - geom.boundaryTopPx;

  const totalTravel = zoneBottom - zoneTop + geom.elementHeight;
  const distanceCovered = zoneBottom - originalTop;

  let progress = 0;
  if (totalTravel > 0) {
    progress = distanceCovered / totalTravel;
  } else {
    progress = distanceCovered > 0 ? 1 : 0;
  }

  const isWithinBoundary = progress >= 0 && progress <= 1;
  progress = Math.max(0, Math.min(1, progress));

  return { progress, isWithinBoundary };
};

/**
 * Calculate progress within the Detection Boundary zone (measure +
 * compute in one call). Prefer the split functions on hot paths.
 */
export const calculateProgressWithinBoundary = (
  element: HTMLElement,
  detectionBoundary: DetectionBoundary,
  visibilityTrigger: number = 0
): { progress: number; isWithinBoundary: boolean } =>
  progressFromGeometry(
    measureProgressGeometry(element, detectionBoundary),
    window.scrollY,
    window.innerHeight,
    visibilityTrigger
  );

/*
 * Ratio/threshold parsing is shared with animate-on-scroll via
 * debug-shared/utils (pure, no debug UI). Re-exported so existing
 * `from './utils'` imports keep working — same pattern as nav-shared.
 * (The old local getVisibilityThreshold returned NaN for junk strings;
 * the shared one falls back to 0.3.)
 */
export {
  getValidIntersectionRatio,
  getVisibilityThreshold,
} from '../../debug-shared/utils';

/**
 * IntersectionObserver `rootMargin` with a pre-activation buffer so the
 * observer fires BEFORE the element reaches the logic boundary — letting
 * the engine start and settle at 0/1 rather than jumping.
 *
 * The buffer is the block's `activationBuffer` attribute: a percentage
 * of viewport height added above and below the configured boundary
 * (default 20, 0 disables — configured and effective boundaries then
 * match and the debug tooling draws a single box). Pixel-valued sides
 * receive the same buffer converted to px against `viewportHeight`.
 */
export const getObserverRootMargin = (
  detectionBoundary: DetectionBoundary,
  activationBuffer: number = 20,
  viewportHeight: number = typeof window !== 'undefined'
    ? window.innerHeight
    : 1000
): string => {
  const buffer = Math.max(0, activationBuffer);
  const bufferPx = Math.round((buffer / 100) * viewportHeight);

  const addBuffer = (value: string) => {
    if (!value) return `${buffer}%`;
    if (value.endsWith('%')) {
      return `${parseFloat(value) + buffer}%`;
    }
    if (value.endsWith('px')) {
      return `${parseFloat(value) + bufferPx}px`;
    }
    return value;
  };

  const top = addBuffer(detectionBoundary.top || '0%');
  const bottom = addBuffer(detectionBoundary.bottom || '0%');

  return `${top} ${detectionBoundary.right || '0%'} ${bottom} ${detectionBoundary.left || '0%'}`;
};
