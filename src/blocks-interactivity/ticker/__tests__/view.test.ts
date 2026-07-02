/**
 * Tests for the ticker block's animation scheduling logic.
 *
 * @jest-environment jsdom
 */

jest.mock(
  '@wordpress/interactivity',
  () => ({
    store: () => ({}),
    getContext: () => ({}),
    getElement: () => ({ ref: null }),
  }),
  { virtual: true }
);

import {
  getTickerPxPerMs,
  isTickerReverseDirection,
  parseTickerDataSpeed,
  shouldAnimateTicker,
} from '../view';

describe('parseTickerDataSpeed', () => {
  it('parses loop duration from data-ticker-speed', () => {
    expect(parseTickerDataSpeed('20')).toBe(20);
  });

  it('falls back when the value is missing or invalid', () => {
    expect(parseTickerDataSpeed(undefined)).toBe(30);
    expect(parseTickerDataSpeed('invalid', 45)).toBe(45);
    expect(parseTickerDataSpeed('0')).toBe(30);
  });
});

describe('isTickerReverseDirection', () => {
  it('treats right as reverse scrolling', () => {
    expect(isTickerReverseDirection('right')).toBe(true);
    expect(isTickerReverseDirection('left')).toBe(false);
    expect(isTickerReverseDirection(undefined)).toBe(false);
  });
});

describe('getTickerPxPerMs', () => {
  it('scrolls one content width over the configured duration', () => {
    expect(getTickerPxPerMs(900, 20)).toBeCloseTo(0.045);
  });

  it('returns zero for invalid measurements', () => {
    expect(getTickerPxPerMs(0, 20)).toBe(0);
    expect(getTickerPxPerMs(900, 0)).toBe(0);
  });
});

describe('shouldAnimateTicker', () => {
  const active = {
    isDestroyed: false,
    isIntersecting: true,
    isDocumentVisible: true,
    reducedMotion: false,
    isPaused: false,
    pxPerMs: 1,
  };

  it('runs only when the ticker is visible and active', () => {
    expect(shouldAnimateTicker(active)).toBe(true);
  });

  it.each([
    ['destroyed', { isDestroyed: true }],
    ['offscreen', { isIntersecting: false }],
    ['in a hidden document', { isDocumentVisible: false }],
    ['reduced motion', { reducedMotion: true }],
    ['paused', { isPaused: true }],
    ['not measured', { pxPerMs: 0 }],
  ])('stops when %s', (_label, change) => {
    expect(shouldAnimateTicker({ ...active, ...change })).toBe(false);
  });
});
