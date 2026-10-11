/**
 * Product Specs settings — ready-made specs to start from.
 *
 * Each preset is a complete spec with sensible steps, labels, advice, and
 * attribute names; adding one is a click, and everything stays editable.
 *
 * @package Aggressive_Apparel
 */

import { __, _x } from '@wordpress/i18n';
import type { SpecItem } from './_types';

export type Preset = {
  group: string;
  summary: string;
  item: Partial<SpecItem> & Pick<SpecItem, 'key' | 'type' | 'name'>;
};

/** Presets, grouped for the Add spec dialog. */
export function presets(): Preset[] {
  const clothing = __('Clothing', 'aggressive-apparel');
  const headwear = __('Headwear', 'aggressive-apparel');
  const details = __('Details', 'aggressive-apparel');

  return [
    {
      group: clothing,
      summary: __('Snug → Oversized, with sizing advice', 'aggressive-apparel'),
      item: {
        key: 'fit',
        type: 'pick',
        name: __('Fit', 'aggressive-apparel'),
        steps: [
          __('Snug', 'aggressive-apparel'),
          __('Fitted', 'aggressive-apparel'),
          __('Regular', 'aggressive-apparel'),
          __('Relaxed', 'aggressive-apparel'),
          __('Oversized', 'aggressive-apparel'),
        ],
        centered: true,
        advice: [
          __(
            'Fitted by design. Size up if you want room.',
            'aggressive-apparel'
          ),
          __('Runs a little small. Consider sizing up.', 'aggressive-apparel'),
          __('True to size. Take your usual size.', 'aggressive-apparel'),
          __('Relaxed cut. Size down for a closer fit.', 'aggressive-apparel'),
          __(
            'Oversized on purpose. Take your usual size.',
            'aggressive-apparel'
          ),
        ],
        attributes: [__('Fit', 'aggressive-apparel')],
      },
    },
    {
      group: clothing,
      summary: __('From g/m², Lightweight → Heavyweight', 'aggressive-apparel'),
      item: {
        key: 'weight',
        type: 'number',
        name: __('Fabric weight', 'aggressive-apparel'),
        steps: [
          __('Lightweight', 'aggressive-apparel'),
          __('Light-midweight', 'aggressive-apparel'),
          __('Midweight', 'aggressive-apparel'),
          __('Mid-heavyweight', 'aggressive-apparel'),
          __('Heavyweight', 'aggressive-apparel'),
        ],
        start: _x(
          'Light',
          'fabric weight scale, lightest end',
          'aggressive-apparel'
        ),
        end: _x(
          'Heavy',
          'fabric weight scale, heaviest end',
          'aggressive-apparel'
        ),
        unit: 'g/m²',
        thresholds: [145, 170, 195, 220],
        attributes: ['Fabric Weight', 'Fabric weight (gsm)', 'GSM'],
      },
    },
    {
      group: clothing,
      summary: __('None → Very stretchy', 'aggressive-apparel'),
      item: {
        key: 'stretch',
        type: 'pick',
        name: __('Stretch', 'aggressive-apparel'),
        steps: [
          __('None', 'aggressive-apparel'),
          __('Slight', 'aggressive-apparel'),
          __('Moderate', 'aggressive-apparel'),
          __('Stretchy', 'aggressive-apparel'),
          __('Very stretchy', 'aggressive-apparel'),
        ],
        attributes: [__('Stretch', 'aggressive-apparel')],
      },
    },
    {
      group: clothing,
      summary: __('Light → Very warm', 'aggressive-apparel'),
      item: {
        key: 'warmth',
        type: 'pick',
        name: __('Warmth', 'aggressive-apparel'),
        steps: [
          _x('Light', 'warmth scale, least warm', 'aggressive-apparel'),
          __('Mid', 'aggressive-apparel'),
          __('Warm', 'aggressive-apparel'),
          __('Very warm', 'aggressive-apparel'),
        ],
        attributes: [__('Warmth', 'aggressive-apparel')],
      },
    },
    {
      group: clothing,
      summary: __('Cropped → Extra long', 'aggressive-apparel'),
      item: {
        key: 'length',
        type: 'pick',
        name: __('Length', 'aggressive-apparel'),
        steps: [
          __('Cropped', 'aggressive-apparel'),
          __('Short', 'aggressive-apparel'),
          __('Regular', 'aggressive-apparel'),
          __('Long', 'aggressive-apparel'),
          __('Extra long', 'aggressive-apparel'),
        ],
        centered: true,
        attributes: [__('Length', 'aggressive-apparel')],
      },
    },
    {
      group: headwear,
      summary: __('Low → High profile', 'aggressive-apparel'),
      item: {
        key: 'crown',
        type: 'pick',
        name: __('Crown', 'aggressive-apparel'),
        steps: [
          __('Low profile', 'aggressive-apparel'),
          __('Mid profile', 'aggressive-apparel'),
          __('High profile', 'aggressive-apparel'),
        ],
        attributes: [
          __('Crown', 'aggressive-apparel'),
          __('Profile', 'aggressive-apparel'),
        ],
      },
    },
    {
      group: headwear,
      summary: __('Flat → Curved', 'aggressive-apparel'),
      item: {
        key: 'brim',
        type: 'pick',
        name: __('Brim', 'aggressive-apparel'),
        steps: [
          __('Flat', 'aggressive-apparel'),
          __('Slight curve', 'aggressive-apparel'),
          __('Curved', 'aggressive-apparel'),
        ],
        attributes: [__('Brim', 'aggressive-apparel')],
      },
    },
    {
      group: headwear,
      summary: __('Text, like “Snapback”', 'aggressive-apparel'),
      item: {
        key: 'closure',
        type: 'text',
        name: __('Closure', 'aggressive-apparel'),
        attributes: [__('Closure', 'aggressive-apparel')],
      },
    },
    {
      group: details,
      summary: __('Text, like “100% cotton”', 'aggressive-apparel'),
      item: {
        key: 'fabric',
        type: 'text',
        name: __('Fabric', 'aggressive-apparel'),
        attributes: ['Fabric', 'Material', 'Composition'],
      },
    },
    {
      group: details,
      summary: __('Text, like “Side-seamed”', 'aggressive-apparel'),
      item: {
        key: 'construction',
        type: 'text',
        name: __('Construction', 'aggressive-apparel'),
        attributes: ['Construction'],
      },
    },
    {
      group: details,
      summary: __('Text, like “Machine wash cold”', 'aggressive-apparel'),
      item: {
        key: 'care',
        type: 'text',
        name: __('Care', 'aggressive-apparel'),
        attributes: ['Care', 'Care instructions'],
      },
    },
    {
      group: details,
      summary: __('Text, like “Portugal”', 'aggressive-apparel'),
      item: {
        key: 'made_in',
        type: 'text',
        name: __('Made in', 'aggressive-apparel'),
        attributes: ['Made in', 'Origin', 'Country of origin'],
      },
    },
  ];
}
