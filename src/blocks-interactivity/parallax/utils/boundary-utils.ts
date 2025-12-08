/**
 * Boundary calculation utilities for the Parallax block
 *
 * @package Aggressive Apparel
 */

import { DetectionBoundary } from '../types';

/**
 * Calculate progress within the Detection Boundary zone
 * Returns 0 when element enters the zone, 1 when it exits the opposite side
 * Uses stateless viewport coordinates for precise start/end points
 * CORRECTS for current transforms to prevent feedback loops
 * ALIGNS with visibilityTrigger to ensure 0 progress at trigger point
 */
export const calculateProgressWithinBoundary = (
  element: HTMLElement,
  detectionBoundary: DetectionBoundary,
  visibilityTrigger: number = 0
): { progress: number; isWithinBoundary: boolean } => {
  const windowHeight = window.innerHeight;

  // Calculate original position using Layout Position (offsetTop)
  // This is immune to transforms (Translate, Scale, Rotate) and provides a stable baseline
  let offsetTop = 0;
  let el: HTMLElement | null = element;

  // Accumulate offsetTop up the tree to find total distance from document top
  while (el) {
    offsetTop += el.offsetTop;
    el = el.offsetParent as HTMLElement;
  }

  // Convert document offset to viewport offset
  const originalTop = offsetTop - window.scrollY;

  // Use offsetHeight for stable height (unaffected by scale/zoom)
  const elementHeight = element.offsetHeight;

  // Parse boundary values (they're in CSS format like "100px" or "50%")
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
    } else if (unit === '%') {
      // For percentages, use VIEWPORT dimension (not element dimension)
      // This is the correct interpretation: "-50%" means 50% of viewport below the screen
      const base = dimension === 'height' ? windowHeight : window.innerWidth;
      return (num / 100) * base;
    }
    return 0;
  };

  // Calculate boundary offsets
  const boundaryTop = parseBoundaryValue(
    detectionBoundary.top || '0%',
    'height'
  );
  const boundaryBottom = parseBoundaryValue(
    detectionBoundary.bottom || '0%',
    'height'
  );

  // Calculate Trigger Offset
  // The animation should start when the element is [visibilityTrigger] visible
  // This means the "Start Line" is higher up the viewport by this amount
  const triggerOffset = elementHeight * visibilityTrigger;

  // Calculate Zone Lines (Viewport Coordinates)
  // Entry Line: The bottom of the detection zone, adjusted for trigger.
  // When element top crosses this line (moving up), progress starts.
  // zoneBottom = windowHeight + boundaryBottom - triggerOffset
  const zoneBottom = windowHeight + boundaryBottom - triggerOffset;

  // Exit Line: The top of the detection zone.
  // When element bottom crosses this line (moving up), progress ends.
  // Start: originalTop == zoneBottom (Progress 0)
  // End: (originalTop + elementHeight) == zoneTop (Progress 1)
  // zoneTop is the top boundary of the zone.
  const zoneTop = 0 - boundaryTop;

  // Calculate Progress
  // Total travel distance required for the element (top edge) to go from
  // zoneBottom to (zoneTop - elementHeight)
  const totalTravel = zoneBottom - zoneTop + elementHeight;

  // Current distance covered (how far originalTop has moved past zoneBottom)
  const distanceCovered = zoneBottom - originalTop;

  let progress = 0;
  if (totalTravel > 0) {
    progress = distanceCovered / totalTravel;
  } else {
    progress = distanceCovered > 0 ? 1 : 0;
  }

  // Check if strictly within boundary for status
  const isWithinBoundary = progress >= 0 && progress <= 1;

  // Clamp progress for animation usage
  progress = Math.max(0, Math.min(1, progress));

  return { progress, isWithinBoundary };
};

/**
 * Calculate "sticky" progress that maintains final state once reached
 * Effects will stay at their maximum intensity until element leaves the zone
 */
export const calculateStickyProgress = (
  element: HTMLElement,
  detectionBoundary: DetectionBoundary,
  visibilityTrigger: number = 0,
  previousProgress: number = 0
): { progress: number; isWithinBoundary: boolean; hasReachedMax: boolean } => {
  const { progress, isWithinBoundary } = calculateProgressWithinBoundary(
    element,
    detectionBoundary,
    visibilityTrigger
  );

  // If we've previously reached the maximum progress and are still within boundary,
  // maintain the maximum progress to create "sticky" effects
  const hasReachedMax = previousProgress >= 1.0;
  const stickyProgress = hasReachedMax && isWithinBoundary ? 1.0 : progress;

  return { progress: stickyProgress, isWithinBoundary, hasReachedMax };
};
