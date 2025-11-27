/**
 * Utility functions for parallax calculations and operations
 *
 * @package Aggressive Apparel
 */

import { EASING_FUNCTIONS, EasingType } from './config';

/**
 * Clamps a number between a minimum and maximum value.
 */
export function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), max);
}

/**
 * Checks if the user prefers reduced motion.
 */
export function prefersReducedMotion(): boolean {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Gets the easing function for the specified type.
 */
export function getEasingFunction(easing: EasingType) {
  return EASING_FUNCTIONS[easing] || EASING_FUNCTIONS.linear;
}

/**
 * Calculates parallax movement based on scroll progress and settings.
 */
export function calculateParallaxMovement(
  scrollProgress: number,
  intensity: number,
  speed: number,
  direction: 'up' | 'down' | 'both' | 'none'
): number {
  const adjustedIntensity = intensity / 100;

  switch (direction) {
    case 'up':
      return -scrollProgress * 100 * adjustedIntensity * speed;
    case 'down':
      return scrollProgress * 100 * adjustedIntensity * speed;
    case 'both': {
      const centeredProgress = (scrollProgress - 0.5) * 2;
      return centeredProgress * 100 * adjustedIntensity * speed;
    }
    case 'none':
    default:
      return 0;
  }
}

/**
 * Converts easing name to CSS easing function.
 */
export function getCSSEasing(easing: string): string {
  switch (easing) {
    case 'easeIn':
      return 'ease-in';
    case 'easeOut':
      return 'ease-out';
    case 'easeInOut':
      return 'ease-in-out';
    case 'linear':
    default:
      return 'linear';
  }
}

/**
 * Generates a stable identifier for an element.
 */
export function getElementStableId(
  element: HTMLElement,
  index: number
): string {
  // Try to use data attributes or element position as stable identifier
  const dataId = element.getAttribute('data-layer-id');
  if (dataId) return dataId;

  // Fallback to type + position
  const blockType =
    element.className
      .split(' ')
      .find(c => c.startsWith('wp-block-'))
      ?.replace('wp-block-', '') || 'unknown';

  return `${blockType}-${index}`;
}

/**
 * Debounces a function call.
 */
export function debounce<T extends Function>(func: T, wait: number): Function {
  let timeout: number;
  return (...args: any[]) => {
    clearTimeout(timeout);
    timeout = setTimeout(() => func(...args), wait);
  };
}

/**
 * Throttles a function call.
 */
export function throttle<T extends Function>(func: T, limit: number): Function {
  let inThrottle: boolean;
  return (...args: any[]) => {
    if (!inThrottle) {
      func(...args);
      inThrottle = true;
      setTimeout(() => (inThrottle = false), limit);
    }
  };
}
