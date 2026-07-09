/**
 * @jest-environment jsdom
 */

import { MOTION_HOLD_CLASS } from '../constants';
import { clearMotionHolds, holdMotion, releaseMotion } from '../motion-hold';

describe('motion-hold', () => {
  it('pins live transform/filter and marks the slide held', () => {
    const slide = document.createElement('div');
    const media = document.createElement('img');
    media.className = 'wp-block-cover__image-background';
    slide.appendChild(media);

    const styleSpy = jest.spyOn(window, 'getComputedStyle').mockReturnValue({
      getPropertyValue: (prop: string) => {
        if (prop === 'transform') return 'matrix(1.08, 0, 0, 1.08, 0, 0)';
        if (prop === 'filter') return 'blur(0px)';
        if (prop === 'clip-path') return 'none';
        return '';
      },
    } as CSSStyleDeclaration);

    holdMotion(slide);

    expect(slide.classList.contains(MOTION_HOLD_CLASS)).toBe(true);
    expect(media.style.getPropertyValue('transform')).toBe(
      'matrix(1.08, 0, 0, 1.08, 0, 0)'
    );
    expect(media.style.getPropertyValue('filter')).toBe('blur(0px)');
    expect(media.style.getPropertyValue('clip-path')).toBe('');

    styleSpy.mockRestore();
  });

  it('clears held styles on release', () => {
    const slide = document.createElement('div');
    const media = document.createElement('img');
    media.className = 'wp-block-cover__image-background';
    media.style.setProperty('transform', 'scale(1.1)');
    slide.classList.add(MOTION_HOLD_CLASS);
    slide.appendChild(media);

    releaseMotion(slide);

    expect(slide.classList.contains(MOTION_HOLD_CLASS)).toBe(false);
    expect(media.style.getPropertyValue('transform')).toBe('');
  });

  it('clearMotionHolds releases every slide', () => {
    const slides = [0, 1].map(() => {
      const slide = document.createElement('div');
      slide.classList.add(MOTION_HOLD_CLASS);
      return slide;
    });

    clearMotionHolds(slides);

    expect(slides.every(s => !s.classList.contains(MOTION_HOLD_CLASS))).toBe(
      true
    );
  });
});
