/**
 * Countdown Timer — Interactivity API Store.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext } from '@wordpress/interactivity';

interface CountdownContext {
  endTs: number;
  days: number;
  hours: number;
  minutes: number;
  seconds: number;
}

store('aggressive-apparel/countdown', {
  callbacks: {
    startTicker(): void {
      const ctx = getContext<CountdownContext>();
      const endTs = ctx.endTs;
      if (!endTs) {
        return;
      }

      const tick = (): void => {
        const now = Math.floor(Date.now() / 1000);
        const diff = Math.max(0, endTs - now);

        ctx.days = Math.floor(diff / 86400);
        ctx.hours = Math.floor((diff % 86400) / 3600);
        ctx.minutes = Math.floor((diff % 3600) / 60);
        ctx.seconds = diff % 60;

        if (diff <= 0) {
          clearInterval(intervalId);
        }
      };

      // Run immediately, then every second.
      tick();
      const intervalId = setInterval(tick, 1000);
    },
  },
});
