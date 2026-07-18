/**
 * Tests for ticker preference helpers.
 *
 * @jest-environment jsdom
 */

import {
  canUseHoverPause,
  prefersReducedMotion,
  whenDocumentFontsReady,
} from '../prefs';

describe('prefersReducedMotion', () => {
  it('reads the prefers-reduced-motion media query', () => {
    const matchMedia = jest.fn().mockReturnValue({ matches: true });
    window.matchMedia = matchMedia as typeof window.matchMedia;

    expect(prefersReducedMotion()).toBe(true);
    expect(matchMedia).toHaveBeenCalledWith('(prefers-reduced-motion: reduce)');
  });
});

describe('canUseHoverPause', () => {
  it('requires a fine pointer with real hover', () => {
    const matchMedia = jest.fn().mockReturnValue({ matches: false });
    window.matchMedia = matchMedia as typeof window.matchMedia;

    expect(canUseHoverPause()).toBe(false);
    expect(matchMedia).toHaveBeenCalledWith(
      '(hover: hover) and (pointer: fine)'
    );
  });
});

describe('whenDocumentFontsReady', () => {
  it('resolves immediately when document.fonts is unavailable', async () => {
    const original = document.fonts;
    Object.defineProperty(document, 'fonts', {
      configurable: true,
      value: undefined,
    });

    await expect(whenDocumentFontsReady()).resolves.toBeUndefined();

    Object.defineProperty(document, 'fonts', {
      configurable: true,
      value: original,
    });
  });

  it('waits for document.fonts.ready when available', async () => {
    const original = document.fonts;
    Object.defineProperty(document, 'fonts', {
      configurable: true,
      value: { ready: Promise.resolve() },
    });

    await expect(whenDocumentFontsReady()).resolves.toBeUndefined();

    Object.defineProperty(document, 'fonts', {
      configurable: true,
      value: original,
    });
  });
});
