/**
 * Card Flip Block — Interactivity API Store.
 *
 * One model for both variants: the toggle button drives `context.isFlipped`
 * (keyboard / touch / reduced-motion accessible), CSS performs the 3D flip, and
 * `syncFaces` marks the away-facing side `inert` so its content leaves the tab
 * order and accessibility tree. The hover variant mirrors the same state from
 * pointer enter/leave, so `inert` and `aria-pressed` stay correct either way.
 *
 * @package Aggressive_Apparel
 */

/// <reference types="@wordpress/interactivity" />
import { store, getContext, getElement } from '@wordpress/interactivity';

interface CardFlipContext {
  isFlipped: boolean;
  flipOn: 'hover' | 'click';
}

/**
 * Keep the away-facing side out of the tab order / accessibility tree while
 * both faces stay in the DOM for the 3D flip. Uses `inert` (not `hidden`) so
 * the flip animation is preserved. Exported for unit testing.
 */
export function applyFaceInert(root: Element, isFlipped: boolean): void {
  root
    .querySelector('.aa-card-flip__face--front')
    ?.toggleAttribute('inert', isFlipped);
  root
    .querySelector('.aa-card-flip__face--back')
    ?.toggleAttribute('inert', !isFlipped);
}

store('aggressive-apparel/card-flip', {
  actions: {
    toggle(): void {
      const ctx = getContext<CardFlipContext>();
      ctx.isFlipped = !ctx.isFlipped;
    },

    pointerEnter(): void {
      const ctx = getContext<CardFlipContext>();
      if (ctx.flipOn === 'hover') {
        ctx.isFlipped = true;
      }
    },

    pointerLeave(): void {
      const ctx = getContext<CardFlipContext>();
      if (ctx.flipOn === 'hover') {
        ctx.isFlipped = false;
      }
    },
  },

  callbacks: {
    // Runs on hydration and whenever isFlipped changes: keep the hidden face
    // out of the tab order / a11y tree while both stay in the DOM for the 3D
    // flip. Toggling `inert` (not `hidden`) preserves the flip animation.
    syncFaces(): void {
      const ctx = getContext<CardFlipContext>();
      const { ref } = getElement();
      if (ref) {
        applyFaceInert(ref, ctx.isFlipped);
      }
    },
  },
});
