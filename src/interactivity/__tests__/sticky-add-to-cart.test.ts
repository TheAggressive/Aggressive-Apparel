/**
 * Sticky add-to-cart lifecycle and geometry tests.
 *
 * @jest-environment jsdom
 */

type MockHandler = (...args: unknown[]) => unknown;

interface MockStoreConfig {
  state: Record<string, unknown>;
  actions: Record<string, MockHandler>;
  callbacks: {
    init: () => (() => void) | void;
  };
}

const mockElement = { ref: null as HTMLElement | null };

// `var` is intentionally hoist-safe for the module's top-level store() call.
var mockStoreConfig: MockStoreConfig | undefined;

jest.mock(
  '@wordpress/interactivity',
  () => ({
    store: (_namespace: string, config: MockStoreConfig) => {
      mockStoreConfig = config;
      return config;
    },
    getElement: () => mockElement,
    withSyncEvent: (handler: MockHandler) => handler,
  }),
  { virtual: true }
);

jest.mock(
  '@aggressive-apparel/helpers',
  () => ({
    matchVariation: jest.fn(),
  }),
  { virtual: true }
);

jest.mock(
  '@aggressive-apparel/use-overlay',
  () => ({
    prepareOverlayOpen: jest.fn(),
    activateOverlayFocus: jest.fn(() => jest.fn()),
    closeOverlay: jest.fn(),
  }),
  { virtual: true }
);

jest.mock(
  '@aggressive-apparel/scroll-lock',
  () => ({
    unlockScroll: jest.fn(),
  }),
  { virtual: true }
);

type IntersectionCallback = (entries: IntersectionObserverEntry[]) => void;

const intersectionObservers: Array<{
  callback: IntersectionCallback;
  observe: jest.Mock;
  disconnect: jest.Mock;
}> = [];

class MockIntersectionObserver {
  callback: IntersectionCallback;
  observe = jest.fn();
  disconnect = jest.fn();
  unobserve = jest.fn();
  takeRecords = jest.fn(() => []);
  root = null;
  rootMargin = '0px';
  thresholds = [0];

  constructor(callback: IntersectionCallback) {
    this.callback = callback;
    intersectionObservers.push(this);
  }
}

const resizeObservers: Array<{
  callback: ResizeObserverCallback;
  observe: jest.Mock;
  disconnect: jest.Mock;
}> = [];

class MockResizeObserver {
  callback: ResizeObserverCallback;
  observe = jest.fn();
  disconnect = jest.fn();
  unobserve = jest.fn();

  constructor(callback: ResizeObserverCallback) {
    this.callback = callback;
    resizeObservers.push(this);
  }
}

import { shouldShowStickyCart } from '../sticky-add-to-cart';

const config = mockStoreConfig as MockStoreConfig;
const state = config.state;
const actions = config.actions;
const callbacks = config.callbacks;
const unlockScroll = jest.requireMock('@aggressive-apparel/scroll-lock')
  .unlockScroll as jest.Mock;

let cleanup: (() => void) | undefined;
let form: HTMLFormElement;
let bar: HTMLElement;
let barHeight = 96;

function makeEntry(
  isIntersecting: boolean,
  bottom: number,
  height = 100
): IntersectionObserverEntry {
  return {
    isIntersecting,
    boundingClientRect: { bottom, height } as DOMRectReadOnly,
  } as IntersectionObserverEntry;
}

function init(): () => void {
  const result = callbacks.init();
  expect(result).toEqual(expect.any(Function));
  return result as () => void;
}

beforeEach(() => {
  document.body.innerHTML = '';
  document.body.classList.remove('aa-sticky-cart-visible');
  document.documentElement.style.removeProperty('--aa-sticky-cart-height');
  intersectionObservers.length = 0;
  resizeObservers.length = 0;
  unlockScroll.mockClear();
  cleanup = undefined;
  barHeight = 96;

  Object.defineProperty(globalThis, 'IntersectionObserver', {
    configurable: true,
    writable: true,
    value: MockIntersectionObserver,
  });
  Object.defineProperty(globalThis, 'ResizeObserver', {
    configurable: true,
    writable: true,
    value: MockResizeObserver,
  });

  form = document.createElement('form');
  form.className = 'wc-block-add-to-cart-form';
  bar = document.createElement('div');
  bar.className = 'aa-sticky-cart';
  Object.defineProperty(bar, 'offsetHeight', {
    configurable: true,
    get: () => barHeight,
  });
  document.body.append(form, bar);
  mockElement.ref = bar;

  Object.assign(state, {
    productType: 'simple',
    selectedAttrs: {},
    isVisible: false,
    isDrawerOpen: false,
    drawerView: 'selection',
    _syncing: false,
  });
  actions.matchVariation = jest.fn();
  actions.closeDrawer = jest.fn();
});

afterEach(() => {
  cleanup?.();
  mockElement.ref = null;
});

describe('shouldShowStickyCart', () => {
  it('shows only after a non-zero source form passes above the viewport', () => {
    expect(shouldShowStickyCart(makeEntry(true, 50))).toBe(false);
    expect(shouldShowStickyCart(makeEntry(false, 900))).toBe(false);
    expect(shouldShowStickyCart(makeEntry(false, 0, 0))).toBe(false);
    expect(shouldShowStickyCart(makeEntry(false, 0))).toBe(true);
    expect(shouldShowStickyCart(makeEntry(false, -1))).toBe(true);
  });
});

describe('callbacks.init', () => {
  it('publishes measured height and updates it while visible', () => {
    cleanup = init();

    expect(intersectionObservers).toHaveLength(1);
    expect(resizeObservers).toHaveLength(1);
    expect(intersectionObservers[0].observe).toHaveBeenCalledWith(form);
    expect(resizeObservers[0].observe).toHaveBeenCalledWith(bar);

    // The form has not entered the viewport yet.
    intersectionObservers[0].callback([makeEntry(false, 900)]);
    expect(state.isVisible).toBe(false);
    expect(document.body.classList.contains('aa-sticky-cart-visible')).toBe(
      false
    );
    expect(
      document.documentElement.style.getPropertyValue('--aa-sticky-cart-height')
    ).toBe('0px');

    // Once it passes above the viewport, publish the actual bar height.
    intersectionObservers[0].callback([makeEntry(false, -1)]);
    expect(state.isVisible).toBe(true);
    expect(document.body.classList.contains('aa-sticky-cart-visible')).toBe(
      true
    );
    expect(
      document.documentElement.style.getPropertyValue('--aa-sticky-cart-height')
    ).toBe('96px');
    expect(bar.style.getPropertyValue('--aa-sticky-cart-height')).toBe('96px');

    barHeight = 112;
    resizeObservers[0].callback(
      [],
      resizeObservers[0] as unknown as ResizeObserver
    );
    expect(
      document.documentElement.style.getPropertyValue('--aa-sticky-cart-height')
    ).toBe('112px');

    intersectionObservers[0].callback([makeEntry(true, 50)]);
    expect(state.isVisible).toBe(false);
    expect(document.body.classList.contains('aa-sticky-cart-visible')).toBe(
      false
    );
    expect(
      document.documentElement.style.getPropertyValue('--aa-sticky-cart-height')
    ).toBe('0px');
  });

  it('disconnects observers, removes listeners, and restores owned globals', () => {
    document.documentElement.style.setProperty(
      '--aa-sticky-cart-height',
      '7px'
    );
    bar.style.setProperty('--aa-sticky-cart-height', '5px');
    document.body.classList.add('aa-sticky-cart-visible');
    state.productType = 'variable';

    const select = document.createElement('select');
    select.name = 'attribute_size';
    select.value = 'large';
    const option = document.createElement('option');
    option.textContent = 'Large';
    option.value = 'large';
    select.append(option);
    form.appendChild(select);

    const drawer = document.createElement('div');
    drawer.className = 'aa-sticky-cart__drawer is-open';
    document.body.appendChild(drawer);

    cleanup = init();
    select.dispatchEvent(new Event('change', { bubbles: true }));
    expect(actions.matchVariation).toHaveBeenCalledTimes(1);

    state.isDrawerOpen = true;
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(actions.closeDrawer).toHaveBeenCalledTimes(1);

    cleanup();
    cleanup();

    expect(intersectionObservers[0].disconnect).toHaveBeenCalledTimes(1);
    expect(resizeObservers[0].disconnect).toHaveBeenCalledTimes(1);
    expect(unlockScroll).toHaveBeenCalledTimes(1);
    expect(drawer.hidden).toBe(true);
    expect(drawer.classList.contains('is-open')).toBe(false);
    expect(
      document.documentElement.style.getPropertyValue('--aa-sticky-cart-height')
    ).toBe('7px');
    expect(bar.style.getPropertyValue('--aa-sticky-cart-height')).toBe('5px');
    expect(document.body.classList.contains('aa-sticky-cart-visible')).toBe(
      true
    );

    select.dispatchEvent(new Event('change', { bubbles: true }));
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(actions.matchVariation).toHaveBeenCalledTimes(1);
    expect(actions.closeDrawer).toHaveBeenCalledTimes(1);
  });

  it('tears down the prior runtime before repeated initialization', () => {
    const firstCleanup = init();
    const firstIntersectionObserver = intersectionObservers[0];
    const firstResizeObserver = resizeObservers[0];

    cleanup = init();

    expect(firstIntersectionObserver.disconnect).toHaveBeenCalledTimes(1);
    expect(firstResizeObserver.disconnect).toHaveBeenCalledTimes(1);
    expect(intersectionObservers).toHaveLength(2);
    expect(resizeObservers).toHaveLength(2);

    // A stale cleanup must not tear down the replacement runtime.
    firstCleanup();
    expect(intersectionObservers[1].disconnect).not.toHaveBeenCalled();
  });

  it('fails closed when IntersectionObserver is unavailable', () => {
    Object.defineProperty(globalThis, 'IntersectionObserver', {
      configurable: true,
      writable: true,
      value: undefined,
    });
    state.isVisible = true;

    cleanup = init();

    expect(state.isVisible).toBe(false);
    expect(document.body.classList.contains('aa-sticky-cart-visible')).toBe(
      false
    );
    expect(intersectionObservers).toHaveLength(0);
  });
});
