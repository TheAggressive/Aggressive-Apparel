/**
 * @jest-environment jsdom
 */

import {
  isSentinelWithinRootMargin,
  retryDelayMs,
  shouldContinueInfiniteScroll,
  shouldHideLoadMoreButton,
} from '../continue';

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
    nextUrl: 'https://store.test/shop/page/2/',
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
    expect(shouldContinueInfiniteScroll({ ...base, nextUrl: '' })).toBe('stop');
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

describe('shouldHideLoadMoreButton', () => {
  it('hides the button in infinite_scroll mode even when more products remain', () => {
    expect(shouldHideLoadMoreButton('infinite_scroll', false)).toBe(true);
  });

  it('shows the button in load_more mode until the catalogue is exhausted', () => {
    expect(shouldHideLoadMoreButton('load_more', false)).toBe(false);
    expect(shouldHideLoadMoreButton('load_more', true)).toBe(true);
  });
});

describe('retryDelayMs', () => {
  const now = Date.parse('2026-10-17T06:00:00Z');

  it('honours Retry-After in seconds or as an HTTP date', () => {
    expect(retryDelayMs('5', 0, now)).toBe(5000);
    expect(retryDelayMs('Sat, 17 Oct 2026 06:00:07 GMT', 0, now)).toBe(7000);
  });

  it('backs off exponentially without a usable header', () => {
    expect(retryDelayMs(null, 0, now)).toBe(2000);
    expect(retryDelayMs('soon', 1, now)).toBe(4000);
    expect(retryDelayMs(null, 2, now)).toBe(8000);
  });

  it('never waits less than 1s or more than 10s', () => {
    expect(retryDelayMs('0', 0, now)).toBe(1000);
    expect(retryDelayMs('3600', 0, now)).toBe(10_000);
    expect(retryDelayMs('Sat, 17 Oct 2026 05:00:00 GMT', 0, now)).toBe(1000);
  });
});
