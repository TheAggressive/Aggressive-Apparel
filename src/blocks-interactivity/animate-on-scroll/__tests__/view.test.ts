/**
 * Tests for animate-on-scroll stagger math and observer wiring.
 *
 * @jest-environment jsdom
 */

interface StoreConfig {
  callbacks: {
    initObserver: () => (() => void) | undefined;
  };
}

interface MockHolder {
  config: StoreConfig | undefined;
  ctx: Record<string, unknown>;
  ref: HTMLElement | null;
}

// State lives inside the mocked module: the hoisted `import '../view'`
// runs store() before any test-file variable initializers would.
jest.mock(
  '@wordpress/interactivity',
  () => {
    const holder: MockHolder = { config: undefined, ctx: {}, ref: null };
    return {
      __holder: holder,
      store: (_name: string, config: StoreConfig) => {
        holder.config = config;
        return config;
      },
      getContext: () => holder.ctx,
      getElement: () => ({ ref: holder.ref }),
    };
  },
  { virtual: true }
);

const holder = jest.requireMock('@wordpress/interactivity')
  .__holder as MockHolder;

// Capture IntersectionObserver instances so tests can drive entries.
type ObserverCallback = (entries: Partial<IntersectionObserverEntry>[]) => void;
const observers: Array<{
  callback: ObserverCallback;
  options: IntersectionObserverInit;
  disconnect: jest.Mock;
}> = [];

class MockIntersectionObserver {
  callback: ObserverCallback;
  options: IntersectionObserverInit;
  disconnect = jest.fn();
  observe = jest.fn();
  unobserve = jest.fn();

  constructor(callback: ObserverCallback, options: IntersectionObserverInit) {
    this.callback = callback;
    this.options = options;
    observers.push(this);
  }
}

Object.defineProperty(window, 'IntersectionObserver', {
  writable: true,
  value: MockIntersectionObserver,
});

Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: jest.fn().mockReturnValue({
    matches: false,
    addEventListener: jest.fn(),
    removeEventListener: jest.fn(),
  }),
});

import { calculateSequentialDelay, calculateWaveDelay } from '../view';

describe('calculateWaveDelay', () => {
  it('produces distinct delays for three children (old sine formula collapsed them)', () => {
    const delays = [0, 1, 2].map(i => calculateWaveDelay(i, 0.2, 1, false, 3));
    expect(new Set(delays).size).toBeGreaterThan(1);
  });

  it('starts the wave at zero delay', () => {
    expect(calculateWaveDelay(0, 0.2, 1, false, 5)).toBe(0);
  });

  it('scales the spread with the number of children', () => {
    const smallMax = Math.max(
      ...[0, 1, 2].map(i => calculateWaveDelay(i, 0.2, 1, false, 3))
    );
    const largeMax = Math.max(
      ...Array.from({ length: 9 }, (_, i) =>
        calculateWaveDelay(i, 0.2, 1, false, 9)
      )
    );
    expect(largeMax).toBeGreaterThan(smallMax);
  });

  it('reverses the ramp for exit animations', () => {
    const forward = calculateWaveDelay(0, 0.2, 1, false, 4);
    const reversed = calculateWaveDelay(3, 0.2, 1, true, 4);
    expect(reversed).toBe(forward);
  });
});

describe('calculateSequentialDelay', () => {
  it('increments per child and reverses cleanly', () => {
    expect(calculateSequentialDelay(2, 0.2, false, 4)).toBeCloseTo(0.4);
    expect(calculateSequentialDelay(2, 0.2, true, 4)).toBeCloseTo(0.2);
  });
});

describe('initObserver reverse-on-scroll-back', () => {
  const setup = (ctxOverrides: Record<string, unknown> = {}) => {
    observers.length = 0;
    holder.ref = document.createElement('div');
    holder.ref.className = 'wp-block-animate-on-scroll';
    document.body.appendChild(holder.ref);
    holder.ctx = {
      isVisible: false,
      hasAnimated: false,
      isExiting: false,
      debugMode: false,
      visibilityTrigger: '0.3',
      detectionBoundary: {
        top: '100%',
        right: '0%',
        bottom: '-25%',
        left: '0%',
      },
      id: 'test',
      reverseOnScrollBack: true,
      announceToScreenReader: false,
      ...ctxOverrides,
    };
    const cleanup = holder.config!.callbacks.initObserver();
    return { observer: observers[0], cleanup };
  };

  afterEach(() => {
    document.body.innerHTML = '';
    jest.useRealTimers();
  });

  it('registers exit hysteresis thresholds alongside the entry threshold', () => {
    const { observer } = setup();
    expect(observer.options.threshold).toEqual([0, 0.15, 0.3]);
  });

  it('exits while the element is still partially visible', () => {
    const { observer } = setup();

    observer.callback([{ intersectionRatio: 0.4, isIntersecting: true }]);
    expect(holder.ctx.isVisible).toBe(true);
    expect(holder.ctx.hasAnimated).toBe(true);

    // Scrolling away: ratio drops to the exit threshold but the element
    // is still intersecting — the reverse animation must start NOW, not
    // after it has fully left the screen.
    observer.callback([{ intersectionRatio: 0.1, isIntersecting: true }]);
    expect(holder.ctx.isVisible).toBe(false);
    expect(holder.ctx.isExiting).toBe(true);
  });

  it('does not exit in the hysteresis band between the two thresholds', () => {
    const { observer } = setup();
    observer.callback([{ intersectionRatio: 0.4, isIntersecting: true }]);
    observer.callback([{ intersectionRatio: 0.2, isIntersecting: true }]);
    expect(holder.ctx.isVisible).toBe(true);
    expect(holder.ctx.isExiting).toBe(false);
  });

  it('clears a pending exit when the element re-enters quickly', () => {
    jest.useFakeTimers();
    const { observer } = setup();

    observer.callback([{ intersectionRatio: 0.4, isIntersecting: true }]);
    observer.callback([{ intersectionRatio: 0.1, isIntersecting: true }]);
    expect(holder.ctx.isExiting).toBe(true);

    observer.callback([{ intersectionRatio: 0.4, isIntersecting: true }]);
    expect(holder.ctx.isExiting).toBe(false);
    expect(holder.ctx.isVisible).toBe(true);

    // The stale exit timeout must not strip anything later.
    jest.runAllTimers();
    expect(holder.ctx.isVisible).toBe(true);
  });

  it('never exits when reverseOnScrollBack is disabled', () => {
    const { observer } = setup({ reverseOnScrollBack: false });
    observer.callback([{ intersectionRatio: 0.4, isIntersecting: true }]);
    observer.callback([{ intersectionRatio: 0, isIntersecting: false }]);
    expect(holder.ctx.isVisible).toBe(true);
  });
});
