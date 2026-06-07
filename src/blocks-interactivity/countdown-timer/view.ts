/**
 * Countdown Timer — Interactivity API Store.
 *
 * Drives sale-ending countdowns in two render paths:
 *
 * 1. SSR cards / single product summary use Interactivity API directives
 *    (`data-wp-init="callbacks.startTicker"` + `data-wp-text="context.*"`).
 *    These are hydrated by the IA and the `startTicker` callback runs once
 *    per element, mutating its reactive context.
 *
 * 2. AJAX-rendered cards (product-filters, load-more) inject countdown
 *    HTML via innerHTML, which the IA does NOT hydrate. Those nodes carry
 *    a plain `data-aa-countdown="{end_ts}"` attribute and per-segment
 *    `data-aa-countdown-segment="days|hours|minutes|seconds"` attributes.
 *    A document-level scanner below ticks them via a single shared
 *    setInterval so we never spawn N parallel timers per page.
 *
 * @package Aggressive_Apparel
 * @since 1.87.0
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

      tick();
      const intervalId = setInterval(tick, 1000);
    },
  },
});

/* ------------------------------------------------------------------ */
/*  Dynamic countdown support (AJAX-rendered cards)                   */
/* ------------------------------------------------------------------ */

const DYNAMIC_INIT_FLAG = 'aaCountdownInitialised';

interface DynamicCountdown {
  endTs: number;
  root: HTMLElement;
  segments: {
    days?: HTMLElement;
    hours?: HTMLElement;
    minutes?: HTMLElement;
    seconds?: HTMLElement;
  };
}

const dynamicCountdowns: DynamicCountdown[] = [];
let sharedTickerId: ReturnType<typeof setInterval> | null = null;

function tickDynamicCountdowns(): void {
  if (dynamicCountdowns.length === 0) {
    if (sharedTickerId !== null) {
      clearInterval(sharedTickerId);
      sharedTickerId = null;
    }
    return;
  }

  const now = Math.floor(Date.now() / 1000);

  for (let i = dynamicCountdowns.length - 1; i >= 0; i--) {
    const entry = dynamicCountdowns[i];

    if (!entry.root.isConnected) {
      dynamicCountdowns.splice(i, 1);
      continue;
    }

    const diff = Math.max(0, entry.endTs - now);
    const days = Math.floor(diff / 86400);
    const hours = Math.floor((diff % 86400) / 3600);
    const minutes = Math.floor((diff % 3600) / 60);
    const seconds = diff % 60;

    if (entry.segments.days) entry.segments.days.textContent = String(days);
    if (entry.segments.hours) entry.segments.hours.textContent = String(hours);
    if (entry.segments.minutes)
      entry.segments.minutes.textContent = String(minutes);
    if (entry.segments.seconds)
      entry.segments.seconds.textContent = String(seconds);

    if (diff <= 0) {
      dynamicCountdowns.splice(i, 1);
    }
  }
}

function ensureSharedTicker(): void {
  if (sharedTickerId === null && dynamicCountdowns.length > 0) {
    sharedTickerId = setInterval(tickDynamicCountdowns, 1000);
  }
}

function registerDynamicCountdowns(root: ParentNode = document): void {
  root
    .querySelectorAll<HTMLElement>('[data-aa-countdown]')
    .forEach((el: HTMLElement) => {
      if (el.dataset[DYNAMIC_INIT_FLAG]) return;
      const endTs = parseInt(el.dataset.aaCountdown || '', 10);
      if (!Number.isFinite(endTs) || endTs <= 0) return;

      el.dataset[DYNAMIC_INIT_FLAG] = '1';

      const entry: DynamicCountdown = {
        endTs,
        root: el,
        segments: {},
      };

      el.querySelectorAll<HTMLElement>('[data-aa-countdown-segment]').forEach(
        seg => {
          const key = seg.dataset.aaCountdownSegment as
            | keyof DynamicCountdown['segments']
            | undefined;
          if (key) entry.segments[key] = seg;
        }
      );

      dynamicCountdowns.push(entry);
    });

  if (dynamicCountdowns.length > 0) {
    tickDynamicCountdowns();
    ensureSharedTicker();
  }
}

document.addEventListener('aa:cards-rendered', ((
  e: CustomEvent<{ container: HTMLElement | null }>
) => {
  registerDynamicCountdowns(e.detail?.container ?? document);
}) as EventListener);

if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () =>
      registerDynamicCountdowns()
    );
  } else {
    registerDynamicCountdowns();
  }
}
