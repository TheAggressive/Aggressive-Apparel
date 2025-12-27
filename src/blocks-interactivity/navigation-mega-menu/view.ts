/**
 * Navigation Mega Menu Block - Interactivity API Store
 *
 * Mega menus have specific positioning and behavior needs that differ
 * from regular submenus. This provides mega menu specific actions.
 *
 * @package Aggressive Apparel
 */

/// <reference types="@wordpress/interactivity" />
import { getContext, store } from '@wordpress/interactivity';
import type { SubmenuContext } from '../navigation/types';

// =============================================================================
// INTERACTIVITY STORE
// =============================================================================

store('aggressive-apparel/navigation-mega-menu', {
  actions: {
    /**
     * Toggle mega menu open state (click behavior)
     */
    toggleMegaMenu(): void {
      const ctx = getContext<SubmenuContext>();
      if (ctx.isMegaMenu) {
        ctx.isOpen = !ctx.isOpen;
      }
    },

    /**
     * Open mega menu
     */
    openMegaMenu(): void {
      const ctx = getContext<SubmenuContext>();
      if (ctx.isMegaMenu) {
        ctx.isOpen = true;
      }
    },

    /**
     * Close mega menu
     */
    closeMegaMenu(): void {
      const ctx = getContext<SubmenuContext>();
      if (ctx.isMegaMenu) {
        ctx.isOpen = false;
      }
    },

    /**
     * Handle hover intent for mega menu flyout
     */
    handleMegaMenuHover(): void {
      const ctx = getContext<SubmenuContext>();
      if (ctx.isMegaMenu && ctx.expandType === 'flyout') {
        ctx.isOpen = true;
      }
    },

    /**
     * Schedule mega menu close with delay for hover intent
     */
    scheduleMegaMenuClose(): void {
      const ctx = getContext<SubmenuContext>();
      if (ctx.isMegaMenu && ctx.expandType === 'flyout') {
        setTimeout(() => {
          const hoveredElement = document.querySelector(
            '.aa-navigation-mega-menu:hover'
          );
          if (!hoveredElement) {
            ctx.isOpen = false;
          }
        }, 300); // HOVER_INTENT_DELAY
      }
    },
  },
});
