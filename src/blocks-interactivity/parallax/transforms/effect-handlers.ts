/**
 * Effect handlers for applying specific visual effects
 *
 * @package Aggressive Apparel
 */

import { ParallaxEffects } from '../types';
import {
  calculateBlur,
  calculateColorTransition,
  calculateRotation,
  calculateScrollOpacity,
  calculateShadow,
} from '../utils';

export const applyScrollOpacityEffect = (
  styleUpdates: Record<string, string>,
  config: ParallaxEffects['scrollOpacity'],
  progress: number
) => {
  if (!config?.enabled) {
    return;
  }

  const { startOpacity, endOpacity, fadeRange } = config;
  const opacity = calculateScrollOpacity(
    progress,
    fadeRange,
    startOpacity,
    endOpacity,
    config.effectStart ?? 0.0,
    config.effectEnd ?? 0.25,
    config.effectMode ?? 'sustain'
  );

  // We return opacity because it's also used for CSS variable
  return opacity;
};

export const applyBlurEffect = (
  styleUpdates: Record<string, string>,
  config: ParallaxEffects['blur'],
  progress: number
) => {
  if (
    !config?.enabled ||
    config.startBlur === undefined ||
    config.endBlur === undefined
  ) {
    return;
  }

  const blurAmount = calculateBlur(
    progress,
    config.startBlur,
    config.endBlur,
    config.fadeRange || 'full',
    config.effectStart ?? 0.0,
    config.effectEnd ?? 0.25,
    config.effectMode ?? 'sustain'
  );
  styleUpdates.filter = `blur(${blurAmount}px)`;
};

export const applyColorEffect = (
  styleUpdates: Record<string, string>,
  config: ParallaxEffects['colorTransition'],
  progress: number
) => {
  if (!config?.enabled || !config.startColor || !config.endColor) {
    return;
  }

  const color = calculateColorTransition(
    progress,
    config.startColor,
    config.endColor,
    config.effectStart ?? 0.0,
    config.effectEnd ?? 0.25,
    config.effectMode ?? 'sustain'
  );

  switch (config.transitionType) {
    case 'background':
      styleUpdates.backgroundColor = color;
      break;
    case 'text':
      styleUpdates.color = color;
      break;
    case 'border':
      styleUpdates.borderColor = color;
      break;
  }
};

export const applyShadowEffect = (
  styleUpdates: Record<string, string>,
  config: ParallaxEffects['dynamicShadow'],
  progress: number
) => {
  if (!config?.enabled || !config.startShadow || !config.endShadow) {
    return;
  }

  const shadow = calculateShadow(
    progress,
    config.startShadow,
    config.endShadow,
    config.effectStart ?? 0.0,
    config.effectEnd ?? 0.25,
    config.effectMode ?? 'sustain'
  );

  switch (config.shadowType) {
    case 'box-shadow':
      styleUpdates.boxShadow = shadow;
      break;
    case 'text-shadow':
      styleUpdates.textShadow = shadow;
      break;
    case 'drop-shadow': {
      // Check for drop-shadow support before applying
      if (
        typeof CSS !== 'undefined' &&
        CSS.supports &&
        CSS.supports('filter', 'drop-shadow(0px 0px 0px black)')
      ) {
        // Combine with existing filter if present
        const existingFilter = styleUpdates.filter || '';
        styleUpdates.filter = existingFilter
          ? `${existingFilter} drop-shadow(${shadow})`
          : `drop-shadow(${shadow})`;
      } else {
        // Fallback to box-shadow for unsupported browsers
        styleUpdates.boxShadow = shadow;
      }
      break;
    }
  }
};

export const applyRotationEffect = (
  styleUpdates: Record<string, string>,
  config: ParallaxEffects['rotation'],
  progress: number
) => {
  if (
    !config?.enabled ||
    config.startRotation === undefined ||
    config.endRotation === undefined
  ) {
    return;
  }

  const rotation = calculateRotation(
    progress,
    config.startRotation,
    config.endRotation,
    config.speed || 1.0,
    config.mode || 'range',
    config.effectStart ?? 0.0,
    config.effectEnd ?? 0.25,
    config.effectMode ?? 'sustain'
  );

  const axis = config.axis || 'z';
  let transformValue = '';

  switch (axis) {
    case 'x':
      transformValue = `rotateX(${rotation}deg)`;
      break;
    case 'y':
      transformValue = `rotateY(${rotation}deg)`;
      break;
    case 'z':
    case 'all':
      transformValue = `rotate(${rotation}deg)`;
      break;
  }

  // Don't read from element.style.transform as it contains previous rotation!
  // Check if transform already set in styleUpdates (shouldn't be, but be safe)
  const existingTransform = styleUpdates.transform || '';
  styleUpdates.transform = existingTransform
    ? `${existingTransform} ${transformValue}`
    : transformValue;
};
