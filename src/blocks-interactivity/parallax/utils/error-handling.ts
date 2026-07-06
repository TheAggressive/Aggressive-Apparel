/**
 * Error handling and logging utilities for the Parallax block
 *
 * @package Aggressive Apparel
 */

/**
 * Logging levels for consistent error reporting
 */
export type LogLevel = 'debug' | 'info' | 'warn' | 'error' | 'critical';

/**
 * Centralized logging utility for parallax operations
 */
export class ParallaxLogger {
  private static isDevelopment =
    typeof window !== 'undefined' && window.location?.hostname === 'localhost';

  static log(
    level: LogLevel,
    message: string,
    context?: Record<string, unknown>
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

  static debug(message: string, context?: Record<string, unknown>): void {
    this.log('debug', message, context);
  }

  static info(message: string, context?: Record<string, unknown>): void {
    this.log('info', message, context);
  }

  static warn(message: string, context?: Record<string, unknown>): void {
    this.log('warn', message, context);
  }

  static error(message: string, context?: Record<string, unknown>): void {
    this.log('error', message, context);
  }

  static critical(message: string, context?: Record<string, unknown>): void {
    this.log('critical', message, context);
  }
}

/**
 * Input validation utilities
 */
export const validators = {
  isValidNumber: (value: unknown, min?: number, max?: number): boolean => {
    if (typeof value !== 'number' || isNaN(value)) return false;
    if (min !== undefined && value < min) return false;
    if (max !== undefined && value > max) return false;
    return true;
  },

  isValidBoolean: (value: unknown): boolean => {
    return typeof value === 'boolean';
  },
};

interface ParallaxContainerConfig {
  intensity?: unknown;
  visibilityTrigger?: unknown;
  detectionBoundary?: Record<string, unknown> | null;
  enableMouseInteraction?: unknown;
  debugMode?: unknown;
}

/**
 * Configuration validation
 */
export function validateConfiguration(config: unknown): {
  isValid: boolean;
  errors: string[];
} {
  const errors: string[] = [];

  // Early return if config is null/undefined
  if (!config || typeof config !== 'object') {
    errors.push('Configuration is null or undefined');
    return { isValid: false, errors };
  }

  const cfg = config as ParallaxContainerConfig;

  // Validate intensity
  if (!validators.isValidNumber(cfg.intensity, 0, 200)) {
    errors.push('Intensity must be a number between 0 and 200');
  }

  // Validate visibilityTrigger (number 0-1)
  if (!validators.isValidNumber(cfg.visibilityTrigger, 0, 1)) {
    errors.push('Visibility trigger must be a number between 0 and 1');
  }

  // Validate detection boundary
  if (
    typeof cfg.detectionBoundary !== 'object' ||
    cfg.detectionBoundary === null
  ) {
    errors.push('Detection boundary must be an object');
  } else {
    const requiredKeys = ['top', 'right', 'bottom', 'left'];
    for (const key of requiredKeys) {
      if (!(key in cfg.detectionBoundary)) {
        errors.push(`Detection boundary must include ${key} property`);
      } else if (typeof cfg.detectionBoundary[key] !== 'string') {
        errors.push(`Detection boundary ${key} must be a string`);
      }
    }
  }

  // Validate mouse interaction
  if (!validators.isValidBoolean(cfg.enableMouseInteraction)) {
    errors.push('Enable mouse interaction must be a boolean');
  }

  // Validate debug mode
  if (!validators.isValidBoolean(cfg.debugMode)) {
    errors.push('Debug mode must be a boolean');
  }

  return {
    isValid: errors.length === 0,
    errors,
  };
}
