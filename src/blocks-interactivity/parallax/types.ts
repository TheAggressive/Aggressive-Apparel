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
  updateInfoPanel: (isIntersecting?: boolean) => void; // eslint-disable-line
  updateZoneVisualization: (
    intersectionRatio?: number, // eslint-disable-line
    isIntersecting?: boolean // eslint-disable-line
  ) => void;
  updateVisibilityTriggerLine: (
    intersectionRatio?: number, // eslint-disable-line
    isIntersecting?: boolean // eslint-disable-line
  ) => void;
  updateDebugContainerPosition: () => void;
  removeDebugOverlays: () => void;
  updatePerformance: () => void;
  debug: () => void;
}
