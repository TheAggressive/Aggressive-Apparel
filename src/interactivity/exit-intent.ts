/**
 * Exit Intent Email Capture — Interactivity API Store
 *
 * Detects exit intent (mouse leaving viewport on desktop, rapid scroll-up
 * on mobile) and shows a modal email capture popup. Follows the established
 * modal pattern with backdrop blur, scroll lock, and focus trap.
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

import { store } from '@wordpress/interactivity';
import type { InteractivityCallbacks } from '../../types/interactivity-shared';
import { lockScroll, unlockScroll } from '@aggressive-apparel/scroll-lock';
import { setupFocusTrap } from '@aggressive-apparel/helpers';

interface ExitIntentState {
  isOpen: boolean;
  isSubmitting: boolean;
  isSuccess: boolean;
  hasError: boolean;
  errorMessage: string;
  successMessage: string;
  announcement: string;
  nonce: string;
  ajaxUrl: string;
  reshowDays: number;
  readonly hasNoError: boolean;
  readonly isNotSuccess: boolean;
}

interface AjaxResponse {
  success: boolean;
  data: {
    message?: string;
  };
}

const DISMISS_KEY = 'aa_exit_intent_dismissed';
const SESSION_KEY = 'aa_exit_intent_shown';
const TRANSITION_DURATION: number = 300;

let focusTrapCleanup: (() => void) | null = null;

/** Gate: only trigger once per page load. */
let hasTriggered = false;

/** Track the element that had focus before the modal opened. */
let previousFocus: HTMLElement | null = null;

/**
 * Check if popup was dismissed within the reshow window.
 */
function isDismissed(): boolean {
  try {
    const ts = localStorage.getItem(DISMISS_KEY);
    if (!ts) return false;
    const days = state.reshowDays || 7;
    return Date.now() - parseInt(ts, 10) < days * 86400000;
  } catch {
    return false;
  }
}

/**
 * Mark the popup as dismissed with current timestamp.
 */
function markDismissed(): void {
  try {
    localStorage.setItem(DISMISS_KEY, String(Date.now()));
  } catch {
    // Private browsing or quota exceeded.
  }
}

/**
 * Check if already shown this session.
 */
function wasShownThisSession(): boolean {
  try {
    return sessionStorage.getItem(SESSION_KEY) === '1';
  } catch {
    return false;
  }
}

/**
 * Mark as shown this session.
 */
function markShownThisSession(): void {
  try {
    sessionStorage.setItem(SESSION_KEY, '1');
  } catch {
    // Ignore.
  }
}

interface ExitIntentStore {
  state: ExitIntentState;
  actions: {
    open: () => void;
    close: () => void;
    submit: (event: Event) => Promise<void>;
    clearError: () => void;
    handleKeydown: (event: KeyboardEvent) => void;
  };
  callbacks: InteractivityCallbacks;
}

const { state } = store<ExitIntentStore>('aggressive-apparel/exit-intent', {
  state: {
    get hasNoError(): boolean {
      return !state.hasError;
    },

    get isNotSuccess(): boolean {
      return !state.isSuccess;
    },
  },

  actions: {
    open(): void {
      if (hasTriggered || isDismissed() || wasShownThisSession()) return;
      hasTriggered = true;
      markShownThisSession();

      previousFocus = document.activeElement as HTMLElement | null;

      const overlay = document.getElementById('aa-exit-intent');
      if (overlay) {
        overlay.hidden = false;
        void overlay.offsetHeight; // Force reflow for CSS transition.
      }

      lockScroll();
      state.isOpen = true;

      requestAnimationFrame(() => {
        const modal = overlay?.querySelector<HTMLElement>(
          '.aa-exit-intent__modal'
        );
        if (modal) {
          focusTrapCleanup = setupFocusTrap(modal);
          const input = modal.querySelector<HTMLInputElement>(
            '.aa-exit-intent__input'
          );
          if (input) input.focus();
        }
      });
    },

    close(): void {
      state.isOpen = false;
      markDismissed();

      if (focusTrapCleanup) {
        focusTrapCleanup();
        focusTrapCleanup = null;
      }

      const overlay = document.getElementById('aa-exit-intent');
      const modal = overlay?.querySelector<HTMLElement>(
        '.aa-exit-intent__modal'
      );

      let done = false;
      const finish = (): void => {
        if (done || state.isOpen) return;
        done = true;
        unlockScroll();
        if (overlay) overlay.hidden = true;
        if (previousFocus && typeof previousFocus.focus === 'function') {
          previousFocus.focus();
        }
      };

      if (modal) {
        modal.addEventListener(
          'transitionend',
          (e: Event) => {
            if ((e as TransitionEvent).propertyName === 'opacity') finish();
          },
          { once: true }
        );
        // Safety fallback for reduced motion or edge cases.
        setTimeout(finish, TRANSITION_DURATION + 50);
      } else {
        finish();
      }
    },

    async submit(event: Event): Promise<void> {
      event.preventDefault();
      const form = event.target as HTMLFormElement;
      const emailInput = form.querySelector<HTMLInputElement>(
        'input[type="email"]'
      );
      const email = emailInput?.value?.trim();

      if (!email) {
        state.hasError = true;
        state.errorMessage = 'Please enter your email address.';
        return;
      }

      state.isSubmitting = true;
      state.hasError = false;

      try {
        const fd = new FormData();
        fd.append('action', 'aa_exit_intent_subscribe');
        fd.append('nonce', state.nonce);
        fd.append('email', email);

        const res = await fetch(state.ajaxUrl, { method: 'POST', body: fd });
        const data: AjaxResponse = await res.json();

        if (data.success) {
          state.isSuccess = true;
          state.successMessage = data.data.message || '';
          state.announcement = data.data.message || '';
        } else {
          state.hasError = true;
          state.errorMessage =
            data.data?.message || 'An error occurred. Please try again.';
        }
      } catch {
        state.hasError = true;
        state.errorMessage = 'Network error. Please try again.';
      } finally {
        state.isSubmitting = false;
      }
    },

    clearError(): void {
      state.hasError = false;
      state.errorMessage = '';
    },

    handleKeydown(event: KeyboardEvent): void {
      if (event.key === 'Escape' && state.isOpen) {
        store<ExitIntentStore>(
          'aggressive-apparel/exit-intent'
        ).actions.close();
      }
    },
  },

  callbacks: {
    init(): void {
      if (isDismissed() || wasShownThisSession()) return;

      const {
        actions: { open },
      } = store<ExitIntentStore>('aggressive-apparel/exit-intent');

      // Arm after 5-second delay to prevent false triggers on page load.
      hasTriggered = true;
      setTimeout(() => {
        hasTriggered = false;
      }, 5000);

      // Desktop: mouse leaving viewport top.
      document.addEventListener('mouseout', (e: MouseEvent) => {
        if (e.clientY <= 0 && !hasTriggered) {
          open();
        }
      });

      // Mobile: rapid upward scroll detection.
      let lastScrollY: number = window.scrollY;
      let scrollUpDistance = 0;
      const SCROLL_THRESHOLD = 300;

      window.addEventListener(
        'scroll',
        () => {
          const currentY = window.scrollY;
          if (currentY < lastScrollY) {
            scrollUpDistance += lastScrollY - currentY;
            if (scrollUpDistance > SCROLL_THRESHOLD && !hasTriggered) {
              open();
            }
          } else {
            scrollUpDistance = 0;
          }
          lastScrollY = currentY;
        },
        { passive: true }
      );
    },
  },
});
