/**
 * Quick View — actions + shared runtime state.
 *
 * Registered via a second store() call on the same namespace (the Interactivity
 * runtime merges by namespace), so the getters can stay in the entry's single
 * state literal while the action bodies live here — keeping both files under the
 * length cap. Module-scoped mutable refs (focus traps, fetch controller, image
 * cache, touch tracking) are used only by these actions, so they live here too.
 *
 * @package Aggressive_Apparel
 */

import { store, getContext } from '@wordpress/interactivity';
import {
  prepareOverlayOpen,
  activateOverlayFocus,
  closeOverlay,
} from '@aggressive-apparel/use-overlay';
import {
  parsePrice,
  matchVariation,
  decodeEntities,
} from '@aggressive-apparel/helpers';
import type { PriceResult, StoreApiPrices } from '@aggressive-apparel/helpers';
import { calculateSalePercentage } from './product-data';
import {
  applyProductResponse,
  applyVariationImage,
  computeAvailableOptions,
  fadeImage,
  populateCartSuccessDOM,
  populateModalDOM,
  syncPriceDOM,
} from './view';
import type {
  CartAddBody,
  GalleryImage,
  QuickViewContext,
  QuickViewOption,
  QuickViewStore,
  ResolvedVariation,
  StoreApiProduct,
} from './types';
import { state, actions, getLabel } from '../quick-view';

// Store reference for focus trap cleanup.
let focusTrapCleanup: (() => void) | null = null;
let successFocusTrapCleanup: (() => void) | null = null;
let triggerElement: HTMLElement | null = null;

// AbortController for the main product fetch — cancels stale requests
// when the user opens a different product before the previous one loads.
let fetchController: AbortController | null = null;

// Cache fetched variation image data so repeated selections don't refetch.
const variationImageCache = new Map<
  number,
  { src: string; alt: string } | null
>();

// Cached media queries — avoids re-creating MediaQueryList on every call.
const prefersReducedMotion: MediaQueryList | { matches: false } =
  typeof window !== 'undefined'
    ? window.matchMedia('(prefers-reduced-motion: reduce)')
    : { matches: false };

/* Touch swipe tracking for mobile gallery. */
let touchStartX = 0;
let touchStartY = 0;
let isSwiping = false;

store<QuickViewStore>('aggressive-apparel/quick-view', {
  actions: {
    open(event?: MouseEvent): void {
      // Try Interactivity API context first; fall back to reading
      // the data-wp-context attribute directly so clicks still work
      // even when hydrateRegions is aborted by a third-party error.
      let productId: number | undefined;
      try {
        const ctx = getContext<QuickViewContext>();
        productId = ctx.productId;
      } catch {
        // Context unavailable — hydration may have failed.
      }

      if (!productId && event?.target) {
        const trigger = (event.target as HTMLElement).closest<HTMLElement>(
          '[data-wp-context]'
        );
        if (trigger) {
          try {
            const raw = JSON.parse(
              trigger.getAttribute('data-wp-context') || '{}'
            ) as { productId?: number };
            productId = raw.productId;
          } catch {
            // Invalid JSON, give up.
          }
        }
      }

      // Validate productId is a positive integer to prevent path traversal.
      productId = parseInt(String(productId), 10);
      if (!productId || productId <= 0 || !Number.isFinite(productId)) {
        return;
      }

      // Store the trigger element for focus restoration.
      triggerElement =
        (event?.target as HTMLElement)?.closest<HTMLElement>('button') || null;

      // Prepare overlay shell for open animation.
      const modalEl = document.getElementById('aggressive-apparel-quick-view');
      if (modalEl) {
        prepareOverlayOpen(modalEl);
      }

      // Reset all state.
      state.isOpen = true;
      state.isSuccessOpen = false;
      state.isLoading = true;
      state.hasError = false;
      state.hasProduct = false;
      state.productId = productId;
      state.productType = 'simple';
      state.productImage = '';
      state.productImageAlt = '';
      state.productName = '';
      state.productPrice = '';
      state.productRegularPrice = '';
      state.productOnSale = false;
      state.productDescription = '';
      state.productLink = '';
      state.productAttributes = [];
      state.productVariations = [];
      state.selectedAttributes = {};
      state.matchedVariationId = 0;
      state.availableOptions = {};
      state.quantity = 1;
      state.cartError = '';
      variationImageCache.clear();
      state.productImages = [];
      state._originalImages = [];
      state.activeImageIndex = 0;
      state.stockStatus = 'instock';
      state.stockQuantity = null;
      state.stockStatusLabel = '';
      state.salePercentage = 0;
      state.productPriceRange = '';
      state.isDrawerOpen = false;
      state.announcement = '';
      // Setup focus trap after modal renders.
      requestAnimationFrame(() => {
        const modal = document.querySelector<HTMLElement>(
          '.aggressive-apparel-quick-view__modal'
        );
        if (modal && modalEl) {
          focusTrapCleanup = activateOverlayFocus({
            shell: modalEl,
            panel: modal,
            focusSelector: '.aggressive-apparel-quick-view__close',
          });
        }
      });

      // Cancel any in-flight product fetch from a previous open.
      if (fetchController) fetchController.abort();
      fetchController = new AbortController();

      // Fetch product data.
      const base = state.restBase || '/wp-json/wc/store/v1/products/';
      const url = `${base}${productId}`;

      fetch(url, { signal: fetchController.signal })
        .then((res: Response) => {
          if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
          }
          return res.json();
        })
        .then(applyProductResponse)
        .catch((err: Error) => {
          // Aborted fetches are expected (user opened a different product).
          if (err?.name === 'AbortError') return;
          state.hasError = true;
        })
        .finally(() => {
          state.isLoading = false;
          // Fallback DOM sync for when hydration didn't run.
          populateModalDOM();
        });
    },

    close(): void {
      const trap = focusTrapCleanup;
      focusTrapCleanup = null;

      state.isOpen = false;
      state.hasProduct = false;
      state.hasError = false;
      state.isCartSuccess = false;
      state.cartError = '';
      state.isDrawerOpen = false;
      state.announcement = '';

      const modalEl = document.getElementById('aggressive-apparel-quick-view');
      const panel = modalEl?.querySelector<HTMLElement>(
        '.aggressive-apparel-quick-view__modal'
      );

      if (modalEl && panel) {
        closeOverlay({
          shell: modalEl,
          panel,
          focusTrapCleanup: trap,
          triggerElement,
          isStillOpen: () => state.isOpen,
          onFinish: () => {
            triggerElement = null;
          },
        });
      } else {
        triggerElement?.focus();
        triggerElement = null;
      }
    },

    /**
     * Replace quick view with a standalone add-to-cart confirmation.
     * Both overlays briefly hold the scroll lock during the handoff so
     * the page cannot jump between dialogs.
     */
    openCartSuccess(): void {
      const successShell = document.getElementById(
        'aggressive-apparel-cart-success'
      );
      const successPanel = successShell?.querySelector<HTMLElement>(
        '.aggressive-apparel-quick-view-success__panel'
      );

      if (!successShell || !successPanel) {
        actions.close();
        return;
      }

      const wasQuickViewOpen = state.isOpen;

      state.isSuccessOpen = true;
      prepareOverlayOpen(successShell);
      populateCartSuccessDOM();

      const quickViewTrap = focusTrapCleanup;
      focusTrapCleanup = null;
      const quickViewShell = document.getElementById(
        'aggressive-apparel-quick-view'
      );
      const quickViewPanel = quickViewShell?.querySelector<HTMLElement>(
        '.aggressive-apparel-quick-view__modal'
      );

      state.isOpen = false;
      state.isDrawerOpen = false;

      if (wasQuickViewOpen && quickViewShell && quickViewPanel) {
        closeOverlay({
          shell: quickViewShell,
          panel: quickViewPanel,
          focusTrapCleanup: quickViewTrap,
          isStillOpen: () => state.isOpen,
        });
      } else {
        quickViewTrap?.();
      }

      successFocusTrapCleanup = activateOverlayFocus({
        shell: successShell,
        panel: successPanel,
        focusSelector: '.aggressive-apparel-quick-view-success__close',
      });
    },

    /**
     * Close the standalone cart confirmation and return focus to the
     * product image that launched quick view.
     */
    closeCartSuccess(): void {
      if (!state.isSuccessOpen) return;

      const trap = successFocusTrapCleanup;
      successFocusTrapCleanup = null;
      state.isSuccessOpen = false;
      state.hasProduct = false;
      state.isCartSuccess = false;
      state.cartError = '';
      state.announcement = '';

      const successShell = document.getElementById(
        'aggressive-apparel-cart-success'
      );
      const successPanel = successShell?.querySelector<HTMLElement>(
        '.aggressive-apparel-quick-view-success__panel'
      );

      if (successShell && successPanel) {
        closeOverlay({
          shell: successShell,
          panel: successPanel,
          focusTrapCleanup: trap,
          triggerElement,
          isStillOpen: () => state.isSuccessOpen,
          onFinish: () => {
            triggerElement = null;
          },
        });
      } else {
        trap?.();
        triggerElement?.focus();
        triggerElement = null;
      }
    },

    selectAttribute(): void {
      const ctx = getContext<QuickViewContext>();
      const item = ctx.item as QuickViewOption;
      // `ctx.item` is set by data-wp-each for the option button.
      // Each option carries its parent `attrSlug`.
      const attrSlug = item.attrSlug;
      // Use varValue (the variation-compatible value) for matching.
      // Falls back to slug for attributes where they're identical.
      const optionValue = item.varValue || item.slug;

      if (!attrSlug || !optionValue) {
        return;
      }

      // Ignore activation of an unavailable option. The button is
      // aria-disabled (still focusable), not natively disabled, so a
      // keyboard/AT activation can still reach here — reject it explicitly.
      const availForAttr = state.availableOptions[attrSlug];
      if (
        availForAttr &&
        state.selectedAttributes[attrSlug] !== optionValue &&
        !availForAttr.includes(optionValue.toLowerCase())
      ) {
        return;
      }

      // Toggle: deselect if same option clicked again.
      const current = state.selectedAttributes[attrSlug];
      const newSelected: Record<string, string> = {
        ...state.selectedAttributes,
      };
      newSelected[attrSlug] = current === optionValue ? '' : optionValue;
      state.selectedAttributes = newSelected;

      // Re-dim options that the new selection makes impossible / sold out.
      state.availableOptions = computeAvailableOptions();

      // Deep-copy variations out of the Interactivity API proxy so
      // matchVariation sees plain objects (avoids potential proxy
      // iteration edge cases with nested arrays/objects).
      const plainVariations = state.productVariations.map(
        (v: ResolvedVariation) => ({
          id: v.id,
          attributes: (v.attributes || []).map(a => ({
            attribute: a.attribute,
            name: a.name,
            value: a.value,
            taxonomy: a.taxonomy,
          })),
          prices: v.prices
            ? {
                price: (v.prices as Record<string, unknown>).price,
                regular_price: (v.prices as Record<string, unknown>)
                  .regular_price,
                sale_price: (v.prices as Record<string, unknown>).sale_price,
                currency_minor_unit: (v.prices as Record<string, unknown>)
                  .currency_minor_unit,
                currency_prefix: (v.prices as Record<string, unknown>)
                  .currency_prefix,
                currency_suffix: (v.prices as Record<string, unknown>)
                  .currency_suffix,
              }
            : null,
          image: v.image,
          imageAlt: v.imageAlt,
        })
      );

      // Try to match a variation.
      const match = matchVariation(plainVariations, newSelected);

      if (match) {
        state.matchedVariationId = match.id;

        // Update price from the variation's own pricing data.
        if (match.prices) {
          const priceData: PriceResult = parsePrice(
            match.prices as StoreApiPrices
          );
          state.productPrice = priceData.current;
          state.productRegularPrice = priceData.regular;
          state.productOnSale = priceData.onSale;

          // Update sale badge.
          if (priceData.onSale) {
            const regular = parseInt(
              String((match.prices as Record<string, unknown>).regular_price),
              10
            );
            const sale = parseInt(
              String(
                (match.prices as Record<string, unknown>).sale_price ||
                  (match.prices as Record<string, unknown>).price
              ),
              10
            );
            state.salePercentage = calculateSalePercentage(regular, sale);
          } else {
            state.salePercentage = 0;
          }
        }

        // Force DOM sync — ensures the price updates visually even
        // if the Interactivity API's data-wp-text reactive binding
        // doesn't trigger (e.g. inside drawers or after populateModalDOM).
        syncPriceDOM();

        // Fetch the variation's own image from the Store API.
        const vid = match.id;
        if (variationImageCache.has(vid)) {
          applyVariationImage(variationImageCache.get(vid) || null);
        } else {
          const base = state.restBase || '/wp-json/wc/store/v1/products/';
          fetch(`${base}${vid}`)
            .then((res: Response) => (res.ok ? res.json() : null))
            .then((data: StoreApiProduct | null) => {
              if (!data) return;
              const rawSrc =
                data.images && data.images.length > 0 ? data.images[0].src : '';
              const img =
                rawSrc && /^https?:\/\//i.test(rawSrc)
                  ? {
                      src: rawSrc,
                      alt:
                        (data.images?.[0]?.alt as string) || state.productName,
                    }
                  : null;
              variationImageCache.set(vid, img);
              // Only apply if this variation is still the active match.
              if (state.matchedVariationId === vid) {
                applyVariationImage(img);
              }
            })
            .catch(() => {});
        }
      } else {
        state.matchedVariationId = 0;

        // Restore range price when no variation is matched.
        if (state.productPriceRange) {
          state.productPrice = state.productPriceRange;
          state.productRegularPrice = '';
          state.productOnSale = false;
          state.salePercentage = 0;
        }

        // Restore original gallery when no variation is matched.
        if (state._originalImages.length > 0) {
          state.productImages = state._originalImages.map(
            (img: GalleryImage) => ({
              ...img,
            })
          );
          state.activeImageIndex = 0;
        }

        syncPriceDOM();
      }
    },

    /**
     * Scroll the thumbnail strip left or right.
     */
    scrollThumbnails(event: MouseEvent): void {
      const btn = (event.target as HTMLElement).closest<HTMLElement>(
        '[data-scroll-dir]'
      );
      if (!btn) {
        return;
      }
      const dir = btn.dataset.scrollDir === 'left' ? -1 : 1;
      const strip = btn
        .closest('.aggressive-apparel-quick-view__thumbnail-nav')
        ?.querySelector<HTMLElement>(
          '.aggressive-apparel-quick-view__thumbnails'
        );
      if (!strip) {
        return;
      }
      const step = 64; // 3.5rem thumb + 0.5rem gap.
      strip.scrollBy({
        left: dir * step,
        behavior: prefersReducedMotion.matches ? 'auto' : 'smooth',
      });
    },

    incrementQty(event?: Event): void {
      // Mark as handled so the delegation fallback doesn't double-fire.
      if (event) event.preventDefault();
      const max = state.stockQuantity || 9999;
      if (state.quantity < max) {
        state.quantity = state.quantity + 1;
      }
    },

    decrementQty(event?: Event): void {
      // Mark as handled so the delegation fallback doesn't double-fire.
      if (event) event.preventDefault();
      if (state.quantity > 1) {
        state.quantity = state.quantity - 1;
      }
    },

    /**
     * Set quantity from direct input.
     */
    setQuantity(event: Event): void {
      const target = event.target as HTMLInputElement;
      const value = parseInt(target.value, 10);
      const max = state.stockQuantity || 9999;
      if (!isNaN(value) && value >= 1 && value <= max) {
        state.quantity = value;
      } else {
        // Reset to valid value.
        target.value = String(state.quantity);
      }
    },

    /**
     * Select a gallery image by thumbnail or dot click.
     */
    selectImage(): void {
      const ctx = getContext<QuickViewContext>();
      const item = ctx.item as GalleryImage | undefined;
      if (!item) {
        return;
      }
      const index = state.productImages.findIndex(
        (img: GalleryImage) => img.id === item.id
      );
      if (index >= 0 && index !== state.activeImageIndex) {
        fadeImage();
        state.activeImageIndex = index;
      }
    },

    /**
     * Navigate to next gallery image.
     */
    nextImage(): void {
      const max = state.productImages.length - 1;
      if (max <= 0) {
        return;
      }
      fadeImage();
      state.activeImageIndex =
        state.activeImageIndex >= max ? 0 : state.activeImageIndex + 1;
    },

    /**
     * Navigate to previous gallery image.
     */
    prevImage(): void {
      const max = state.productImages.length - 1;
      if (max <= 0) {
        return;
      }
      fadeImage();
      state.activeImageIndex =
        state.activeImageIndex <= 0 ? max : state.activeImageIndex - 1;
    },

    /**
     * Touch swipe handlers for mobile gallery navigation.
     */
    handleTouchStart(event: TouchEvent): void {
      if (!state.hasMultipleImages) return;
      const touch = event.touches[0];
      touchStartX = touch.clientX;
      touchStartY = touch.clientY;
      isSwiping = false;
    },

    handleTouchMove(event: TouchEvent): void {
      if (!state.hasMultipleImages) return;
      const touch = event.touches[0];
      const deltaX = touch.clientX - touchStartX;
      const deltaY = touch.clientY - touchStartY;

      // Lock to horizontal swipe once threshold is met.
      if (
        !isSwiping &&
        Math.abs(deltaX) > 10 &&
        Math.abs(deltaX) > Math.abs(deltaY)
      ) {
        isSwiping = true;
      }

      // Prevent vertical scroll while swiping gallery.
      if (isSwiping) {
        event.preventDefault();
      }
    },

    handleTouchEnd(event: TouchEvent): void {
      if (!state.hasMultipleImages || !isSwiping) return;
      const touch = event.changedTouches[0];
      const deltaX = touch.clientX - touchStartX;
      const threshold = 50;

      if (Math.abs(deltaX) >= threshold) {
        if (deltaX < 0) {
          actions.nextImage();
        } else {
          actions.prevImage();
        }
      }

      isSwiping = false;
    },

    /**
     * Open the mobile bottom drawer for option selection.
     */
    openDrawer(): void {
      const drawerEl = document.querySelector<HTMLElement>(
        '.aggressive-apparel-quick-view__drawer'
      );
      if (drawerEl) {
        drawerEl.hidden = false;
        void drawerEl.offsetHeight;
      }
      state.isDrawerOpen = true;
    },

    /**
     * Close the mobile bottom drawer with slide-down animation.
     */
    closeDrawer(): void {
      state.isDrawerOpen = false;

      const panel = document.querySelector<HTMLElement>(
        '.aggressive-apparel-quick-view__drawer-panel'
      );
      let done = false;
      const finish = (): void => {
        if (done) return;
        done = true;
        const el = document.querySelector<HTMLElement>(
          '.aggressive-apparel-quick-view__drawer'
        );
        if (el && !state.isDrawerOpen) {
          el.hidden = true;
        }
      };

      if (panel) {
        panel.addEventListener(
          'transitionend',
          (e: Event) => {
            if ((e as TransitionEvent).propertyName === 'transform') finish();
          },
          { once: true }
        );
        setTimeout(finish, 400);
      } else {
        finish();
      }
    },

    /**
     * Handle keyboard events (ESC to close, arrows for gallery).
     */
    handleKeydown(event: KeyboardEvent): void {
      if (state.isSuccessOpen && event.key === 'Escape') {
        actions.closeCartSuccess();
        return;
      }

      if (!state.isOpen) {
        return;
      }

      if (event.key === 'Escape') {
        if (state.isDrawerOpen) {
          actions.closeDrawer();
        } else {
          actions.close();
        }
        return;
      }

      // Arrow keys for gallery navigation.
      if (state.hasMultipleImages) {
        if (event.key === 'ArrowLeft') {
          actions.prevImage();
        } else if (event.key === 'ArrowRight') {
          actions.nextImage();
        }
      }
    },

    async addToCart(event?: Event): Promise<void> {
      if (event) event.preventDefault();
      if (!state.canAddToCart) {
        return;
      }

      state.isAddingToCart = true;
      state.cartError = '';

      // Determine the ID to add (variation ID for variable, product ID for simple).
      const itemId: number =
        state.productType === 'variable' && state.matchedVariationId
          ? state.matchedVariationId
          : state.productId;

      const body: CartAddBody = {
        id: itemId,
        quantity: state.quantity,
      };

      // For variable products, send variation attributes using the keys
      // from the matched variation (which use the proper taxonomy slugs
      // like "pa_color" that the Store API expects).
      if (state.productType === 'variable' && state.matchedVariationId) {
        const matchedVar = state.productVariations.find(
          (v: ResolvedVariation) => v.id === state.matchedVariationId
        );
        if (matchedVar && matchedVar.attributes) {
          body.variation = matchedVar.attributes
            .filter(attr => attr.value)
            .map(attr => ({
              attribute: attr.attribute || attr.name || '',
              value: attr.value,
            }));
        }
      }

      const cartUrl = state.cartApiUrl || '/wp-json/wc/store/v1/cart';
      const addUrl = `${cartUrl}/add-item`;

      // Ensure we have a valid nonce before sending.
      if (!state.cartNonce) {
        try {
          const cartRes = await fetch(cartUrl, {
            credentials: 'same-origin',
          });
          const freshNonce = cartRes.headers.get('Nonce');
          if (freshNonce) {
            state.cartNonce = freshNonce;
          }
        } catch {
          // Fall through — request will fail with a clear nonce error.
        }
      }

      if (!state.cartNonce) {
        state.cartError = 'Session expired. Please reload the page.';
        state.isAddingToCart = false;
        return;
      }

      const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        Nonce: state.cartNonce,
      };

      fetch(addUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers,
        body: JSON.stringify(body),
      })
        .then((res: Response) => {
          // Capture the refreshed nonce for subsequent requests.
          const newNonce = res.headers.get('Nonce');
          if (newNonce) {
            state.cartNonce = newNonce;
          }

          if (!res.ok) {
            return res.json().then((err: { message?: string }) => {
              throw new Error(err.message || `HTTP ${res.status}`);
            });
          }
          return res.json();
        })
        .then(() => {
          // Show success state on the button briefly before opening the
          // standalone cart confirmation.
          state.isCartSuccess = true;
          state.isAddingToCart = false;

          setTimeout(() => {
            state.isCartSuccess = false;

            state.announcement = getLabel(
              'addedSuccessAnnounce',
              'Product added to cart successfully'
            );
            actions.openCartSuccess();

            // Dispatch a custom event so WooCommerce mini-cart can update.
            document.body.dispatchEvent(
              new CustomEvent('wc-blocks_added_to_cart', {
                bubbles: true,
              })
            );
          }, 800);
        })
        .catch((err: Error) => {
          if (typeof window.SCRIPT_DEBUG !== 'undefined') {
            console.error('[Quick View] Add to cart failed:', err);
          }
          state.cartError =
            decodeEntities(err.message) ||
            getLabel('addToCartError', 'Could not add to cart.');
          const errorTemplate = getLabel('errorAnnounce', 'Error: %s');
          state.announcement = errorTemplate.replace('%s', state.cartError);
          state.isAddingToCart = false;
        });
    },

    /**
     * Add item to cart and redirect to checkout immediately.
     *
     * Mirrors addToCart logic but navigates to the checkout page on
     * success instead of showing the cart confirmation.
     */
    async buyNow(event?: Event): Promise<void> {
      if (event) event.preventDefault();
      if (!state.canAddToCart) {
        return;
      }

      state.isBuyingNow = true;
      state.cartError = '';

      const itemId: number =
        state.productType === 'variable' && state.matchedVariationId
          ? state.matchedVariationId
          : state.productId;

      const body: CartAddBody = {
        id: itemId,
        quantity: state.quantity,
      };

      if (state.productType === 'variable' && state.matchedVariationId) {
        const matchedVar = state.productVariations.find(
          (v: ResolvedVariation) => v.id === state.matchedVariationId
        );
        if (matchedVar && matchedVar.attributes) {
          body.variation = matchedVar.attributes
            .filter(attr => attr.value)
            .map(attr => ({
              attribute: attr.attribute || attr.name || '',
              value: attr.value,
            }));
        }
      }

      const cartUrl = state.cartApiUrl || '/wp-json/wc/store/v1/cart';
      const addUrl = `${cartUrl}/add-item`;

      if (!state.cartNonce) {
        try {
          const cartRes = await fetch(cartUrl, {
            credentials: 'same-origin',
          });
          const freshNonce = cartRes.headers.get('Nonce');
          if (freshNonce) {
            state.cartNonce = freshNonce;
          }
        } catch {
          // Fall through.
        }
      }

      if (!state.cartNonce) {
        state.cartError = 'Session expired. Please reload the page.';
        state.isBuyingNow = false;
        return;
      }

      try {
        const res = await fetch(addUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Nonce: state.cartNonce,
          },
          body: JSON.stringify(body),
        });

        const newNonce = res.headers.get('Nonce');
        if (newNonce) {
          state.cartNonce = newNonce;
        }

        if (!res.ok) {
          const err: { message?: string } = await res.json();
          throw new Error(err.message || `HTTP ${res.status}`);
        }

        // Redirect to checkout.
        window.location.href = state.checkoutUrl;
      } catch (err) {
        if (typeof window.SCRIPT_DEBUG !== 'undefined') {
          console.error('[Quick View] Buy now failed:', err);
        }
        state.cartError =
          decodeEntities((err as Error).message) ||
          getLabel('addToCartError', 'Could not add to cart.');
        const errorTemplate = getLabel('errorAnnounce', 'Error: %s');
        state.announcement = errorTemplate.replace('%s', state.cartError);
        state.isBuyingNow = false;
      }
    },
  },
});
