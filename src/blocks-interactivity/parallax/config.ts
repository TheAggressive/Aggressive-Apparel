/**
 * Configuration constants for the Parallax block
 *
 * @package Aggressive Apparel
 */

import { ParallaxContext } from './types';

type PartialParallaxContext = Partial<ParallaxContext>;

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

    PERFORMANCE: {
      TARGET_FRAME_TIME: 16.67,
      MAX_FRAME_TIME: 33.33,
      FRAME_TIME_WINDOW: 60,
      LAG_THRESHOLD: 0.2,
      JITTER_THRESHOLD: 5,
      UPDATE_INTERVAL_MS: 200,
      ENABLED_BY_DEBUG: true,
    },

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

/**
 * Apply defaults to a parallax context in one place for consistent typing.
 */
export function applyParallaxDefaults(
  context: PartialParallaxContext
): ParallaxContext {
  return {
    id: context.id,
    intensity: context.intensity ?? 50,
    visibilityTrigger: context.visibilityTrigger ?? 0.3,
    detectionBoundary: context.detectionBoundary ?? {
      top: '0%',
      right: '0%',
      bottom: '0%',
      left: '0%',
    },
    enableMouseInteraction: context.enableMouseInteraction ?? false,
    debugMode: context.debugMode ?? false,
    parallaxDirection: context.parallaxDirection ?? 'down',
    mouseInfluenceMultiplier: context.mouseInfluenceMultiplier ?? 0.5,
    maxMouseTranslation: context.maxMouseTranslation ?? 20,
    mouseSensitivityThreshold: context.mouseSensitivityThreshold ?? 0.001,
    depthIntensityMultiplier: context.depthIntensityMultiplier ?? 50,
    transitionDuration: context.transitionDuration ?? 0.1,
    perspectiveDistance: context.perspectiveDistance ?? 1000,
    maxMouseRotation: context.maxMouseRotation ?? 5,
    parallaxDepth: context.parallaxDepth ?? 1.0,
    // Runtime state
    isIntersecting: context.isIntersecting ?? false,
    intersectionRatio: context.intersectionRatio ?? 0,
    hasInitialized: context.hasInitialized ?? false,
    scrollProgress: context.scrollProgress ?? 0,
    mouseX: context.mouseX ?? 0.5,
    mouseY: context.mouseY ?? 0.5,
    previousProgress: context.previousProgress ?? 0,
  };
}

export function shouldMonitorPerformance(ctx: ParallaxContext): boolean {
  return Boolean(ctx.debugMode);
}
