/**
 * Tests for the horizontal-scroll block's pure logic (speed resolution, mode
 * selection, progress mapping, and the small DOM/media helpers).
 *
 * @jest-environment jsdom
 */

// view.ts calls store() at module load; mock the WP runtime virtually.
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
  addMediaChangeListener,
  clamp,
  computeProgress,
  getSlides,
  isEditableTarget,
  pickMode,
  resolveSpeed,
} from '../view';

describe('clamp', () => {
  it('keeps in-range values and clamps to the bounds', () => {
    expect(clamp(5, 0, 10)).toBe(5);
    expect(clamp(-3, 0, 10)).toBe(0);
    expect(clamp(42, 0, 10)).toBe(10);
  });
});

describe('resolveSpeed', () => {
  it('prefers a valid context speed', () => {
    expect(resolveSpeed(2, Number.NaN)).toBe(2);
  });

  it('falls back to the CSS speed when context is invalid', () => {
    expect(resolveSpeed(0, 1.5)).toBe(1.5);
    expect(resolveSpeed(Number.NaN, 2)).toBe(2);
  });

  it('defaults to 1 when neither is usable', () => {
    expect(resolveSpeed(Number.NaN, Number.NaN)).toBe(1);
  });

  it('clamps to the supported [0.5, 3] range', () => {
    expect(resolveSpeed(10, Number.NaN)).toBe(3);
    expect(resolveSpeed(0.1, Number.NaN)).toBe(0.5);
  });
});

describe('pickMode', () => {
  const base = {
    reducedMotion: false,
    desktopMatches: true,
    maxTranslate: 800,
    scrollDistance: 800,
  };

  it('is static when reduced motion is requested', () => {
    expect(pickMode({ ...base, reducedMotion: true })).toBe('static');
  });

  it('is static when there is nothing to scroll', () => {
    expect(pickMode({ ...base, maxTranslate: 0 })).toBe('static');
    expect(pickMode({ ...base, scrollDistance: 1 })).toBe('static');
  });

  it('is desktop on a fine pointer / wide viewport', () => {
    expect(pickMode(base)).toBe('desktop');
  });

  it('is snap otherwise (touch / coarse pointer)', () => {
    expect(pickMode({ ...base, desktopMatches: false })).toBe('snap');
  });

  it('defaults to pinned (desktop) when pinned is omitted', () => {
    expect(pickMode(base)).toBe('desktop');
  });

  it('uses the inline snap carousel on desktop when not pinned', () => {
    expect(pickMode({ ...base, pinned: false })).toBe('snap');
  });

  it('still respects reduced motion / nothing-to-scroll when inline', () => {
    expect(pickMode({ ...base, pinned: false, reducedMotion: true })).toBe(
      'static'
    );
    expect(pickMode({ ...base, pinned: false, maxTranslate: 0 })).toBe(
      'static'
    );
  });
});

describe('computeProgress', () => {
  it('is 0 when there is no scroll distance', () => {
    expect(computeProgress(0, -100, 0)).toBe(0);
  });

  it('maps vertical position to 0–1 linearly', () => {
    expect(computeProgress(0, -250, 500)).toBe(0.5);
    expect(computeProgress(0, -500, 500)).toBe(1);
  });

  it('honours a sticky-top offset (activation position)', () => {
    // Pinned 100px down: progress is 0 until the block top passes that line.
    expect(computeProgress(100, 100, 500)).toBe(0);
    expect(computeProgress(100, -150, 500)).toBe(0.5);
  });

  it('clamps out-of-range values', () => {
    expect(computeProgress(0, 100, 500)).toBe(0); // before start
    expect(computeProgress(0, -9999, 500)).toBe(1); // past end
  });
});

describe('getSlides', () => {
  it('returns only element children, ignoring text/comment nodes', () => {
    const track = document.createElement('div');
    track.appendChild(document.createElement('div'));
    track.appendChild(document.createTextNode('whitespace'));
    track.appendChild(document.createComment('c'));
    track.appendChild(document.createElement('section'));

    const slides = getSlides(track);
    expect(slides).toHaveLength(2);
    expect(slides.every(s => s instanceof HTMLElement)).toBe(true);
  });
});

describe('isEditableTarget', () => {
  it('is true for form fields and contenteditable', () => {
    expect(isEditableTarget(document.createElement('input'))).toBe(true);
    expect(isEditableTarget(document.createElement('select'))).toBe(true);
    expect(isEditableTarget(document.createElement('textarea'))).toBe(true);

    const editable = document.createElement('div');
    Object.defineProperty(editable, 'isContentEditable', { value: true });
    expect(isEditableTarget(editable)).toBe(true);
  });

  it('is false for non-editable elements and null', () => {
    expect(isEditableTarget(document.createElement('div'))).toBe(false);
    expect(isEditableTarget(null)).toBe(false);
  });
});

describe('addMediaChangeListener', () => {
  it('uses addEventListener and returns a cleanup that removes it', () => {
    const add = jest.fn();
    const remove = jest.fn();
    const mql = {
      addEventListener: add,
      removeEventListener: remove,
    } as unknown as MediaQueryList;
    const listener = jest.fn();

    const cleanup = addMediaChangeListener(mql, listener);
    expect(add).toHaveBeenCalledWith('change', expect.any(Function));

    // The registered handler invokes the listener.
    add.mock.calls[0][1]();
    expect(listener).toHaveBeenCalledTimes(1);

    cleanup();
    expect(remove).toHaveBeenCalledWith('change', expect.any(Function));
  });

  it('falls back to the legacy addListener/removeListener API', () => {
    const addListener = jest.fn();
    const removeListener = jest.fn();
    const mql = {
      addEventListener: undefined,
      addListener,
      removeListener,
    } as unknown as MediaQueryList;

    const cleanup = addMediaChangeListener(mql, jest.fn());
    expect(addListener).toHaveBeenCalled();

    cleanup();
    expect(removeListener).toHaveBeenCalled();
  });
});
