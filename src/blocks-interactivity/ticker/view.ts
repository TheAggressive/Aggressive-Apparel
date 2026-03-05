/// <reference types="@wordpress/interactivity" />
import { getContext, getElement, store } from '@wordpress/interactivity';

interface TickerContext {
  isPaused: boolean;
  pauseOnHover: boolean;
}

/**
 * Clone enough `.ticker__content` copies to fill the viewport,
 * then hand off to a CSS animation.
 *
 * The CSS `@keyframes ticker-scroll` translates the track by
 * `calc(-100% / var(--ticker-copies))` — exactly one copy's footprint.
 * Because the browser computes the percentage from the actual rendered
 * layout (width:max-content + padding-inline-end on each copy), there
 * is zero JS measurement error at the loop boundary.
 */
function setupTicker(ticker: HTMLElement): void {
  const track = ticker.querySelector<HTMLElement>('.ticker__track');
  if (!track) return;

  const firstContent = track.querySelector<HTMLElement>('.ticker__content');
  if (!firstContent) return;

  // Each copy's footprint includes its padding-inline-end (the inter-copy gap).
  const copyFootprint = firstContent.getBoundingClientRect().width;
  if (copyFootprint <= 0) return;

  const containerWidth = ticker.offsetWidth;
  const styles = window.getComputedStyle(ticker);

  // Need N copies so that at translateX(-100%/N) the viewport is
  // still fully covered. The +2 accounts for the copy being scrolled
  // off-screen on the left and partial coverage on the right.
  const copiesNeeded = Math.max(
    2,
    Math.ceil(containerWidth / copyFootprint) + 2
  );

  const existing = track.querySelectorAll('.ticker__content');
  const template = existing[1] || existing[0]; // prefer the aria-hidden copy

  for (let i = existing.length; i < copiesNeeded; i++) {
    const clone = template.cloneNode(true) as HTMLElement;
    clone.setAttribute('aria-hidden', 'true');
    clone.setAttribute('inert', '');
    track.appendChild(clone);
  }

  // With width:max-content on the track + padding-inline-end on each copy,
  // translateX(-100%/N) moves exactly one copy footprint — pixel-perfect.
  const totalCopies = track.querySelectorAll('.ticker__content').length;

  // Normalize duration so visual speed is consistent regardless of content width.
  // speed (--ticker-duration) = seconds to cross the viewport.
  const speed = parseFloat(styles.getPropertyValue('--ticker-duration')) || 30;
  const normalizedDuration = speed * (copyFootprint / containerWidth);

  track.style.setProperty('--ticker-copies', `${totalCopies}`);
  track.style.setProperty('--ticker-duration', `${normalizedDuration}s`);
  track.classList.add('is-ready');
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
