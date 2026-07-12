/**
 * Tests for preset color reference mapping.
 */

import {
  flattenPresetColors,
  fromPresetColorRef,
  toPresetColorRef,
} from '../preset-colors';

const PALETTE = [
  { name: 'White', slug: 'white', color: '#FFFFFF' },
  { name: 'Transparent', slug: 'transparent', color: 'transparent' },
  {
    name: 'Accent (Adaptive)',
    slug: 'accent',
    color: 'light-dark(oklch(57.7% 0.245 27.325), oklch(65% 0.2 27))',
  },
];

describe('flattenPresetColors', () => {
  it('merges origin groups and tolerates missing lists', () => {
    expect(
      flattenPresetColors([
        { name: 'Theme', colors: [PALETTE[0]] },
        { name: 'Default' },
        { name: 'Custom', colors: [PALETTE[1]] },
      ])
    ).toEqual([PALETTE[0], PALETTE[1]]);

    expect(flattenPresetColors(undefined)).toEqual([]);
  });
});

describe('toPresetColorRef', () => {
  it('stores palette picks as preset references (case-insensitive)', () => {
    expect(toPresetColorRef('#ffffff', PALETTE)).toBe('var:preset|color|white');
    expect(toPresetColorRef('transparent', PALETTE)).toBe(
      'var:preset|color|transparent'
    );
    expect(
      toPresetColorRef(
        'light-dark(oklch(57.7% 0.245 27.325), oklch(65% 0.2 27))',
        PALETTE
      )
    ).toBe('var:preset|color|accent');
  });

  it('keeps custom colors and clears empty picks', () => {
    expect(toPresetColorRef('#123456', PALETTE)).toBe('#123456');
    expect(toPresetColorRef(undefined, PALETTE)).toBe('');
    expect(toPresetColorRef('', PALETTE)).toBe('');
  });
});

describe('fromPresetColorRef', () => {
  it('resolves references back to the palette value for the picker', () => {
    expect(fromPresetColorRef('var:preset|color|white', PALETTE)).toBe(
      '#FFFFFF'
    );
    expect(fromPresetColorRef('var:preset|color|accent', PALETTE)).toBe(
      PALETTE[2].color
    );
  });

  it('falls back to the preset variable for unknown slugs', () => {
    expect(fromPresetColorRef('var:preset|color|ghost', PALETTE)).toBe(
      'var(--wp--preset--color--ghost)'
    );
  });

  it('passes custom colors through and maps empty to undefined', () => {
    expect(fromPresetColorRef('#123456', PALETTE)).toBe('#123456');
    expect(fromPresetColorRef('', PALETTE)).toBeUndefined();
    expect(fromPresetColorRef(undefined, PALETTE)).toBeUndefined();
  });
});
