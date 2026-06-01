/**
 * Sticky Add to Cart — Interactivity API Store
 *
 * Shows a fixed bar with product info and add-to-cart when the main
 * add-to-cart form scrolls out of the viewport.
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
import { matchVariation } from '@aggressive-apparel/helpers';
import type { Variation } from '@aggressive-apparel/helpers';

interface StickyCartState {
  isVisible: boolean;
  isAdding: boolean;
  isBuyingNow: boolean;
  isSuccess: boolean;
  hasError: boolean;
  isDrawerOpen: boolean;
  productType: string;
  productId: number;
  matchedVariationId: number;
  quantity: number;
  selectedAttrs: Record<string, string>;
  variations: Variation[];
  displayPrice: string;
  regularPrice: string;
  originalPrice: string;
  originalRegularPrice: string;
  drawerImage: string;
  drawerImageAlt: string;
  originalDrawerImage: string;
  originalDrawerImageAlt: string;
  drawerView: string;
  cartApiUrl: string;
  checkoutUrl: string;
  nonce: string;
  announcement: string;
  _syncing: boolean;
  readonly ariaHidden: string;
  readonly isVariable: boolean;
  readonly isAddDisabled: boolean;
  readonly buttonText: string;
  readonly isDrawerAddDisabled: boolean;
  readonly isOnSale: boolean;
  readonly drawerButtonText: string;
  readonly buyNowLabel: string;
  readonly hideDrawerSelection: boolean;
  readonly hideDrawerSuccess: boolean;
}

interface CartAddBody {
  id: number;
  quantity: number;
  variation?: Array<{ attribute: string; value: string }>;
}

/* ---------------------------------------------------------------
 * Main form selectors
 * ------------------------------------------------------------- */

const FORM_SELECTORS: string = [
  '.wc-block-add-to-cart-form',
  '.wp-block-woocommerce-add-to-cart-with-options',
  '.variations_form',
].join(', ');

const ATTR_SELECTORS: string = [
  'select[data-attribute_name]',
  'select[name^="attribute_"]',
  'input[name^="attribute_"]:checked',
].join(', ');

// Focus trap cleanup reference.
let focusTrapCleanup: (() => void) | null = null;

/**
 * Read all current attribute selections from a form element.
 */
function readFormAttributes(form: Element): Record<string, string> {
  const attrs: Record<string, string> = {};
  form
    .querySelectorAll<HTMLSelectElement | HTMLInputElement>(ATTR_SELECTORS)
    .forEach(el => {
      const name = el.getAttribute('data-attribute_name') || el.name || '';
      const value = el.value;
      if (name && value) {
        attrs[name] = value;
      }
    });
  return attrs;
}

/**
 * Sync drawer pill button visual states with current selectedAttrs.
 */
function syncDrawerOptions(): void {
  document
    .querySelectorAll<HTMLButtonElement>('.aa-sticky-cart__drawer-option')
    .forEach(btn => {
      const attrName = btn.dataset.attribute;
      const attrValue = btn.dataset.value;
      const isSelected =
        attrName !== undefined &&
        attrValue !== undefined &&
        state.selectedAttrs[attrName] === attrValue;
      btn.classList.toggle('is-selected', isSelected);

      // Set --swatch-color for the color swatch selection ring.
      if (
        btn.classList.contains('is-color-swatch') &&
        btn.style.backgroundColor
      ) {
        btn.style.setProperty('--swatch-color', btn.style.backgroundColor);
      }
    });
}

/* ---------------------------------------------------------------
 * Interactivity store
 * ------------------------------------------------------------- */

interface StickyCartStore {
  state: StickyCartState;
  actions: InteractivityActions;
  callbacks: InteractivityCallbacks;
}

const { state, actions } = store<StickyCartStore>(
  'aggressive-apparel/sticky-add-to-cart',
  {
    state: {
      get ariaHidden(): string {
        return state.isVisible ? 'false' : 'true';
      },
      get isVariable(): boolean {
        return state.productType === 'variable';
      },
      get isAddDisabled(): boolean {
        if (state.isAdding || state.isBuyingNow) return true;
        // Variable products without matched variation can still be clicked
        // to open the drawer — not disabled.
        return false;
      },
      get buttonText(): string {
        if (state.productType === 'variable') return 'Select options';
        if (state.isAdding) return '…';
        if (state.isSuccess) return '✓';
        if (state.hasError) return 'Error';
        return 'Add to Cart';
      },
      get isDrawerAddDisabled(): boolean {
        if (state.isAdding || state.isBuyingNow) return true;
        if (state.productType === 'variable' && !state.matchedVariationId)
          return true;
        return false;
      },
      get isOnSale(): boolean {
        return (
          !!state.regularPrice && state.regularPrice !== state.displayPrice
        );
      },
      get drawerButtonText(): string {
        if (state.isAdding) return '…';
        if (state.isSuccess) return '✓ Added';
        if (state.hasError) return 'Error';
        if (state.productType === 'variable' && !state.matchedVariationId) {
          return 'Select options';
        }
        return 'Add to Cart';
      },
      get buyNowLabel(): string {
        if (state.isBuyingNow) return 'Redirecting…';
        return 'Buy Now';
      },
      get hideDrawerSelection(): boolean {
        return state.drawerView !== 'selection';
      },
      get hideDrawerSuccess(): boolean {
        return state.drawerView !== 'success';
      },
    },

    actions: {
      addToCart(): void {
        if (state.isAdding || state.isBuyingNow) return;

        // Variable products: open the drawer first so the user can pick options.
        // Once the drawer is open the button inside it calls this same action,
        // so we only bail when the drawer still needs to be opened.
        if (state.productType === 'variable' && !state.isDrawerOpen) {
          actions.openDrawer();
          return;
        }

        state.isAdding = true;
        state.isSuccess = false;
        state.hasError = false;

        const itemId: number =
          state.productType === 'variable'
            ? state.matchedVariationId
            : state.productId;

        if (!itemId) {
          state.isAdding = false;
          return;
        }

        const wasDrawerOpen = state.isDrawerOpen;

        // Build the request body.
        const body: CartAddBody = { id: itemId, quantity: state.quantity };

        // For variable products, include variation attributes so the
        // Store API can validate the selection.
        if (state.productType === 'variable' && state.matchedVariationId) {
          const matchedVar = state.variations.find(
            (v: Variation) => v.id === state.matchedVariationId
          );
          if (matchedVar && matchedVar.attributes) {
            body.variation = Object.entries(
              matchedVar.attributes as Record<string, string>
            )
              .filter(([, val]: [string, string]) => val)
              .map(([key, val]: [string, string]) => ({
                attribute: key.replace(/^attribute_/, ''),
                value: val,
              }));
          }
        }

        fetch(state.cartApiUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Nonce: state.nonce,
          },
          body: JSON.stringify(body),
        })
          .then((res: Response) => {
            // Capture refreshed nonce for subsequent requests.
            const newNonce = res.headers.get('Nonce');
            if (newNonce) {
              state.nonce = newNonce;
            }

            if (!res.ok) {
              return res.json().then((err: { message?: string }) => {
                throw new Error(err.message || `HTTP ${res.status}`);
              });
            }
            return res.json();
          })
          .then(() => {
            state.isAdding = false;
            state.isSuccess = true;
            state.announcement = 'Added to cart';
            state.quantity = 1;

            // Dispatch on document.body with bubbles so WooCommerce
            // mini-cart block picks it up and refreshes.
            document.body.dispatchEvent(
              new CustomEvent('wc-blocks_added_to_cart', {
                bubbles: true,
              })
            );

            // Switch drawer to success view.
            if (wasDrawerOpen) {
              setTimeout(() => {
                state.isSuccess = false;
                state.drawerView = 'success';
              }, 600);
            } else {
              setTimeout(() => {
                state.isSuccess = false;
              }, 2000);
            }
          })
          .catch(() => {
            state.isAdding = false;
            state.hasError = true;
            state.announcement = 'Error adding to cart';

            setTimeout(() => {
              state.hasError = false;
              state.announcement = '';
            }, 3000);
          });
      },

      /**
       * Add item to cart and redirect to checkout.
       */
      buyNow(): void {
        if (state.isBuyingNow || state.isAdding) return;

        // Variable products: open drawer first if not already open.
        if (state.productType === 'variable' && !state.isDrawerOpen) {
          // Set flag so that when the drawer's Buy Now is clicked,
          // we know to redirect after adding.
          actions.openDrawer();
          return;
        }

        const itemId: number =
          state.productType === 'variable'
            ? state.matchedVariationId
            : state.productId;

        if (!itemId) return;

        state.isBuyingNow = true;
        state.hasError = false;

        const body: CartAddBody = { id: itemId, quantity: state.quantity };

        if (state.productType === 'variable' && state.matchedVariationId) {
          const matchedVar = state.variations.find(
            (v: Variation) => v.id === state.matchedVariationId
          );
          if (matchedVar && matchedVar.attributes) {
            body.variation = Object.entries(
              matchedVar.attributes as Record<string, string>
            )
              .filter(([, val]: [string, string]) => val)
              .map(([key, val]: [string, string]) => ({
                attribute: key.replace(/^attribute_/, ''),
                value: val,
              }));
          }
        }

        fetch(state.cartApiUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Nonce: state.nonce,
          },
          body: JSON.stringify(body),
        })
          .then((res: Response) => {
            const newNonce = res.headers.get('Nonce');
            if (newNonce) {
              state.nonce = newNonce;
            }
            if (!res.ok) {
              return res.json().then((err: { message?: string }) => {
                throw new Error(err.message || `HTTP ${res.status}`);
              });
            }
            return res.json();
          })
          .then(() => {
            window.location.href = state.checkoutUrl;
          })
          .catch(() => {
            state.isBuyingNow = false;
            state.hasError = true;
            state.announcement = 'Error adding to cart';

            setTimeout(() => {
              state.hasError = false;
              state.announcement = '';
            }, 3000);
          });
      },

      /* -----------------------------------------------------------
       * Quantity controls
       * --------------------------------------------------------- */

      incrementQty(event?: Event): void {
        if (event) event.preventDefault();
        state.quantity = state.quantity + 1;
      },

      decrementQty(event?: Event): void {
        if (event) event.preventDefault();
        if (state.quantity > 1) {
          state.quantity = state.quantity - 1;
        }
      },

      setQuantity(event: Event): void {
        const target = event.target as HTMLInputElement;
        const value = parseInt(target.value, 10);
        if (!isNaN(value) && value >= 1) {
          state.quantity = value;
        } else {
          target.value = String(state.quantity);
        }
      },

      /* -----------------------------------------------------------
       * Variant selection + sync
       * --------------------------------------------------------- */

      matchVariation(): void {
        const selected = state.selectedAttrs;
        const activeKeys = Object.keys(selected).filter(
          (k: string) => selected[k]
        );

        if (activeKeys.length === 0) {
          state.matchedVariationId = 0;
          state.drawerImage = state.originalDrawerImage;
          state.drawerImageAlt = state.originalDrawerImageAlt;
          return;
        }

        const match = matchVariation(state.variations, selected);

        if (match) {
          state.matchedVariationId = match.id;
          state.displayPrice = (match.price as string) || '';
          state.regularPrice = (match.regularPrice as string) || '';

          // Swap drawer image if the variation has its own image.
          if (match.image) {
            state.drawerImage = match.image as string;
            state.drawerImageAlt =
              (match.imageAlt as string) || state.originalDrawerImageAlt;
          }
        } else {
          state.matchedVariationId = 0;
          state.displayPrice = state.originalPrice;
          state.regularPrice = state.originalRegularPrice || '';

          // Restore original product image.
          state.drawerImage = state.originalDrawerImage;
          state.drawerImageAlt = state.originalDrawerImageAlt;
        }
      },

      /**
       * Push a single attribute change from sticky bar to main form.
       */
      syncToMainForm(attrName: string, attrValue: string): void {
        if (state._syncing) return;
        state._syncing = true;

        const form = document.querySelector<HTMLElement>(FORM_SELECTORS);
        if (form) {
          // Try select dropdown first (classic WC + block dropdown).
          const sel = form.querySelector<HTMLSelectElement>(
            `select[data-attribute_name="${attrName}"], select[name="${attrName}"]`
          );
          if (sel) {
            sel.value = attrValue;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
          } else {
            // Try radio pill input (block-based form).
            const radio = form.querySelector<HTMLInputElement>(
              `input[name="${attrName}"][value="${attrValue}"]`
            );
            if (radio) {
              radio.checked = true;
              radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
          }
        }

        state._syncing = false;
      },

      /* -----------------------------------------------------------
       * Drawer (variant bottom sheet)
       * --------------------------------------------------------- */

      openDrawer(): void {
        if (state.isDrawerOpen) return;

        const drawer = document.querySelector<HTMLElement>(
          '.aa-sticky-cart__drawer'
        );
        if (!drawer) return;

        prepareOverlayOpen(drawer);
        state.isDrawerOpen = true;

        syncDrawerOptions();

        const panel = drawer.querySelector<HTMLElement>(
          '.aa-sticky-cart__drawer-panel'
        );

        if (panel) {
          focusTrapCleanup = activateOverlayFocus({
            shell: drawer,
            panel,
            focusSelector: '.aa-sticky-cart__drawer-close',
          });
        }
      },

      continueShopping(): void {
        state.drawerView = 'selection';
        actions.closeDrawer();
      },

      closeDrawer(): void {
        const drawer = document.querySelector<HTMLElement>(
          '.aa-sticky-cart__drawer'
        );
        if (!drawer) return;

        state.isDrawerOpen = false;
        state.drawerView = 'selection';

        const selected = state.selectedAttrs;
        for (const attrName of Object.keys(selected)) {
          actions.syncToMainForm(attrName, selected[attrName]);
        }

        const panel = drawer.querySelector<HTMLElement>(
          '.aa-sticky-cart__drawer-panel'
        );

        if (!panel) {
          focusTrapCleanup?.();
          focusTrapCleanup = null;
          return;
        }

        closeOverlay({
          shell: drawer,
          panel,
          focusTrapCleanup,
          manageOpenClass: true,
          transitionProperty: 'transform',
          isStillOpen: () => state.isDrawerOpen,
        });

        focusTrapCleanup = null;
      },

      selectDrawerOption(event: MouseEvent): void {
        event.stopPropagation();
        const button = (event.target as HTMLElement).closest<HTMLButtonElement>(
          '.aa-sticky-cart__drawer-option'
        );
        if (!button) return;

        const attrName = button.dataset.attribute;
        const clickedValue = button.dataset.value;

        if (!attrName || !clickedValue) return;

        // Toggle: clicking the same option deselects it.
        const newAttrs: Record<string, string> = { ...state.selectedAttrs };
        if (newAttrs[attrName] === clickedValue) {
          delete newAttrs[attrName];
        } else {
          newAttrs[attrName] = clickedValue;
        }
        state.selectedAttrs = newAttrs;

        // Update pill button visuals.
        syncDrawerOptions();

        // Match variation.
        actions.matchVariation();

        // Do NOT sync to main form here — dispatching change events
        // on the WooCommerce form triggers its own variation handler
        // which can cause page navigation. Sync happens on close.
      },
    },

    callbacks: {
      init(): void {
        // Observe the main add-to-cart form.
        const form = document.querySelector<HTMLElement>(FORM_SELECTORS);

        if (!form) return;

        const observer = new IntersectionObserver(
          ([entry]: IntersectionObserverEntry[]) => {
            state.isVisible = !entry.isIntersecting;
          },
          { threshold: 0 }
        );

        observer.observe(form);

        // Match default variation if set.
        if (
          state.productType === 'variable' &&
          Object.keys(state.selectedAttrs).length > 0
        ) {
          actions.matchVariation();
        }

        /* ---------------------------------------------------------
         * Variant sync: main form → sticky bar
         * ------------------------------------------------------- */

        if (state.productType === 'variable') {
          form.addEventListener('change', (e: Event) => {
            if (state._syncing) return;

            const el = e.target as HTMLSelectElement | HTMLInputElement;
            const name =
              el.name || el.getAttribute('data-attribute_name') || '';
            if (!name.startsWith('attribute_')) return;

            state._syncing = true;
            state.selectedAttrs = readFormAttributes(form);
            actions.matchVariation();

            // Sync drawer pills if open.
            if (state.isDrawerOpen) {
              syncDrawerOptions();
            }

            state._syncing = false;
          });
        }

        /* ---------------------------------------------------------
         * Escape key closes drawer
         * ------------------------------------------------------- */

        document.addEventListener('keydown', (e: KeyboardEvent) => {
          if (e.key === 'Escape' && state.isDrawerOpen) {
            actions.closeDrawer();
          }
        });
      },
    },
  }
);
