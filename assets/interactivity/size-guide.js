/**
 * Size Guide — Interactivity API Store.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext } from '@wordpress/interactivity';

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
      ctx.isOpen = !ctx.isOpen;
      document.body.style.overflow = ctx.isOpen ? 'hidden' : '';

      if (ctx.isOpen) {
        triggerElement = document.activeElement;
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
        if (focusTrapCleanup) {
          focusTrapCleanup();
          focusTrapCleanup = null;
        }
        if (triggerElement) {
          triggerElement.focus();
          triggerElement = null;
        }
      }
    },

    close() {
      const ctx = getContext();
      ctx.isOpen = false;
      document.body.style.overflow = '';

      if (focusTrapCleanup) {
        focusTrapCleanup();
        focusTrapCleanup = null;
      }
      if (triggerElement) {
        triggerElement.focus();
        triggerElement = null;
      }
    },
  },
});
