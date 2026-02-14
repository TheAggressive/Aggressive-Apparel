/**
 * Size Guide — Interactivity API Store.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext } from '@wordpress/interactivity';
import { lockScroll, unlockScroll } from '@aggressive-apparel/scroll-lock';

/** @type {number} Matches the 0.3s CSS transition duration. */
const TRANSITION_DURATION = 300;

let triggerElement = null;
let focusTrapCleanup = null;

/**
 * Setup focus trap within a container element.
 *
 * @param {HTMLElement} container - The container to trap focus within.
 * @return {Function} Cleanup function to remove the trap.
 */
function setupFocusTrap(container) {
  const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]), ' +
    'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  const handler = e => {
    if (e.key !== 'Tab') {
      return;
    }
    const focusable = Array.from(container.querySelectorAll(FOCUSABLE)).filter(
      el => !el.closest('[hidden]')
    );
    if (focusable.length === 0) {
      e.preventDefault();
      return;
    }
    const idx = focusable.indexOf(document.activeElement);
    let next;
    if (e.shiftKey) {
      next = idx <= 0 ? focusable.length - 1 : idx - 1;
    } else {
      next = idx >= focusable.length - 1 ? 0 : idx + 1;
    }
    e.preventDefault();
    focusable[next].focus();
  };

  container.addEventListener('keydown', handler);
  return () => container.removeEventListener('keydown', handler);
}

store('aggressive-apparel/size-guide', {
  actions: {
    toggle() {
      const ctx = getContext();

      if (!ctx.isOpen) {
        // --- Opening ---
        triggerElement = document.activeElement;
        lockScroll();

        // Remove hidden + force reflow so the browser renders the "before" state.
        const overlay = document.querySelector(
          '.aggressive-apparel-size-guide__overlay'
        );
        if (overlay) {
          overlay.hidden = false;
          void overlay.offsetHeight;
        }

        ctx.isOpen = true;

        requestAnimationFrame(() => {
          const modal = document.querySelector(
            '.aggressive-apparel-size-guide__modal'
          );
          if (modal) {
            focusTrapCleanup = setupFocusTrap(modal);
            const closeBtn = modal.querySelector(
              '.aggressive-apparel-size-guide__close'
            );
            closeBtn?.focus();
          }
        });
      } else {
        // --- Closing (delegate to close action) ---
        const { actions } = store('aggressive-apparel/size-guide');
        actions.close();
      }
    },

    close() {
      const ctx = getContext();
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
      const overlay = document.querySelector(
        '.aggressive-apparel-size-guide__overlay'
      );
      const modal = overlay?.querySelector(
        '.aggressive-apparel-size-guide__modal'
      );

      let done = false;
      const finish = () => {
        if (done || ctx.isOpen) return;
        done = true;
        unlockScroll();
        if (overlay) overlay.hidden = true;
      };

      if (modal) {
        modal.addEventListener(
          'transitionend',
          e => {
            if (e.propertyName === 'opacity') finish();
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
