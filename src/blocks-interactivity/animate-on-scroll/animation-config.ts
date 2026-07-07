/**
 * Shared animation catalog for the Animate On Scroll block editor UI.
 *
 * Single source of truth for the available animation types, their
 * direction options and human-readable labels. Consumed by edit.tsx and
 * the inspector components.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';
import type { AnimationSequenceItem } from './types';

export interface AnimationDirectionOption {
  label: string;
  value: string;
}

export interface AnimationConfig {
  label: string;
  directions?: AnimationDirectionOption[];
  defaultDirection?: string;
}

export const BASE_ANIMATIONS: Record<string, AnimationConfig> = {
  fade: {
    label: __('Fade', 'aggressive-apparel'),
  },
  slide: {
    label: __('Slide', 'aggressive-apparel'),
    directions: [
      { label: __('Up', 'aggressive-apparel'), value: 'up' },
      { label: __('Down', 'aggressive-apparel'), value: 'down' },
      { label: __('Left', 'aggressive-apparel'), value: 'left' },
      { label: __('Right', 'aggressive-apparel'), value: 'right' },
    ],
    defaultDirection: 'up',
  },
  zoom: {
    label: __('Zoom', 'aggressive-apparel'),
    directions: [
      { label: __('In', 'aggressive-apparel'), value: 'in' },
      { label: __('Out', 'aggressive-apparel'), value: 'out' },
    ],
    defaultDirection: 'in',
  },
  flip: {
    label: __('Flip', 'aggressive-apparel'),
    directions: [
      { label: __('Up', 'aggressive-apparel'), value: 'up' },
      { label: __('Down', 'aggressive-apparel'), value: 'down' },
      { label: __('Left', 'aggressive-apparel'), value: 'left' },
      { label: __('Right', 'aggressive-apparel'), value: 'right' },
    ],
    defaultDirection: 'up',
  },
  rotate: {
    label: __('Rotate', 'aggressive-apparel'),
    directions: [
      { label: __('Left', 'aggressive-apparel'), value: 'left' },
      { label: __('Right', 'aggressive-apparel'), value: 'right' },
    ],
    defaultDirection: 'left',
  },
  blur: {
    label: __('Blur', 'aggressive-apparel'),
  },
  bounce: {
    label: __('Bounce', 'aggressive-apparel'),
    directions: [
      { label: __('Standard', 'aggressive-apparel'), value: 'standard' },
      { label: __('Elastic', 'aggressive-apparel'), value: 'elastic' },
      { label: __('Spring', 'aggressive-apparel'), value: 'spring' },
    ],
    defaultDirection: 'standard',
  },
};

/** SelectControl options for the animation type dropdowns. */
export const getAnimationOptions = (): Array<{
  value: string;
  label: string;
}> =>
  Object.entries(BASE_ANIMATIONS).map(([value, config]) => ({
    value,
    label: config.label,
  }));

/** Direction options for an animation type, or an empty list if none. */
export const getDirectionOptions = (
  animation: string
): AnimationDirectionOption[] => BASE_ANIMATIONS[animation]?.directions ?? [];

/** Default direction for an animation type ('' when directionless). */
export const getDefaultDirection = (animation: string): string =>
  BASE_ANIMATIONS[animation]?.defaultDirection ?? '';

/** Compact glyphs used in the sequence flow strip and step titles. */
const DIRECTION_GLYPHS: Record<string, string> = {
  up: '↑',
  down: '↓',
  left: '←',
  right: '→',
};

/** Human-readable summary of a sequence step, e.g. "Slide ↑" or "Zoom · In". */
export const describeSequenceItem = (item: AnimationSequenceItem): string => {
  const label = BASE_ANIMATIONS[item.animation]?.label ?? item.animation;

  if (!item.direction) {
    return label;
  }

  const glyph = DIRECTION_GLYPHS[item.direction];
  if (glyph) {
    return `${label} ${glyph}`;
  }

  const directionLabel =
    BASE_ANIMATIONS[item.animation]?.directions?.find(
      option => option.value === item.direction
    )?.label ?? item.direction;

  return `${label} · ${directionLabel}`;
};
