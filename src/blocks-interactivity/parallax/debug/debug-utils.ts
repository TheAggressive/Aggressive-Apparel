/**
 * Debug utility functions for the Parallax block
 *
 * @package Aggressive Apparel
 */

import { DetectionBoundary } from '../types';

/**
 * Invert the value of a CSS variable (for rootMargin visualization)
 */
export const invertValue = (value: string): string => {
  const match = value?.match(/(-?\d+\.?\d*)(px|%)/);
  if (!match) {
    return value;
  }
  const num = parseInt(match[1], 10);
  const unit = match[2];
  return `${-num}${unit}`;
};

/**
 * Calculate accurate detection boundary rectangle in viewport coordinates
 */
export const calculateDetectionBoundary = (
  boundary: DetectionBoundary
): {
  top: string;
  right: string;
  bottom: string;
  left: string;
} => {
  return {
    top: invertValue(boundary.top || '0%'),
    right: invertValue(boundary.right || '0%'),
    bottom: invertValue(boundary.bottom || '0%'),
    left: invertValue(boundary.left || '0%'),
  };
};

/**
 * Position a container element to match the target element's position
 */
export const positionContainer = (
  container: HTMLElement,
  element: HTMLElement,
  entryHeight: { current: number }
): void => {
  const rect = element.getBoundingClientRect();
  const scroll = {
    top: window.pageYOffset || document.documentElement.scrollTop,
    left: window.pageXOffset || document.documentElement.scrollLeft,
  };
  const absoluteTop = rect.top + scroll.top;
  const absoluteLeft = rect.left + scroll.left;

  entryHeight.current = rect.height;

  // Only set dynamic positioning values (position: absolute is in CSS)
  container.style.top = `${absoluteTop}px`;
  container.style.left = `${absoluteLeft}px`;
  container.style.width = `${rect.width}px`;
  container.style.height = `${rect.height}px`;
};
