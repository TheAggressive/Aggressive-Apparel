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
 * Per-block parallax settings stored on `aggressiveApparelParallax`.
 */
export interface ElementParallaxSettings extends DefaultElementSettings {
  effects?: ParallaxEffects;
}

/** Block attributes that may include nested parallax element settings. */
export interface BlockAttributesWithParallax {
  aggressiveApparelParallax?: ElementParallaxSettings;
  [key: string]: unknown;
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
  velocityBlur?: {
    enabled?: boolean;
    maxBlur?: number;
    sensitivity?: number;
    direction?: 'vertical' | 'horizontal' | string;
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
  context?: Record<string, unknown>;
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
export function isParallaxDirection(
  value: unknown
): value is ParallaxDirection {
  return (
    typeof value === 'string' &&
    (['up', 'down', 'both', 'none'] as const).includes(
      value as ParallaxDirection
    )
  );
}

/**
 * Type guard for EasingType
 */
export function isEasingType(value: unknown): value is EasingType {
  return (
    typeof value === 'string' &&
    (['linear', 'easeIn', 'easeOut', 'easeInOut'] as const).includes(
      value as EasingType
    )
  );
}

/**
 * Type guard for ZoomType
 */
export function isZoomType(value: unknown): value is ZoomType {
  return (
    typeof value === 'string' &&
    (['in', 'out'] as const).includes(value as ZoomType)
  );
}

/**
 * Validation function for ParallaxAttributes
 */
export function validateParallaxAttributes(attrs: unknown): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  if (typeof attrs !== 'object' || attrs === null) {
    return {
      isValid: false,
      errors: ['Attributes must be an object'],
      warnings: [],
    };
  }

  const values = attrs as Record<string, unknown>;

  // Validate intensity
  if (
    typeof values.intensity !== 'number' ||
    values.intensity < 0 ||
    values.intensity > 200
  ) {
    errors.push('Intensity must be a number between 0 and 200');
  }

  // Validate detection boundary
  const boundary = values.detectionBoundary;
  if (typeof boundary !== 'object' || boundary === null) {
    errors.push('Detection boundary must be an object');
  } else {
    const boundaryRecord = boundary as Record<string, unknown>;
    const requiredKeys = ['top', 'right', 'bottom', 'left'];
    for (const key of requiredKeys) {
      if (!(key in boundaryRecord)) {
        errors.push(`Detection boundary must include ${key} property`);
      } else if (typeof boundaryRecord[key] !== 'string') {
        errors.push(`Detection boundary ${key} must be a string`);
      }
    }
  }

  // Validate direction
  if (!isParallaxDirection(values.parallaxDirection)) {
    errors.push('Invalid parallax direction');
  }

  // Validate boolean fields
  const booleanFields = [
    'enableIntersectionObserver',
    'enableMouseInteraction',
    'debugMode',
  ];
  booleanFields.forEach(field => {
    if (typeof values[field] !== 'boolean') {
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
export function validateBlurEffect(config: unknown): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  if (typeof config !== 'object' || config === null) {
    return { isValid: false, errors: ['Invalid blur config'], warnings: [] };
  }

  const c = config as Record<string, unknown>;

  if (typeof c.startBlur !== 'number' || c.startBlur < 0 || c.startBlur > 50) {
    errors.push('Start blur must be a number between 0 and 50');
  }

  if (typeof c.endBlur !== 'number' || c.endBlur < 0 || c.endBlur > 50) {
    errors.push('End blur must be a number between 0 and 50');
  }

  if (
    typeof c.fadeRange !== 'string' ||
    !['top', 'middle', 'bottom', 'full'].includes(c.fadeRange)
  ) {
    errors.push('Invalid fade range');
  }

  if (c.startBlur === c.endBlur) {
    warnings.push(
      'Start and end blur values are the same - no transition will occur'
    );
  }

  return { isValid: errors.length === 0, errors, warnings };
}

/**
 * Validate color transition effect configuration
 */
export function validateColorTransitionEffect(
  config: unknown
): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  if (typeof config !== 'object' || config === null) {
    return {
      isValid: false,
      errors: ['Invalid color transition config'],
      warnings: [],
    };
  }

  const c = config as Record<string, unknown>;

  // Basic color validation (hex, rgb, rgba, hsl, hsla)
  const colorRegex =
    /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$|^rgb\((\d{1,3}),\s*(\d{1,3}),\s*(\d{1,3})\)$|^rgba\((\d{1,3}),\s*(\d{1,3}),\s*(\d{1,3}),\s*(0|1|0?\.\d+)\)$|^hsl\((\d{1,3}),\s*(\d{1,3})%,\s*(\d{1,3})%\)$|^hsla\((\d{1,3}),\s*(\d{1,3})%,\s*(\d{1,3})%,\s*(0|1|0?\.\d+)\)$/;

  if (typeof c.startColor !== 'string' || !colorRegex.test(c.startColor)) {
    errors.push('Invalid start color format');
  }

  if (typeof c.endColor !== 'string' || !colorRegex.test(c.endColor)) {
    errors.push('Invalid end color format');
  }

  if (
    typeof c.transitionType !== 'string' ||
    !['background', 'text', 'border'].includes(c.transitionType)
  ) {
    errors.push('Invalid transition type');
  }

  if (c.startColor === c.endColor) {
    warnings.push(
      'Start and end colors are the same - no transition will occur'
    );
  }

  return { isValid: errors.length === 0, errors, warnings };
}

/**
 * Validate dynamic shadow effect configuration
 */
export function validateDynamicShadowEffect(config: unknown): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  if (typeof config !== 'object' || config === null) {
    return { isValid: false, errors: ['Invalid shadow config'], warnings: [] };
  }

  const c = config as Record<string, unknown>;

  if (
    typeof c.shadowType !== 'string' ||
    ['box-shadow', 'text-shadow', 'drop-shadow'].indexOf(c.shadowType) === -1
  ) {
    errors.push('Invalid shadow type');
  }

  // Basic CSS shadow value validation
  const shadowRegex = /^(-?\d+px\s+){3,4}(rgba?\([^)]+\)|#[0-9a-fA-F]{3,8})$/;
  if (
    typeof c.startShadow === 'string' &&
    c.startShadow &&
    !shadowRegex.test(c.startShadow.trim())
  ) {
    warnings.push('Start shadow may not be a valid CSS shadow value');
  }

  if (
    typeof c.endShadow === 'string' &&
    c.endShadow &&
    !shadowRegex.test(c.endShadow.trim())
  ) {
    warnings.push('End shadow may not be a valid CSS shadow value');
  }

  return { isValid: errors.length === 0, errors, warnings };
}

/**
 * Validate rotation effect configuration
 */
export function validateRotationEffect(config: unknown): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  if (typeof config !== 'object' || config === null) {
    return {
      isValid: false,
      errors: ['Invalid rotation config'],
      warnings: [],
    };
  }

  const c = config as Record<string, unknown>;

  if (
    typeof c.startRotation !== 'number' ||
    c.startRotation < -360 ||
    c.startRotation > 360
  ) {
    errors.push('Start rotation must be a number between -360 and 360');
  }

  if (
    typeof c.endRotation !== 'number' ||
    c.endRotation < -360 ||
    c.endRotation > 360
  ) {
    errors.push('End rotation must be a number between -360 and 360');
  }

  if (typeof c.axis !== 'string' || !['x', 'y', 'z', 'all'].includes(c.axis)) {
    errors.push('Invalid rotation axis');
  }

  if (c.startRotation === c.endRotation) {
    warnings.push(
      'Start and end rotation values are the same - no rotation will occur'
    );
  }

  return { isValid: errors.length === 0, errors, warnings };
}

/**
 * Enhanced effect validation that includes all new effects
 */
export function validateParallaxEffects(effects: unknown): ValidationResult {
  const errors: string[] = [];
  const warnings: string[] = [];

  if (typeof effects !== 'object' || effects === null) {
    return {
      isValid: false,
      errors: ['Effects must be an object'],
      warnings: [],
    };
  }

  const e = effects as Record<string, Record<string, unknown> | undefined>;

  // Validate each enabled effect
  if (e.blur?.enabled) {
    const result = validateBlurEffect(e.blur);
    errors.push(...result.errors);
    warnings.push(...result.warnings);
  }

  if (e.colorTransition?.enabled) {
    const result = validateColorTransitionEffect(e.colorTransition);
    errors.push(...result.errors);
    warnings.push(...result.warnings);
  }

  if (e.dynamicShadow?.enabled) {
    const result = validateDynamicShadowEffect(e.dynamicShadow);
    errors.push(...result.errors);
    warnings.push(...result.warnings);
  }

  if (e.rotation?.enabled) {
    const result = validateRotationEffect(e.rotation);
    errors.push(...result.errors);
    warnings.push(...result.warnings);
  }

  // Check for conflicting effects
  if (
    e.blur?.enabled &&
    e.dynamicShadow?.enabled &&
    e.dynamicShadow.shadowType === 'drop-shadow'
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
