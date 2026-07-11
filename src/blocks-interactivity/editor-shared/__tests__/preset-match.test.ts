/**
 * Tests for shared preset matching (active-tile detection).
 *
 * @jest-environment jsdom
 */

import { findActivePresetKey, isDeepSubset } from '../preset-match';

describe('isDeepSubset', () => {
  it('matches when every patch value is present in the target', () => {
    expect(
      isDeepSubset(
        { animation: 'slide', direction: 'up' },
        { animation: 'slide', direction: 'up', duration: 0.6 }
      )
    ).toBe(true);
  });

  it('recurses into nested objects (merged effects still match)', () => {
    const patch = { effects: { depthLevel: { value: 1.2 } } };
    const target = {
      effects: { depthLevel: { value: 1.2 }, zoom: { enabled: true } },
    };
    expect(isDeepSubset(patch, target)).toBe(true);
    expect(
      isDeepSubset({ effects: { depthLevel: { value: 2 } } }, target)
    ).toBe(false);
  });

  it('requires arrays to match exactly', () => {
    expect(
      isDeepSubset(
        { animationSequence: [{ animation: 'fade' }] },
        { animationSequence: [{ animation: 'fade' }] }
      )
    ).toBe(true);
    expect(
      isDeepSubset(
        { animationSequence: [{ animation: 'fade' }] },
        { animationSequence: [{ animation: 'fade' }, { animation: 'slide' }] }
      )
    ).toBe(false);
  });

  it('fails on missing keys and different primitives', () => {
    expect(isDeepSubset({ speed: 1.5 }, { direction: 'down' })).toBe(false);
    expect(isDeepSubset({ speed: 1.5 }, { speed: 0.5 })).toBe(false);
  });
});

describe('findActivePresetKey', () => {
  const presets = [
    { key: 'subtle', value: { speed: 0.5, easing: 'easeOut' } },
    { key: 'dramatic', value: { speed: 1.5, easing: 'easeOut' } },
  ];

  it('returns the matching preset key', () => {
    expect(
      findActivePresetKey(presets, { speed: 1.5, easing: 'easeOut', delay: 0 })
    ).toBe('dramatic');
  });

  it('returns null when nothing matches', () => {
    expect(
      findActivePresetKey(presets, { speed: 2.5, easing: 'linear' })
    ).toBeNull();
  });
});
