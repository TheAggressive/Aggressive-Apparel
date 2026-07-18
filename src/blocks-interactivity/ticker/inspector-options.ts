/**
 * Ticker block — editor InspectorControl option lists.
 *
 * Values must stay in sync with `constants.ts` allowlists and `render.php`.
 *
 * @package Aggressive_Apparel
 */

import { __ } from '@wordpress/i18n';

export const LABEL_TYPE_OPTIONS = [
  { label: __('Text', 'aggressive-apparel'), value: 'text' },
  { label: __('Icon', 'aggressive-apparel'), value: 'icon' },
];

export const INDICATOR_SHAPE_OPTIONS = [
  { label: __('Square', 'aggressive-apparel'), value: 'square' },
  { label: __('Circle', 'aggressive-apparel'), value: 'circle' },
  { label: __('Diamond', 'aggressive-apparel'), value: 'diamond' },
  { label: __('None', 'aggressive-apparel'), value: 'none' },
];

export const BLEND_MODE_OPTIONS = [
  { label: __('Normal', 'aggressive-apparel'), value: 'normal' },
  { label: __('Overlay', 'aggressive-apparel'), value: 'overlay' },
  { label: __('Multiply', 'aggressive-apparel'), value: 'multiply' },
  { label: __('Screen', 'aggressive-apparel'), value: 'screen' },
  { label: __('Soft Light', 'aggressive-apparel'), value: 'soft-light' },
  { label: __('Difference', 'aggressive-apparel'), value: 'difference' },
];

export const FONT_WEIGHT_OPTIONS = [
  { label: __('Inherit', 'aggressive-apparel'), value: '' },
  { label: __('400 — Normal', 'aggressive-apparel'), value: '400' },
  { label: __('500 — Medium', 'aggressive-apparel'), value: '500' },
  { label: __('600 — SemiBold', 'aggressive-apparel'), value: '600' },
  { label: __('700 — Bold', 'aggressive-apparel'), value: '700' },
  { label: __('800 — ExtraBold', 'aggressive-apparel'), value: '800' },
  { label: __('900 — Black', 'aggressive-apparel'), value: '900' },
];

export const TEXT_TRANSFORM_OPTIONS = [
  { label: __('None', 'aggressive-apparel'), value: '' },
  { label: __('Uppercase', 'aggressive-apparel'), value: 'uppercase' },
  { label: __('Lowercase', 'aggressive-apparel'), value: 'lowercase' },
  { label: __('Capitalize', 'aggressive-apparel'), value: 'capitalize' },
];

export const PATTERN_OPTIONS = [
  { label: __('None', 'aggressive-apparel'), value: 'none' },
  { label: __('Diagonal Stripes', 'aggressive-apparel'), value: 'diagonal' },
  { label: __('Crosshatch', 'aggressive-apparel'), value: 'crosshatch' },
  { label: __('Dots', 'aggressive-apparel'), value: 'dots' },
  { label: __('Halftone', 'aggressive-apparel'), value: 'halftone' },
  { label: __('Noise', 'aggressive-apparel'), value: 'noise' },
  { label: __('Grain', 'aggressive-apparel'), value: 'grain' },
  { label: __('Scratch', 'aggressive-apparel'), value: 'scratch' },
  { label: __('Grunge', 'aggressive-apparel'), value: 'grunge' },
  { label: __('Herringbone', 'aggressive-apparel'), value: 'herringbone' },
  { label: __('Carbon', 'aggressive-apparel'), value: 'carbon' },
  { label: __('Honeycomb', 'aggressive-apparel'), value: 'honeycomb' },
  { label: __('Linen', 'aggressive-apparel'), value: 'linen' },
];

export const INNER_BLOCKS_TEMPLATE: Array<[string, Record<string, unknown>]> = [
  [
    'core/paragraph',
    {
      placeholder: __('Add ticker content…', 'aggressive-apparel'),
    },
  ],
];
