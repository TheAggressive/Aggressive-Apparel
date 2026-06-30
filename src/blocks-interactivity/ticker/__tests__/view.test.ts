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

import { shouldAnimateTicker } from '../view';

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
