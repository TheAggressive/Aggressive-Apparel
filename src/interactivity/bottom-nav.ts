/**
 * Mobile Bottom Navigation — Interactivity API Store
 *
 * Scroll-aware bottom bar with cart count sync and search overlay.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

import type {
  InteractivityActions,
  InteractivityCallbacks,
} from '../../types/interactivity-shared';

import { store } from '@wordpress/interactivity';
import { lockScroll, unlockScroll } from '@aggressive-apparel/scroll-lock';

interface JQueryLike {
  on(events: string, handler: () => void): void;
}
declare const jQuery: (el: EventTarget) => JQueryLike;

interface BottomNavState {
  isSearchOpen: boolean;
  isHiddenByScroll: boolean;
  cartCount: number;
  cartApiUrl: string;
  readonly hasEmptyCart: boolean;
  readonly cartAriaLabel: string;
  readonly isSearchClosed: boolean;
}

interface CartApiResponse {
  items_count?: number;
}

/** Matches the 0.3s CSS transition duration. */
const TRANSITION_DURATION: number = 300;

/**
 * Defer unlockScroll until the search panel fade-out finishes.
 */
function deferUnlock(): void {
  const panel = document.querySelector('.aa-bottom-nav__search-panel');

  let done = false;
  const finish = (): void => {
    if (done || state.isSearchOpen) return;
    done = true;
    unlockScroll();
  };

  if (panel) {
    panel.addEventListener(
      'transitionend',
      (e: Event) => {
        if ((e as TransitionEvent).propertyName === 'opacity') finish();
      },
      { once: true }
    );
    setTimeout(finish, TRANSITION_DURATION + 50);
  } else {
    finish();
  }
}

interface BottomNavStore {
  state: BottomNavState;
  actions: InteractivityActions;
  callbacks: InteractivityCallbacks;
}

const { state, actions } = store<BottomNavStore>(
  'aggressive-apparel/bottom-nav',
  {
    state: {
      get hasEmptyCart(): boolean {
        return state.cartCount === 0;
      },
      get cartAriaLabel(): string {
        return state.cartCount > 0
          ? `Cart (${state.cartCount} ${state.cartCount === 1 ? 'item' : 'items'})`
          : 'Cart';
      },
      get isSearchClosed(): boolean {
        return !state.isSearchOpen;
      },
    },

    actions: {
      toggleSearch(): void {
        state.isSearchOpen = !state.isSearchOpen;

        if (state.isSearchOpen) {
          lockScroll();

          // Focus the search input after a brief delay for DOM update.
          requestAnimationFrame(() => {
            const input = document.querySelector<HTMLInputElement>(
              '.aa-bottom-nav__search-input'
            );
            if (input) input.focus();
          });
        } else {
          deferUnlock();
        }
      },

      closeSearch(): void {
        state.isSearchOpen = false;
        deferUnlock();
      },

      refreshCartCount(): void {
        fetch(state.cartApiUrl, {
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json' },
        })
          .then((res: Response) => res.json())
          .then((cart: CartApiResponse) => {
            state.cartCount = cart.items_count ?? 0;
          })
          .catch(() => {
            // Silently fail — count stays stale.
          });
      },
    },

    callbacks: {
      init(): void {
        // Scroll-aware show/hide.
        const prefersReducedMotion = window.matchMedia(
          '(prefers-reduced-motion: reduce)'
        ).matches;

        if (!prefersReducedMotion) {
          let lastScrollY: number = window.scrollY;
          let ticking = false;

          const onScroll = (): void => {
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
        document.addEventListener('keydown', (e: KeyboardEvent) => {
          if (e.key === 'Escape' && state.isSearchOpen) {
            actions.closeSearch();
          }
        });
      },
    },
  }
);
