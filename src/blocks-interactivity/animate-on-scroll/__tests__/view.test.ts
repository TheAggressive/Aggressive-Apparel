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

import {
  calculateRandomDelay,
  calculateSequentialDelay,
  calculateWaveDelay,
  getExitHoldMs,
  getMaxChildStaggerDelaySeconds,
  parseCssTimeSeconds,
} from '../view';

describe('parseCssTimeSeconds', () => {
  it('parses seconds and milliseconds', () => {
    expect(parseCssTimeSeconds('0.5s', 0)).toBeCloseTo(0.5);
    expect(parseCssTimeSeconds('200ms', 0)).toBeCloseTo(0.2);
  });

  it('falls back for empty or invalid values', () => {
    expect(parseCssTimeSeconds('', 0.5)).toBe(0.5);
    expect(parseCssTimeSeconds('nope', 0.5)).toBe(0.5);
  });
});

describe('getExitHoldMs', () => {
  it('adds duration, initial delay, and max stagger', () => {
    expect(getExitHoldMs(0.5, 0.1, 0.4)).toBe(1000);
  });
});

describe('getMaxChildStaggerDelaySeconds', () => {
  it('reads the largest inline stagger delay', () => {
    const wrap = document.createElement('div');
    const a = document.createElement('p');
    const b = document.createElement('p');
    a.style.setProperty('--wp-block-animate-on-scroll-stagger-delay', '0.2s');
    b.style.setProperty('--wp-block-animate-on-scroll-stagger-delay', '0.6s');
    wrap.append(a, b);
    expect(getMaxChildStaggerDelaySeconds(wrap)).toBeCloseTo(0.6);
  });
});

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

describe('calculateRandomDelay', () => {
  it('is stable for the same seed and index', () => {
    const a = calculateRandomDelay(1, 0, 0.5, 42, false, 3);
    const b = calculateRandomDelay(1, 0, 0.5, 42, false, 3);
    expect(a).toBe(b);
  });

  it('differs across child indices', () => {
    const delays = [0, 1, 2].map(i =>
      calculateRandomDelay(i, 0, 0.5, 99, false, 3)
    );
    expect(new Set(delays.map(d => d.toFixed(6))).size).toBe(3);
  });

  it('reverse reuses the peer child’s delay', () => {
    const forwardLast = calculateRandomDelay(2, 0, 0.5, 7, false, 3);
    const reverseFirst = calculateRandomDelay(0, 0, 0.5, 7, true, 3);
    expect(reverseFirst).toBe(forwardLast);
  });
});

describe('initObserver stagger children', () => {
  afterEach(() => {
    document.body.innerHTML = '';
  });

  it('writes sequential stagger delays onto each direct child', () => {
    observers.length = 0;
    holder.ref = document.createElement('div');
    holder.ref.className = 'wp-block-animate-on-scroll';
    holder.ref.dataset.staggerChildren = 'true';
    for (let i = 0; i < 3; i++) {
      holder.ref.appendChild(document.createElement('p'));
    }
    document.body.appendChild(holder.ref);
    holder.ctx = {
      isVisible: false,
      hasAnimated: false,
      isExiting: false,
      debugMode: false,
      visibilityTrigger: '0.3',
      detectionBoundary: {
        top: '0%',
        right: '0%',
        bottom: '0%',
        left: '0%',
      },
      id: 'stagger-test',
      reverseOnScrollBack: false,
      announceToScreenReader: false,
      staggerPattern: 'sequential',
      staggerDelay: 0.2,
    };

    holder.config!.callbacks.initObserver();

    const delays = [...holder.ref.children].map(child =>
      (child as HTMLElement).style.getPropertyValue(
        '--wp-block-animate-on-scroll-stagger-delay'
      )
    );
    expect(delays).toEqual(['0s', '0.2s', '0.4s']);
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

  it('holds is-exiting until duration plus max stagger elapses', () => {
    jest.useFakeTimers();
    observers.length = 0;
    holder.ref = document.createElement('div');
    holder.ref.className = 'wp-block-animate-on-scroll';
    holder.ref.dataset.staggerChildren = 'true';
    holder.ref.style.setProperty(
      '--wp-block-animate-on-scroll-animation-duration',
      '0.5s'
    );
    holder.ref.style.setProperty(
      '--wp-block-animate-on-scroll-initial-delay',
      '0s'
    );
    for (let i = 0; i < 3; i++) {
      holder.ref.appendChild(document.createElement('p'));
    }
    document.body.appendChild(holder.ref);
    holder.ctx = {
      isVisible: false,
      hasAnimated: false,
      isExiting: false,
      debugMode: false,
      visibilityTrigger: '0.3',
      detectionBoundary: {
        top: '0%',
        right: '0%',
        bottom: '0%',
        left: '0%',
      },
      id: 'exit-stagger',
      reverseOnScrollBack: true,
      announceToScreenReader: false,
      staggerPattern: 'sequential',
      staggerDelay: 0.2,
    };

    holder.config!.callbacks.initObserver();
    const observer = observers[0];

    observer.callback([{ intersectionRatio: 0.4, isIntersecting: true }]);
    observer.callback([{ intersectionRatio: 0.1, isIntersecting: true }]);
    expect(holder.ctx.isExiting).toBe(true);

    // Duration alone (0.5s) must not clear exit — last child waits 0.4s more.
    jest.advanceTimersByTime(500);
    expect(holder.ctx.isExiting).toBe(true);

    jest.advanceTimersByTime(400);
    expect(holder.ctx.isExiting).toBe(false);
  });
});

describe('initObserver reduced motion', () => {
  afterEach(() => {
    document.body.innerHTML = '';
    (window.matchMedia as jest.Mock).mockReturnValue({
      matches: false,
      addEventListener: jest.fn(),
      removeEventListener: jest.fn(),
    });
  });

  it('shows content immediately and skips the observer', () => {
    (window.matchMedia as jest.Mock).mockReturnValue({
      matches: true,
      addEventListener: jest.fn(),
      removeEventListener: jest.fn(),
    });
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
        top: '0%',
        right: '0%',
        bottom: '0%',
        left: '0%',
      },
      id: 'reduced',
      reverseOnScrollBack: false,
      respectReducedMotion: true,
      announceToScreenReader: false,
    };

    holder.config!.callbacks.initObserver();

    expect(holder.ctx.isVisible).toBe(true);
    expect(holder.ctx.hasAnimated).toBe(true);
    expect(observers).toHaveLength(0);
  });
});
