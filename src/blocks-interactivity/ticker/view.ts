/// <reference types="@wordpress/interactivity" />
import { getContext, getElement, store } from '@wordpress/interactivity';

interface TickerContext {
  isPaused: boolean;
  pauseOnHover: boolean;
}

interface TickerController {
  pause(): void;
  play(): void;
}

/** Registry of active ticker controllers keyed by element. */
const controllers = new WeakMap<HTMLElement, TickerController>();

/**
 * Sync the animation controller with the current isPaused state.
 */
function syncAnimation(paused: boolean): void {
  const { ref } = getElement();
  if (!(ref instanceof HTMLElement)) return;
  const ctrl = controllers.get(ref);
  if (!ctrl) return;
  if (paused) {
    ctrl.pause();
  } else {
    ctrl.play();
  }
}

/**
 * Ensure enough `.ticker__content` copies exist so the scrolling
 * animation loops seamlessly regardless of content width.
 *
 * Uses a manual rAF loop instead of CSS @keyframes or Web Animations
 * API iterations — both can produce a visible "snap" at the loop
 * boundary due to browser-specific iteration handling.
 */
function ensureSeamlessLoop(ticker: HTMLElement, initialPaused: boolean): void {
  const track = ticker.querySelector<HTMLElement>('.ticker__track');
  if (!track) return;

  const firstContent = track.querySelector<HTMLElement>('.ticker__content');
  if (!firstContent) return;

  // getBoundingClientRect gives subpixel precision (scrollWidth rounds).
  const contentWidth = firstContent.getBoundingClientRect().width;
  if (contentWidth <= 0) return;

  const containerWidth = ticker.offsetWidth;
  const styles = window.getComputedStyle(ticker);
  const gap = parseFloat(styles.getPropertyValue('--ticker-gap')) || 0;

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

  // Measure the actual rendered distance between copy #1 and copy #2.
  const copies = track.querySelectorAll('.ticker__content');
  const absStride =
    copies[1].getBoundingClientRect().left -
    copies[0].getBoundingClientRect().left;
  if (absStride <= 0) return;

  const durationMs =
    (parseFloat(styles.getPropertyValue('--ticker-duration')) || 30) * 1000;
  const reversed =
    styles.getPropertyValue('--ticker-direction').trim() === 'reverse';

  // --- rAF animation loop ---
  let elapsed = 0;
  let lastTime: number | null = null;
  let running = false;
  let rafId = 0;

  function frame(now: number): void {
    if (lastTime !== null) {
      elapsed += now - lastTime;
    }
    lastTime = now;

    const progress = (elapsed % durationMs) / durationMs;
    const px = progress * absStride;
    track.style.transform = `translateX(${reversed ? px : -px}px)`;

    rafId = requestAnimationFrame(frame);
  }

  function start(): void {
    if (running) return;
    running = true;
    lastTime = null;
    rafId = requestAnimationFrame(frame);
  }

  function stop(): void {
    if (!running) return;
    running = false;
    cancelAnimationFrame(rafId);
    lastTime = null;
  }

  controllers.set(ticker, { pause: stop, play: start });

  // Start in the correct state — honor isPaused if it was set
  // before the controller was created (e.g. reduced motion).
  if (!initialPaused) {
    start();
  }
}

store('aggressive-apparel/ticker', {
  state: {
    get isPausedString(): string {
      const ctx = getContext<TickerContext>();
      return ctx.isPaused ? 'true' : 'false';
    },
  },
  actions: {
    togglePause() {
      const ctx = getContext<TickerContext>();
      ctx.isPaused = !ctx.isPaused;
      syncAnimation(ctx.isPaused);
    },
    mouseEnter() {
      const ctx = getContext<TickerContext>();
      if (ctx.pauseOnHover) {
        ctx.isPaused = true;
        syncAnimation(true);
      }
    },
    mouseLeave() {
      const ctx = getContext<TickerContext>();
      if (ctx.pauseOnHover) {
        ctx.isPaused = false;
        syncAnimation(false);
      }
    },
    focusIn() {
      const ctx = getContext<TickerContext>();
      ctx.isPaused = true;
      syncAnimation(true);
    },
    focusOut() {
      const ctx = getContext<TickerContext>();
      if (ctx.pauseOnHover) {
        ctx.isPaused = false;
        syncAnimation(false);
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
        document.fonts.ready.then(() => ensureSeamlessLoop(ref, ctx.isPaused));
      }
    },
  },
});
