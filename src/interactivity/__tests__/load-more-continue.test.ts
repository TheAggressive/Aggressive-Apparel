/**
 * @jest-environment jsdom
 */

import {
  isSentinelWithinRootMargin,
  shouldContinueInfiniteScroll,
} from '../load-more-continue';

describe('isSentinelWithinRootMargin', () => {
  it('treats a sentinel still inside rootMargin as near the viewport', () => {
    // Regression: after the first append, mobile sentinels often stay within
    // 400px of the viewport and IntersectionObserver never re-fires.
    expect(isSentinelWithinRootMargin(900, 844, 400)).toBe(true);
    expect(isSentinelWithinRootMargin(1244, 844, 400)).toBe(true);
  });

  it('returns false once the sentinel is beyond the root margin', () => {
    expect(isSentinelWithinRootMargin(1245, 844, 400)).toBe(false);
  });
});

describe('shouldContinueInfiniteScroll', () => {
  const base = {
    mode: 'infinite_scroll',
    isLoading: false,
    allLoaded: false,
    nextCursor: 'cursor-token',
    sentinelHidden: false,
    sentinelTop: 900,
    viewportHeight: 844,
    msSinceLastFetch: 500,
    cooldownMs: 400,
  };

  it('loads again when the sentinel remains near after an append', () => {
    expect(shouldContinueInfiniteScroll(base)).toBe('load');
  });

  it('waits when still inside the fetch cooldown window', () => {
    expect(
      shouldContinueInfiniteScroll({
        ...base,
        msSinceLastFetch: 100,
      })
    ).toBe('wait');
  });

  it('stops when exhausted, loading, filtered mode, or sentinel left', () => {
    expect(shouldContinueInfiniteScroll({ ...base, allLoaded: true })).toBe(
      'stop'
    );
    expect(shouldContinueInfiniteScroll({ ...base, isLoading: true })).toBe(
      'stop'
    );
    expect(shouldContinueInfiniteScroll({ ...base, nextCursor: '' })).toBe(
      'stop'
    );
    expect(shouldContinueInfiniteScroll({ ...base, mode: 'load_more' })).toBe(
      'stop'
    );
    expect(
      shouldContinueInfiniteScroll({ ...base, sentinelHidden: true })
    ).toBe('stop');
    expect(
      shouldContinueInfiniteScroll({
        ...base,
        sentinelTop: 2000,
      })
    ).toBe('stop');
  });
});
