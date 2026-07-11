/**
 * Configuration constants for the Parallax block
 *
 * @package Aggressive Apparel
 */

import { ParallaxContext } from './types';

type PartialParallaxContext = Partial<ParallaxContext>;

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
  depth: 0,
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
    activationBuffer: context.activationBuffer ?? 20,
    enableMouseInteraction: context.enableMouseInteraction ?? false,
    debugMode: context.debugMode ?? false,
    parallaxDirection: context.parallaxDirection ?? 'down',
    mouseInfluenceMultiplier: context.mouseInfluenceMultiplier ?? 0.5,
    maxMouseTranslation: context.maxMouseTranslation ?? 20,
    depthIntensityMultiplier: context.depthIntensityMultiplier ?? 50,
    transitionDuration: context.transitionDuration ?? 0.1,
    perspectiveDistance: context.perspectiveDistance ?? 1000,
    maxMouseRotation: context.maxMouseRotation ?? 5,
    depthOfField: context.depthOfField ?? false,
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
