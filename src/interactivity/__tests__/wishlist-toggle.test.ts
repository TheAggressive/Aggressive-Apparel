/** Slow requests must never delay the product-card heart's visual feedback. */
let mockState: { items: number[] };
const mockConfig = {
  account: true,
  itemsUrl: '/wishlist/items',
  productsUrl: '/products',
  restNonce: 'rest',
  nonce: 'store',
};

jest.mock(
  '@wordpress/interactivity',
  () => ({
    getConfig: () => mockConfig,
    getContext: () => ({ loaded: true }),
    getElement: () => ({}),
    store: (_name: string, definition: { state: typeof mockState }) => {
      mockState = definition.state;
      return definition;
    },
  }),
  { virtual: true }
);
jest.mock(
  '@aggressive-apparel/helpers',
  () => ({
    decodeEntities: (text: string) => text,
    formatVariablePriceRange: () => '',
  }),
  { virtual: true }
);

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>(done => {
    resolve = done;
  });
  return { promise, resolve };
}
function reply(body: unknown, status = 200): Response {
  return {
    status,
    ok: status < 400,
    headers: { get: () => null },
    json: async () => body,
  } as unknown as Response;
}
async function settle() {
  for (let turn = 0; turn < 30; turn++) await Promise.resolve();
}

let click: EventListener;
let fetchMock: jest.Mock;
let initial: ReturnType<typeof deferred<Response>>;
let save: ReturnType<typeof deferred<Response>>;

beforeEach(async () => {
  jest.resetModules();
  localStorage.clear();
  document.body.innerHTML =
    '<button class="aggressive-apparel-wishlist__toggle" data-aa-product-id="12" data-aa-product-type="simple" aria-pressed="false"></button><button class="aggressive-apparel-wishlist__toggle" data-aa-product-id="12" data-aa-product-type="simple" aria-pressed="false"></button>';
  initial = deferred<Response>();
  save = deferred<Response>();
  fetchMock = jest
    .fn()
    .mockReturnValueOnce(initial.promise)
    .mockReturnValueOnce(save.promise);
  window.fetch = fetchMock;
  jest
    .spyOn(document, 'addEventListener')
    .mockImplementation((name, handler) => {
      if (name === 'click') click = handler as EventListener;
    });
  jest.spyOn(window, 'addEventListener').mockImplementation(() => {});
  jest
    .spyOn(MutationObserver.prototype, 'observe')
    .mockImplementation(() => {});
  await import('../wishlist');
});

afterEach(() => {
  jest.useRealTimers();
  mockConfig.account = true;
  jest.restoreAllMocks();
});

function press() {
  const button = document.querySelector('button')!;
  click({
    target: button,
    preventDefault() {},
    stopPropagation() {},
  } as unknown as Event);
}

it('updates every matching heart before initialization or saving completes', async () => {
  press();
  for (const button of document.querySelectorAll('button')) {
    expect(button.getAttribute('aria-pressed')).toBe('true');
    expect(button.getAttribute('aria-busy')).toBe('true');
  }
  press(); // Ignore repeated taps while this save is pending.
  initial.resolve(reply([]));
  await settle();
  expect(fetchMock).toHaveBeenCalledTimes(2);
  expect(document.querySelector('button')!.getAttribute('aria-pressed')).toBe(
    'true'
  );
  save.resolve(
    reply(
      {
        id: 12,
        product_id: 12,
        key: 'saved',
        name: 'Hat',
        permalink: 'https://example.com/hat',
        prices: null,
      },
      201
    )
  );
  await settle();
  expect(mockState.items).toEqual([12]);
  expect(document.querySelector('button')!.hasAttribute('aria-busy')).toBe(
    false
  );
  expect(fetchMock).toHaveBeenCalledTimes(2); // No redundant confirmation GET.
});

it('restores every matching heart and announces a failed save', async () => {
  initial.resolve(reply([]));
  await settle();
  press();
  await settle();
  expect(document.querySelector('button')!.getAttribute('aria-pressed')).toBe(
    'true'
  );
  save.resolve(reply({ message: 'Unavailable' }, 500));
  await settle();
  for (const button of document.querySelectorAll('button')) {
    expect(button.getAttribute('aria-pressed')).toBe('false');
    expect(button.hasAttribute('aria-busy')).toBe(false);
  }
  expect(document.querySelector('[role="status"]')!.textContent).toContain(
    'could not be updated'
  );
  expect(mockState.items).toEqual([]);
});

it('clears a saved heart immediately and restores it when removal fails', async () => {
  initial.resolve(
    reply([
      {
        id: 12,
        product_id: 12,
        key: 'saved',
        name: 'Hat',
        permalink: 'https://example.com/hat',
        prices: null,
      },
    ])
  );
  await settle();
  press();
  expect(document.querySelector('button')!.getAttribute('aria-pressed')).toBe(
    'false'
  );
  await settle();
  expect(fetchMock.mock.calls[1][1].method).toBe('DELETE');
  save.resolve(reply({ message: 'Unavailable' }, 500));
  await settle();
  expect(document.querySelector('button')!.getAttribute('aria-pressed')).toBe(
    'true'
  );
  expect(mockState.items).toEqual([12]);
});

it('confirms a removal without fetching the list again', async () => {
  initial.resolve(
    reply([
      {
        id: 12,
        product_id: 12,
        key: 'saved',
        name: 'Hat',
        permalink: 'https://example.com/hat',
        prices: null,
      },
    ])
  );
  await settle();
  press();
  await settle();
  save.resolve(reply(null, 204));
  await settle();
  expect(document.querySelector('button')!.getAttribute('aria-pressed')).toBe(
    'false'
  );
  expect(document.querySelector('button')!.hasAttribute('aria-busy')).toBe(
    false
  );
  expect(mockState.items).toEqual([]);
  expect(fetchMock).toHaveBeenCalledTimes(2);
});

it('saves a variable card on this device without navigating or POSTing a parent', async () => {
  initial.resolve(reply([]));
  await settle();
  const originalUrl = window.location.href;
  const button = document.querySelector('button')!;
  button.dataset.aaProductType = 'variable';
  button.dataset.aaProductUrl = '#choose-options';
  press();
  await settle();
  expect(window.location.href).toBe(originalUrl);
  expect(fetchMock).toHaveBeenCalledTimes(1);
  expect(mockState.items).toEqual([12]);
  expect(
    JSON.parse(localStorage.getItem('aggressive_apparel_wishlist')!)
  ).toEqual([12]);
  expect(button.getAttribute('aria-pressed')).toBe('true');
  press();
  await settle();
  expect(mockState.items).toEqual([]);
  expect(button.getAttribute('aria-pressed')).toBe('false');
  expect(fetchMock).toHaveBeenCalledTimes(1);
});

it('saves an out-of-stock variable parent from older cached markup without navigation', async () => {
  initial.resolve(reply([]));
  await settle();
  const originalUrl = window.location.href;
  const button = document.querySelector('button')!;
  delete button.dataset.aaProductType;
  press();
  await settle();
  save.resolve(
    reply([
      {
        id: 12,
        type: 'variable',
        is_in_stock: false,
        permalink: '#cached-options',
        name: 'Hat',
        prices: null,
      },
    ])
  );
  await settle();
  expect(window.location.href).toBe(originalUrl);
  expect(
    fetchMock.mock.calls.map(([, options]) => options?.method || 'GET')
  ).toEqual(['GET', 'GET']);
  expect(mockState.items).toEqual([12]);
  expect(button.getAttribute('aria-pressed')).toBe('true');
});

it('preserves guest variable saves without navigation or account requests', async () => {
  initial.resolve(reply([]));
  await settle();
  mockConfig.account = false;
  jest.resetModules();
  await import('../wishlist');
  await settle();
  fetchMock.mockClear();
  const originalUrl = window.location.href;
  const button = document.querySelector('button')!;
  button.dataset.aaProductType = 'variable';
  press();
  await settle();
  expect(window.location.href).toBe(originalUrl);
  expect(mockState.items).toEqual([12]);
  expect(button.getAttribute('aria-pressed')).toBe('true');
  expect(fetchMock).not.toHaveBeenCalled();
});

it('does not create an empty banner and hides feedback after its announcement', async () => {
  initial.resolve(reply([]));
  await settle();
  expect(document.querySelector('[data-aa-wishlist-feedback]')).toBeNull();
  jest.useFakeTimers();
  press();
  await settle();
  save.resolve(reply({ message: 'Unavailable' }, 500));
  await settle();
  const notice = document.querySelector<HTMLElement>(
    '[data-aa-wishlist-feedback]'
  )!;
  expect(notice.hidden).toBe(false);
  expect(notice.textContent).toContain('could not be updated');
  jest.advanceTimersByTime(8000);
  expect(notice.hidden).toBe(true);
  expect(notice.textContent).toBe('');
});

it('rolls back a local variable save when browser storage refuses it', async () => {
  initial.resolve(reply([]));
  await settle();
  document.querySelector('button')!.dataset.aaProductType = 'variable';
  jest.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
    throw new Error('Storage unavailable');
  });
  press();
  await settle();
  expect(document.querySelector('button')!.getAttribute('aria-pressed')).toBe(
    'false'
  );
  expect(document.querySelector('button')!.hasAttribute('aria-busy')).toBe(
    false
  );
  expect(mockState.items).toEqual([]);
  expect(document.querySelector('[role="status"]')!.textContent).toContain(
    'could not be updated'
  );
});

it('reconciles a timed-out save before another click without duplicating the POST', async () => {
  initial.resolve(reply([]));
  await settle();
  jest.useFakeTimers();
  press();
  await settle();
  await jest.advanceTimersByTimeAsync(15_000);
  await settle();
  expect(document.querySelector('button')!.hasAttribute('aria-busy')).toBe(
    false
  );
  expect(document.querySelector('button')!.getAttribute('aria-pressed')).toBe(
    'false'
  );
  // The server committed the write but its response never reached the browser.
  fetchMock.mockResolvedValueOnce(
    reply([
      {
        id: 12,
        product_id: 12,
        key: 'saved',
        name: 'Hat',
        permalink: 'https://example.com/hat',
        prices: null,
      },
    ])
  );
  press();
  await settle();
  expect(mockState.items).toEqual([12]);
  expect(document.querySelector('button')!.hasAttribute('aria-busy')).toBe(
    false
  );
  expect(
    fetchMock.mock.calls.filter(([, init]) => init.method === 'POST')
  ).toHaveLength(1);
});

it('retains account products when the local product lookup fails', async () => {
  document.body.insertAdjacentHTML(
    'beforeend',
    '<div class="wp-block-aggressive-apparel-wishlist"></div>'
  );
  localStorage.setItem('aggressive_apparel_wishlist', '[99]');
  jest.resetModules();
  fetchMock
    .mockReset()
    .mockReturnValueOnce(initial.promise)
    .mockReturnValueOnce(save.promise);
  await import('../wishlist');
  initial.resolve(
    reply([
      {
        id: 12,
        product_id: 12,
        key: 'saved',
        name: 'Hat',
        permalink: 'https://example.com/hat',
        prices: null,
      },
    ])
  );
  save.resolve(reply({ message: 'Offline' }, 503));
  await settle();
  await settle();
  const state = mockState as typeof mockState & {
    wishlistProducts: { id: number }[];
    loadFailed: boolean;
  };
  expect(state.wishlistProducts.map(item => item.id)).toEqual([12]);
  expect(state.loadFailed).toBe(true);
  expect(mockState.items).toEqual([12, 99]);
});
