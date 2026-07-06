/**
 * Viewport geometry helpers for the Parallax block: detection-zone
 * progress plus the IntersectionObserver threshold/rootMargin inputs.
 *
 * @package Aggressive Apparel
 */

import { DetectionBoundary } from '../types';

/**
 * Calculate progress within the Detection Boundary zone.
 * Returns 0 when the element enters the zone, 1 when it exits the opposite
 * side. Uses layout position (offsetTop) so it is immune to the transforms
 * the engine applies, preventing feedback loops, and aligns with
 * visibilityTrigger so progress is 0 at the trigger point.
 */
export const calculateProgressWithinBoundary = (
  element: HTMLElement,
  detectionBoundary: DetectionBoundary,
  visibilityTrigger: number = 0
): { progress: number; isWithinBoundary: boolean } => {
  const windowHeight = window.innerHeight;

  // Accumulate offsetTop up the tree for a transform-immune baseline.
  let offsetTop = 0;
  let el: HTMLElement | null = element;
  while (el) {
    offsetTop += el.offsetTop;
    el = el.offsetParent as HTMLElement;
  }

  const originalTop = offsetTop - window.scrollY;
  const elementHeight = element.offsetHeight;

  const parseBoundaryValue = (
    value: string,
    dimension: 'height' | 'width'
  ): number => {
    if (!value || value === '0%' || value === '0px') return 0;

    const match = value.match(/(-?\d+\.?\d*)(px|%)/);
    if (!match) return 0;

    const num = parseFloat(match[1]);
    const unit = match[2];

    if (unit === 'px') {
      return num;
    }
    // Percentages are relative to the viewport dimension.
    const base = dimension === 'height' ? windowHeight : window.innerWidth;
    return (num / 100) * base;
  };

  const boundaryTop = parseBoundaryValue(
    detectionBoundary.top || '0%',
    'height'
  );
  const boundaryBottom = parseBoundaryValue(
    detectionBoundary.bottom || '0%',
    'height'
  );

  // Animation starts when the element is [visibilityTrigger] visible, so
  // the start line sits higher in the viewport by this amount.
  const triggerOffset = elementHeight * visibilityTrigger;
  const zoneBottom = windowHeight + boundaryBottom - triggerOffset;
  const zoneTop = 0 - boundaryTop;

  const totalTravel = zoneBottom - zoneTop + elementHeight;
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

/** Clamp a raw intersectionRatio into [0, 1], falling back on NaN. */
export const getValidIntersectionRatio = (
  ratio: number | undefined,
  fallback: number = 0
): number =>
  Math.max(
    0,
    Math.min(
      1,
      typeof ratio === 'number' && !isNaN(ratio)
        ? ratio
        : typeof fallback === 'number' && !isNaN(fallback)
          ? fallback
          : 0
    )
  );

/** Parse the visibility threshold from context (string or number). */
export const getVisibilityThreshold = (
  visibilityTrigger: string | number | undefined
): number => {
  if (typeof visibilityTrigger === 'string') {
    return parseFloat(visibilityTrigger);
  }
  if (typeof visibilityTrigger === 'number' && !isNaN(visibilityTrigger)) {
    return visibilityTrigger;
  }
  return 0.3;
};

/** IntersectionObserver `threshold` array from the visibility trigger. */
export const getObserverThreshold = (
  visibilityTrigger: number | string | undefined
): number[] => [getVisibilityThreshold(visibilityTrigger)];

/**
 * IntersectionObserver `rootMargin` with a buffer so the observer fires
 * BEFORE the element reaches the logic boundary — letting the engine start
 * and settle at 0/1 rather than jumping.
 */
export const getObserverRootMargin = (
  detectionBoundary: DetectionBoundary
): string => {
  const addBuffer = (value: string) => {
    if (!value) return '20%';
    if (value.endsWith('%')) {
      return `${parseFloat(value) + 20}%`;
    }
    if (value.endsWith('px')) {
      return `${parseFloat(value) + 200}px`;
    }
    return value;
  };

  const top = addBuffer(detectionBoundary.top || '0%');
  const bottom = addBuffer(detectionBoundary.bottom || '0%');

  return `${top} ${detectionBoundary.right || '0%'} ${bottom} ${detectionBoundary.left || '0%'}`;
};
