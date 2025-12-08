/**
 * Utility functions for the Parallax block
 *
 * @package Aggressive Apparel
 */

export {
  calculateBlur,
  calculateColorTransition,
  calculateMagneticForce,
  calculateParallaxMovement,
  calculateRotation,
  calculateScrollOpacity,
  calculateShadow,
  clamp,
  debounce,
  getCSSEasing,
  getEasingFunction,
  getElementStableId,
  mapProgressToEffectRange,
  prefersReducedMotion,
  throttle,
} from '../calculations';
export type { EffectMode } from '../calculations';
export {
  validateBlurEffect,
  validateColorTransitionEffect,
  validateDynamicShadowEffect,
  validateParallaxEffects,
  validateRotationEffect,
} from '../types';
export * from './boundary-utils';
export * from './error-handling';
export * from './intersection-utils';
export * from './observer-utils';
export * from './scroll-utils';
