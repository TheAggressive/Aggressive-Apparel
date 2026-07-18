/// <reference types="@wordpress/interactivity" />
/**
 * Ticker block — Interactivity API store.
 *
 * Pause model mirrors the hero carousel:
 * - `isPaused` — manual play/pause control (and reduced-motion lock).
 * - `isHeld` — temporary hover / focus hold that must not clobber manual pause.
 *
 * Accessible control labels live on `context.controlLabel` (not a `state`
 * getter) so SSR directive processing keeps a real name before hydration.
 *
 * @package Aggressive_Apparel
 */

import { getContext, getElement, store } from '@wordpress/interactivity';
import { isEffectivelyPaused, isTickerPauseControl } from './logic';
import {
  canUseHoverPause,
  prefersReducedMotion,
  whenDocumentFontsReady,
} from './prefs';
import { destroyTicker, setupTicker } from './runtime';
import type { TickerContext } from './types';

function rootFromEvent(event: Event): HTMLElement | null {
  const { ref } = getElement();
  if (ref instanceof HTMLElement) {
    return ref;
  }

  const target = event.currentTarget;
  return target instanceof HTMLElement ? target : null;
}

function focusRemainsInRoot(
  root: HTMLElement | null,
  relatedTarget: EventTarget | null
): boolean {
  if (!root) {
    return false;
  }

  if (relatedTarget instanceof Node && root.contains(relatedTarget)) {
    return true;
  }

  const active = document.activeElement;
  return active instanceof Node && root.contains(active);
}

/** Sync the control's accessible name with manual pause / motion lock. */
function syncControlLabel(ctx: TickerContext): void {
  ctx.controlLabel =
    ctx.isPaused || ctx.motionLocked
      ? (ctx.i18n?.play ?? 'Play animation')
      : (ctx.i18n?.pause ?? 'Pause animation');
}

store('aggressive-apparel/ticker', {
  state: {
    get isEffectivelyPaused(): boolean {
      return isEffectivelyPaused(getContext<TickerContext>());
    },
    /** `aria-pressed` reflects the manual control, not a hover hold. */
    get isPausedPressed(): boolean {
      const ctx = getContext<TickerContext>();
      return ctx.isPaused || ctx.motionLocked;
    },
  },
  actions: {
    togglePause() {
      const ctx = getContext<TickerContext>();
      if (ctx.motionLocked) {
        return;
      }

      ctx.isPaused = !ctx.isPaused;

      // Resuming from the control must clear hover/focus hold. On touch, tap
      // leaves focus on the button; without this, Play toggles `isPaused` off
      // but `isHeld` keeps the marquee frozen.
      if (!ctx.isPaused) {
        ctx.isHeld = false;
      }

      syncControlLabel(ctx);

      // Drop sticky tap-focus on the control so `:focus-within` / hold don't linger.
      const active = document.activeElement;
      if (isTickerPauseControl(active) && active instanceof HTMLElement) {
        active.blur();
      }
    },
    mouseEnter() {
      const ctx = getContext<TickerContext>();
      if (!ctx.pauseOnHover || !canUseHoverPause()) {
        return;
      }

      ctx.isHeld = true;
    },
    mouseLeave() {
      const ctx = getContext<TickerContext>();
      if (!ctx.pauseOnHover || !canUseHoverPause()) {
        return;
      }

      const { ref } = getElement();
      const root = ref instanceof HTMLElement ? ref : null;
      // Keep the hold if keyboard focus is still inside ticker content.
      if (focusRemainsInRoot(root, null)) {
        return;
      }

      ctx.isHeld = false;
    },
    focusIn(event: FocusEvent) {
      // Hold so keyboard users can read moving content — but never when the
      // pause control itself is focused, or Play cannot release the hold.
      if (isTickerPauseControl(event.target)) {
        return;
      }

      getContext<TickerContext>().isHeld = true;
    },
    focusOut(event: FocusEvent) {
      const root = rootFromEvent(event);
      if (focusRemainsInRoot(root, event.relatedTarget)) {
        return;
      }

      getContext<TickerContext>().isHeld = false;
    },
  },
  callbacks: {
    init(): (() => void) | void {
      const ctx = getContext<TickerContext>();
      const { ref } = getElement();
      if (!(ref instanceof HTMLElement)) {
        return;
      }

      // Lock motion for reduced-motion visitors; keep the control disabled
      // so "Play" cannot imply animation is available.
      if (prefersReducedMotion()) {
        ctx.isPaused = true;
        ctx.motionLocked = true;
        syncControlLabel(ctx);
      }

      let cancelled = false;

      whenDocumentFontsReady().then(() => {
        if (!cancelled && ref.isConnected) {
          setupTicker(ref);
        }
      });

      return () => {
        cancelled = true;
        destroyTicker(ref);
      };
    },
  },
});
