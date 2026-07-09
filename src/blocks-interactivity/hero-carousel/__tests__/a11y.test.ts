/**
 * @jest-environment jsdom
 */

import {
  announceSlide,
  focusSlide,
  isSlideFocused,
  syncDeepLinkHash,
} from '../a11y';
import { CLONE_ATTR } from '../constants';

describe('announceSlide', () => {
  it('writes the slide position into the live region', () => {
    const live = document.createElement('div');
    announceSlide(live, 'Slide %1$s of %2$s', 1, 5, false);
    expect(live.textContent).toBe('Slide 2 of 5');
  });

  it('is a no-op when suppressed (active autoplay)', () => {
    const live = document.createElement('div');
    live.textContent = 'unchanged';
    announceSlide(live, 'Slide %1$s of %2$s', 2, 5, true);
    expect(live.textContent).toBe('unchanged');
  });

  it('is a no-op without an announcer node', () => {
    expect(() =>
      announceSlide(null, 'Slide %1$s of %2$s', 0, 3, false)
    ).not.toThrow();
  });
});

describe('syncDeepLinkHash', () => {
  const original = window.location.hash;

  afterEach(() => {
    window.history.replaceState(null, '', original || ' ');
  });

  it('writes the slide hash when deep linking is enabled', () => {
    syncDeepLinkHash('promo', 2, true);
    expect(window.location.hash).toBe('#promo-slide-3');
  });

  it('does nothing when disabled or id is empty', () => {
    window.history.replaceState(null, '', '#keep');
    syncDeepLinkHash('promo', 0, false);
    expect(window.location.hash).toBe('#keep');
    syncDeepLinkHash('', 0, true);
    expect(window.location.hash).toBe('#keep');
  });
});

describe('isSlideFocused', () => {
  it('is true only for a real slide inside the carousel root', () => {
    const root = document.createElement('section');
    const slide = document.createElement('div');
    slide.className = 'aa-hero__slide';
    slide.tabIndex = 0;
    root.appendChild(slide);
    document.body.appendChild(root);
    slide.focus();

    expect(isSlideFocused(root)).toBe(true);
    expect(isSlideFocused(root, document.createElement('button'))).toBe(false);

    slide.setAttribute(CLONE_ATTR, 'first');
    expect(isSlideFocused(root, slide)).toBe(false);

    root.remove();
  });
});

describe('focusSlide', () => {
  it('focuses the slide after two animation frames', () => {
    jest.useFakeTimers();
    const raf = jest
      .spyOn(window, 'requestAnimationFrame')
      .mockImplementation(
        cb => window.setTimeout(() => cb(0), 0) as unknown as number
      );

    const slide = document.createElement('div');
    slide.className = 'aa-hero__slide';
    slide.tabIndex = 0;
    document.body.appendChild(slide);

    focusSlide(slide);
    expect(document.activeElement).not.toBe(slide);
    jest.runAllTimers();
    expect(document.activeElement).toBe(slide);

    raf.mockRestore();
    slide.remove();
    jest.useRealTimers();
  });

  it('is a no-op for a missing slide', () => {
    expect(() => focusSlide(null)).not.toThrow();
  });
});
