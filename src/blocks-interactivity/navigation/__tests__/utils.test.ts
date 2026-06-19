/**
 * Tests for the desktop navigation utility helpers.
 *
 * @jest-environment jsdom
 */

import {
  clearHoverTimeouts,
  createHoverIntent,
  focusMenuItem,
  generateNavId,
  getAnnouncerId,
  isValidNavId,
} from '../utils';

describe('id helpers', () => {
  it('generateNavId is prefixed and unique', () => {
    const a = generateNavId();
    const b = generateNavId();
    expect(a).toMatch(/^nav-/);
    expect(a).not.toBe(b);
  });

  it('getAnnouncerId derives a per-nav id, with a fallback for empty', () => {
    expect(getAnnouncerId('shop')).toBe('navigation-announcer-shop');
    expect(getAnnouncerId('')).toBe('navigation-announcer');
  });

  it('isValidNavId only accepts non-empty strings', () => {
    expect(isValidNavId('nav-1')).toBe(true);
    expect(isValidNavId('')).toBe(false);
    expect(isValidNavId(null)).toBe(false);
    expect(isValidNavId(123)).toBe(false);
  });
});

describe('focusMenuItem (roving tabindex)', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('puts tabindex=0 on the target and -1 on the rest', () => {
    document.body.innerHTML =
      '<a href="#" id="a"></a><a href="#" id="b"></a><a href="#" id="c"></a>';
    const items = Array.from(document.querySelectorAll('a')) as HTMLElement[];

    focusMenuItem(items, 1);
    expect(items.map(i => i.getAttribute('tabindex'))).toEqual([
      '-1',
      '0',
      '-1',
    ]);
  });

  it('clamps the index to the valid range', () => {
    document.body.innerHTML = '<a href="#"></a><a href="#"></a>';
    const items = Array.from(document.querySelectorAll('a')) as HTMLElement[];

    focusMenuItem(items, 99);
    expect(items[1].getAttribute('tabindex')).toBe('0');

    focusMenuItem(items, -5);
    expect(items[0].getAttribute('tabindex')).toBe('0');
  });

  it('is a no-op for an empty list', () => {
    expect(() => focusMenuItem([], 0)).not.toThrow();
  });
});

describe('hover intent', () => {
  it('createHoverIntent starts with no timers', () => {
    expect(createHoverIntent()).toEqual({
      openTimeout: null,
      closeTimeout: null,
      activeId: null,
    });
  });

  it('clearHoverTimeouts cancels and nulls both timers', () => {
    const spy = jest.spyOn(globalThis, 'clearTimeout');
    const state = createHoverIntent();
    state.openTimeout = setTimeout(() => {}, 1000);
    state.closeTimeout = setTimeout(() => {}, 1000);

    clearHoverTimeouts(state);

    expect(state.openTimeout).toBeNull();
    expect(state.closeTimeout).toBeNull();
    expect(spy).toHaveBeenCalledTimes(2);
    spy.mockRestore();
  });
});
