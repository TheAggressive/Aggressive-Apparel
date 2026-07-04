/**
 * Pure behavior tests for horizontal-scroll geometry and mode decisions.
 *
 * @jest-environment jsdom
 */

import {
  addMediaChangeListener,
  buildSlideStops,
  clamp,
  computeProgress,
  computeScrollStart,
  formatSlideAnnouncement,
  getPagedSnapTarget,
  getProximitySnapTarget,
  getSnapStrengthPreset,
  getSlideIndexFromProgress,
  getSlides,
  getSlideTarget,
  isEditableTarget,
  pickMode,
  progressToPercentage,
  resolveSpeed,
  shouldShowSwipeHint,
  toLogicalSlideOffsets,
  toSignedTranslate,
} from '../logic';

describe('clamp', () => {
  it('keeps in-range values and clamps to the bounds', () => {
    expect(clamp(5, 0, 10)).toBe(5);
    expect(clamp(-3, 0, 10)).toBe(0);
    expect(clamp(42, 0, 10)).toBe(10);
  });
});

describe('resolveSpeed', () => {
  it('prefers valid context and falls back to CSS or 1', () => {
    expect(resolveSpeed(2, Number.NaN)).toBe(2);
    expect(resolveSpeed(0, 1.5)).toBe(1.5);
    expect(resolveSpeed(Number.NaN, Number.NaN)).toBe(1);
  });

  it('clamps to the supported range', () => {
    expect(resolveSpeed(10, 1)).toBe(3);
    expect(resolveSpeed(0.1, 1)).toBe(0.5);
  });
});

describe('pickMode', () => {
  const base = {
    reducedMotion: false,
    desktopMatches: true,
    maxTranslate: 800,
  };

  it('uses native-progress pinning on desktop', () => {
    expect(pickMode(base)).toBe('pinned');
    expect(pickMode({ ...base, snapBehavior: 'paged' })).toBe('paged');
  });

  it('uses native scrolling for touch and inline desktop', () => {
    expect(pickMode({ ...base, desktopMatches: false })).toBe('native');
    expect(pickMode({ ...base, pinned: false })).toBe('native');
  });

  it('uses static document flow for reduced motion or no overflow', () => {
    expect(pickMode({ ...base, reducedMotion: true })).toBe('static');
    expect(pickMode({ ...base, maxTranslate: 1 })).toBe('static');
  });
});

describe('snap strength presets', () => {
  it('increases both reach and proportional strength predictably', () => {
    expect(getSnapStrengthPreset('soft')).toEqual({
      maxDistance: 240,
      maxSegmentRatio: 0.12,
    });
    expect(getSnapStrengthPreset('medium')).toEqual({
      maxDistance: 480,
      maxSegmentRatio: 0.25,
    });
    expect(getSnapStrengthPreset('strong')).toEqual({
      maxDistance: 720,
      maxSegmentRatio: 0.35,
    });
    expect(getSnapStrengthPreset('aggressive')).toEqual({
      maxDistance: 1600,
      maxSegmentRatio: 0.5,
    });
  });
});

describe('scroll geometry', () => {
  it('maps absolute vertical positions to clamped progress', () => {
    expect(computeProgress(900, 1000, 500)).toBe(0);
    expect(computeProgress(1250, 1000, 500)).toBe(0.5);
    expect(computeProgress(1600, 1000, 500)).toBe(1);
    expect(computeProgress(1200, 1000, 0)).toBe(0);
  });

  it('does not report completion before the real endpoint', () => {
    expect(progressToPercentage(0.994)).toBe(99);
    expect(progressToPercentage(0.9999)).toBe(99);
    expect(progressToPercentage(1)).toBe(100);
  });

  it('accounts for the sticky inset when finding the seat', () => {
    expect(computeScrollStart(1200, 0)).toBe(1200);
    expect(computeScrollStart(1200, 100)).toBe(1100);
  });

  it('builds real slide stops from offsets and clamps the final slide', () => {
    expect(buildSlideStops([0, 632, 1264, 1896], 1496)).toEqual([
      0,
      632 / 1496,
      1264 / 1496,
      1,
    ]);
    expect(buildSlideStops([0, 300], 0)).toEqual([0, 0]);
    expect(buildSlideStops([], 600)).toEqual([]);
  });

  it('finds the nearest slide and its transform target', () => {
    const stops = [0, 0.42, 0.84, 1];
    expect(getSlideIndexFromProgress(0.3, stops)).toBe(1);
    expect(getSlideIndexFromProgress(0.7, stops)).toBe(2);
    expect(getSlideIndexFromProgress(1, stops)).toBe(3);
    expect(getSlideTarget(2, stops, 1000)).toBe(840);
    expect(getSlideTarget(99, stops, 1000)).toBe(1000);
  });
});

describe('getProximitySnapTarget', () => {
  const base = {
    scrollStart: 1000,
    scrollDistance: 2000,
    slideStops: [0, 0.5, 1],
  };

  it('gently aligns a nearby slide', () => {
    expect(getProximitySnapTarget({ ...base, scrollPosition: 1910 })).toEqual({
      index: 1,
      scrollPosition: 2000,
    });
  });

  it('does not pull from beyond the proportional proximity distance', () => {
    expect(
      getProximitySnapTarget({ ...base, scrollPosition: 1700 })
    ).toBeNull();
  });

  it('aligns endpoints from inside but never pulls back after leaving', () => {
    expect(getProximitySnapTarget({ ...base, scrollPosition: 1100 })).toEqual({
      index: 0,
      scrollPosition: 1000,
    });
    expect(getProximitySnapTarget({ ...base, scrollPosition: 2900 })).toEqual({
      index: 2,
      scrollPosition: 3000,
    });
    expect(getProximitySnapTarget({ ...base, scrollPosition: 999 })).toBeNull();
    expect(
      getProximitySnapTarget({ ...base, scrollPosition: 3001 })
    ).toBeNull();
    expect(
      getProximitySnapTarget({
        ...base,
        scrollPosition: 1500,
        slideStops: [0, 1],
      })
    ).toBeNull();
  });

  it('does nothing when already aligned', () => {
    expect(
      getProximitySnapTarget({ ...base, scrollPosition: 2000 })
    ).toBeNull();
  });
});

describe('getPagedSnapTarget', () => {
  const stops = [0, 0.5, 1];

  it('commits after crossing 10% toward an adjacent slide', () => {
    expect(
      getPagedSnapTarget({
        progress: 0.049,
        settledIndex: 0,
        slideStops: stops,
        commitRatio: 0.1,
      })
    ).toBeNull();
    expect(
      getPagedSnapTarget({
        progress: 0.05,
        settledIndex: 0,
        slideStops: stops,
        commitRatio: 0.1,
      })
    ).toBe(1);
    expect(
      getPagedSnapTarget({
        progress: 0.45,
        settledIndex: 1,
        slideStops: stops,
        commitRatio: 0.1,
      })
    ).toBe(0);
  });

  it('never skips more than one adjacent slide', () => {
    expect(
      getPagedSnapTarget({
        progress: 0.95,
        settledIndex: 0,
        slideStops: stops,
      })
    ).toBe(1);
    expect(
      getPagedSnapTarget({
        progress: 0.05,
        settledIndex: 2,
        slideStops: stops,
      })
    ).toBe(1);
  });
});

describe('RTL geometry', () => {
  it('passes LTR offsets through untouched', () => {
    expect(
      toLogicalSlideOffsets({
        offsets: [0, 632, 1264],
        sizes: [600, 600, 600],
        trackSize: 1864,
        rtl: false,
      })
    ).toEqual([0, 632, 1264]);
  });

  it('measures RTL offsets from the inline-start (right) edge', () => {
    // Track 1864 wide, three 600-wide slides: the first slide sits at the
    // physical right (offsetLeft 1264) but logically at inline-start 0.
    expect(
      toLogicalSlideOffsets({
        offsets: [1264, 632, 0],
        sizes: [600, 600, 600],
        trackSize: 1864,
        rtl: true,
      })
    ).toEqual([0, 632, 1264]);
  });

  it('never returns negative logical offsets', () => {
    expect(
      toLogicalSlideOffsets({
        offsets: [10],
        sizes: [200],
        trackSize: 100,
        rtl: true,
      })
    ).toEqual([0]);
  });

  it('signs the track translation per direction', () => {
    expect(toSignedTranslate(123.456, false)).toBe(-123.46);
    expect(toSignedTranslate(123.456, true)).toBe(123.46);
    expect(toSignedTranslate(0, false)).toBe(-0);
  });
});

describe('presentation helpers', () => {
  it('formats slide announcements', () => {
    expect(formatSlideAnnouncement(0, 4)).toBe('Slide 1 of 4');
    expect(formatSlideAnnouncement(3, 4)).toBe('Slide 4 of 4');
  });

  it('fills translated templates and rejects malformed ones', () => {
    expect(formatSlideAnnouncement(1, 5, 'Diapositive %1$s sur %2$s')).toBe(
      'Diapositive 2 sur 5'
    );
    expect(formatSlideAnnouncement(1, 5, '%1$s / %2$s')).toBe('2 / 5');
    // Missing placeholders fall back to the default template.
    expect(formatSlideAnnouncement(1, 5, 'broken template')).toBe(
      'Slide 2 of 5'
    );
    expect(formatSlideAnnouncement(1, 5, '')).toBe('Slide 2 of 5');
  });

  it('shows swipe hints only for an unfinished native carousel', () => {
    expect(
      shouldShowSwipeHint({
        mode: 'native',
        slideCount: 4,
        currentIndex: 0,
        dismissed: false,
        style: 'cue',
      })
    ).toBe(true);
    expect(
      shouldShowSwipeHint({
        mode: 'pinned',
        slideCount: 4,
        currentIndex: 0,
        dismissed: false,
        style: 'cue',
      })
    ).toBe(false);
    expect(
      shouldShowSwipeHint({
        mode: 'native',
        slideCount: 4,
        currentIndex: 3,
        dismissed: false,
        style: 'cue',
      })
    ).toBe(false);
  });
});

describe('DOM helpers', () => {
  it('returns only element children as slides', () => {
    const track = document.createElement('div');
    track.appendChild(document.createElement('div'));
    track.appendChild(document.createTextNode('whitespace'));
    track.appendChild(document.createComment('comment'));
    track.appendChild(document.createElement('section'));
    expect(getSlides(track)).toHaveLength(2);
  });

  it('recognizes editable targets', () => {
    expect(isEditableTarget(document.createElement('input'))).toBe(true);
    expect(isEditableTarget(document.createElement('select'))).toBe(true);
    expect(isEditableTarget(document.createElement('div'))).toBe(false);
    expect(isEditableTarget(null)).toBe(false);
  });

  it('supports modern media-query listeners and cleanup', () => {
    const add = jest.fn();
    const remove = jest.fn();
    const media = {
      addEventListener: add,
      removeEventListener: remove,
    } as unknown as MediaQueryList;
    const listener = jest.fn();
    const cleanup = addMediaChangeListener(media, listener);

    add.mock.calls[0][1]();
    expect(listener).toHaveBeenCalledTimes(1);
    cleanup();
    expect(remove).toHaveBeenCalledWith('change', expect.any(Function));
  });

  it('supports legacy media-query listeners and cleanup', () => {
    const addListener = jest.fn();
    const removeListener = jest.fn();
    const media = {
      addEventListener: undefined,
      addListener,
      removeListener,
    } as unknown as MediaQueryList;
    const cleanup = addMediaChangeListener(media, jest.fn());

    expect(addListener).toHaveBeenCalled();
    cleanup();
    expect(removeListener).toHaveBeenCalled();
  });
});
