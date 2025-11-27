/**
 * Shared TypeScript types and interfaces for the Parallax block
 *
 * @package Aggressive Apparel
 */

/**
 * Settings for individual parallax elements
 */
export interface ElementSettings {
  speed: number;
  direction: 'up' | 'down' | 'both' | 'none';
  delay: number;
  easing: 'linear' | 'easeIn' | 'easeOut' | 'easeInOut';
}

/**
 * Context passed to the Interactivity API
 */
export interface ParallaxContext {
  // Core settings
  intensity: number;
  enableIntersectionObserver: boolean;
  intersectionThreshold: number;
  enableMouseInteraction: boolean;
  parallaxDirection: string;
  debugMode: boolean;

  // Runtime state
  isIntersecting: boolean;
  scrollProgress: number;
  mouseX: number;
  mouseY: number;
  hasInitialized: boolean;

  // Layer information (populated by JavaScript)
  layers: Record<string, any>;
}

/**
 * Block attributes type definition
 */
export interface ParallaxAttributes {
  intensity: number;
  enableIntersectionObserver: boolean;
  intersectionThreshold: number;
  enableMouseInteraction: boolean;
  parallaxDirection: 'up' | 'down' | 'both';
  debugMode: boolean;
}

/**
 * Layer data structure for parallax elements
 */
export interface ParallaxLayer {
  element: HTMLElement;
  initialY: number;
  speed: number;
  direction: ElementSettings['direction'];
  delay: number;
  easing: ElementSettings['easing'];
  isActive: boolean;
}
