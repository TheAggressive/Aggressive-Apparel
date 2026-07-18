/**
 * Tests for ticker pure helpers.
 *
 * @jest-environment jsdom
 */

import {
  getTickerPxPerMs,
  isEffectivelyPaused,
  isTickerPauseControl,
  isTickerReverseDirection,
  parseTickerDataSpeed,
  pickAllowed,
  resolveTickerControlColor,
  shouldAnimateTicker,
} from '../logic';
import { PATTERN_SLUGS, TICKER_DIRECTIONS } from '../constants';

describe('parseTickerDataSpeed', () => {
  it('parses loop duration from data-ticker-speed', () => {
    expect(parseTickerDataSpeed('20')).toBe(20);
  });

  it('falls back when the value is missing or invalid', () => {
    expect(parseTickerDataSpeed(undefined)).toBe(30);
    expect(parseTickerDataSpeed('invalid', 45)).toBe(45);
    expect(parseTickerDataSpeed('0')).toBe(30);
    expect(parseTickerDataSpeed('-5')).toBe(30);
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
    expect(getTickerPxPerMs(-10, 20)).toBe(0);
  });
});

describe('isEffectivelyPaused', () => {
  const running = {
    isPaused: false,
    isHeld: false,
    motionLocked: false,
  };

  it('is false when the marquee is free to run', () => {
    expect(isEffectivelyPaused(running)).toBe(false);
  });

  it.each([
    ['manual pause', { isPaused: true }],
    ['hover/focus hold', { isHeld: true }],
    ['reduced-motion lock', { motionLocked: true }],
  ])('is true for %s', (_label, change) => {
    expect(isEffectivelyPaused({ ...running, ...change })).toBe(true);
  });

  it('keeps manual pause while a hold is cleared', () => {
    expect(
      isEffectivelyPaused({
        isPaused: true,
        isHeld: false,
        motionLocked: false,
      })
    ).toBe(true);
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

describe('pickAllowed', () => {
  it('returns the value when allowlisted', () => {
    expect(pickAllowed('right', TICKER_DIRECTIONS, 'left')).toBe('right');
    expect(pickAllowed('diagonal', PATTERN_SLUGS, 'none')).toBe('diagonal');
  });

  it('falls back when the value is missing or unknown', () => {
    expect(pickAllowed(undefined, TICKER_DIRECTIONS, 'left')).toBe('left');
    expect(pickAllowed('nope', PATTERN_SLUGS, 'none')).toBe('none');
  });
});

describe('isTickerPauseControl', () => {
  it('detects the pause button and its descendants', () => {
    const button = document.createElement('button');
    button.className = 'ticker__pause';
    const icon = document.createElement('span');
    button.appendChild(icon);

    expect(isTickerPauseControl(button)).toBe(true);
    expect(isTickerPauseControl(icon)).toBe(true);
    expect(isTickerPauseControl(document.createElement('div'))).toBe(false);
    expect(isTickerPauseControl(null)).toBe(false);
  });
});

describe('resolveTickerControlColor', () => {
  it('samples the first text-bearing child color', () => {
    const content = document.createElement('div');
    const paragraph = document.createElement('p');
    paragraph.style.color = 'rgb(1, 2, 3)';
    content.appendChild(paragraph);
    document.body.appendChild(content);

    expect(resolveTickerControlColor(content)).toBe('rgb(1, 2, 3)');

    content.remove();
  });

  it('returns empty when content is missing', () => {
    expect(resolveTickerControlColor(null)).toBe('');
  });
});
