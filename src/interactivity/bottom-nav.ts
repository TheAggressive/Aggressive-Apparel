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
import {
  prepareOverlayOpen,
  activateOverlayFocus,
  closeOverlay,
} from '@aggressive-apparel/use-overlay';

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
}

interface CartApiResponse {
  items_count?: number;
}

let focusTrapCleanup: (() => void) | null = null;
let searchTrigger: HTMLElement | null = null;

function getSearchOverlay(): HTMLElement | null {
  return document.querySelector<HTMLElement>('.aa-bottom-nav__search-overlay');
}

function openSearchOverlay(): void {
  const overlay = getSearchOverlay();
  if (!overlay) return;

  searchTrigger = document.activeElement as HTMLElement | null;

  prepareOverlayOpen(overlay, { manageOpenClass: false });
  state.isSearchOpen = true;

  const panel = overlay.querySelector<HTMLElement>(
    '.aa-bottom-nav__search-panel'
  );

  if (panel) {
    focusTrapCleanup = activateOverlayFocus({
      shell: overlay,
      panel,
      focusSelector: '.aa-bottom-nav__search-input',
    });
  }
}

function closeSearchOverlay(): void {
  state.isSearchOpen = false;

  const overlay = getSearchOverlay();
  const panel = overlay?.querySelector<HTMLElement>(
    '.aa-bottom-nav__search-panel'
  );

  if (!overlay || !panel) {
    focusTrapCleanup?.();
    focusTrapCleanup = null;
    searchTrigger = null;
    return;
  }

  closeOverlay({
    shell: overlay,
    panel,
    focusTrapCleanup,
    triggerElement: searchTrigger,
    manageOpenClass: false,
    isStillOpen: () => state.isSearchOpen,
    onFinish: () => {
      searchTrigger = null;
    },
  });

  focusTrapCleanup = null;
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
    },

    actions: {
      toggleSearch(): void {
        if (state.isSearchOpen) {
          closeSearchOverlay();
        } else {
          openSearchOverlay();
        }
      },

      closeSearch(): void {
        if (!state.isSearchOpen) return;
        closeSearchOverlay();
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
        // One-shot: header mini-cart replaces the bottom-nav cart link on
        // mobile (mini-cart.css). A class beats body:has(block) — see above.
        if (document.querySelector('.wp-block-woocommerce-mini-cart')) {
          document.body.classList.add('aa-has-mini-cart');
        }

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
              // Mirrored as a body class so dependent CSS (sticky cart
              // offset) avoids body:has() — a body-level :has() forces a
              // document-wide style re-scan on every childList mutation.
              document.body.classList.toggle(
                'aa-bottom-nav-hidden',
                state.isHiddenByScroll
              );

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
