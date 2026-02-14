/**
 * Mobile Bottom Navigation — Interactivity API Store
 *
 * Scroll-aware bottom bar with cart count sync and search overlay.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

import { store } from '@wordpress/interactivity';
import { lockScroll, unlockScroll } from '@aggressive-apparel/scroll-lock';

/** @type {number} Matches the 0.3s CSS transition duration. */
const TRANSITION_DURATION = 300;

/**
 * Defer unlockScroll until the search panel fade-out finishes.
 */
function deferUnlock() {
  const panel = document.querySelector('.aa-bottom-nav__search-panel');

  let done = false;
  const finish = () => {
    if (done || state.isSearchOpen) return;
    done = true;
    unlockScroll();
  };

  if (panel) {
    panel.addEventListener(
      'transitionend',
      e => {
        if (e.propertyName === 'opacity') finish();
      },
      { once: true }
    );
    setTimeout(finish, TRANSITION_DURATION + 50);
  } else {
    finish();
  }
}

const { state, actions } = store('aggressive-apparel/bottom-nav', {
  state: {
    get hasEmptyCart() {
      return state.cartCount === 0;
    },
    get isSearchClosed() {
      return !state.isSearchOpen;
    },
  },

  actions: {
    toggleSearch() {
      state.isSearchOpen = !state.isSearchOpen;

      if (state.isSearchOpen) {
        lockScroll();

        // Focus the search input after a brief delay for DOM update.
        requestAnimationFrame(() => {
          const input = document.querySelector('.aa-bottom-nav__search-input');
          if (input) input.focus();
        });
      } else {
        deferUnlock();
      }
    },

    closeSearch() {
      state.isSearchOpen = false;
      deferUnlock();
    },

    refreshCartCount() {
      fetch(state.cartApiUrl, {
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
      })
        .then(res => res.json())
        .then(cart => {
          state.cartCount = cart.items_count ?? 0;
        })
        .catch(() => {
          // Silently fail — count stays stale.
        });
    },
  },

  callbacks: {
    init() {
      // Scroll-aware show/hide.
      const prefersReducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
      ).matches;

      if (!prefersReducedMotion) {
        let lastScrollY = window.scrollY;
        let ticking = false;

        const onScroll = () => {
          if (ticking) return;
          ticking = true;
          requestAnimationFrame(() => {
            const currentY = window.scrollY;
            const delta = currentY - lastScrollY;

            if (delta > 50 && currentY > 100) {
              state.isHiddenByScroll = true;
            } else if (delta < -10) {
              state.isHiddenByScroll = false;
            }

            lastScrollY = currentY;
            ticking = false;
          });
        };

        window.addEventListener('scroll', onScroll, { passive: true });
      }

      // Cart count sync: listen for WooCommerce events.
      document.addEventListener('wc-blocks_added_to_cart', () => {
        actions.refreshCartCount();
      });

      // jQuery event from classic WooCommerce.
      if (typeof jQuery !== 'undefined') {
        jQuery(document.body).on('added_to_cart removed_from_cart', () => {
          actions.refreshCartCount();
        });
      }

      // Escape key closes search.
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && state.isSearchOpen) {
          actions.closeSearch();
        }
      });
    },
  },
});
