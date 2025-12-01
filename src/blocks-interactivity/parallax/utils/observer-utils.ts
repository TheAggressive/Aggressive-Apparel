/**
 * Observer utilities for the Parallax block
 *
 * @package Aggressive Apparel
 */

import { DetectionBoundary } from '../types';

/**
 * Get observer threshold array
 */
export const getObserverThreshold = (
  visibilityTrigger: number | string | undefined
): number[] => {
  const threshold =
    typeof visibilityTrigger === 'string'
      ? parseFloat(visibilityTrigger)
      : (visibilityTrigger ?? 0.3);
  return [threshold];
};

/**
 * Get observer root margin string with buffer
 * We add a buffer (20%) to top/bottom to ensure the observer triggers
 * BEFORE the element hits the logic boundary. This prevents "jumps"
 * by allowing the animation loop to start and settle at 0/1.
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
      return `${parseFloat(value) + 200}px`; // Approx 200px buffer for pixels
    }
    return value;
  };

  const top = addBuffer(detectionBoundary.top || '0%');
  const bottom = addBuffer(detectionBoundary.bottom || '0%');

  return `${top} ${detectionBoundary.right || '0%'} ${bottom} ${detectionBoundary.left || '0%'}`;
};
