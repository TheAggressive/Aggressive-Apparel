/**
 * Social Proof — Interactivity API Store.
 *
 * Cycles through recent purchase notifications on a timer.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

interface SocialProofNotification {
  name: string;
  city?: string;
  product: string;
  ago?: string;
  url?: string;
  thumbnail?: string;
}

interface SocialProofContext {
  notifications: SocialProofNotification[];
  currentIndex: number;
  isDismissed: boolean;
  isVisible: boolean;
  isHovered: boolean;
  displayDurationMs: number;
  intervalMs: number;
}

store('aggressive-apparel/social-proof', {
  state: {
    get currentMessage(): string {
      const ctx = getContext<SocialProofContext>();
      const n = ctx.notifications[ctx.currentIndex];
      if (!n) {
        return '';
      }
      const location = n.city ? ` from ${n.city}` : '';
      return `${n.name}${location} purchased ${n.product}`;
    },

    get currentTime(): string {
      const ctx = getContext<SocialProofContext>();
      const n = ctx.notifications[ctx.currentIndex];
      if (!n || !n.ago) {
        return '';
      }
      return `${n.ago} ago`;
    },

    get currentUrl(): string {
      const ctx = getContext<SocialProofContext>();
      const n = ctx.notifications[ctx.currentIndex];
      return n && n.url ? n.url : '#';
    },
  },

  actions: {
    dismiss(): void {
      const ctx = getContext<SocialProofContext>();
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

    handleMouseEnter(): void {
      const ctx = getContext<SocialProofContext>();
      ctx.isHovered = true;
    },

    handleMouseLeave(): void {
      const ctx = getContext<SocialProofContext>();
      ctx.isHovered = false;
    },
  },

  callbacks: {
    syncImage(): void {
      const ctx = getContext<SocialProofContext>();
      const { ref } = getElement();
      if (!ref) return;
      const n = ctx.notifications[ctx.currentIndex];
      (ref as HTMLImageElement).src = n && n.thumbnail ? n.thumbnail : '';
    },

    startCycle(): void {
      const ctx = getContext<SocialProofContext>();

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

      // Preload all notification thumbnails so they display instantly.
      ctx.notifications.forEach((n: SocialProofNotification) => {
        if (n.thumbnail) {
          const img = new Image();
          img.src = n.thumbnail;
        }
      });

      const displayMs = ctx.displayDurationMs || 5000;
      let hideTimer: ReturnType<typeof setTimeout> | null = null;
      let remaining = 0;
      let hideStartedAt = 0;

      /**
       * Start or resume the hide countdown.
       */
      const startHideTimer = (ms: number): void => {
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

      const showNext = (): void => {
        if (ctx.isDismissed) {
          return;
        }

        ctx.isVisible = true;
        startHideTimer(displayMs);
      };

      const pauseTimer = (): void => {
        if (hideTimer && ctx.isVisible) {
          clearTimeout(hideTimer);
          hideTimer = null;
          const elapsed = Date.now() - hideStartedAt;
          remaining = Math.max(remaining - elapsed, 0);
        }
      };

      const resumeTimer = (): void => {
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
        el.addEventListener('focusout', (e: Event) => {
          if (!el.contains((e as FocusEvent).relatedTarget as Node | null)) {
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
