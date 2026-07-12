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

import { store, getContext, getElement } from '@wordpress/interactivity';

interface CountdownI18n {
  template: string;
  day: [string, string];
  hour: [string, string];
  minute: [string, string];
  lessThanMinute: string;
  ended: string;
  dropLive?: string;
}

interface CountdownContext {
  endTs: number;
  days: number;
  hours: number;
  minutes: number;
  seconds: number;
  dropPageUrl?: string;
  ariaLabel?: string;
  labelText?: string;
  isExpired?: boolean;
  i18n?: CountdownI18n;
}

/** Set on a countdown root once a ticker owns it (hydrated or dynamic). */
const DYNAMIC_INIT_FLAG = 'aaCountdownInitialised';

/** Pick the singular/plural template by count and fill in the number. */
function formatCount(pair: [string, string], count: number): string {
  return (count === 1 ? pair[0] : pair[1]).replace('%s', String(count));
}

/**
 * Human-readable remaining time for the wrapper's aria-label. Seconds are
 * intentionally omitted — the label refreshes at most once per minute.
 */
function buildAriaLabel(
  i18n: CountdownI18n,
  diff: number,
  days: number,
  hours: number,
  minutes: number
): string {
  if (diff < 60) {
    return i18n.template.replace('%s', i18n.lessThanMinute);
  }

  const parts: string[] = [];

  if (days > 0) {
    parts.push(formatCount(i18n.day, days));
  }

  if (days > 0 || hours > 0) {
    parts.push(formatCount(i18n.hour, hours));
  }

  parts.push(formatCount(i18n.minute, minutes));

  return i18n.template.replace('%s', parts.join(', '));
}

store('aggressive-apparel/countdown-timer', {
  callbacks: {
    startTicker(): void {
      const ctx = getContext<CountdownContext>();
      const endTs = ctx.endTs;
      if (!endTs) {
        return;
      }

      // Claim this instance so the dynamic scanner below (for AJAX-injected
      // copies the Interactivity API never hydrates) skips it.
      const { ref } = getElement();
      if (ref) {
        ref.dataset[DYNAMIC_INIT_FLAG] = '1';
      }

      let intervalId: ReturnType<typeof setInterval> | null = null;
      let lastAriaMinutes = -1;
      let finished = false;

      const tick = (): void => {
        if (finished) {
          return;
        }

        const now = Math.floor(Date.now() / 1000);
        const diff = Math.max(0, endTs - now);

        ctx.days = Math.floor(diff / 86400);
        ctx.hours = Math.floor((diff % 86400) / 3600);
        ctx.minutes = Math.floor((diff % 3600) / 60);
        ctx.seconds = diff % 60;

        // Refresh the accessible label once per minute, never per second.
        const totalMinutes = Math.floor(diff / 60);
        if (ctx.i18n && totalMinutes !== lastAriaMinutes) {
          lastAriaMinutes = totalMinutes;
          ctx.ariaLabel = buildAriaLabel(
            ctx.i18n,
            diff,
            ctx.days,
            ctx.hours,
            ctx.minutes
          );
        }

        if (diff <= 0) {
          finished = true;
          if (intervalId !== null) {
            clearInterval(intervalId);
          }
          if (ctx.dropPageUrl) {
            document.dispatchEvent(
              new CustomEvent('aa:drop-live', { bubbles: false })
            );

            // Announce to screen readers before redirecting.
            // The live region must be in the DOM before text is set — rAF ensures that.
            const live = document.createElement('div');
            live.setAttribute('aria-live', 'assertive');
            live.setAttribute('aria-atomic', 'true');
            live.style.cssText =
              'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap';
            document.body.appendChild(live);
            requestAnimationFrame(() => {
              live.textContent =
                ctx.i18n?.dropLive ?? 'Drop is live. Redirecting now.';
            });

            // Leave the assertive announcement time to finish before the
            // change of context (WCAG 3.2.5) — 500ms cut it off mid-sentence.
            const url = ctx.dropPageUrl;
            setTimeout(() => {
              window.location.href = url;
            }, 2000);
          } else {
            // No drop page: mark the timer expired instead of freezing at
            // "Sale ends in 0d 0h 0m 0s".
            ctx.isExpired = true;
            if (ctx.i18n) {
              ctx.labelText = ctx.i18n.ended;
              ctx.ariaLabel = ctx.i18n.ended;
            }
          }
        }
      };

      tick();
      if (!finished) {
        intervalId = setInterval(tick, 1000);
      }
    },
  },
});

/* ------------------------------------------------------------------ */
/*  Dynamic countdown support (AJAX-rendered cards)                   */
/* ------------------------------------------------------------------ */

interface DynamicCountdown {
  endTs: number;
  root: HTMLElement;
  label: HTMLElement | null;
  i18n?: CountdownI18n;
  lastAriaMinutes: number;
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

    // Same once-per-minute accessible-label refresh as hydrated instances.
    const totalMinutes = Math.floor(diff / 60);
    if (entry.i18n && totalMinutes !== entry.lastAriaMinutes) {
      entry.lastAriaMinutes = totalMinutes;
      entry.root.setAttribute(
        'aria-label',
        buildAriaLabel(entry.i18n, diff, days, hours, minutes)
      );
    }

    if (diff <= 0) {
      // Dynamic copies never redirect (a card shouldn't navigate the page);
      // they just flip to the expired state like hydrated ones do.
      entry.root.classList.add('aggressive-apparel-countdown--expired');
      if (entry.i18n) {
        if (entry.label) {
          entry.label.textContent = entry.i18n.ended;
        }
        entry.root.setAttribute('aria-label', entry.i18n.ended);
      }
      dynamicCountdowns.splice(i, 1);
    }
  }
}

function ensureSharedTicker(): void {
  if (sharedTickerId === null && dynamicCountdowns.length > 0) {
    sharedTickerId = setInterval(tickDynamicCountdowns, 1000);
  }
}

function registerDynamicCountdowns(
  root: ParentNode = document,
  // Initial-document sweeps must leave hydratable markup to the Interactivity
  // API (startTicker claims it); hydration order vs. this sweep is not
  // guaranteed, so skipping by attribute is the only deterministic guard.
  // AJAX-injected copies carry the same attribute but are never hydrated,
  // so event-driven registration passes false.
  skipHydratable = false
): void {
  root
    .querySelectorAll<HTMLElement>('[data-aa-countdown]')
    .forEach((el: HTMLElement) => {
      if (el.dataset[DYNAMIC_INIT_FLAG]) return;
      if (skipHydratable && el.hasAttribute('data-wp-interactive')) return;
      const endTs = parseInt(el.dataset.aaCountdown || '', 10);
      if (!Number.isFinite(endTs) || endTs <= 0) return;

      el.dataset[DYNAMIC_INIT_FLAG] = '1';

      // The SSR markup carries the i18n strings in data-wp-context; reuse
      // them so dynamic copies keep the accessible label fresh too.
      let i18n: CountdownI18n | undefined;
      try {
        const rawContext = el.getAttribute('data-wp-context');
        if (rawContext) {
          i18n = (JSON.parse(rawContext) as { i18n?: CountdownI18n }).i18n;
        }
      } catch {
        // Malformed context — the label just stays at its SSR value.
      }

      const entry: DynamicCountdown = {
        endTs,
        root: el,
        label: el.querySelector<HTMLElement>(
          '.aggressive-apparel-countdown__label'
        ),
        i18n,
        lastAriaMinutes: -1,
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
      registerDynamicCountdowns(document, true)
    );
  } else {
    registerDynamicCountdowns(document, true);
  }
}
