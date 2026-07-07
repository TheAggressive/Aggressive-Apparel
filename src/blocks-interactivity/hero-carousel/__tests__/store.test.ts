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

import '../view';

const config = mockStoreConfig as MockStoreConfig;
const actions = config.actions;
const callbacks = config.callbacks;
const state = config.state;

interface TestContext {
  activeIndex: number;
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
  return {
    activeIndex: 0,
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
}

function buildCarousel(count = 3): HTMLElement {
  const root = document.createElement('section');
  root.className = 'wp-block-aggressive-apparel-hero-carousel';
  const track = document.createElement('div');
  track.className = 'aa-hero__track';
  for (let i = 0; i < count; i++) {
    const slide = document.createElement('div');
    slide.className = 'aa-hero__slide';
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
