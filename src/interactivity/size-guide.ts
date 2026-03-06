/**
 * Size Guide — Interactivity API Store.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext } from '@wordpress/interactivity';
import { lockScroll, unlockScroll } from '@aggressive-apparel/scroll-lock';
import { setupFocusTrap } from '@aggressive-apparel/helpers';

interface SizeGuideContext {
  isOpen: boolean;
}

interface SizeGuideStore {
  state: Record<string, never>;
  actions: Record<string, (...args: any[]) => any>;
}

/** Matches the 0.3s CSS transition duration. */
const TRANSITION_DURATION: number = 300;

let triggerElement: HTMLElement | null = null;
let focusTrapCleanup: (() => void) | null = null;

store<SizeGuideStore>('aggressive-apparel/size-guide', {
  actions: {
    toggle(): void {
      const ctx = getContext<SizeGuideContext>();

      if (!ctx.isOpen) {
        // --- Opening ---
        triggerElement = document.activeElement as HTMLElement | null;
        lockScroll();

        // Remove hidden + force reflow so the browser renders the "before" state.
        const overlay = document.querySelector<HTMLElement>(
          '.aggressive-apparel-size-guide__overlay'
        );
        if (overlay) {
          overlay.hidden = false;
          void overlay.offsetHeight;
        }

        ctx.isOpen = true;

        requestAnimationFrame(() => {
          const modal = document.querySelector<HTMLElement>(
            '.aggressive-apparel-size-guide__modal'
          );
          if (modal) {
            focusTrapCleanup = setupFocusTrap(modal);
            const closeBtn = modal.querySelector<HTMLElement>(
              '.aggressive-apparel-size-guide__close'
            );
            closeBtn?.focus();
          }
        });
      } else {
        // --- Closing (delegate to close action) ---
        const storeRef = store('aggressive-apparel/size-guide') as unknown as {
          actions: { close: () => void };
        };
        storeRef.actions.close();
      }
    },

    close(): void {
      const ctx = getContext<SizeGuideContext>();
      ctx.isOpen = false;

      if (focusTrapCleanup) {
        focusTrapCleanup();
        focusTrapCleanup = null;
      }
      if (triggerElement) {
        triggerElement.focus();
        triggerElement = null;
      }

      // Unlock scroll + hide the instant the fade-out transition ends.
      const overlay = document.querySelector<HTMLElement>(
        '.aggressive-apparel-size-guide__overlay'
      );
      const modal = overlay?.querySelector<HTMLElement>(
        '.aggressive-apparel-size-guide__modal'
      );

      let done = false;
      const finish = (): void => {
        if (done || ctx.isOpen) return;
        done = true;
        unlockScroll();
        if (overlay) overlay.hidden = true;
      };

      if (modal) {
        modal.addEventListener(
          'transitionend',
          (e: Event) => {
            if ((e as TransitionEvent).propertyName === 'opacity') finish();
          },
          { once: true }
        );

        // Safety fallback if transitionend never fires (reduced motion, etc.).
        setTimeout(finish, TRANSITION_DURATION + 50);
      } else {
        finish();
      }
    },
  },
});
