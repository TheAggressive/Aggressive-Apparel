/**
 * Effect progress mapping modes.
 *
 * @jest-environment jsdom
 */

import { mapProgressToEffectRange } from '../calculations';
import { MOBILE_MAX_WIDTH_PX } from '../config';

describe('mapProgressToEffectRange', () => {
  it('returns 0 before the effect window and 1 after in sustain mode', () => {
    expect(mapProgressToEffectRange(0.1, 0.2, 0.7, 'sustain')).toBe(0);
    expect(mapProgressToEffectRange(0.8, 0.2, 0.7, 'sustain')).toBe(1);
  });

  it('interpolates linearly inside the sustain window', () => {
    expect(mapProgressToEffectRange(0.45, 0.2, 0.7, 'sustain')).toBeCloseTo(
      0.5
    );
  });

  it('peaks mid-window for peek and reverse, then falls', () => {
    const mid = mapProgressToEffectRange(0.5, 0, 1, 'peek');
    const late = mapProgressToEffectRange(0.9, 0, 1, 'peek');
    expect(mid).toBeCloseTo(1, 1);
    expect(late).toBeLessThan(mid);

    expect(mapProgressToEffectRange(1, 0, 1, 'reverse')).toBe(0);
    expect(mapProgressToEffectRange(0.5, 0, 1, 'reverse')).toBeCloseTo(1, 1);
  });

  it('falls back to raw progress when the range is invalid', () => {
    expect(mapProgressToEffectRange(0.4, 0.8, 0.2, 'sustain')).toBe(0.4);
  });
});

describe('MOBILE_MAX_WIDTH_PX', () => {
  it('stays aligned with the CSS mobile gate (768)', () => {
    expect(MOBILE_MAX_WIDTH_PX).toBe(768);
  });
});
