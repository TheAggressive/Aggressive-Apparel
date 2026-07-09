/**
 * Tests for the hero carousel Interactivity store.
 *
 * `@wordpress/interactivity` is virtual-mocked so the store config is
 * captured directly and getContext/getElement are controllable. Fade mode
 * is used so the controller has no scroll-track geometry to satisfy.
 *
 * @jest-environment jsdom
 */

let mockContext: Record<string, unknown> = {};
const mockElement = { ref: null as HTMLElement | null };

type MockHandler = (...args: unknown[]) => unknown;
interface MockStoreConfig {
  state: Record<string, unknown>;
  actions: Record<string, MockHandler>;
  callbacks: Record<string, () => (() => void) | void>;
}

// `var` (not let/const) so the binding is hoist-initialized to undefined before
// view.ts's top-level store() call assigns it during the (Jest-hoisted) import
// below — a let/const would still be in its temporal dead zone at that point.
var mockStoreConfig: MockStoreConfig | undefined;

jest.mock(
  '@wordpress/interactivity',
  () => ({
    store: (_ns: string, config: unknown) => {
      mockStoreConfig = config as MockStoreConfig;
      return config;
    },
    getContext: () => mockContext,
    getElement: () => mockElement,
    withSyncEvent: (fn: unknown) => fn,
  }),
  { virtual: true }
);

class MockIntersectionObserver {
  observe(): void {}
  unobserve(): void {}
  disconnect(): void {}
}

globalThis.IntersectionObserver =
  MockIntersectionObserver as unknown as typeof IntersectionObserver;

window.matchMedia = jest.fn().mockImplementation((query: string) => ({
  matches: false,
  media: query,
  addEventListener: jest.fn(),
  removeEventListener: jest.fn(),
}));

import { MOTION_HOLD_CLASS } from '../constants';
import {
  restoreElementScrollTo,
  stubElementScrollTo,
} from '../test-utils/dom-stubs';
import '../view';

const config = mockStoreConfig as MockStoreConfig;
const actions = config.actions;
const callbacks = config.callbacks;
const state = config.state;

interface TestContext {
  activeIndex: number;
  displayIndex: number;
  slideIndex: number;
  isPlaying: boolean;
  isPaused: boolean;
  autoplay: boolean;
  autoplaySpeed: number;
  loop: boolean;
  pauseOnHover: boolean;
  count: number;
  transition: 'slide' | 'fade' | 'crossfade';
  deepLink: boolean;
  i18n: { play: string; pause: string; slide: string };
}

function makeContext(overrides: Partial<TestContext> = {}): TestContext {
  const ctx: TestContext = {
    activeIndex: 0,
    displayIndex: 0,
    slideIndex: 0,
    isPlaying: false,
    isPaused: false,
    autoplay: false,
    autoplaySpeed: 6000,
    loop: true,
    pauseOnHover: true,
    count: 3,
    transition: 'fade',
    deepLink: false,
    i18n: {
      play: 'Play slideshow',
      pause: 'Pause slideshow',
      slide: 'Slide %1$s of %2$s',
    },
    ...overrides,
  };
  // Keep chrome in sync when a test only overrides activeIndex.
  if (
    overrides.displayIndex === undefined &&
    overrides.activeIndex !== undefined
  ) {
    ctx.displayIndex = overrides.activeIndex;
  }
  return ctx;
}

function buildCarousel(count = 3): HTMLElement {
  const root = document.createElement('section');
  root.className = 'wp-block-aggressive-apparel-hero-carousel';
  const track = document.createElement('div');
  track.className = 'aa-hero__track';
  for (let i = 0; i < count; i++) {
    const slide = document.createElement('div');
    slide.className = 'aa-hero__slide';
    const media = document.createElement('img');
    media.className = 'wp-block-cover__image-background';
    slide.appendChild(media);
    track.appendChild(slide);
  }
  const live = document.createElement('div');
  live.className = 'aa-hero__live';
  root.append(track, live);
  document.body.appendChild(root);
  return root;
}

/** Init the store against a fresh DOM root; returns the destroy cleanup. */
function initCarousel(
  ctx: TestContext,
  id?: string
): {
  root: HTMLElement;
  destroy: () => void;
} {
  const root = buildCarousel(ctx.count);
  if (id) root.id = id;
  mockElement.ref = root;
  mockContext = ctx as unknown as Record<string, unknown>;
  const destroy = callbacks.init() as () => void;
  return { root, destroy };
}

afterEach(() => {
  document.body.innerHTML = '';
  // Strip any deep-link hash without firing hashchange (replaceState never does).
  window.history.replaceState(
    null,
    '',
    window.location.pathname + window.location.search
  );
  jest.useRealTimers();
});

describe('state getters', () => {
  it('derives active slide, inert, and aria-hidden from indices', () => {
    mockContext = makeContext({
      activeIndex: 1,
      slideIndex: 1,
    }) as unknown as Record<string, unknown>;
    expect(state.isActiveSlide).toBe(true);
    expect(state.slideInert).toBe(false);
    expect(state.ariaHiddenSlide).toBe('false');

    mockContext = makeContext({
      activeIndex: 1,
      slideIndex: 2,
    }) as unknown as Record<string, unknown>;
    expect(state.isActiveSlide).toBe(false);
    expect(state.slideInert).toBe(true);
    expect(state.ariaHiddenSlide).toBe('true');
  });

  it('never marks slides inert in slide (scroll-track) mode', () => {
    mockContext = makeContext({
      transition: 'slide',
      activeIndex: 0,
      slideIndex: 2,
    }) as unknown as Record<string, unknown>;
    expect(state.slideInert).toBe(false);
    expect(state.slideTabindex).toBe('0');
  });

  it('treats crossfade as a stacked mode (inert + tabindex on inactive)', () => {
    mockContext = makeContext({
      transition: 'crossfade',
      activeIndex: 0,
      slideIndex: 2,
    }) as unknown as Record<string, unknown>;
    expect(state.slideInert).toBe(true);
    expect(state.ariaHiddenSlide).toBe('true');
    expect(state.slideTabindex).toBe('-1');
  });

  it('reports fraction, play label, and live-region politeness', () => {
    mockContext = makeContext({
      activeIndex: 1,
    }) as unknown as Record<string, unknown>;
    expect(state.fraction).toBe('2 / 3');
    expect(state.playLabel).toBe('Play slideshow');
    expect(state.ariaLive).toBe('polite');

    mockContext = makeContext({
      isPlaying: true,
    }) as unknown as Record<string, unknown>;
    expect(state.playLabel).toBe('Pause slideshow');
    expect(state.ariaLive).toBe('off');
  });

  it('disables arrows at the ends only when not looping', () => {
    mockContext = makeContext({
      loop: false,
      activeIndex: 0,
    }) as unknown as Record<string, unknown>;
    expect(state.prevDisabled).toBe(true);
    expect(state.nextDisabled).toBe(false);

    mockContext = makeContext({
      loop: false,
      activeIndex: 2,
    }) as unknown as Record<string, unknown>;
    expect(state.nextDisabled).toBe(true);

    mockContext = makeContext({
      loop: true,
      activeIndex: 2,
    }) as unknown as Record<string, unknown>;
    expect(state.prevDisabled).toBe(false);
    expect(state.nextDisabled).toBe(false);
  });
});

describe('navigation actions', () => {
  it('next/prev move and wrap when looping', () => {
    const ctx = makeContext();
    const { destroy } = initCarousel(ctx);

    actions.next();
    expect(ctx.activeIndex).toBe(1);

    actions.prev();
    actions.prev();
    expect(ctx.activeIndex).toBe(2);

    actions.next();
    expect(ctx.activeIndex).toBe(0);
    destroy();
  });

  it('goTo jumps to the dot context index', () => {
    const ctx = makeContext();
    const { destroy } = initCarousel(ctx);

    ctx.slideIndex = 2;
    actions.goTo();
    expect(ctx.activeIndex).toBe(2);
    destroy();
  });

  it('announces the slide position via the live region', () => {
    const ctx = makeContext();
    const { root, destroy } = initCarousel(ctx);

    actions.next();
    const live = root.querySelector('.aa-hero__live');
    expect(live?.textContent).toBe('Slide 2 of 3');
    destroy();
  });

  it('handles arrow, Home, and End keys', () => {
    const ctx = makeContext();
    const { destroy } = initCarousel(ctx);
    const key = (k: string) => ({ key: k, preventDefault: jest.fn() });

    actions.handleKeydown(key('ArrowRight'));
    expect(ctx.activeIndex).toBe(1);

    actions.handleKeydown(key('ArrowLeft'));
    expect(ctx.activeIndex).toBe(0);

    actions.handleKeydown(key('End'));
    expect(ctx.activeIndex).toBe(2);

    actions.handleKeydown(key('Home'));
    expect(ctx.activeIndex).toBe(0);
    destroy();
  });

  it('keeps keyboard focus on the newly active slide after arrow navigation', () => {
    jest.useFakeTimers();
    const raf = jest
      .spyOn(window, 'requestAnimationFrame')
      .mockImplementation(
        cb => window.setTimeout(() => cb(0), 0) as unknown as number
      );

    const ctx = makeContext({ transition: 'fade' });
    const { root, destroy } = initCarousel(ctx);
    const slides = Array.from(
      root.querySelectorAll<HTMLElement>(
        '.aa-hero__slide:not([data-aa-hero-clone])'
      )
    );
    const first = slides[0];
    const second = slides[1];
    first?.setAttribute('tabindex', '0');
    second?.setAttribute('tabindex', '0');
    first?.focus();
    expect(document.activeElement).toBe(first);

    actions.handleKeydown({
      key: 'ArrowRight',
      preventDefault: jest.fn(),
      target: first,
    });
    expect(ctx.activeIndex).toBe(1);

    // focusActiveSlide waits two animation frames for inert/tabindex binds.
    jest.runAllTimers();
    expect(document.activeElement).toBe(second);

    raf.mockRestore();
    destroy();
    jest.useRealTimers();
  });
});

describe('slide-change event', () => {
  it('dispatches aa:slide-change with index + count on navigation', () => {
    const ctx = makeContext();
    const { root, destroy } = initCarousel(ctx);
    const handler = jest.fn();
    root.addEventListener('aa:slide-change', handler as EventListener);

    actions.next();
    expect(handler).toHaveBeenCalledTimes(1);
    const event = handler.mock.calls[0][0] as CustomEvent;
    expect(event.detail.index).toBe(1);
    expect(event.detail.count).toBe(3);
    expect(event.bubbles).toBe(true);
    destroy();
  });

  it('does not re-fire when the index does not change', () => {
    const ctx = makeContext();
    const { root, destroy } = initCarousel(ctx);
    const handler = jest.fn();
    root.addEventListener('aa:slide-change', handler as EventListener);

    ctx.slideIndex = 0;
    actions.goTo(); // already on slide 0
    expect(handler).not.toHaveBeenCalled();
    destroy();
  });
});

describe('deep linking', () => {
  it('honors an incoming #<id>-slide-N hash on load', () => {
    window.history.replaceState(null, '', '#promo-slide-3');
    const ctx = makeContext({ deepLink: true });
    const { destroy } = initCarousel(ctx, 'promo');
    expect(ctx.activeIndex).toBe(2);
    destroy();
  });

  it('reflects the active slide in the URL hash', () => {
    const ctx = makeContext({ deepLink: true });
    const { destroy } = initCarousel(ctx, 'promo');
    actions.next();
    expect(window.location.hash).toBe('#promo-slide-2');
    destroy();
  });

  it('leaves the hash untouched when deep linking is off', () => {
    const ctx = makeContext();
    const { destroy } = initCarousel(ctx, 'promo');
    actions.next();
    expect(window.location.hash).toBe('');
    destroy();
  });
});

describe('autoplay', () => {
  it('advances on the configured interval and stops on togglePlay', () => {
    jest.useFakeTimers();
    const ctx = makeContext({ autoplay: true, isPlaying: true });
    const { destroy } = initCarousel(ctx);

    jest.advanceTimersByTime(6000);
    expect(ctx.activeIndex).toBe(1);

    actions.togglePlay();
    expect(ctx.isPlaying).toBe(false);

    jest.advanceTimersByTime(12000);
    expect(ctx.activeIndex).toBe(1);
    destroy();
  });

  it('does not pause autoplay when the track scrolls from goTo/autoplay', () => {
    jest.useFakeTimers();
    stubElementScrollTo({ dispatchScroll: true });

    const ctx = makeContext({
      autoplay: true,
      isPlaying: true,
      transition: 'slide',
    });
    const { root, destroy } = initCarousel(ctx);
    const track = root.querySelector('.aa-hero__track') as HTMLElement;
    Object.defineProperty(track, 'clientWidth', {
      value: 800,
      configurable: true,
    });

    expect(root.classList.contains('is-playing')).toBe(true);

    jest.advanceTimersByTime(6000);
    expect(ctx.activeIndex).toBe(1);

    // Flush scroll-idle debounce — must not pause / restart progress.
    jest.advanceTimersByTime(200);
    expect(ctx.isPaused).toBe(false);
    expect(root.classList.contains('is-playing')).toBe(true);

    // Still advancing on the original interval (not reset by a false pause).
    jest.advanceTimersByTime(6000);
    expect(ctx.activeIndex).toBe(2);
    expect(root.classList.contains('is-playing')).toBe(true);

    restoreElementScrollTo();
    destroy();
  });

  it('does not pause autoplay on a no-op scroll settle (same slide)', () => {
    jest.useFakeTimers();
    stubElementScrollTo({ dispatchScroll: true });

    const ctx = makeContext({
      autoplay: true,
      isPlaying: true,
      transition: 'slide',
    });
    const { root, destroy } = initCarousel(ctx);
    const track = root.querySelector('.aa-hero__track') as HTMLElement;
    Object.defineProperty(track, 'clientWidth', {
      value: 800,
      configurable: true,
    });
    Object.defineProperty(track, 'scrollLeft', {
      value: 800, // physical index 1 = logical 0 with seamless clones.
      configurable: true,
      writable: true,
    });

    // Spurious scroll while already on slide 0 — must not pause.
    track.dispatchEvent(new Event('scroll'));
    jest.advanceTimersByTime(200);
    expect(ctx.isPaused).toBe(false);
    expect(ctx.activeIndex).toBe(0);
    expect(root.classList.contains('is-playing')).toBe(true);

    restoreElementScrollTo();
    destroy();
  });

  it('freezes progress class on pause and resumes remaining dwell', () => {
    jest.useFakeTimers();
    const ctx = makeContext({ autoplay: true, isPlaying: true });
    const { root, destroy } = initCarousel(ctx);

    expect(root.classList.contains('is-playing')).toBe(true);

    // Run halfway through the dwell, then pause.
    jest.advanceTimersByTime(3000);
    expect(ctx.activeIndex).toBe(0);

    actions.pause();
    expect(ctx.isPaused).toBe(true);
    expect(root.classList.contains('is-playing')).toBe(false);

    // Time while paused must not advance the slide.
    jest.advanceTimersByTime(10000);
    expect(ctx.activeIndex).toBe(0);

    actions.resume();
    expect(ctx.isPaused).toBe(false);
    expect(root.classList.contains('is-playing')).toBe(true);

    // Remaining ~3s of the original 6s dwell, then advances.
    jest.advanceTimersByTime(3000);
    expect(ctx.activeIndex).toBe(1);
    destroy();
  });

  it('holds while hovered and resumes after leaving', () => {
    jest.useFakeTimers();
    const ctx = makeContext({ autoplay: true, isPlaying: true });
    const { destroy } = initCarousel(ctx);

    actions.pause();
    expect(ctx.isPaused).toBe(true);
    jest.advanceTimersByTime(12000);
    expect(ctx.activeIndex).toBe(0);

    actions.resume();
    expect(ctx.isPaused).toBe(false);
    jest.advanceTimersByTime(6000);
    expect(ctx.activeIndex).toBe(1);
    destroy();
  });

  it('stops at the last slide when not looping', () => {
    jest.useFakeTimers();
    const ctx = makeContext({
      autoplay: true,
      isPlaying: true,
      loop: false,
      count: 2,
    });
    const { destroy } = initCarousel(ctx);

    jest.advanceTimersByTime(6000);
    expect(ctx.activeIndex).toBe(1);
    expect(ctx.isPlaying).toBe(false);
    destroy();
  });

  it('never starts under reduced motion', () => {
    jest.useFakeTimers();
    (window.matchMedia as jest.Mock).mockImplementation((query: string) => ({
      matches: query.includes('prefers-reduced-motion'),
      media: query,
      addEventListener: jest.fn(),
      removeEventListener: jest.fn(),
    }));

    const ctx = makeContext({ autoplay: true, isPlaying: true });
    const { destroy } = initCarousel(ctx);

    expect(ctx.isPlaying).toBe(false);
    jest.advanceTimersByTime(20000);
    expect(ctx.activeIndex).toBe(0);
    destroy();

    (window.matchMedia as jest.Mock).mockImplementation((query: string) => ({
      matches: false,
      media: query,
      addEventListener: jest.fn(),
      removeEventListener: jest.fn(),
    }));
  });
});

describe('motion hold across transition modes', () => {
  let styleSpy: jest.SpyInstance;

  beforeEach(() => {
    styleSpy = jest.spyOn(window, 'getComputedStyle').mockImplementation(
      (el: Element) =>
        ({
          getPropertyValue: (prop: string) => {
            if (
              el instanceof HTMLElement &&
              el.classList.contains('wp-block-cover__image-background')
            ) {
              if (prop === 'transform') {
                return 'matrix(1.08, 0, 0, 1.08, 0, 0)';
              }
              if (prop === 'filter') return 'none';
              if (prop === 'clip-path') return 'none';
            }
            if (prop === 'object-position') return '50% 50%';
            if (prop === 'direction') return 'ltr';
            return '';
          },
        }) as CSSStyleDeclaration
    );
  });

  afterEach(() => {
    styleSpy.mockRestore();
    restoreElementScrollTo();
  });

  it.each(['fade', 'crossfade', 'slide'] as const)(
    'holds outgoing media transform in %s mode',
    transition => {
      if (transition === 'slide') {
        stubElementScrollTo();
      }

      const ctx = makeContext({ transition });
      const { root, destroy } = initCarousel(ctx);
      const track = root.querySelector('.aa-hero__track') as HTMLElement;
      Object.defineProperty(track, 'clientWidth', {
        value: 800,
        configurable: true,
      });

      const slides = Array.from(
        root.querySelectorAll<HTMLElement>(
          '.aa-hero__slide:not([data-aa-hero-clone])'
        )
      );
      const outgoing = slides[0];
      const incoming = slides[1];
      const media = outgoing?.querySelector<HTMLElement>(
        '.wp-block-cover__image-background'
      );

      actions.next();

      expect(outgoing?.classList.contains(MOTION_HOLD_CLASS)).toBe(true);
      expect(media?.style.getPropertyValue('transform')).toBe(
        'matrix(1.08, 0, 0, 1.08, 0, 0)'
      );
      expect(incoming?.classList.contains(MOTION_HOLD_CLASS)).toBe(false);
      expect(ctx.activeIndex).toBe(1);
      destroy();
    }
  );

  it('releases hold when a held slide becomes active again', () => {
    const ctx = makeContext({ transition: 'fade', loop: true });
    const { root, destroy } = initCarousel(ctx);
    const first = root.querySelector<HTMLElement>(
      '.aa-hero__slide:not([data-aa-hero-clone])'
    );

    actions.next();
    expect(first?.classList.contains(MOTION_HOLD_CLASS)).toBe(true);

    actions.prev();
    expect(first?.classList.contains(MOTION_HOLD_CLASS)).toBe(false);
    expect(
      first
        ?.querySelector<HTMLElement>('.wp-block-cover__image-background')
        ?.style.getPropertyValue('transform')
    ).toBe('');
    destroy();
  });
});
