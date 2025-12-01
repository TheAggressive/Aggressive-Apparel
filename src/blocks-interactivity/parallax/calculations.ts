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
  direction: string,
  mouseX: number,
  mouseY: number,
  enableMouseInteraction: boolean,
  options: {
    mouseInfluenceMultiplier: number;
    maxMouseTranslation: number;
    depthFactor: number;
  }
): { x: number; y: number } {
  // Scroll movement
  let scrollX = 0;
  let scrollY = 0;
  const movement = scrollProgress * intensity * speed;

  switch (direction) {
    case 'up':
      scrollY = -movement;
      break;
    case 'down':
      scrollY = movement;
      break;
    case 'left':
      scrollX = -movement;
      break;
    case 'right':
      scrollX = movement;
      break;
    case 'both':
      scrollX = movement * 0.5;
      scrollY = movement * 0.5;
      break;
  }

  // Mouse movement
  let mouseXTrans = 0;
  let mouseYTrans = 0;

  if (enableMouseInteraction) {
    const { mouseInfluenceMultiplier, maxMouseTranslation, depthFactor } =
      options;
    const mouseInfluence = mouseInfluenceMultiplier * intensity * depthFactor;

    mouseXTrans = (mouseX - 0.5) * mouseInfluence * speed;
    mouseYTrans = (mouseY - 0.5) * mouseInfluence * speed;

    // Clamp
    mouseXTrans = Math.max(
      -maxMouseTranslation,
      Math.min(maxMouseTranslation, mouseXTrans)
    );
    mouseYTrans = Math.max(
      -maxMouseTranslation,
      Math.min(maxMouseTranslation, mouseYTrans)
    );
  }

  return {
    x: scrollX + mouseXTrans,
    y: scrollY + mouseYTrans,
  };
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
/**
 * Debounces a function call.
 */
export function debounce<T extends Function>(func: T, wait: number): Function {
  let timeout: number;
  return (...args: any[]) => {
    clearTimeout(timeout);
    timeout = window.setTimeout(() => func(...args), wait);
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
      window.setTimeout(() => (inThrottle = false), limit);
    }
  };
}
