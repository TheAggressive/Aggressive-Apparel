/** @jest-environment jsdom */

interface NotificationContext {
  notifications: Array<{ thumbnail: string }>;
  currentIndex: number;
  isVisible: boolean;
  isDismissed: boolean;
}

let mockContext: NotificationContext;
// Captured by the module's top-level store registration.
var mockStartCycle: () => void;

jest.mock(
  '@wordpress/interactivity',
  () => ({
    store: (
      _name: string,
      config: { callbacks: { startCycle: () => void } }
    ) => {
      mockStartCycle = config.callbacks.startCycle;
    },
    getContext: () => mockContext,
    getElement: () => ({ ref: null }),
  }),
  { virtual: true }
);

describe('social proof image loading', () => {
  beforeEach(() => {
    jest.useFakeTimers();
    jest.resetModules();
    sessionStorage.clear();
    mockContext = {
      notifications: Array.from({ length: 12 }, (_, i) => ({
        thumbnail: `/image-${i}.jpg`,
      })),
      currentIndex: 0,
      isVisible: false,
      isDismissed: false,
    };
  });

  afterEach(() => {
    jest.clearAllTimers();
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  it('warms the current toast and then the next, without fetching the whole pool', async () => {
    const requested: string[] = [];
    jest.spyOn(window, 'Image').mockImplementation(() => {
      const image = document.createElement('img');
      Object.defineProperty(image, 'src', {
        set: (url: string) => {
          requested.push(url);
        },
      });
      return image;
    });
    await import('../social-proof');
    mockStartCycle();
    expect(requested).toEqual(['/image-0.jpg']);

    jest.advanceTimersByTime(5000);
    expect(mockContext.isVisible).toBe(true);
    expect(requested).toEqual(['/image-0.jpg', '/image-1.jpg']);

    jest.advanceTimersByTime(20350);
    expect(mockContext.currentIndex).toBe(1);
    expect(mockContext.isVisible).toBe(true);
    expect(requested).toEqual(['/image-0.jpg', '/image-1.jpg', '/image-2.jpg']);
  });
});
