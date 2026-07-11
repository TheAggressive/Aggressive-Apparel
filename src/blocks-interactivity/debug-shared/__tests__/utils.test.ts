/**
 * Tests for the shared scroll-debug pure helpers.
 *
 * @jest-environment jsdom
 */

import {
  boundaryExtendsViewport,
  boundaryToRootMargin,
  getIntersectionPhase,
  getMaxReachableRatio,
  getRootBoxHeight,
  getValidIntersectionRatio,
  getVisibilityThreshold,
  invertValue,
  marginPartToPx,
} from '../utils';

describe('invertValue', () => {
  it('inverts px and % values', () => {
    expect(invertValue('100px')).toBe('-100px');
    expect(invertValue('-20%')).toBe('20%');
  });

  it('preserves decimals (the old parseInt truncated them)', () => {
    expect(invertValue('2.5%')).toBe('-2.5%');
    expect(invertValue('-12.75px')).toBe('12.75px');
  });

  it('passes through unparseable values', () => {
    expect(invertValue('auto')).toBe('auto');
  });
});

describe('getVisibilityThreshold', () => {
  it('parses numeric strings', () => {
    expect(getVisibilityThreshold('0.5')).toBe(0.5);
  });

  it('falls back to 0.3 for invalid input', () => {
    expect(getVisibilityThreshold('nope')).toBe(0.3);
    expect(getVisibilityThreshold(NaN)).toBe(0.3);
    expect(getVisibilityThreshold(undefined)).toBe(0.3);
  });

  it('accepts numbers directly', () => {
    expect(getVisibilityThreshold(0.75)).toBe(0.75);
  });
});

describe('getValidIntersectionRatio', () => {
  it('clamps into [0, 1]', () => {
    expect(getValidIntersectionRatio(1.5)).toBe(1);
    expect(getValidIntersectionRatio(-0.5)).toBe(0);
    expect(getValidIntersectionRatio(0.42)).toBe(0.42);
  });

  it('uses the fallback on NaN/undefined', () => {
    expect(getValidIntersectionRatio(NaN, 0.2)).toBe(0.2);
    expect(getValidIntersectionRatio(undefined)).toBe(0);
  });
});

describe('getIntersectionPhase', () => {
  it('is waiting when not intersecting regardless of ratio', () => {
    expect(getIntersectionPhase(false, 0.9, 0.3)).toBe('waiting');
  });

  it('is approaching below the threshold and active at/above it', () => {
    expect(getIntersectionPhase(true, 0.1, 0.3)).toBe('approaching');
    expect(getIntersectionPhase(true, 0.3, 0.3)).toBe('active');
    expect(getIntersectionPhase(true, 0.9, 0.3)).toBe('active');
  });
});

describe('boundary math', () => {
  it('serializes boundaries with defaults for empty sides', () => {
    expect(
      boundaryToRootMargin({ top: '10%', right: '', bottom: '-5px', left: '' })
    ).toBe('10% 0% -5px 0%');
  });

  it('resolves margin parts against the viewport', () => {
    expect(marginPartToPx('20%', 1000)).toBe(200);
    expect(marginPartToPx('-150px', 1000)).toBe(-150);
    expect(marginPartToPx('garbage', 1000)).toBe(0);
  });

  it('computes the root box height from top/bottom margins', () => {
    expect(getRootBoxHeight('20% 0% 20% 0%', 1000)).toBe(1400);
    expect(getRootBoxHeight('-100px 0px -100px 0px', 1000)).toBe(800);
    expect(getRootBoxHeight('0px', 768)).toBe(768);
  });

  it('computes the max reachable ratio for tall elements', () => {
    expect(getMaxReachableRatio(2000, 1000)).toBe(0.5);
    expect(getMaxReachableRatio(500, 1000)).toBe(1);
    expect(getMaxReachableRatio(0, 1000)).toBe(1);
  });

  it('detects boundaries extending beyond the viewport', () => {
    expect(
      boundaryExtendsViewport({
        top: '200px',
        right: '0%',
        bottom: '0%',
        left: '0%',
      })
    ).toBe(true);
    expect(
      boundaryExtendsViewport({
        top: '-10%',
        right: '0%',
        bottom: '-10%',
        left: '0%',
      })
    ).toBe(false);
  });
});
