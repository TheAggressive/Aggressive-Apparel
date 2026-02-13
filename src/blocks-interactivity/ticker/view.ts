/// <reference types="@wordpress/interactivity" />
import { getContext, getElement, store } from '@wordpress/interactivity';

interface TickerContext {
  isPaused: boolean;
  pauseOnHover: boolean;
}

/**
 * Ensure enough `.ticker__content` copies exist so the scrolling
 * animation loops seamlessly regardless of content width.
 *
 * PHP renders exactly 2 copies. If the content is narrower than the
 * viewport, we clone additional copies so there is never a visible gap.
 */
function ensureSeamlessLoop(ticker: HTMLElement): void {
  const track = ticker.querySelector<HTMLElement>('.ticker__track');
  if (!track) return;

  const firstContent = track.querySelector<HTMLElement>('.ticker__content');
  if (!firstContent) return;

  const contentWidth = firstContent.scrollWidth;
  if (contentWidth <= 0) return;

  const containerWidth = ticker.offsetWidth;
  const gap =
    parseFloat(
      window.getComputedStyle(ticker).getPropertyValue('--ticker-gap')
    ) || 0;

  // Need N copies so that (N − 1) copies span ≥ the viewport.
  const copiesNeeded = Math.max(
    2,
    Math.ceil(containerWidth / (contentWidth + gap)) + 1
  );

  const existing = track.querySelectorAll('.ticker__content');
  const template = existing[1] || existing[0]; // prefer the aria-hidden copy

  for (let i = existing.length; i < copiesNeeded; i++) {
    const clone = template.cloneNode(true) as HTMLElement;
    clone.setAttribute('aria-hidden', 'true');
    clone.setAttribute('inert', '');
    track.appendChild(clone);
  }
}

store('aggressive-apparel/ticker', {
  state: {
    get isPausedString(): string {
      const ctx = getContext<TickerContext>();
      return ctx.isPaused ? 'true' : 'false';
    },
    get isPlaying(): boolean {
      const ctx = getContext<TickerContext>();
      return !ctx.isPaused;
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
      const ctx = getContext<TickerContext>();
      ctx.isPaused = true;
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
      // Auto-pause if user prefers reduced motion.
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const ctx = getContext<TickerContext>();
        ctx.isPaused = true;
      }

      // Clone content copies so the loop is seamless at any viewport width.
      const { ref } = getElement();
      if (ref instanceof HTMLElement) {
        ensureSeamlessLoop(ref);
      }
    },
  },
});
