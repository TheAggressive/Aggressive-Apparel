/**
 * Social Proof — Interactivity API Store.
 *
 * Cycles through recent purchase notifications on a timer.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext } from '@wordpress/interactivity';

/**
 * Escape a string for safe use in HTML attributes.
 *
 * @param {string} str - Raw string.
 * @return {string} Escaped string.
 */
function escapeHtml(str) {
  if (!str) {
    return '';
  }
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

store('aggressive-apparel/social-proof', {
  state: {
    get currentMessage() {
      const ctx = getContext();
      const n = ctx.notifications[ctx.currentIndex];
      if (!n) {
        return '';
      }
      const location = n.city ? ` from ${n.city}` : '';
      return `${n.name}${location} purchased ${n.product}`;
    },

    get currentTime() {
      const ctx = getContext();
      const n = ctx.notifications[ctx.currentIndex];
      if (!n || !n.ago) {
        return '';
      }
      return `${n.ago} ago`;
    },

    get currentThumbnail() {
      const ctx = getContext();
      const n = ctx.notifications[ctx.currentIndex];
      if (!n || !n.thumbnail) {
        return '';
      }
      return `<img src="${escapeHtml(n.thumbnail)}" alt="${escapeHtml(n.product)}" />`;
    },
  },

  actions: {
    dismiss() {
      const ctx = getContext();
      ctx.isDismissed = true;
      ctx.isVisible = false;
      // Remember dismissal for this session.
      try {
        sessionStorage.setItem(
          'aggressive_apparel_social_proof_dismissed',
          '1'
        );
      } catch {
        // Ignore storage errors.
      }
    },
  },

  callbacks: {
    startCycle() {
      const ctx = getContext();

      // Check if previously dismissed.
      try {
        if (
          sessionStorage.getItem(
            'aggressive_apparel_social_proof_dismissed'
          ) === '1'
        ) {
          ctx.isDismissed = true;
          return;
        }
      } catch {
        // Ignore storage errors.
      }

      if (!ctx.notifications || ctx.notifications.length === 0) {
        return;
      }

      const showNext = () => {
        if (ctx.isDismissed) {
          return;
        }

        ctx.isVisible = true;

        // Hide after display duration.
        setTimeout(() => {
          ctx.isVisible = false;

          // Advance to next notification.
          ctx.currentIndex = (ctx.currentIndex + 1) % ctx.notifications.length;
        }, ctx.displayDurationMs || 5000);
      };

      // First notification after a short delay.
      setTimeout(showNext, 5000);

      // Subsequent notifications on interval.
      setInterval(showNext, ctx.intervalMs || 20000);
    },
  },
});
