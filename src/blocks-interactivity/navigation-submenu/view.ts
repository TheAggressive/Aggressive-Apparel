/**
 * Navigation Submenu Block - Interactivity API Store
 *
 * Submenu-specific behaviors for flyout, accordion, and drill-down modes.
 * Uses the shared SubmenuContext type from navigation/types.ts.
 *
 * @package Aggressive Apparel
 */

/// <reference types="@wordpress/interactivity" />
import { getContext, store, withSyncEvent } from '@wordpress/interactivity';
import type { SubmenuContext } from '../navigation/types';

// =============================================================================
// CONSTANTS
// =============================================================================

const HOVER_INTENT_DELAY = 300;

// =============================================================================
// INTERACTIVITY STORE
// =============================================================================

store('aggressive-apparel/navigation', {
  actions: {
    /**
     * Toggle submenu open state (click behavior)
     */
    toggleSubmenu(): void {
      const ctx = getContext<SubmenuContext>();
      ctx.isOpen = !ctx.isOpen;
    },

    /**
     * Open a submenu
     */
    openSubmenu: withSyncEvent((): void => {
      const ctx = getContext<SubmenuContext>();
      ctx.isOpen = true;
    }),

    /**
     * Close a submenu
     */
    closeSubmenu(): void {
      const ctx = getContext<SubmenuContext>();
      ctx.isOpen = false;
    },

    /**
     * Toggle accordion submenu
     */
    toggleAccordion: withSyncEvent((): void => {
      const ctx = getContext<SubmenuContext>();
      ctx.isOpen = !ctx.isOpen;
    }),

    /**
     * Handle hover intent for flyout submenus
     */
    handleSubmenuHover: withSyncEvent((): void => {
      const ctx = getContext<SubmenuContext>();
      if (ctx.expandType === 'flyout') {
        ctx.isOpen = true;
      }
    }),

    /**
     * Schedule submenu close with delay for hover intent
     */
    scheduleSubmenuClose(): void {
      const ctx = getContext<SubmenuContext>();
      if (ctx.expandType === 'flyout') {
        setTimeout(() => {
          const hoveredElement = document.querySelector(
            '.aa-navigation-submenu:hover'
          );
          if (!hoveredElement) {
            ctx.isOpen = false;
          }
        }, HOVER_INTENT_DELAY);
      }
    },

    /**
     * Navigate into submenu (drill-down mode)
     */
    navigateDrillDown: withSyncEvent((): void => {
      const ctx = getContext<SubmenuContext>();
      if (ctx.expandType === 'drill-down') {
        ctx.isOpen = true;
      }
    }),
  },
});
