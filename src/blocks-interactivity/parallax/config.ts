/**
 * Configuration constants for the Parallax block
 *
 * @package Aggressive Apparel
 */

import { ParallaxContext } from './types';

/**
 * Get parallax configuration with context-aware values
 */
export function getParallaxConfig(context?: Partial<ParallaxContext>) {
  return {
    // Performance settings (not user-configurable)
    SCROLL_THROTTLE_MS: 16, // ~60fps

    // Mouse interaction settings (user-configurable via block attributes)
    MOUSE_THRESHOLD: context?.mouseSensitivityThreshold ?? 0.001, // Minimum mouse movement to trigger updates

    // 3D perspective settings (user-configurable via block attributes)
    DEPTH_MULTIPLIER: context?.depthIntensityMultiplier ?? 50, // Multiplier for Z-depth calculation

    // Transition settings (user-configurable via block attributes)
    TRANSITION_DURATION: context?.transitionDuration ?? 0.1, // Default transition duration

    // Element selectors (not user-configurable)
    ELEMENT_SELECTORS: ['[data-parallax-enabled="true"]'] as const,

    // Constants
    CENTER_POINT: 0.5,
    MOUSE_INFLUENCE_MULTIPLIER: context?.mouseInfluenceMultiplier ?? 0.5,
  } as const;
}

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

/**
 * Default element settings for parallax (simplified)
 */
export const DEFAULT_ELEMENT_SETTINGS = {
  enabled: false, // Will be set to true by controls.tsx for blocks inside parallax containers
  speed: 1.0,
  direction: 'down' as const,
  delay: 0,
  easing: 'linear' as const,
} as const;
