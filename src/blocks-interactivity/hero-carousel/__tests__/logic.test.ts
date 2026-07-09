/**
 * Tests for the hero carousel's pure logic.
 */

import {
  activeIndexFromScroll,
  canAdvance,
  clampIndex,
  focalToTransformOrigin,
  isLoopWrap,
  logicalToPhysical,
  nextIndex,
  normalizeAutoplaySpeed,
  parseSlideHash,
  physicalToLogical,
  prevIndex,
  scrollLeftForIndex,
  seamlessPhysicalTarget,
  slideHash,
  usesSeamlessLoop,
} from '../logic';

describe('clampIndex', () => {
  it('keeps in-range indices', () => {
    expect(clampIndex(2, 5)).toBe(2);
  });
  it('clamps below zero and above the last slide', () => {
    expect(clampIndex(-3, 5)).toBe(0);
    expect(clampIndex(9, 5)).toBe(4);
  });
  it('handles an empty carousel', () => {
    expect(clampIndex(3, 0)).toBe(0);
  });
});

describe('nextIndex', () => {
  it('advances within range', () => {
    expect(nextIndex(0, 3, true)).toBe(1);
  });
  it('wraps to the first slide when looping', () => {
    expect(nextIndex(2, 3, true)).toBe(0);
  });
  it('sticks on the last slide when not looping', () => {
    expect(nextIndex(2, 3, false)).toBe(2);
  });
});

describe('prevIndex', () => {
  it('moves backward within range', () => {
    expect(prevIndex(2, 3, true)).toBe(1);
  });
  it('wraps to the last slide when looping', () => {
    expect(prevIndex(0, 3, true)).toBe(2);
  });
  it('sticks on the first slide when not looping', () => {
    expect(prevIndex(0, 3, false)).toBe(0);
  });
});

describe('canAdvance', () => {
  it('is false with one slide', () => {
    expect(canAdvance(0, 1, true)).toBe(false);
  });
  it('is true while looping', () => {
    expect(canAdvance(2, 3, true)).toBe(true);
  });
  it('stops at the end when not looping', () => {
    expect(canAdvance(2, 3, false)).toBe(false);
    expect(canAdvance(1, 3, false)).toBe(true);
  });
});

describe('focalToTransformOrigin', () => {
  it('passes through a focal point', () => {
    expect(focalToTransformOrigin('30% 70%')).toBe('30% 70%');
  });
  it('falls back to center for empty input', () => {
    expect(focalToTransformOrigin(null)).toBe('center center');
    expect(focalToTransformOrigin('  ')).toBe('center center');
  });
});

describe('normalizeAutoplaySpeed', () => {
  it('accepts sane values', () => {
    expect(normalizeAutoplaySpeed(5000)).toBe(5000);
  });
  it('rejects too-fast or invalid values', () => {
    expect(normalizeAutoplaySpeed(200)).toBe(6000);
    expect(normalizeAutoplaySpeed('nope')).toBe(6000);
    expect(normalizeAutoplaySpeed(undefined, 4000)).toBe(4000);
  });
});

describe('activeIndexFromScroll', () => {
  it('maps LTR positive offsets to an index', () => {
    expect(activeIndexFromScroll(0, 500, 4)).toBe(0);
    expect(activeIndexFromScroll(1000, 500, 4)).toBe(2);
  });
  it('maps RTL negative offsets by magnitude', () => {
    expect(activeIndexFromScroll(-1000, 500, 4)).toBe(2);
  });
  it('rounds to the nearest slide and clamps', () => {
    expect(activeIndexFromScroll(740, 500, 4)).toBe(1);
    expect(activeIndexFromScroll(99999, 500, 4)).toBe(3);
  });
  it('is safe with a zero width', () => {
    expect(activeIndexFromScroll(100, 0, 4)).toBe(0);
  });
});

describe('scrollLeftForIndex', () => {
  it('is positive in LTR, negative in RTL', () => {
    expect(scrollLeftForIndex(2, 500, false)).toBe(1000);
    expect(scrollLeftForIndex(2, 500, true)).toBe(-1000);
  });
  it('round-trips through activeIndexFromScroll', () => {
    const left = scrollLeftForIndex(3, 480, true);
    expect(activeIndexFromScroll(left, 480, 5)).toBe(3);
  });
});

describe('parseSlideHash', () => {
  it('parses a matching hash into a 0-based index', () => {
    expect(parseSlideHash('#hero-slide-3', 'hero', 5)).toBe(2);
    expect(parseSlideHash('hero-slide-1', 'hero', 5)).toBe(0);
  });
  it('rejects other carousels, out-of-range, and junk', () => {
    expect(parseSlideHash('#other-slide-2', 'hero', 5)).toBeNull();
    expect(parseSlideHash('#hero-slide-9', 'hero', 5)).toBeNull();
    expect(parseSlideHash('#hero-slide-0', 'hero', 5)).toBeNull();
    expect(parseSlideHash('#hero-slide-x', 'hero', 5)).toBeNull();
    expect(parseSlideHash('#hero-slide-3', '', 5)).toBeNull();
  });
  it('round-trips with slideHash', () => {
    expect(parseSlideHash(slideHash('promo', 4), 'promo', 6)).toBe(4);
  });
});

describe('usesSeamlessLoop', () => {
  it('is only for looping slide mode (fade/crossfade wrap via opacity)', () => {
    expect(usesSeamlessLoop('slide', true, 3)).toBe(true);
    expect(usesSeamlessLoop('slide', false, 3)).toBe(false);
    expect(usesSeamlessLoop('fade', true, 3)).toBe(false);
    expect(usesSeamlessLoop('crossfade', true, 3)).toBe(false);
    expect(usesSeamlessLoop('slide', true, 1)).toBe(false);
  });
});

describe('isLoopWrap', () => {
  it('detects end wraps only', () => {
    expect(isLoopWrap(2, 0, 3)).toBe(true);
    expect(isLoopWrap(0, 2, 3)).toBe(true);
    expect(isLoopWrap(0, 1, 3)).toBe(false);
    expect(isLoopWrap(1, 2, 3)).toBe(false);
  });
});

describe('logicalToPhysical / physicalToLogical', () => {
  it('offsets real slides by one for the leading clone', () => {
    expect(logicalToPhysical(0, 3)).toBe(1);
    expect(logicalToPhysical(2, 3)).toBe(3);
  });
  it('maps clones back to the matching real slide', () => {
    expect(physicalToLogical(0, 3)).toBe(2);
    expect(physicalToLogical(1, 3)).toBe(0);
    expect(physicalToLogical(3, 3)).toBe(2);
    expect(physicalToLogical(4, 3)).toBe(0);
  });
});

describe('seamlessPhysicalTarget', () => {
  it('returns the trailing clone when wrapping last → first', () => {
    expect(seamlessPhysicalTarget(2, 0, 3, true)).toBe(4);
  });
  it('returns the leading clone when wrapping first → last', () => {
    expect(seamlessPhysicalTarget(0, 2, 3, true)).toBe(0);
  });
  it('returns the real physical index for non-wrap moves', () => {
    expect(seamlessPhysicalTarget(0, 1, 3, true)).toBe(2);
  });
  it('is null when seamless looping is off', () => {
    expect(seamlessPhysicalTarget(2, 0, 3, false)).toBeNull();
  });
});
