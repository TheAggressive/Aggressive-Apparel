/**
 * Integration-style tests for the shared scroll-debug controller.
 *
 * @jest-environment jsdom
 */

import {
  createScrollDebugController,
  rootMarginToBoundary,
  type ScrollDebugController,
} from '../controller';

// Capture IntersectionObserver instances so tests can drive entries.
type ObserverCallback = (entries: Partial<IntersectionObserverEntry>[]) => void;
const observers: Array<{
  callback: ObserverCallback;
  options: IntersectionObserverInit;
}> = [];

class MockIntersectionObserver {
  callback: ObserverCallback;
  options: IntersectionObserverInit;
  observe = jest.fn();
  unobserve = jest.fn();
  disconnect = jest.fn();

  constructor(callback: ObserverCallback, options: IntersectionObserverInit) {
    this.callback = callback;
    this.options = options;
    observers.push(this);
  }
}

beforeAll(() => {
  Object.defineProperty(window, 'IntersectionObserver', {
    writable: true,
    value: MockIntersectionObserver,
  });
  // Keep the perf monitor inert/deterministic.
  jest
    .spyOn(window, 'requestAnimationFrame')
    .mockImplementation(() => 1 as unknown as number);
  jest.spyOn(window, 'cancelAnimationFrame').mockImplementation(() => {});
});

afterAll(() => {
  jest.restoreAllMocks();
});

beforeEach(() => {
  observers.length = 0;
  document.body.innerHTML = '';
  window.localStorage.clear();
});

const buildController = (
  overrides: Partial<Parameters<typeof createScrollDebugController>[0]> = {}
): { controller: ScrollDebugController; element: HTMLElement } => {
  const element = document.createElement('div');
  document.body.appendChild(element);

  const controller = createScrollDebugController({
    id: 'block-1',
    namespace: 'test',
    title: 'Test Debug',
    element,
    configuredBoundary: { top: '0%', right: '0%', bottom: '0%', left: '0%' },
    effectiveRootMargin: '0% 0% 0% 0%',
    threshold: 0.3,
    ...overrides,
  });

  return { controller, element };
};

describe('rootMarginToBoundary', () => {
  it('expands CSS shorthand into four sides', () => {
    expect(rootMarginToBoundary('10px')).toEqual({
      top: '10px',
      right: '10px',
      bottom: '10px',
      left: '10px',
    });
    expect(rootMarginToBoundary('10px 20px')).toEqual({
      top: '10px',
      right: '20px',
      bottom: '10px',
      left: '20px',
    });
    expect(rootMarginToBoundary('1px 2px 3px 4px')).toEqual({
      top: '1px',
      right: '2px',
      bottom: '3px',
      left: '4px',
    });
  });
});

describe('createScrollDebugController', () => {
  it('probes with dense thresholds and the effective rootMargin', () => {
    const { controller } = buildController({
      effectiveRootMargin: '20% 0% 20% 0%',
    });

    expect(observers).toHaveLength(1);
    expect(observers[0].options.rootMargin).toBe('20% 0% 20% 0%');
    expect(observers[0].options.threshold).toHaveLength(101);

    controller.destroy();
  });

  it('tags the element outline and panel subtitle with the instance id', () => {
    const { controller } = buildController();

    expect(document.querySelector('.aa-dbg-element__tag')?.textContent).toBe(
      'block-1'
    );
    expect(document.querySelector('.aa-dbg-panel__subtitle')?.textContent).toBe(
      'block-1'
    );

    controller.destroy();
  });

  it('gives concurrent instances distinct identity colors, shared between outline and panel dot', () => {
    const first = buildController();
    const second = buildController({ id: 'block-2' });

    const elements = document.querySelectorAll<HTMLElement>('.aa-dbg-element');
    const dots = document.querySelectorAll<HTMLElement>('.aa-dbg-panel__dot');
    expect(elements).toHaveLength(2);
    expect(dots).toHaveLength(2);

    const identityOf = (node: HTMLElement): string =>
      node.style.getPropertyValue('--aa-dbg-identity');

    expect(identityOf(elements[0])).not.toBe(identityOf(elements[1]));
    // jsdom normalizes color notation on style.background, so compare
    // the dots to each other rather than to the raw palette strings.
    expect(dots[0].style.background).toBeTruthy();
    expect(dots[1].style.background).toBeTruthy();
    expect(dots[0].style.background).not.toBe(dots[1].style.background);

    first.controller.destroy();
    second.controller.destroy();
  });

  it('draws one boundary when configured === effective, two when they differ', () => {
    const { controller } = buildController();
    expect(document.querySelectorAll('.aa-dbg-boundary')).toHaveLength(1);
    controller.destroy();

    const { controller: buffered } = buildController({
      effectiveRootMargin: '20% 0% 20% 0%',
    });
    const boundaries = document.querySelectorAll('.aa-dbg-boundary');
    expect(boundaries).toHaveLength(2);
    expect(
      document.querySelector('.aa-dbg-boundary--effective')
    ).not.toBeNull();
    buffered.destroy();
  });

  it('updates phase badge and overlay classes from probe samples', () => {
    // Live meters throttle to 10Hz; step a fake clock past the window so
    // every sample in this test lands.
    let fakeNow = 0;
    jest.spyOn(performance, 'now').mockImplementation(() => (fakeNow += 200));

    const { controller } = buildController();

    const badge = document.querySelector('.aa-dbg-badge');
    expect(badge?.textContent).toBe('Waiting');

    observers[0].callback([{ intersectionRatio: 0.1, isIntersecting: true }]);
    expect(badge?.textContent).toBe('Approaching');
    expect(
      document
        .querySelector('.aa-dbg-element')
        ?.classList.contains('is-approaching')
    ).toBe(true);

    observers[0].callback([{ intersectionRatio: 0.5, isIntersecting: true }]);
    expect(badge?.textContent).toBe('Active');

    const fill = document.querySelector<HTMLElement>('.aa-dbg-meter__fill');
    expect(fill?.style.width).toBe('50%');

    controller.destroy();
    (performance.now as jest.Mock).mockRestore();
  });

  it('throttles rapid meter updates but always lands resting values', () => {
    let fakeNow = 1000;
    jest.spyOn(performance, 'now').mockImplementation(() => fakeNow);

    const { controller } = buildController({ trackProgress: true });
    const fills = document.querySelectorAll<HTMLElement>('.aa-dbg-meter__fill');
    const progressFill = fills[1];

    controller.setProgress(0.2); // first write within a fresh window
    fakeNow += 10;
    controller.setProgress(0.4); // 10ms later → throttled away
    expect(progressFill.style.width).toBe('20%');

    fakeNow += 200;
    controller.setProgress(0.6); // past the window → lands
    expect(progressFill.style.width).toBe('60%');

    fakeNow += 10;
    controller.setProgress(1); // resting value bypasses the throttle
    expect(progressFill.style.width).toBe('100%');

    controller.destroy();
    (performance.now as jest.Mock).mockRestore();
  });

  it('shows engine state and progress when configured', () => {
    const { controller } = buildController({
      engine: { label: 'Engine', active: 'Active', idle: 'Idle' },
      trackProgress: true,
    });

    const badges = document.querySelectorAll('.aa-dbg-badge');
    // state + engine + framerate badges exist; engine badge is second.
    expect(badges[1].textContent).toBe('Idle');

    controller.setEngineState(true);
    expect(badges[1].textContent).toBe('Active');

    controller.setProgress(0.42);
    const fills = document.querySelectorAll<HTMLElement>('.aa-dbg-meter__fill');
    expect(fills[1].style.width).toBe('42%');

    controller.destroy();
  });

  it('renders a collapsed legend describing the overlays, matching the config', () => {
    const { controller } = buildController({ exitThreshold: 0.15 });

    const legendTexts = Array.from(
      document.querySelectorAll('.aa-dbg-legend__text')
    ).map(node => node.textContent ?? '');

    expect(legendTexts.some(text => text.includes('Detection boundary'))).toBe(
      true
    );
    expect(legendTexts.some(text => text.includes('Exit line'))).toBe(true);
    // configured === effective here, so no buffer entry.
    expect(
      legendTexts.some(text => text.includes('pre-activation buffer'))
    ).toBe(false);

    // Legend section starts collapsed.
    const legendBody = document
      .querySelector('.aa-dbg-legend')
      ?.closest('.aa-dbg-section__body') as HTMLElement;
    expect(legendBody.hidden).toBe(true);

    controller.destroy();

    const { controller: buffered } = buildController({
      effectiveRootMargin: '20% 0% 20% 0%',
    });
    const bufferedTexts = Array.from(
      document.querySelectorAll('.aa-dbg-legend__text')
    ).map(node => node.textContent ?? '');
    expect(
      bufferedTexts.some(text => text.includes('pre-activation buffer'))
    ).toBe(true);
    expect(bufferedTexts.some(text => text.includes('Exit line'))).toBe(false);
    buffered.destroy();
  });

  it('warns when the entry threshold is unreachable for tall elements', () => {
    const element = document.createElement('div');
    Object.defineProperty(element, 'offsetHeight', { value: 5000 });
    document.body.appendChild(element);

    const controller = createScrollDebugController({
      id: 'tall',
      namespace: 'test',
      title: 'Tall Debug',
      element,
      configuredBoundary: { top: '0%', right: '0%', bottom: '0%', left: '0%' },
      effectiveRootMargin: '0% 0% 0% 0%',
      threshold: 0.5,
    });

    const warning = document.querySelector<HTMLElement>(
      '.aa-dbg-panel__warning'
    );
    expect(warning?.hidden).toBe(false);
    expect(warning?.textContent).toContain('unreachable');

    controller.destroy();
  });

  it('removes every DOM artifact on destroy', () => {
    const { controller } = buildController({
      effectiveRootMargin: '20% 0% 20% 0%',
    });

    controller.destroy();

    expect(document.querySelector('.aa-dbg-panel')).toBeNull();
    expect(document.querySelector('.aa-dbg-element')).toBeNull();
    expect(document.querySelectorAll('.aa-dbg-boundary')).toHaveLength(0);
  });
});
