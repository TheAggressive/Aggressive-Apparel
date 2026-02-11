/// <reference types="@wordpress/interactivity" />
import { getContext, store } from '@wordpress/interactivity';

interface TickerContext {
  isPaused: boolean;
  pauseOnHover: boolean;
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
    },
  },
});
