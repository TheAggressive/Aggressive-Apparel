/**
 * Social Proof — Interactivity API Store.
 *
 * Cycles through pre-built notification strings on a timer. The
 * interesting decisions (anonymisation, source mixing, demo gating)
 * all happen server-side in PHP — the client just paints what it's
 * given and handles the timing / hover-pause UX. Trusted SVG/HTML for
 * decor and badge slots is applied with data-wp-watch callbacks because
 * data-wp-html is not part of the Interactivity API directive set.
 *
 * Notifications can come from any source (trust messages, real purchases,
 * engagement cues, custom announcements, admin demo) and may legitimately
 * lack a thumbnail, link, or relative time. The derived state below
 * exposes hide-flags so the toast template can render the right
 * variant without having to inline that logic in PHP.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext, getElement } from '@wordpress/interactivity';

interface SocialProofNotification {
  message: string;
  time?: string;
  url?: string;
  thumbnail?: string;
  /** Safe HTML for left-column PREFIX icons without a WC thumbnail (trust / announcements). */
  decor_html?: string;
  /** Safe HTML thumbnail badge overlay (WC purchases, engagement, demo). */
  badge_html?: string;
  kind?: 'trust' | 'purchase' | 'engagement' | 'announcement' | 'demo' | string;
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

const DISMISSED_KEY = 'aggressive_apparel_social_proof_dismissed';
const SOCIAL_PROOF_STORE = 'aggressive-apparel/social-proof';

const currentNotification = (
  ctx: SocialProofContext
): SocialProofNotification | undefined => ctx.notifications[ctx.currentIndex];

store(SOCIAL_PROOF_STORE, {
  state: {
    get currentMessage(): string {
      return (
        currentNotification(getContext<SocialProofContext>())?.message ?? ''
      );
    },

    get currentTime(): string {
      return currentNotification(getContext<SocialProofContext>())?.time ?? '';
    },

    get currentUrl(): string {
      return currentNotification(getContext<SocialProofContext>())?.url ?? '#';
    },

    get currentDecorHtml(): string {
      return (
        currentNotification(getContext<SocialProofContext>())?.decor_html ?? ''
      );
    },

    get currentBadgeHtml(): string {
      return (
        currentNotification(getContext<SocialProofContext>())?.badge_html ?? ''
      );
    },

    get currentThumbnailWrapHidden(): boolean {
      const n = currentNotification(getContext<SocialProofContext>());
      return !n?.thumbnail?.trim();
    },

    get currentDecorHidden(): boolean {
      const n = currentNotification(getContext<SocialProofContext>());
      const decor = n?.decor_html?.trim();

      if (!decor) {
        return true;
      }

      // Product thumbnails win the visual row; PREFIX icons are trust-only.
      if (n?.thumbnail?.trim()) {
        return true;
      }

      return false;
    },

    get currentBadgeHidden(): boolean {
      const n = currentNotification(getContext<SocialProofContext>());
      const badge = n?.badge_html?.trim();
      const thumb = n?.thumbnail?.trim();

      return !badge || !thumb;
    },

    get currentVisualHidden(): boolean {
      const n = currentNotification(getContext<SocialProofContext>());

      if (n?.thumbnail?.trim()) {
        return false;
      }

      return !n?.decor_html?.trim();
    },

    get currentHasNoTime(): boolean {
      return !currentNotification(getContext<SocialProofContext>())?.time;
    },

    get currentHasLink(): boolean {
      return !!currentNotification(getContext<SocialProofContext>())?.url;
    },

    get currentHasNoLink(): boolean {
      return !currentNotification(getContext<SocialProofContext>())?.url;
    },

    get currentIsDemo(): boolean {
      return (
        currentNotification(getContext<SocialProofContext>())?.kind === 'demo'
      );
    },
  },

  actions: {
    dismiss(): void {
      const ctx = getContext<SocialProofContext>();
      ctx.isDismissed = true;
      ctx.isVisible = false;
      try {
        sessionStorage.setItem(DISMISSED_KEY, '1');
      } catch {
        // Ignore storage errors (private mode, full quota, etc.).
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
      const n = currentNotification(ctx);
      const src = n?.thumbnail ?? '';
      const img = ref as HTMLImageElement;
      // Avoid setting src="" which fires a network request to the page URL.
      if (src) {
        img.src = src;
      } else {
        img.removeAttribute('src');
      }
    },

    syncDecorHtml(): void {
      const { ref } = getElement();
      if (!ref) {
        return;
      }
      const { state: st } = store(SOCIAL_PROOF_STORE);
      ref.innerHTML = st.currentDecorHtml;
    },

    syncBadgeHtml(): void {
      const { ref } = getElement();
      if (!ref) {
        return;
      }
      const { state: st } = store(SOCIAL_PROOF_STORE);
      ref.innerHTML = st.currentBadgeHtml;
    },

    startCycle(): void {
      const ctx = getContext<SocialProofContext>();

      try {
        if (sessionStorage.getItem(DISMISSED_KEY) === '1') {
          ctx.isDismissed = true;
          return;
        }
      } catch {
        // Ignore storage errors.
      }

      if (!ctx.notifications || ctx.notifications.length === 0) {
        return;
      }

      ctx.notifications.forEach((n: SocialProofNotification) => {
        if (n.thumbnail) {
          const img = new Image();
          img.src = n.thumbnail;
        }

        const decor = n.decor_html?.trim();
        if (decor) {
          const match = decor.match(/src="([^"]+)"/u);
          if (match?.[1]) {
            const img = new Image();
            img.src = match[1];
          }
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
            // User is hovering — wait for mouseleave to resume.
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

      setTimeout(showNext, 5000);

      setInterval(showNext, ctx.intervalMs || 20000);
    },
  },
});
