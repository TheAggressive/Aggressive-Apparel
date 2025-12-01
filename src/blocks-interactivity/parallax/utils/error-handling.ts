/**
 * Error handling and logging utilities for the Parallax block
 *
 * @package Aggressive Apparel
 */

import { ParallaxError, ParallaxErrorType } from '../types';

/**
 * Logging levels for consistent error reporting
 */
export type LogLevel = 'debug' | 'info' | 'warn' | 'error' | 'critical';

// LogLevel enum values are used via LogLevel.XYZ in the log method

/**
 * Centralized logging utility for parallax operations
 */
export class ParallaxLogger {
  private static isDevelopment =
    typeof window !== 'undefined' &&
    (window as any).location?.hostname === 'localhost';

  static log(
    level: LogLevel,
    message: string,
    context?: Record<string, any>
  ): void {
    const prefix = '[PARALLAX]';
    const logData = context ? { message, ...context } : message;

    switch (level) {
      case 'debug':
        if (this.isDevelopment) console.debug(prefix, logData);
        break;
      case 'info':
        console.info(prefix, logData);
        break;
      case 'warn':
        console.warn(prefix, logData);
        break;
      case 'error':
        console.error(prefix, logData);
        break;
      case 'critical':
        console.error(prefix + ' [CRITICAL]', logData);
        break;
    }
  }

  static debug(message: string, context?: Record<string, any>): void {
    this.log('debug', message, context);
  }

  static info(message: string, context?: Record<string, any>): void {
    this.log('info', message, context);
  }

  static warn(message: string, context?: Record<string, any>): void {
    this.log('warn', message, context);
  }

  static error(message: string, context?: Record<string, any>): void {
    this.log('error', message, context);
  }

  static critical(message: string, context?: Record<string, any>): void {
    this.log('critical', message, context);
  }
}

/**
 * Creates a typed parallax error
 */
export function createParallaxError(
  type: ParallaxErrorType,
  message: string,
  code: string,
  context?: Record<string, any>,
  recoverable: boolean = true
): ParallaxError {
  const error = new Error(message) as ParallaxError;
  error.type = type;
  error.code = code;
  error.context = context;
  error.recoverable = recoverable;

  return error;
}

/**
 * Error boundary wrapper for parallax operations
 */
export function withErrorBoundary(fn: Function, fallback: any): Function {
  return (...args: any[]): any => {
    try {
      return fn(...args);
    } catch (_error) {
      const parallaxError =
        _error instanceof Error && 'type' in _error
          ? (_error as ParallaxError)
          : createParallaxError(
              'runtime_error',
              _error instanceof Error ? _error.message : 'Unknown error',
              'UNKNOWN_ERROR',
              { originalError: _error },
              true
            );

      // Log error
      logParallaxError(parallaxError);

      return fallback;
    }
  };
}

/**
 * Safe JSON parsing with error handling
 */
export function safeJsonParse<T>(
  jsonString: string,
  fallback: T,
  context?: string
): T {
  try {
    return JSON.parse(jsonString);
  } catch (error) {
    const parallaxError = createParallaxError(
      'validation_error',
      `Failed to parse JSON${context ? ` for ${context}` : ''}`,
      'JSON_PARSE_ERROR',
      { jsonString, originalError: error },
      true
    );

    logParallaxError(parallaxError);
    return fallback;
  }
}

/**
 * Safe DOM element operations
 */
export function safeGetAttribute(
  element: HTMLElement,
  attribute: string,
  fallback: string = ''
): string {
  try {
    return element.getAttribute(attribute) || fallback;
  } catch (error) {
    const parallaxError = createParallaxError(
      'runtime_error',
      'Failed to get element attribute',
      'ATTRIBUTE_GET_ERROR',
      { attribute, elementTag: element.tagName, originalError: error },
      true
    );

    logParallaxError(parallaxError);
    return fallback;
  }
}

/**
 * Safe style property operations
 */

export function safeSetStyleProperty(
  element: HTMLElement,
  property: string,
  value: string
): void {
  try {
    element.style.setProperty(property, value);
  } catch (error) {
    const parallaxError = createParallaxError(
      'runtime_error',
      'Failed to set style property',
      'STYLE_SET_ERROR',
      { property, value, elementTag: element.tagName, originalError: error },
      true
    );

    logParallaxError(parallaxError);
  }
}

/**
 * Safe style property getting
 */

export function safeGetStyleProperty(
  element: HTMLElement,
  property: string,
  fallback: string = ''
): string {
  try {
    return element.style.getPropertyValue(property) || fallback;
  } catch (error) {
    const parallaxError = createParallaxError(
      'runtime_error',
      'Failed to get style property',
      'STYLE_GET_ERROR',
      { property, elementTag: element.tagName, originalError: error },
      true
    );

    logParallaxError(parallaxError);
    return fallback;
  }
}

/**
 * Logs parallax errors with appropriate level
 */
export function logParallaxError(error: ParallaxError): void {
  const logData = {
    type: error.type,
    code: error.code,
    message: error.message,
    context: error.context,
    recoverable: error.recoverable,
    stack: error.stack,
    timestamp: new Date().toISOString(),
  };

  switch (error.type) {
    case 'configuration_error':
      console.warn('[Parallax Config Error]', logData);
      break;
    case 'validation_error':
      console.warn('[Parallax Validation Error]', logData);
      break;
    case 'performance_error':
      console.warn('[Parallax Performance Error]', logData);
      break;
    case 'runtime_error':
    default:
      if (error.recoverable) {
        console.warn('[Parallax Runtime Error]', logData);
      } else {
        console.error('[Parallax Critical Error]', logData);
      }
      break;
  }
}

/**
 * Performance monitoring wrapper
 */
export function withPerformanceMonitoring(
  fn: Function,
  operationName: string,
  thresholdMs: number = 16 // ~60fps
): Function {
  return (...args: any[]): any => {
    const startTime = performance.now();

    try {
      const result = fn(...args);
      const duration = performance.now() - startTime;

      if (duration > thresholdMs) {
        const performanceError = createParallaxError(
          'runtime_error',
          `Slow operation: ${operationName} took ${duration.toFixed(2)}ms`,
          'PERFORMANCE_THRESHOLD_EXCEEDED',
          {
            operation: operationName,
            duration,
            threshold: thresholdMs,
            args: args.length,
          },
          true
        );

        logParallaxError(performanceError);
      }

      return result;
    } catch (error) {
      const duration = performance.now() - startTime;
      const performanceError = createParallaxError(
        'runtime_error',
        `Operation ${operationName} failed after ${duration.toFixed(2)}ms`,
        'OPERATION_FAILED',
        {
          operation: operationName,
          duration,
          originalError: error,
        },
        false
      );

      throw performanceError;
    }
  };
}

/**
 * Input validation utilities
 */
export const validators = {
  isValidNumber: (value: any, min?: number, max?: number): boolean => {
    if (typeof value !== 'number' || isNaN(value)) return false;
    if (min !== undefined && value < min) return false;
    if (max !== undefined && value > max) return false;
    return true;
  },

  isValidString: (value: any, maxLength?: number): boolean => {
    if (typeof value !== 'string') return false;
    if (maxLength !== undefined && value.length > maxLength) return false;
    return true;
  },

  isValidBoolean: (value: any): boolean => {
    return typeof value === 'boolean';
  },

  isValidElement: (value: any): value is HTMLElement => {
    return value instanceof HTMLElement;
  },
};

/**
 * Configuration validation
 */
export function validateConfiguration(config: any): {
  isValid: boolean;
  errors: string[];
} {
  const errors: string[] = [];

  // Early return if config is null/undefined
  if (!config) {
    errors.push('Configuration is null or undefined');
    return { isValid: false, errors };
  }

  // Validate intensity
  if (!validators.isValidNumber(config.intensity, 0, 200)) {
    errors.push('Intensity must be a number between 0 and 200');
  }

  // Validate visibilityTrigger (number 0-1)
  if (!validators.isValidNumber(config.visibilityTrigger, 0, 1)) {
    errors.push('Visibility trigger must be a number between 0 and 1');
  }

  // Validate detection boundary
  if (
    typeof config.detectionBoundary !== 'object' ||
    config.detectionBoundary === null
  ) {
    errors.push('Detection boundary must be an object');
  } else {
    const requiredKeys = ['top', 'right', 'bottom', 'left'];
    for (const key of requiredKeys) {
      if (!(key in config.detectionBoundary)) {
        errors.push(`Detection boundary must include ${key} property`);
      } else if (typeof config.detectionBoundary[key] !== 'string') {
        errors.push(`Detection boundary ${key} must be a string`);
      }
    }
  }

  // Validate mouse interaction
  if (!validators.isValidBoolean(config.enableMouseInteraction)) {
    errors.push('Enable mouse interaction must be a boolean');
  }

  // Validate debug mode
  if (!validators.isValidBoolean(config.debugMode)) {
    errors.push('Debug mode must be a boolean');
  }

  return {
    isValid: errors.length === 0,
    errors,
  };
}
