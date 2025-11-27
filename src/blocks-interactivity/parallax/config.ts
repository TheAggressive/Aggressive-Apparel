/**
 * Configuration constants for the Parallax block
 *
 * @package Aggressive Apparel
 */

/**
 * Parallax system configuration
 */
export const PARALLAX_CONFIG = {
  // Performance settings
  MOBILE_BREAKPOINT: 768,
  SCROLL_THROTTLE_MS: 16, // ~60fps
  TRANSITION_DURATION: 0.1,

  // Default values
  DEFAULT_INTENSITY: 50,
  DEFAULT_SPEED: 1.0,
  DEFAULT_DELAY: 0,

  // Element selectors
  ELEMENT_SELECTORS: ['[data-parallax-enabled="true"]'] as const,
} as const;

/**
 * Default element settings for parallax
 */
export const DEFAULT_ELEMENT_SETTINGS = {
  enabled: false,
  speed: PARALLAX_CONFIG.DEFAULT_SPEED,
  direction: 'down' as const,
  delay: PARALLAX_CONFIG.DEFAULT_DELAY,
  easing: 'linear' as const,
} as const;

/**
 * Easing function implementations
 */
export const EASING_FUNCTIONS = {
  linear: (t: number) => t,
  easeIn: (t: number) => t * t,
  easeOut: (t: number) => t * (2 - t),
  easeInOut: (t: number) => (t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t),
} as const;

export type EasingType = keyof typeof EASING_FUNCTIONS;
