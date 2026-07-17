/**
 * Directional step controller — ownership, entry/exit, coast, pending queue,
 * keyboard without focus, tween generation.
 *
 * @jest-environment jsdom
 */
import { StepController } from '../controllers/step';
import type { Geometry, Presentation } from '../controllers/types';

let now = 0;
let scrollY = 0;
let rafQueue: Array<{ id: number; cb: FrameRequestCallback }> = [];
let timeoutQueue: Array<{ id: number; cb: () => void; at: number }> = [];
let handleSeq = 0;

function setScrollY(value: number): void {
  scrollY = value;
}

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
    syncControls: () => {},
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
    stepDurationMs: 620,
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
  return new WheelEvent('wheel', { deltaY, cancelable: true, bubbles: true });
}

function dispatchWheel(deltaY: number): WheelEvent {
  const event = wheel(deltaY);
  window.dispatchEvent(event);
  return event;
}

function key(name: string): KeyboardEvent {
  return new KeyboardEvent('keydown', { key: name });
}

describe('StepController (enterprise paging)', () => {
  it('advances exactly one slide on a downward wheel gesture', () => {
    setScrollY(1000);
    const { controller, ref, presentation } = mountController();

    const event = dispatchWheel(120);
    expect(event.defaultPrevented).toBe(true);

    advance(700);
    expect(scrollY).toBeCloseTo(2000, 0);
    expect(presentation.announcements).toEqual([1]);
    expect(ref.dataset.aaHscrollStepState).toBe('ready');
    controller.destroy();
  });

  it('steps backward on an upward wheel gesture', () => {
    setScrollY(2000);
    const { controller, presentation } = mountController();

    dispatchWheel(-120);
    advance(700);
    expect(scrollY).toBeCloseTo(1000, 0);
    expect(presentation.announcements).toEqual([0]);
    controller.destroy();
  });

  it('accepts the next wheel immediately after entering (no entry coast lock)', () => {
    setScrollY(0);
    const { controller, presentation } = mountController();

    // Approach the range so wheel capture binds (hysteresis), then enter.
    setScrollY(1000);
    window.dispatchEvent(new Event('scroll'));
    advance(50);

    dispatchWheel(120);
    advance(700);
    expect(presentation.announcements).toContain(1);
    expect(scrollY).toBeCloseTo(2000, 0);
    controller.destroy();
  });

  it('does not bind non-passive wheel far from the pin range', () => {
    setScrollY(0);
    const { controller } = mountController();

    const far = dispatchWheel(120);
    expect(far.defaultPrevented).toBe(false);

    // Enter the listen band — scroll sync binds capture.
    setScrollY(850);
    window.dispatchEvent(new Event('scroll'));
    setScrollY(1000);
    window.dispatchEvent(new Event('scroll'));
    advance(16);

    const near = dispatchWheel(120);
    expect(near.defaultPrevented).toBe(true);
    controller.destroy();
  });

  it('releases at the last slide and does not immediately reclaim', () => {
    setScrollY(3000);
    const { controller, presentation } = mountController();

    // Already on last slide — exit down.
    const exit = dispatchWheel(120);
    expect(exit.defaultPrevented).toBe(false);

    // Still physically in band; must not reclaim and trap.
    const trapped = dispatchWheel(120);
    expect(trapped.defaultPrevented).toBe(false);
    expect(presentation.announcements).toEqual([]);

    // Fully leave, then re-enter works.
    setScrollY(4000);
    window.dispatchEvent(new Event('scroll'));
    setScrollY(3000);
    window.dispatchEvent(new Event('scroll'));
    const reenter = dispatchWheel(-120);
    expect(reenter.defaultPrevented).toBe(true);
    controller.destroy();
  });

  it('honors a shorter stepDurationMs for the glide', () => {
    setScrollY(1000);
    const geometry = createGeometry();
    geometry.stepDurationMs = 200;
    const { controller, presentation } = mountController(geometry);

    dispatchWheel(120);
    advance(250);
    expect(scrollY).toBeCloseTo(2000, 0);
    expect(presentation.announcements).toEqual([1]);
    controller.destroy();
  });

  it('snaps instantly when stepDurationMs is 0', () => {
    setScrollY(1000);
    const geometry = createGeometry();
    geometry.stepDurationMs = 0;
    const { controller, presentation } = mountController(geometry);

    dispatchWheel(120);
    advance(16);
    expect(scrollY).toBeCloseTo(2000, 0);
    expect(presentation.announcements).toEqual([1]);
    controller.destroy();
  });

  it('does not steal wheel when another handler already prevented default', () => {
    setScrollY(1000);
    const { controller, presentation } = mountController();

    const event = wheel(120);
    Object.defineProperty(event, 'defaultPrevented', {
      configurable: true,
      get: () => true,
    });
    window.dispatchEvent(event);
    advance(700);
    expect(presentation.announcements).toEqual([]);
    controller.destroy();
  });

  it('queues one pending step while a glide is in flight', () => {
    setScrollY(1000);
    const { controller, presentation } = mountController();

    dispatchWheel(120);
    advance(100);
    // Second flick mid-glide queues slide 2.
    dispatchWheel(120);
    advance(1200);
    expect(presentation.announcements).toEqual([1, 2]);
    expect(scrollY).toBeCloseTo(3000, 0);
    controller.destroy();
  });

  it('pages with the keyboard without requiring section focus', () => {
    setScrollY(1000);
    const { controller, presentation } = mountController();

    expect(controller.keydown(key('ArrowDown'))).toBe(true);
    advance(700);
    expect(controller.keydown(key('ArrowDown'))).toBe(true);
    advance(700);
    expect(controller.keydown(key('ArrowUp'))).toBe(true);
    advance(700);

    expect(presentation.announcements).toEqual([1, 2, 1]);
    controller.destroy();
  });

  it('queues keyboard repeats while animating then walks slides', () => {
    setScrollY(1000);
    const { controller, presentation } = mountController();

    expect(controller.keydown(key('ArrowDown'))).toBe(true);
    advance(100);
    expect(controller.keydown(key('ArrowDown'))).toBe(true);
    advance(1200);
    expect(presentation.announcements).toEqual([1, 2]);
    controller.destroy();
  });

  it('does not steal Arrow keys outside the range', () => {
    setScrollY(0);
    const { controller } = mountController();
    expect(controller.keydown(key('ArrowDown'))).toBe(false);
    controller.destroy();
  });

  it('releases Arrow keys at the first and last slide', () => {
    setScrollY(1000);
    const { controller } = mountController();

    expect(controller.keydown(key('ArrowUp'))).toBe(false);
    expect(controller.keydown(key('End'))).toBe(true);
    advance(700);
    expect(controller.keydown(key('ArrowDown'))).toBe(false);
    controller.destroy();
  });

  it('swallows brief same-direction coast then accepts the next flick', () => {
    setScrollY(1000);
    const { controller, presentation } = mountController();

    dispatchWheel(120);
    advance(620);

    dispatchWheel(40);
    advance(30);
    dispatchWheel(40);
    expect(presentation.announcements).toEqual([1]);

    advance(100);
    dispatchWheel(120);
    advance(700);
    expect(presentation.announcements).toEqual([1, 2]);
    controller.destroy();
  });

  it('accepts opposite direction immediately after landing', () => {
    setScrollY(1000);
    const { controller, presentation } = mountController();

    dispatchWheel(120);
    advance(620);
    dispatchWheel(-120);
    advance(700);
    expect(presentation.announcements).toEqual([1, 0]);
    controller.destroy();
  });

  it('does not finish a cancelled tween after geometry invalidates it', () => {
    setScrollY(1000);
    const { controller, presentation } = mountController();

    dispatchWheel(120);
    advance(100);
    controller.updateGeometry(createGeometry());
    advance(1000);
    expect(presentation.announcements).toEqual([]);
    controller.destroy();
  });

  it('settles onto the nearest slide when scrolled into the range', () => {
    setScrollY(0);
    const { controller, presentation } = mountController();

    setScrollY(1900);
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

    const event = dispatchWheel(120);
    expect(event.defaultPrevented).toBe(false);
    expect(controller.keydown(key('ArrowRight'))).toBe(false);

    advance(700);
    expect(presentation.announcements).toEqual([]);
  });
});
