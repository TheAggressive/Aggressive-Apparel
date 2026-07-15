/**
 * Tests for shared light/dark color-scheme persistence and resolution.
 *
 * @jest-environment jsdom
 */

import {
  COLOR_SCHEME_STORAGE_KEY,
  getStoredColorScheme,
  hasStoredColorScheme,
  resolveColorScheme,
  storeColorScheme,
} from '../color-scheme-storage';

function mediaQuery(matches: boolean): MediaQueryList {
  return { matches } as MediaQueryList;
}

describe('storeColorScheme', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('persists to the canonical key', () => {
    storeColorScheme('dark');

    expect(localStorage.getItem(COLOR_SCHEME_STORAGE_KEY)).toBe('dark');
  });

  it('swallows storage errors (private browsing / quota)', () => {
    const setItem = jest
      .spyOn(Storage.prototype, 'setItem')
      .mockImplementation(() => {
        throw new Error('QuotaExceededError');
      });

    expect(() => storeColorScheme('dark')).not.toThrow();

    setItem.mockRestore();
  });
});

describe('getStoredColorScheme', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('returns null when nothing is stored', () => {
    expect(getStoredColorScheme()).toBeNull();
    expect(hasStoredColorScheme()).toBe(false);
  });

  it('returns the canonical key', () => {
    localStorage.setItem(COLOR_SCHEME_STORAGE_KEY, 'light');

    expect(getStoredColorScheme()).toBe('light');
  });

  it('ignores invalid stored values', () => {
    localStorage.setItem(COLOR_SCHEME_STORAGE_KEY, 'blue');

    expect(getStoredColorScheme()).toBeNull();
  });
});

describe('resolveColorScheme', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('returns the manual preference when stored', () => {
    localStorage.setItem(COLOR_SCHEME_STORAGE_KEY, 'dark');

    expect(resolveColorScheme(mediaQuery(false))).toEqual({
      scheme: 'dark',
      isSystemPreference: false,
    });
  });

  it('falls back to the OS preference when nothing is stored', () => {
    expect(resolveColorScheme(mediaQuery(true))).toEqual({
      scheme: 'dark',
      isSystemPreference: true,
    });
    expect(resolveColorScheme(mediaQuery(false))).toEqual({
      scheme: 'light',
      isSystemPreference: true,
    });
  });
});
