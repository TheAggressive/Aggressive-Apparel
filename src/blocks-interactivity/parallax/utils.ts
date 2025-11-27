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
  direction: 'up' | 'down' | 'both' | 'none',
  mouseX?: number,
  mouseY?: number,
  enableMouseInteraction?: boolean
): { x: number; y: number } {
  const adjustedIntensity = intensity / 100;

  // Initialize movement values
  let scrollMovementY = 0;
  let mouseMovementY = 0;
  let mouseMovementX = 0;

  // Calculate scroll-based movement (primarily affects Y axis)
  switch (direction) {
    case 'up':
      scrollMovementY = -scrollProgress * 100 * adjustedIntensity * speed;
      break;
    case 'down':
      scrollMovementY = scrollProgress * 100 * adjustedIntensity * speed;
      break;
    case 'both': {
      const centeredProgress = (scrollProgress - 0.5) * 2;
      scrollMovementY = centeredProgress * 100 * adjustedIntensity * speed;
      break;
    }
    case 'none':
    default:
      scrollMovementY = 0;
  }

  // Add mouse interaction if enabled (affects both X and Y axes for 3D effect)
  if (enableMouseInteraction && mouseX !== undefined && mouseY !== undefined) {
    // Mouse influence: convert mouse position to movement offset
    // mouseX and mouseY are normalized 0-1, convert to -1 to 1 range
    const mouseOffsetX = (mouseX - 0.5) * 2; // -1 to 1
    const mouseOffsetY = (mouseY - 0.5) * 2; // -1 to 1

    // Apply mouse influence based on direction
    switch (direction) {
      case 'up':
      case 'down':
        // Mouse creates subtle 3D movement: horizontal mouse affects horizontal parallax
        mouseMovementX = mouseOffsetX * 30 * adjustedIntensity * speed;
        mouseMovementY = mouseOffsetY * 50 * adjustedIntensity * speed;
        break;
      case 'both':
        // Full 3D mouse parallax effect
        mouseMovementX = mouseOffsetX * 40 * adjustedIntensity * speed;
        mouseMovementY = mouseOffsetY * 40 * adjustedIntensity * speed;
        break;
      case 'none':
      default:
        mouseMovementX = 0;
        mouseMovementY = 0;
    }
  }

  // Combine scroll and mouse movement (mouse has less influence)
  return {
    x: mouseMovementX * 0.3, // Mouse X influence
    y: scrollMovementY + mouseMovementY * 0.3, // Scroll Y + Mouse Y influence
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
