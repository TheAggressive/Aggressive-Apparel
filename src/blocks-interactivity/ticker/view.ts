/// <reference types="@wordpress/interactivity" />
import { getContext, getElement, store } from '@wordpress/interactivity';

interface TickerContext {
  isPaused: boolean;
  pauseOnHover: boolean;
}

interface TickerRuntime {
  destroy: () => void;
}

interface TickerMetrics {
  maxContentWidth: number;
  trackWidth: number;
}

const tickerRuntimes = new WeakMap<HTMLElement, TickerRuntime>();

/**
 * Clone enough `.ticker__content` copies to cover the scroll area and animate
 * by recycling the offscreen copy to the far end of the track. This avoids a
 * visible reset point altogether, even when product-page styles or loaded media
 * shift the rendered widths after first paint.
 */
function setupTicker(ticker: HTMLElement): void {
  tickerRuntimes.get(ticker)?.destroy();

  const scroll = ticker.querySelector<HTMLElement>('.ticker__scroll');
  const track = ticker.querySelector<HTMLElement>('.ticker__track');
  if (!scroll || !track) return;

  let frameId = 0;
  let offset = 0;
  let previousTime = 0;
  let pxPerMs = 0;
  let reverse = false;
  let isDestroyed = false;
  let resizeFrameId = 0;
  const watchedImages = new WeakSet<HTMLImageElement>();

  const getContents = (): HTMLElement[] =>
    Array.from(track.querySelectorAll<HTMLElement>('.ticker__content'));

  const getContentWidth = (content: HTMLElement): number =>
    content.getBoundingClientRect().width;

  const getMetrics = (): TickerMetrics =>
    getContents().reduce<TickerMetrics>(
      (metrics, content) => {
        const width = getContentWidth(content);

        return {
          maxContentWidth: Math.max(metrics.maxContentWidth, width),
          trackWidth: metrics.trackWidth + width,
        };
      },
      { maxContentWidth: 0, trackWidth: 0 }
    );

  const getSpeedSeconds = (): number => {
    const styles = window.getComputedStyle(ticker);
    return parseFloat(styles.getPropertyValue('--ticker-duration')) || 30;
  };

  const setTransform = (): void => {
    track.style.transform = `translate3d(${-offset}px, 0, 0)`;
  };

  const recycleForward = (): void => {
    let firstContent = getContents()[0];
    let firstWidth = firstContent ? getContentWidth(firstContent) : 0;

    while (firstContent && firstWidth > 0 && offset >= firstWidth) {
      track.appendChild(firstContent);
      offset -= firstWidth;
      firstContent = getContents()[0];
      firstWidth = firstContent ? getContentWidth(firstContent) : 0;
    }
  };

  const recycleBackward = (): void => {
    let contents = getContents();
    let lastContent = contents[contents.length - 1];
    let lastWidth = lastContent ? getContentWidth(lastContent) : 0;

    while (lastContent && lastWidth > 0 && offset <= 0) {
      track.insertBefore(lastContent, track.firstElementChild);
      offset += lastWidth;
      contents = getContents();
      lastContent = contents[contents.length - 1];
      lastWidth = lastContent ? getContentWidth(lastContent) : 0;
    }
  };

  const watchImages = (): void => {
    track.querySelectorAll('img').forEach(image => {
      if (watchedImages.has(image)) return;

      watchedImages.add(image);
      if (!image.complete) {
        image.addEventListener('load', scheduleMeasure);
        image.addEventListener('error', scheduleMeasure);
      }
    });
  };

  const measure = (): void => {
    const contents = getContents();
    const firstContent = contents[0];
    const template = contents[1] || contents[0];
    const containerWidth = scroll.getBoundingClientRect().width;
    if (!firstContent || !template || containerWidth <= 0) return;

    const firstWidth = getContentWidth(firstContent);
    if (firstWidth <= 0) return;

    pxPerMs = containerWidth / (getSpeedSeconds() * 1000);
    reverse =
      window
        .getComputedStyle(ticker)
        .getPropertyValue('--ticker-direction')
        .trim() === 'reverse';

    const metrics = getMetrics();
    let trackWidth = metrics.trackWidth;
    let maxContentWidth = metrics.maxContentWidth || firstWidth;
    const minTrackWidth = containerWidth + maxContentWidth * 2;
    while (trackWidth < minTrackWidth) {
      const clone = template.cloneNode(true) as HTMLElement;
      clone.setAttribute('aria-hidden', 'true');
      clone.setAttribute('inert', '');
      track.appendChild(clone);
      const cloneWidth = getContentWidth(clone) || maxContentWidth;
      trackWidth += cloneWidth;
      maxContentWidth = Math.max(maxContentWidth, cloneWidth);
    }

    if (reverse && offset === 0) {
      offset = firstWidth;
    }

    if (reverse) {
      recycleBackward();
    } else {
      recycleForward();
    }

    watchImages();
    setTransform();
  };

  const tick = (time: number): void => {
    if (isDestroyed) return;

    if (!previousTime) {
      previousTime = time;
    }

    const delta = time - previousTime;
    previousTime = time;

    if (!ticker.classList.contains('is-paused') && pxPerMs > 0) {
      offset += (reverse ? -1 : 1) * delta * pxPerMs;
      if (reverse) {
        recycleBackward();
      } else {
        recycleForward();
      }
      setTransform();
    }

    frameId = window.requestAnimationFrame(tick);
  };

  const scheduleMeasure = (): void => {
    if (resizeFrameId) {
      window.cancelAnimationFrame(resizeFrameId);
    }

    resizeFrameId = window.requestAnimationFrame(() => {
      resizeFrameId = 0;
      measure();
    });
  };

  const resizeObserver = new ResizeObserver(scheduleMeasure);
  resizeObserver.observe(ticker);
  resizeObserver.observe(scroll);

  watchImages();

  tickerRuntimes.set(ticker, {
    destroy: () => {
      isDestroyed = true;
      window.cancelAnimationFrame(frameId);
      window.cancelAnimationFrame(resizeFrameId);
      resizeObserver.disconnect();
      track.querySelectorAll('img').forEach(image => {
        image.removeEventListener('load', scheduleMeasure);
        image.removeEventListener('error', scheduleMeasure);
      });
    },
  });

  measure();
  frameId = window.requestAnimationFrame(tick);
}

store('aggressive-apparel/ticker', {
  state: {
    get isPausedString(): string {
      return getContext<TickerContext>().isPaused ? 'true' : 'false';
    },
  },
  actions: {
    togglePause() {
      const ctx = getContext<TickerContext>();
      ctx.isPaused = !ctx.isPaused;
    },
    mouseEnter() {
      const ctx = getContext<TickerContext>();
      if (ctx.pauseOnHover) {
        ctx.isPaused = true;
      }
    },
    mouseLeave() {
      const ctx = getContext<TickerContext>();
      if (ctx.pauseOnHover) {
        ctx.isPaused = false;
      }
    },
    focusIn() {
      getContext<TickerContext>().isPaused = true;
    },
    focusOut() {
      const ctx = getContext<TickerContext>();
      if (ctx.pauseOnHover) {
        ctx.isPaused = false;
      }
    },
  },
  callbacks: {
    init() {
      const ctx = getContext<TickerContext>();

      // Auto-pause if user prefers reduced motion.
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        ctx.isPaused = true;
      }

      // Defer measurement until fonts are loaded so content widths are final.
      const { ref } = getElement();
      if (ref instanceof HTMLElement) {
        document.fonts.ready.then(() => setupTicker(ref));
      }
    },
  },
});
