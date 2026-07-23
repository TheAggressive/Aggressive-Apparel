/**
 * Unit tests for the store-notices Interactivity store: the per-type role
 * getters and the auto-dismiss / manual-dismiss / pause-resume timer lifecycle.
 *
 * `@wordpress/interactivity` is virtual-mocked so the store config is captured
 * directly and getContext/getElement are controllable. Entrance/exit CSS and
 * the WooCommerce notice bridge need real layout + Woo markup and are covered
 * by the Playwright e2e (`tests/e2e/store-notices.spec.ts`) instead.
 *
 * @jest-environment jsdom
 */

let mockContext: Record<string, unknown> = {};
const mockElement = { ref: null as HTMLElement | null };

jest.mock(
  '@wordpress/interactivity',
  () => ({
    store: (_ns: string, config: unknown) => config,
    getContext: () => mockContext,
    getElement: () => mockElement,
    withSyncEvent: (fn: unknown) => fn,
  }),
  { virtual: true }
);

import { storeNoticesStore, makeNotice } from '../view';

const { state, actions, callbacks } = storeNoticesStore as unknown as {
  state: Record<string, unknown>;
  actions: Record<string, (...args: unknown[]) => void>;
  callbacks: Record<string, () => void>;
};

interface TestNotice {
  id: string;
  type: 'success' | 'error' | 'notice';
  message: string;
  leaving: boolean;
}

/** Build a container context holding a single active notice + its element. */
function seedNotice(
  overrides: Partial<TestNotice> = {},
  durations = { success: 5000, error: 0, notice: 6000 }
): { notices: TestNotice[]; notice: TestNotice } {
  const notice: TestNotice = {
    id: overrides.id ?? `n-${Math.random().toString(36).slice(2)}`,
    type: overrides.type ?? 'success',
    message: overrides.message ?? 'Hello',
    leaving: false,
  };
  const notices = [notice];
  mockContext = {
    notices,
    durations,
    maxVisible: 4,
    i18n: { dismiss: 'Dismiss notification' },
    notice,
  };
  mockElement.ref = document.createElement('div');
  return { notices, notice };
}

afterEach(() => {
  jest.useRealTimers();
  mockContext = {};
  mockElement.ref = null;
  document.body.innerHTML = '';
});

/**
 * Seed a notice whose toast element lives inside a real `.aa-notices` container
 * with the two live regions, so the announcer path can be exercised.
 */
function seedNoticeInDom(
  type: 'success' | 'error' | 'notice',
  message: string
): {
  polite: HTMLElement;
  assertive: HTMLElement;
} {
  const { notice } = seedNotice({ type, message });
  const container = document.createElement('div');
  container.className = 'aa-notices';
  const polite = document.createElement('div');
  polite.setAttribute('data-aa-live', 'polite');
  const assertive = document.createElement('div');
  assertive.setAttribute('data-aa-live', 'assertive');
  const toast = document.createElement('div');
  container.append(polite, assertive, toast);
  document.body.appendChild(container);
  void notice;
  mockElement.ref = toast;
  return { polite, assertive };
}

describe('state getters', () => {
  it('reads the dismiss label from context i18n', () => {
    seedNotice();
    expect(state.dismissLabel).toBe('Dismiss notification');
  });
});

describe('makeNotice (bridge notice shape)', () => {
  it('sets the full field set the render template binds to', () => {
    expect(makeNotice('error', 'Boom', 'x1')).toEqual({
      id: 'x1',
      type: 'error',
      message: 'Boom',
      leaving: false,
      thumbnail: '',
      noThumbnail: true,
      isSuccess: false,
      isError: true,
      isNotice: false,
    });
  });

  it('flags success and notice types correctly', () => {
    expect(makeNotice('success', 'a', '1')).toMatchObject({
      isSuccess: true,
      isError: false,
      isNotice: false,
    });
    expect(makeNotice('notice', 'a', '1')).toMatchObject({
      isSuccess: false,
      isError: false,
      isNotice: true,
    });
  });
});

describe('syncMessage', () => {
  it('paints sanitised HTML into the message element', () => {
    seedNotice({
      message: 'Added <a href="/cart">View cart</a><script>bad()</script>',
    });
    callbacks.syncMessage();
    const el = mockElement.ref as HTMLElement;
    expect(el.querySelector('a')?.getAttribute('href')).toBe('/cart');
    expect(el.innerHTML.toLowerCase()).not.toContain('<script');
  });
});

describe('syncThumb', () => {
  it('sets the img src from the notice thumbnail', () => {
    seedNotice({ type: 'success', message: 'Added' });
    (mockContext.notice as { thumbnail?: string }).thumbnail =
      'https://example.test/shirt.jpg';
    const img = document.createElement('img');
    mockElement.ref = img;
    callbacks.syncThumb();
    expect(img.getAttribute('src')).toBe('https://example.test/shirt.jpg');
  });

  it('removes src rather than setting an empty one when there is no thumbnail', () => {
    seedNotice({ type: 'error', message: 'Nope' });
    const img = document.createElement('img');
    img.setAttribute('src', 'https://example.test/old.jpg');
    mockElement.ref = img;
    callbacks.syncThumb();
    expect(img.hasAttribute('src')).toBe(false);
  });
});

describe('auto-dismiss lifecycle', () => {
  it('auto-dismisses a success toast after its duration + exit window', () => {
    jest.useFakeTimers();
    const { notices, notice } = seedNotice({ type: 'success' });

    callbacks.initToast();
    expect(notices).toHaveLength(1);

    jest.advanceTimersByTime(5000); // duration elapses → begin exit
    expect(notice.leaving).toBe(true);

    jest.advanceTimersByTime(400); // exit fallback → splice
    expect(notices).toHaveLength(0);
  });

  it('does not auto-dismiss an error toast (sticky, duration 0)', () => {
    jest.useFakeTimers();
    const { notices } = seedNotice({ type: 'error' });

    callbacks.initToast();
    jest.advanceTimersByTime(60_000);

    expect(notices).toHaveLength(1);
  });
});

describe('manual dismiss', () => {
  it('removes the toast when dismiss() is invoked', () => {
    jest.useFakeTimers();
    const { notices, notice } = seedNotice({ type: 'error' });

    callbacks.initToast();
    actions.dismiss();
    expect(notice.leaving).toBe(true);

    jest.advanceTimersByTime(400);
    expect(notices).toHaveLength(0);
  });
});

describe('pause / resume', () => {
  it('pauses the auto-dismiss timer on hover and resumes with the remainder', () => {
    jest.useFakeTimers();
    const { notices } = seedNotice({ type: 'success' });

    callbacks.initToast();
    jest.advanceTimersByTime(2000);

    actions.pause(); // ~3000ms remaining
    jest.advanceTimersByTime(30_000); // stays put while paused
    expect(notices).toHaveLength(1);

    actions.resume();
    jest.advanceTimersByTime(3000); // remainder elapses → begin exit
    jest.advanceTimersByTime(400); // exit fallback → splice
    expect(notices).toHaveLength(0);
  });
});

describe('accessibility', () => {
  it('announces success/info into the polite live region as plain text', () => {
    jest.useFakeTimers(); // announce schedules a cleanup timeout
    const { polite, assertive } = seedNoticeInDom(
      'success',
      'Added <a href="/cart">View cart</a>'
    );
    callbacks.initToast();
    expect(polite.textContent).toContain('Added View cart');
    expect(assertive.textContent).toBe('');
  });

  it('announces errors into the assertive live region', () => {
    jest.useFakeTimers();
    const { polite, assertive } = seedNoticeInDom('error', 'Coupon invalid.');
    callbacks.initToast();
    expect(assertive.textContent).toBe('Coupon invalid.');
    expect(polite.textContent).toBe('');
  });

  it('dismisses the focused toast on Escape', () => {
    jest.useFakeTimers();
    const { notices, notice } = seedNotice({ type: 'error' });

    callbacks.initToast();
    actions.onKeydown({
      key: 'Escape',
      stopPropagation: () => {},
    } as unknown as KeyboardEvent);
    expect(notice.leaving).toBe(true);

    jest.advanceTimersByTime(400);
    expect(notices).toHaveLength(0);
  });

  it('ignores non-Escape keys', () => {
    const { notices } = seedNotice({ type: 'error' });
    callbacks.initToast();
    actions.onKeydown({
      key: 'a',
      stopPropagation: () => {},
    } as unknown as KeyboardEvent);
    expect(notices).toHaveLength(1);
  });
});
