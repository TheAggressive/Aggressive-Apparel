/**
 * Shared TypeScript types and interfaces for the Parallax block
 *
 * @package Aggressive Apparel
 */

// =============================================================================
// BASIC TYPE DEFINITIONS
// =============================================================================

/**
 * Direction options for parallax movement
 */
export type ParallaxDirection = 'up' | 'down' | 'both' | 'none';

/**
 * Easing function types
 */
export type EasingType = 'linear' | 'easeIn' | 'easeOut' | 'easeInOut';

/**
 * Zoom effect types
 */
export type ZoomType = 'in' | 'out';

// =============================================================================
// CONFIGURATION TYPES
// =============================================================================

/**
 * Settings for individual parallax elements with validation
 */
export interface ElementSettings {
  speed: number;
  direction: ParallaxDirection;
  delay: number;
  easing: EasingType;
}

/**
 * Default element settings with proper typing
 */
export interface DefaultElementSettings extends ElementSettings {
  enabled: boolean;
}

/**
 * Effects configuration for parallax elements with strict typing
 */
export interface ParallaxEffects {
  zoom?: {
    enabled: boolean;
    type: ZoomType;
    intensity: number;
  };
  depthLevel?: {
    value: number;
  };
  zIndex?: {
    value: number;
  };
  scrollOpacity?: {
    enabled: boolean;
    startOpacity: number; // 0-1
    endOpacity: number; // 0-1
    fadeRange: 'top' | 'middle' | 'bottom' | 'full';
    effectStart?: number; // 0-1, when effect animation starts
    effectEnd?: number; // 0-1, when effect animation completes
    effectMode?: 'sustain' | 'peek' | 'reverse'; // Animation behavior (default: peek)
  };
  magneticMouse?: {
    enabled: boolean;
    strength: number; // 0-100
    range: number; // px distance
    elastic: boolean;
    mode: 'attract' | 'repel';
  };
  blur?: {
    enabled: boolean;
    startBlur: number; // CSS blur value (px)
    endBlur: number; // CSS blur value (px)
    fadeRange: 'top' | 'middle' | 'bottom' | 'full';
    effectStart?: number; // 0-1, when effect animation starts
    effectEnd?: number; // 0-1, when effect animation completes
    effectMode?: 'sustain' | 'peek' | 'reverse'; // Animation behavior (default: peek)
  };
  colorTransition?: {
    enabled: boolean;
    startColor: string; // Hex, RGB, or HSL color
    endColor: string; // Hex, RGB, or HSL color
    transitionType: 'background' | 'text' | 'border';
    effectStart?: number; // 0-1, when effect animation starts
    effectEnd?: number; // 0-1, when effect animation completes
    effectMode?: 'sustain' | 'peek' | 'reverse'; // Animation behavior (default: peek)
  };
  dynamicShadow?: {
    enabled: boolean;
    startShadow: string; // CSS box-shadow value
    endShadow: string; // CSS box-shadow value
    shadowType: 'box-shadow' | 'text-shadow' | 'drop-shadow';
    effectStart?: number; // 0-1, when effect animation starts
    effectEnd?: number; // 0-1, when effect animation completes
    effectMode?: 'sustain' | 'peek' | 'reverse'; // Animation behavior (default: peek)
  };
  rotation?: {
    enabled: boolean;
    startRotation: number; // degrees
    endRotation: number; // degrees
    axis: 'x' | 'y' | 'z' | 'all';
    speed: number; // multiplier for rotation speed (0.1 to 3.0)
    mode: 'range' | 'continuous' | 'looping'; // how rotation behaves
    effectStart?: number; // 0-1, when effect animation starts
    effectEnd?: number; // 0-1, when effect animation completes
    effectMode?: 'sustain' | 'peek' | 'reverse'; // Animation behavior (default: peek)
  };
}

/**
 * Detection boundary definition for advanced trigger control
 */
export interface DetectionBoundary {
  top: string;
  right: string;
  bottom: string;
  left: string;
}

/**
 * Block attributes with advanced detection boundary system
 */
export interface ParallaxAttributes {
  // Core settings (matching animate-on-scroll simplicity)
  intensity: number; // 0-200
  visibilityTrigger: number; // Changed to number to match animate-on-scroll
  detectionBoundary: DetectionBoundary; // Advanced boundary control
  enableMouseInteraction: boolean;
  debugMode: boolean;

  // New properties for enhanced control
  parallaxDirection?: 'up' | 'down' | 'both';
  mouseInfluenceMultiplier?: number;
  maxMouseTranslation?: number;
  mouseSensitivityThreshold?: number;
  depthIntensityMultiplier?: number;
  transitionDuration?: number;
  perspectiveDistance?: number;
  maxMouseRotation?: number;
  parallaxDepth?: number;
}

/**
 * Validated block attributes with runtime checks
 */
export interface ValidatedParallaxAttributes extends ParallaxAttributes {
  intensity: number & { readonly __brand: 'ValidatedIntensity' };
  visibilityTrigger: number & {
    readonly __brand: 'ValidatedVisibilityTrigger';
  };
  detectionBoundary: DetectionBoundary & {
    readonly __brand: 'ValidatedBoundary';
  };
}

// =============================================================================
// RUNTIME TYPES
// =============================================================================

/**
 * Context passed to the Interactivity API with enhanced typing
 */
export interface ParallaxContext {
  // Core validated settings (matching animate-on-scroll)
  intensity: number;
  visibilityTrigger: number; // Changed from threshold to match animate-on-scroll
  detectionBoundary: DetectionBoundary;
  enableMouseInteraction: boolean;
  debugMode: boolean;

  // Runtime state (matching animate-on-scroll)
  isIntersecting: boolean;
  intersectionRatio: number;
  hasInitialized?: boolean;
  previousProgress?: number; // Track previous progress for sticky effects

  // New properties for enhanced control
  parallaxDirection?: 'up' | 'down' | 'both';
  mouseInfluenceMultiplier?: number;
  maxMouseTranslation?: number;
  mouseSensitivityThreshold?: number;
  depthIntensityMultiplier?: number;
  transitionDuration?: number;
  perspectiveDistance?: number;
  maxMouseRotation?: number;
  parallaxDepth?: number;

  // Runtime state properties
  scrollProgress?: number;
  mouseX?: number;
  mouseY?: number;
  layers?: Record<string, ParallaxLayer>;
  id?: string;
}

/**
 * Layer data structure with enhanced type safety
 */
export interface ParallaxLayer {
  element: HTMLElement;
  initialY: number;
  speed: number;
  direction: ParallaxDirection;
  delay: number;
  easing: EasingType;
  effects?: ParallaxEffects;
  isActive: boolean;
  layerId: string; // Add explicit layer ID for tracking
}

/**
 * Error types for better error classification
 */
export type ParallaxErrorType =
  | 'configuration_error'
  | 'runtime_error'
  | 'validation_error'
  | 'performance_error';

/**
 * Enhanced error interface for parallax-specific errors
 */
export interface ParallaxError extends Error {
  type: ParallaxErrorType;
  code: string;
  context?: Record<string, any>;
  recoverable: boolean;
}

/**
 * Validation result interface
 */
export interface ValidationResult {
  isValid: boolean;
  errors: string[];
  warnings: string[];
}

// =============================================================================
// UTILITY TYPES
// =============================================================================

/**
 * Movement calculation result
 */
export interface MovementResult {
  x: number;
  y: number;
}

/**
 * Manager interface for consistent manager implementations
 */
export interface ParallaxManager {
  start(): void;
  stop(): void;
  destroy(): void;
}

// =============================================================================
// TYPE GUARDS & VALIDATION
// =============================================================================

/**
 * Type guard for ParallaxDirection
 */
export function isParallaxDirection(value: any): value is ParallaxDirection {
  return ['up', 'down', 'both', 'none'].includes(value);
}

/**
 * Type guard for EasingType
 */
export function isEasingType(value: any): value is EasingType {
  return ['linear', 'easeIn', 'easeOut', 'easeInOut'].includes(value);
}

/**
 * Type guard for ZoomType
 */
export function isZoomType(value: any): value is ZoomType {
  return ['in', 'out'].includes(value);
}

/**
 * Validation function for ParallaxAttributes
 */
export function validateParallaxAttributes(attrs: any): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  // Validate intensity
  if (
    typeof attrs.intensity !== 'number' ||
    attrs.intensity < 0 ||
    attrs.intensity > 200
  ) {
    errors.push('Intensity must be a number between 0 and 200');
  }

  // Validate detection boundary
  if (
    typeof attrs.detectionBoundary !== 'object' ||
    attrs.detectionBoundary === null
  ) {
    errors.push('Detection boundary must be an object');
  } else {
    const requiredKeys = ['top', 'right', 'bottom', 'left'];
    for (const key of requiredKeys) {
      if (!(key in attrs.detectionBoundary)) {
        errors.push(`Detection boundary must include ${key} property`);
      } else if (typeof attrs.detectionBoundary[key] !== 'string') {
        errors.push(`Detection boundary ${key} must be a string`);
      }
    }
  }

  // Validate direction
  if (!isParallaxDirection(attrs.parallaxDirection)) {
    errors.push('Invalid parallax direction');
  }

  // Validate boolean fields
  const booleanFields = [
    'enableIntersectionObserver',
    'enableMouseInteraction',
    'debugMode',
  ];
  booleanFields.forEach(field => {
    if (typeof attrs[field] !== 'boolean') {
      errors.push(`${field} must be a boolean`);
    }
  });

  return {
    isValid: errors.length === 0,
    errors,
    warnings,
  };
}

/**
 * Validate blur effect configuration
 */
export function validateBlurEffect(config: any): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  if (
    typeof config.startBlur !== 'number' ||
    config.startBlur < 0 ||
    config.startBlur > 50
  ) {
    errors.push('Start blur must be a number between 0 and 50');
  }

  if (
    typeof config.endBlur !== 'number' ||
    config.endBlur < 0 ||
    config.endBlur > 50
  ) {
    errors.push('End blur must be a number between 0 and 50');
  }

  if (!['top', 'middle', 'bottom', 'full'].includes(config.fadeRange)) {
    errors.push('Invalid fade range');
  }

  if (config.startBlur === config.endBlur) {
    warnings.push(
      'Start and end blur values are the same - no transition will occur'
    );
  }

  return { isValid: errors.length === 0, errors, warnings };
}

/**
 * Validate color transition effect configuration
 */
export function validateColorTransitionEffect(config: any): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  // Basic color validation (hex, rgb, rgba, hsl, hsla)
  const colorRegex =
    /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$|^rgb\((\d{1,3}),\s*(\d{1,3}),\s*(\d{1,3})\)$|^rgba\((\d{1,3}),\s*(\d{1,3}),\s*(\d{1,3}),\s*(0|1|0?\.\d+)\)$|^hsl\((\d{1,3}),\s*(\d{1,3})%,\s*(\d{1,3})%\)$|^hsla\((\d{1,3}),\s*(\d{1,3})%,\s*(\d{1,3})%,\s*(0|1|0?\.\d+)\)$/;

  if (!colorRegex.test(config.startColor)) {
    errors.push('Invalid start color format');
  }

  if (!colorRegex.test(config.endColor)) {
    errors.push('Invalid end color format');
  }

  if (!['background', 'text', 'border'].includes(config.transitionType)) {
    errors.push('Invalid transition type');
  }

  if (config.startColor === config.endColor) {
    warnings.push(
      'Start and end colors are the same - no transition will occur'
    );
  }

  return { isValid: errors.length === 0, errors, warnings };
}

/**
 * Validate dynamic shadow effect configuration
 */
export function validateDynamicShadowEffect(config: any): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  if (
    ['box-shadow', 'text-shadow', 'drop-shadow'].indexOf(config.shadowType) ===
    -1
  ) {
    errors.push('Invalid shadow type');
  }

  // Basic CSS shadow value validation
  const shadowRegex = /^(-?\d+px\s+){3,4}(rgba?\([^)]+\)|#[0-9a-fA-F]{3,8})$/;
  if (config.startShadow && !shadowRegex.test(config.startShadow.trim())) {
    warnings.push('Start shadow may not be a valid CSS shadow value');
  }

  if (config.endShadow && !shadowRegex.test(config.endShadow.trim())) {
    warnings.push('End shadow may not be a valid CSS shadow value');
  }

  return { isValid: errors.length === 0, errors, warnings };
}

/**
 * Validate rotation effect configuration
 */
export function validateRotationEffect(config: any): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  if (
    typeof config.startRotation !== 'number' ||
    config.startRotation < -360 ||
    config.startRotation > 360
  ) {
    errors.push('Start rotation must be a number between -360 and 360');
  }

  if (
    typeof config.endRotation !== 'number' ||
    config.endRotation < -360 ||
    config.endRotation > 360
  ) {
    errors.push('End rotation must be a number between -360 and 360');
  }

  if (!['x', 'y', 'z', 'all'].includes(config.axis)) {
    errors.push('Invalid rotation axis');
  }

  if (config.startRotation === config.endRotation) {
    warnings.push(
      'Start and end rotation values are the same - no rotation will occur'
    );
  }

  return { isValid: errors.length === 0, errors, warnings };
}

/**
 * Enhanced effect validation that includes all new effects
 */
export function validateParallaxEffects(effects: any): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  // Validate each enabled effect
  if (effects.blur?.enabled) {
    const result = validateBlurEffect(effects.blur);
    errors.push(...result.errors);
    warnings.push(...result.warnings);
  }

  if (effects.colorTransition?.enabled) {
    const result = validateColorTransitionEffect(effects.colorTransition);
    errors.push(...result.errors);
    warnings.push(...result.warnings);
  }

  if (effects.dynamicShadow?.enabled) {
    const result = validateDynamicShadowEffect(effects.dynamicShadow);
    errors.push(...result.errors);
    warnings.push(...result.warnings);
  }

  if (effects.rotation?.enabled) {
    const result = validateRotationEffect(effects.rotation);
    errors.push(...result.errors);
    warnings.push(...result.warnings);
  }

  // Check for conflicting effects
  if (
    effects.blur?.enabled &&
    effects.dynamicShadow?.enabled &&
    effects.dynamicShadow.shadowType === 'drop-shadow'
  ) {
    warnings.push(
      'Using blur effect with drop-shadow may cause visual conflicts'
    );
  }

  return {
    isValid: errors.length === 0,
    errors,
    warnings,
  };
}

// =============================================================================
// STORE TYPES
// =============================================================================

/**
 * Global state interface for the Parallax store
 */
export interface ParallaxState {
  // Runtime state
  isIntersecting: boolean;
  intersectionRatio: number;
  mouseX: number;
  mouseY: number;
  hasInitialized: boolean;
  debugMode: boolean;

  // Debug state
  elementRef: HTMLElement | null;
  previousRatio: number;
  previousTop: number;
  previousScrollY: number;
  entryHeight: number;
  ctx: ParallaxContext;
  resizeTimeout: number | null;
  scrollDirection: 'up' | 'down';
  velocity: number;
  lastScrollTime: number;

  // Performance tracking
  performanceStatus: 'good' | 'lag' | 'jitter' | 'poor';
  frameTimes: number[];
  frameTimeIndex: number;
  lastFrameTime: number;
  lastPerformanceUpdate: number;
  averageFrameTime: number;
  lagCount: number;
  jitterVariance: number;

  // Debug element cache
  debugElements: Record<string, HTMLElement | null>;
}

/**
 * Actions interface for the Parallax store
 */
export interface ParallaxActions {
  updateDetectionBoundary: () => void;
  updateInfoPanel: (isIntersecting?: boolean) => void;
  updateZoneVisualization: (
    intersectionRatio?: number,
    isIntersecting?: boolean
  ) => void;
  updateVisibilityTriggerLine: (
    intersectionRatio?: number,
    isIntersecting?: boolean
  ) => void;
  updateDebugContainerPosition: () => void;
  removeDebugOverlays: () => void;
  updatePerformance: () => void;
  debug: () => void;
}
