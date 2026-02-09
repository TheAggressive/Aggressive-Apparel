/**
 * Social Proof — Interactivity API Store.
 *
 * Cycles through recent purchase notifications on a timer.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext } from '@wordpress/interactivity';

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

    get currentThumbnailSrc() {
      const ctx = getContext();
      const n = ctx.notifications[ctx.currentIndex];
      return n && n.thumbnail ? n.thumbnail : '';
    },

    get currentUrl() {
      const ctx = getContext();
      const n = ctx.notifications[ctx.currentIndex];
      return n && n.url ? n.url : '#';
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

    handleMouseEnter() {
      const ctx = getContext();
      ctx.isHovered = true;
    },

    handleMouseLeave() {
      const ctx = getContext();
      ctx.isHovered = false;
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

      const displayMs = ctx.displayDurationMs || 5000;
      let hideTimer = null;
      let remaining = 0;
      let hideStartedAt = 0;

      /**
       * Start or resume the hide countdown.
       *
       * @param {number} ms Milliseconds until hide.
       */
      const startHideTimer = ms => {
        remaining = ms;
        hideStartedAt = Date.now();
        hideTimer = setTimeout(() => {
          if (ctx.isHovered) {
            // User is hovering — wait for mouseleave.
            remaining = 0;
            return;
          }
          ctx.isVisible = false;

          // Advance after the CSS fade-out transition finishes (300ms).
          setTimeout(() => {
            ctx.currentIndex =
              (ctx.currentIndex + 1) % ctx.notifications.length;
          }, 350);
        }, ms);
      };

      const showNext = () => {
        if (ctx.isDismissed) {
          return;
        }

        ctx.isVisible = true;
        startHideTimer(displayMs);
      };

      const pauseTimer = () => {
        if (hideTimer && ctx.isVisible) {
          clearTimeout(hideTimer);
          hideTimer = null;
          const elapsed = Date.now() - hideStartedAt;
          remaining = Math.max(remaining - elapsed, 0);
        }
      };

      const resumeTimer = () => {
        if (ctx.isVisible && !ctx.isDismissed) {
          if (remaining <= 0) {
            ctx.isVisible = false;
            setTimeout(() => {
              ctx.currentIndex =
                (ctx.currentIndex + 1) % ctx.notifications.length;
            }, 350);
          } else {
            startHideTimer(remaining);
          }
        }
      };

      // Pause on hover and keyboard focus; resume on leave/blur.
      const el = document.querySelector(
        '.aggressive-apparel-social-proof__toast'
      );
      if (el) {
        el.addEventListener('mouseenter', pauseTimer);
        el.addEventListener('mouseleave', resumeTimer);
        el.addEventListener('focusin', pauseTimer);
        el.addEventListener('focusout', e => {
          if (!el.contains(e.relatedTarget)) {
            resumeTimer();
          }
        });
      }

      // First notification after a short delay.
      setTimeout(showNext, 5000);

      // Subsequent notifications on interval.
      setInterval(showNext, ctx.intervalMs || 20000);
    },
  },
});
