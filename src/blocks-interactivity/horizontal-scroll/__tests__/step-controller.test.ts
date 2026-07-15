/**
 * State-machine tests for the step (paged) controller: gesture → eased glide →
 * input-lock → cooldown, plus boundary release, entry settle, keyboard paging,
 * and teardown. A controllable clock drives rAF, setTimeout, and performance.now
 * so the tween and cooldown run deterministically.
 *
 * @jest-environment jsdom
 */
import { StepController } from '../controllers/step';
import type { Geometry, Presentation } from '../controllers/types';

// --- Controllable clock: rAF, setTimeout, and performance.now share one `now`.
let now = 0;
let scrollY = 0;
let rafQueue: Array<{ id: number; cb: FrameRequestCallback }> = [];
let timeoutQueue: Array<{ id: number; cb: () => void; at: number }> = [];
let handleSeq = 0;

function setScrollY(value: number): void {
  scrollY = value;
}

/** Advance the clock, running due timeouts and rAF callbacks at each frame. */
function advance(ms: number, frameMs = 16): void {
  const end = now + ms;
  let guard = 0;
  while (now < end && guard < 10_000) {
    guard += 1;
    now = Math.min(now + frameMs, end);

    const dueTimeouts = timeoutQueue.filter(entry => entry.at <= now);
    timeoutQueue = timeoutQueue.filter(entry => entry.at > now);
    dueTimeouts.forEach(entry => entry.cb());

    const dueFrames = rafQueue;
    rafQueue = [];
    dueFrames.forEach(frame => frame.cb(now));
  }
}

beforeEach(() => {
  now = 0;
  scrollY = 0;
  rafQueue = [];
  timeoutQueue = [];
  handleSeq = 0;

  jest.spyOn(performance, 'now').mockImplementation(() => now);

  window.requestAnimationFrame = (cb: FrameRequestCallback): number => {
    handleSeq += 1;
    rafQueue.push({ id: handleSeq, cb });
    return handleSeq;
  };
  window.cancelAnimationFrame = (id: number): void => {
    rafQueue = rafQueue.filter(frame => frame.id !== id);
  };

  const mockSetTimeout = (handler: TimerHandler, timeout = 0): number => {
    handleSeq += 1;
    timeoutQueue.push({
      id: handleSeq,
      cb: () => (handler as () => void)(),
      at: now + timeout,
    });
    return handleSeq;
  };
  window.setTimeout = mockSetTimeout as typeof window.setTimeout;
  window.clearTimeout = ((id?: number): void => {
    timeoutQueue = timeoutQueue.filter(entry => entry.id !== id);
  }) as typeof window.clearTimeout;

  window.scrollTo = ((
    xOrOptions?: number | ScrollToOptions,
    y?: number
  ): void => {
    if (typeof xOrOptions === 'object' && xOrOptions !== null) {
      scrollY = xOrOptions.top ?? scrollY;
    } else if (typeof y === 'number') {
      scrollY = y;
    }
  }) as typeof window.scrollTo;

  Object.defineProperty(window, 'scrollY', {
    configurable: true,
    get: () => scrollY,
  });
});

afterEach(() => {
  jest.restoreAllMocks();
});

interface TrackedPresentation extends Presentation {
  announcements: number[];
}

function createPresentation(): TrackedPresentation {
  let index = 0;
  const announcements: number[] = [];
  return {
    announcements,
    getIndex: () => index,
    setActive: (next, options) => {
      index = next;
      if (options?.announce) announcements.push(next);
      return index;
    },
    setProgress: () => {},
    dismissSwipeHint: () => {},
  };
}

/** Three slides: stops [0, 0.5, 1] → document stops at 1000, 2000, 3000. */
function createGeometry(slideCount = 3): Geometry {
  const slides = Array.from({ length: slideCount }, () =>
    document.createElement('div')
  );
  return {
    slides,
    slideStops: slides.map((_slide, index) => index / (slideCount - 1)),
    maxTranslate: 1000,
    scrollDistance: 2000,
    scrollStart: 1000,
    rtl: false,
  };
}

function mountController(geometry = createGeometry()) {
  const ref = document.createElement('section');
  const viewport = document.createElement('div');
  ref.appendChild(viewport);
  const presentation = createPresentation();
  const controller = new StepController(
    { ref, viewport },
    presentation,
    geometry
  );
  return { controller, ref, presentation };
}

function wheel(deltaY: number): WheelEvent {
  return new WheelEvent('wheel', { deltaY, cancelable: true });
}

function key(name: string): KeyboardEvent {
  return new KeyboardEvent('keydown', { key: name });
}

describe('StepController', () => {
  it('advances exactly one slide on a wheel gesture in range', () => {
    setScrollY(1000);
    const { controller, ref, presentation } = mountController();

    const event = wheel(120);
    ref.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(true);

    advance(800);
    expect(scrollY).toBeCloseTo(2000, 0);
    expect(presentation.announcements).toEqual([1]);
    expect(ref.dataset.aaHscrollStepState).toBe('ready');
    controller.destroy();
  });

  it('locks out input while a glide is in flight', () => {
    setScrollY(1000);
    const { controller, ref, presentation } = mountController();

    ref.dispatchEvent(wheel(120));
    advance(100); // mid-glide
    expect(ref.dataset.aaHscrollStepState).toBe('locked');

    const during = wheel(120);
    ref.dispatchEvent(during);
    expect(during.defaultPrevented).toBe(true); // swallowed, not queued

    advance(1000);
    expect(presentation.announcements).toEqual([1]); // one step, not two
    controller.destroy();
  });

  it('releases the gesture at the last slide (scrolling down)', () => {
    setScrollY(3000); // last slide stop
    const { controller, ref } = mountController();

    const event = wheel(120);
    ref.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(false); // native scroll takes over
    controller.destroy();
  });

  it('releases the gesture at the first slide (scrolling up)', () => {
    setScrollY(1000); // first slide stop
    const { controller, ref } = mountController();

    const event = wheel(-120);
    ref.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(false);
    controller.destroy();
  });

  it('pages with the keyboard (arrows, End, Home)', () => {
    setScrollY(1000);
    const { controller, presentation } = mountController();

    // Each advance must clear the glide (620ms) *and* the cooldown (90ms)
    // before the next key is accepted.
    expect(controller.keydown(key('ArrowRight'))).toBe(true);
    advance(800);
    expect(controller.keydown(key('End'))).toBe(true);
    advance(800);
    expect(controller.keydown(key('Home'))).toBe(true);
    advance(800);

    expect(presentation.announcements).toEqual([1, 2, 0]);
    controller.destroy();
  });

  it('ignores non-navigation keys', () => {
    setScrollY(1000);
    const { controller } = mountController();
    expect(controller.keydown(key('a'))).toBe(false);
    expect(controller.keydown(key('Enter'))).toBe(false);
    controller.destroy();
  });

  it('settles onto the nearest slide when scrolled into the range', () => {
    setScrollY(0); // outside the pinned range
    const { controller, presentation } = mountController();

    setScrollY(1900); // enter near slide 1's stop (progress 0.45)
    window.dispatchEvent(new Event('scroll'));
    advance(700);

    expect(scrollY).toBeCloseTo(2000, 0);
    expect(presentation.announcements).toEqual([1]);
    controller.destroy();
  });

  it('stops responding after destroy', () => {
    setScrollY(1000);
    const { controller, ref, presentation } = mountController();
    controller.destroy();
    expect(ref.hasAttribute('data-aa-hscroll-step-state')).toBe(false);

    const event = wheel(120);
    ref.dispatchEvent(event);
    expect(event.defaultPrevented).toBe(false);

    advance(700);
    expect(presentation.announcements).toEqual([]);
  });
});
