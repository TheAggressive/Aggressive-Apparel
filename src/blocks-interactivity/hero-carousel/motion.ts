/**
 * Hero Carousel — background motion variants.
 *
 * Shared by the carousel inspector and per-slide Cover overrides so the
 * option lists stay in sync. Variant slugs live in `motion-variants.json`
 * (also loaded by render.php).
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';

import variants from './motion-variants.json';

/**
 * Concrete per-slide motion classes (excludes carousel meta modes).
 * JSON imports as `string[]`; assert through `unknown` to the tuple shape
 * that matches `motion-variants.json` (kept in sync with render.php).
 */
export const HERO_MOTION_VARIANTS = variants as unknown as readonly [
  'zoom-in',
  'zoom-out',
  'pan-left',
  'pan-right',
  'pan-up',
  'pan-down',
  'diagonal',
  'zoom-pan',
  'punch-in',
  'pull-back',
  'breathe',
  'idle-sway',
  'blur-sharp',
  'brightness',
  'colorize',
  'clip-reveal',
  'slide-in',
  'scale-edge',
  'tilt',
  'orbit',
  'grain',
];

export type HeroMotionVariant = (typeof HERO_MOTION_VARIANTS)[number];

/** Carousel-level attribute values (variants + assignment modes). */
export type HeroMotionMode =
  | 'none'
  | HeroMotionVariant
  | 'alternate'
  | 'random';

const VARIANT_LABELS: Record<HeroMotionVariant, string> = {
  'zoom-in': __('Zoom in', 'aggressive-apparel'),
  'zoom-out': __('Zoom out', 'aggressive-apparel'),
  'pan-left': __('Pan left', 'aggressive-apparel'),
  'pan-right': __('Pan right', 'aggressive-apparel'),
  'pan-up': __('Pan up', 'aggressive-apparel'),
  'pan-down': __('Pan down', 'aggressive-apparel'),
  diagonal: __('Diagonal drift', 'aggressive-apparel'),
  'zoom-pan': __('Zoom + pan', 'aggressive-apparel'),
  'punch-in': __('Punch in', 'aggressive-apparel'),
  'pull-back': __('Pull back', 'aggressive-apparel'),
  breathe: __('Breathing', 'aggressive-apparel'),
  'idle-sway': __('Idle sway', 'aggressive-apparel'),
  'blur-sharp': __('Blur to sharp', 'aggressive-apparel'),
  brightness: __('Brightness lift', 'aggressive-apparel'),
  colorize: __('Colorize', 'aggressive-apparel'),
  'clip-reveal': __('Clip reveal', 'aggressive-apparel'),
  'slide-in': __('Slide in', 'aggressive-apparel'),
  'scale-edge': __('Scale from edge', 'aggressive-apparel'),
  tilt: __('Tilt zoom', 'aggressive-apparel'),
  orbit: __('Orbit drift', 'aggressive-apparel'),
  grain: __('Film grain drift', 'aggressive-apparel'),
};

/** Carousel inspector options (includes alternate / random). */
export const HERO_MOTION_MODES: Array<{
  label: string;
  value: HeroMotionMode;
}> = [
  { label: __('None', 'aggressive-apparel'), value: 'none' },
  ...HERO_MOTION_VARIANTS.map(value => ({
    label: VARIANT_LABELS[value],
    value,
  })),
  {
    label: __('Alternate per slide', 'aggressive-apparel'),
    value: 'alternate',
  },
  {
    label: __('Random per slide', 'aggressive-apparel'),
    value: 'random',
  },
];

/** Per-slide Cover override options (inherit + none + concrete variants). */
export const HERO_MOTION_OVERRIDE_OPTIONS: Array<{
  label: string;
  value: string;
}> = [
  {
    label: __('Inherit from carousel', 'aggressive-apparel'),
    value: '',
  },
  { label: __('None', 'aggressive-apparel'), value: 'none' },
  ...HERO_MOTION_VARIANTS.map(value => ({
    label: VARIANT_LABELS[value],
    value,
  })),
];
