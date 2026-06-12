/**
 * Card Flip Block — Interactivity API Store.
 *
 * Handles click-to-flip variant. Hover variant is pure CSS.
 *
 * @package Aggressive_Apparel
 */

/// <reference types="@wordpress/interactivity" />
import { store, getContext } from '@wordpress/interactivity';

interface CardFlipContext {
  isFlipped: boolean;
  flipOn: string;
}

store('aggressive-apparel/card-flip', {
  actions: {
    toggle() {
      const ctx = getContext<CardFlipContext>();
      if (ctx.flipOn !== 'click') return;
      ctx.isFlipped = !ctx.isFlipped;
    },
    onKeydown(e: KeyboardEvent) {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        const ctx = getContext<CardFlipContext>();
        if (ctx.flipOn !== 'click') return;
        ctx.isFlipped = !ctx.isFlipped;
      }
    },
  },
});
