/**
 * Quick View — gallery, quantity, and touch-swipe actions.
 *
 * Split out of actions.ts to stay under the file-length budget. Registered via a
 * third store() call on the same 'aggressive-apparel/quick-view' namespace — the
 * Interactivity runtime merges actions by namespace. These actions share no
 * module state with the core lifecycle actions (open/close/success/variation),
 * so they live cleanly in their own module.
 *
 * @package Aggressive_Apparel
 */

import { store, getContext } from '@wordpress/interactivity';
import { fadeImage } from './view';
import type { GalleryImage, QuickViewContext, QuickViewStore } from './types';
import { state, actions } from '../quick-view';

// Cached media query — avoids re-creating MediaQueryList on every call.
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
  },
});
