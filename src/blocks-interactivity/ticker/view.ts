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

interface TickerAnimationState {
  isDestroyed: boolean;
  isIntersecting: boolean;
  isDocumentVisible: boolean;
  reducedMotion: boolean;
  isPaused: boolean;
  pxPerMs: number;
}

const tickerRuntimes = new WeakMap<HTMLElement, TickerRuntime>();

/** Marks `.ticker__content` copies created at runtime (vs server-rendered). */
const CLONE_ATTR = 'data-ticker-clone';

/**
 * Strip hydration/accessibility attributes from a runtime clone's subtree.
 *
 * Clones are created after the Interactivity API has hydrated, so their
 * `data-wp-*` directives are inert — leaving them in place is misleading and
 * duplicate `aria-live` regions / ids are invalid. Clones are purely
 * presentational (`aria-hidden` + `inert`).
 */
function sanitizeTickerClone(clone: HTMLElement): void {
  const elements = [clone, ...Array.from(clone.querySelectorAll('*'))];

  elements.forEach(element => {
    Array.from(element.attributes).forEach(attribute => {
      if (attribute.name.startsWith('data-wp-')) {
        element.removeAttribute(attribute.name);
      }
    });

    element.removeAttribute('aria-live');
    element.removeAttribute('aria-atomic');
    element.removeAttribute('id');
  });
}

/**
 * Pixels per millisecond for one full content-loop at the given speed.
 *
 * Speed is measured in seconds to scroll one `.ticker__content` width, so loop
 * duration stays consistent regardless of viewport width.
 */
export function getTickerPxPerMs(
  loopWidth: number,
  speedSeconds: number
): number {
  if (loopWidth <= 0 || speedSeconds <= 0) {
    return 0;
  }

  return loopWidth / (speedSeconds * 1000);
}

const DEFAULT_TICKER_SPEED = 30;

/** Parse loop duration from `data-ticker-speed`. */
export function parseTickerDataSpeed(
  value: string | undefined,
  fallback = DEFAULT_TICKER_SPEED
): number {
  const parsed = Number.parseFloat(value ?? '');
  return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

/** Whether the marquee scrolls right based on `data-ticker-direction`. */
export function isTickerReverseDirection(
  direction: string | undefined
): boolean {
  return direction === 'right';
}

/**
 * Whether a ticker should consume animation frames right now.
 */
export function shouldAnimateTicker(state: TickerAnimationState): boolean {
  return (
    !state.isDestroyed &&
    state.isIntersecting &&
    state.isDocumentVisible &&
    !state.reducedMotion &&
    !state.isPaused &&
    state.pxPerMs > 0
  );
}

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
  let contents: HTMLElement[] = [];
  let contentWidths = new Map<HTMLElement, number>();
  let isIntersecting = !('IntersectionObserver' in window);
  let isDocumentVisible = !document.hidden;
  let isPaused = ticker.classList.contains('is-paused');
  let cloneSyncFrameId = 0;
  const watchedImages = new WeakSet<HTMLImageElement>();
  const reducedMotionMql = window.matchMedia(
    '(prefers-reduced-motion: reduce)'
  );

  const getContents = (): HTMLElement[] =>
    Array.from(track.children).filter(
      (child): child is HTMLElement =>
        child instanceof HTMLElement &&
        child.classList.contains('ticker__content')
    );

  const getContentWidth = (content: HTMLElement): number =>
    content.getBoundingClientRect().width;

  const syncAnimationRate = (): void => {
    const firstContent = contents[0];
    const loopWidth = firstContent ? (contentWidths.get(firstContent) ?? 0) : 0;

    pxPerMs = getTickerPxPerMs(
      loopWidth,
      parseTickerDataSpeed(ticker.dataset.tickerSpeed)
    );
    reverse = isTickerReverseDirection(ticker.dataset.tickerDirection);
  };

  const setTransform = (): void => {
    track.style.transform = `translate3d(${-offset}px, 0, 0)`;
  };

  const recycleForward = (): void => {
    let firstContent = contents[0];
    let firstWidth = firstContent ? (contentWidths.get(firstContent) ?? 0) : 0;

    while (firstContent && firstWidth > 0 && offset >= firstWidth) {
      track.appendChild(firstContent);
      offset -= firstWidth;

      const movedContent = contents.shift();
      if (movedContent) {
        contents.push(movedContent);
      }

      firstContent = contents[0];
      firstWidth = firstContent ? (contentWidths.get(firstContent) ?? 0) : 0;
    }
  };

  const recycleBackward = (): void => {
    let lastContent = contents[contents.length - 1];
    let lastWidth = lastContent ? (contentWidths.get(lastContent) ?? 0) : 0;

    while (lastContent && lastWidth > 0 && offset <= 0) {
      track.insertBefore(lastContent, track.firstElementChild);
      offset += lastWidth;

      const movedContent = contents.pop();
      if (movedContent) {
        contents.unshift(movedContent);
      }

      lastContent = contents[contents.length - 1];
      lastWidth = lastContent ? (contentWidths.get(lastContent) ?? 0) : 0;
    }
  };

  const canAnimate = (): boolean =>
    shouldAnimateTicker({
      isDestroyed,
      isIntersecting,
      isDocumentVisible,
      reducedMotion: reducedMotionMql.matches,
      isPaused,
      pxPerMs,
    });

  const stopAnimation = (): void => {
    if (frameId) {
      window.cancelAnimationFrame(frameId);
      frameId = 0;
    }

    previousTime = 0;
    track.style.removeProperty('will-change');
  };

  const tick = (time: number): void => {
    frameId = 0;

    syncAnimationRate();

    if (!canAnimate()) {
      stopAnimation();
      return;
    }

    if (!previousTime) {
      previousTime = time;
    }

    const delta = time - previousTime;
    previousTime = time;
    offset += (reverse ? -1 : 1) * delta * pxPerMs;

    if (reverse) {
      recycleBackward();
    } else {
      recycleForward();
    }

    setTransform();
    frameId = window.requestAnimationFrame(tick);
  };

  const syncAnimation = (): void => {
    if (!canAnimate()) {
      stopAnimation();
      return;
    }

    if (frameId) return;

    previousTime = 0;
    track.style.willChange = 'transform';
    frameId = window.requestAnimationFrame(tick);
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
    if (isDestroyed) return;

    const nextContents = getContents();
    const firstContent = nextContents[0];
    const template = nextContents[1] || nextContents[0];
    const containerWidth = scroll.getBoundingClientRect().width;
    if (!firstContent || !template || containerWidth <= 0) {
      pxPerMs = 0;
      syncAnimation();
      return;
    }

    const nextContentWidths = new Map<HTMLElement, number>();
    const metrics = nextContents.reduce<TickerMetrics>(
      (result, content) => {
        const width = getContentWidth(content);
        nextContentWidths.set(content, width);

        return {
          maxContentWidth: Math.max(result.maxContentWidth, width),
          trackWidth: result.trackWidth + width,
        };
      },
      { maxContentWidth: 0, trackWidth: 0 }
    );

    const firstWidth = nextContentWidths.get(firstContent) ?? 0;
    if (firstWidth <= 0) {
      pxPerMs = 0;
      syncAnimation();
      return;
    }

    syncAnimationRate();

    let trackWidth = metrics.trackWidth;
    let maxContentWidth = metrics.maxContentWidth || firstWidth;
    const minTrackWidth = containerWidth + maxContentWidth * 2;
    while (trackWidth < minTrackWidth) {
      const clone = template.cloneNode(true) as HTMLElement;
      clone.setAttribute('aria-hidden', 'true');
      clone.setAttribute('inert', '');
      clone.setAttribute(CLONE_ATTR, '');
      sanitizeTickerClone(clone);
      track.appendChild(clone);
      const cloneWidth = getContentWidth(clone) || maxContentWidth;
      nextContents.push(clone);
      nextContentWidths.set(clone, cloneWidth);
      trackWidth += cloneWidth;
      maxContentWidth = Math.max(maxContentWidth, cloneWidth);
    }

    contents = nextContents;
    contentWidths = nextContentWidths;

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
    syncAnimation();
  };

  const scheduleMeasure = (): void => {
    if (isDestroyed) return;

    if (resizeFrameId) {
      window.cancelAnimationFrame(resizeFrameId);
    }

    resizeFrameId = window.requestAnimationFrame(() => {
      resizeFrameId = 0;
      measure();
    });
  };

  // ==========================================================================
  // Keep runtime clones in sync with hydrated content.
  //
  // The Interactivity API only hydrates DOM present at load time. The
  // server-rendered `.ticker__content` copies hydrate and update reactively
  // (e.g. the free-shipping amount), but copies cloned here at runtime are
  // inert snapshots — without this observer they keep stale text forever.
  // ==========================================================================
  const originals = getContents().filter(
    content => !content.hasAttribute(CLONE_ATTR)
  );

  const syncClones = (): void => {
    if (isDestroyed || originals.length === 0) return;

    const source = originals[0];

    getContents().forEach(content => {
      if (!content.hasAttribute(CLONE_ATTR)) return;

      content.innerHTML = source.innerHTML;
      sanitizeTickerClone(content);
    });

    // Text changes shift content widths; re-measure so the loop stays seamless.
    scheduleMeasure();
  };

  const scheduleCloneSync = (): void => {
    if (isDestroyed) return;

    if (cloneSyncFrameId) {
      window.cancelAnimationFrame(cloneSyncFrameId);
    }

    cloneSyncFrameId = window.requestAnimationFrame(() => {
      cloneSyncFrameId = 0;
      syncClones();
    });
  };

  const contentObserver = new MutationObserver(scheduleCloneSync);
  originals.forEach(original => {
    contentObserver.observe(original, {
      subtree: true,
      childList: true,
      characterData: true,
    });
  });

  const resizeObserver = new ResizeObserver(scheduleMeasure);
  resizeObserver.observe(ticker);
  resizeObserver.observe(scroll);

  const intersectionObserver =
    'IntersectionObserver' in window
      ? new IntersectionObserver(entries => {
          isIntersecting = entries.some(entry => entry.isIntersecting);
          syncAnimation();
        })
      : null;
  intersectionObserver?.observe(ticker);

  const attributeObserver = new MutationObserver(() => {
    isPaused = ticker.classList.contains('is-paused');
    syncAnimationRate();
    syncAnimation();
  });
  attributeObserver.observe(ticker, {
    attributes: true,
    attributeFilter: ['class', 'data-ticker-speed', 'data-ticker-direction'],
  });

  const handleVisibilityChange = (): void => {
    isDocumentVisible = !document.hidden;
    syncAnimation();
  };
  document.addEventListener('visibilitychange', handleVisibilityChange);

  const handleReducedMotionChange = (): void => {
    syncAnimation();
  };
  reducedMotionMql.addEventListener('change', handleReducedMotionChange);

  watchImages();

  tickerRuntimes.set(ticker, {
    destroy: () => {
      isDestroyed = true;
      window.cancelAnimationFrame(frameId);
      window.cancelAnimationFrame(resizeFrameId);
      window.cancelAnimationFrame(cloneSyncFrameId);
      resizeObserver.disconnect();
      intersectionObserver?.disconnect();
      attributeObserver.disconnect();
      contentObserver.disconnect();
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      reducedMotionMql.removeEventListener('change', handleReducedMotionChange);
      track.style.removeProperty('will-change');
      track.querySelectorAll('img').forEach(image => {
        image.removeEventListener('load', scheduleMeasure);
        image.removeEventListener('error', scheduleMeasure);
      });
      tickerRuntimes.delete(ticker);
    },
  });

  measure();
  syncAnimation();
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
        document.fonts.ready.then(() => {
          if (ref.isConnected) {
            setupTicker(ref);
          }
        });
      }
    },
  },
});
