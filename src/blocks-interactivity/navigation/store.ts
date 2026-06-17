/**
 * Navigation Block System — Interactivity Store (v3)
 *
 * Desktop navigation only. Owns the sliding indicator, hover/click submenus,
 * and keyboard navigation for the menubar. The mobile panel is a separate
 * block (navigation-panel) with its own store.
 *
 * Mutable state lives in state._navs[navId] (global store):
 * - isMobile: disables hover-based submenu opening below the breakpoint.
 * - activeSubmenuId: tracks which desktop dropdown/mega is open.
 *
 * @package Aggressive_Apparel
 */

import {
  getContext,
  getElement,
  store,
  withSyncEvent,
} from '@wordpress/interactivity';
import {
  ARROW_KEYS,
  DEFAULT_BREAKPOINT,
  HOVER_INTENT,
  INDICATOR_DURATION_MS,
  KEYS,
  SELECTORS,
} from './constants';
import type { NavigationContext, NavigationState, NavState } from './types';
import {
  announce,
  clearHoverTimeouts,
  focusMenuItem,
  generateNavId,
  isValidNavId,
  logError,
  logWarning,
  prefersReducedMotion,
  safeQuerySelector,
} from './utils';
import { hoverIntent, mediaQueryRegistry } from './registries';
import {
  expandIndicatorForSubmenu,
  resetIndicatorOnClose,
  setupDesktopIndicator,
} from './indicator';
import { getMenuItems, getSubmenuItems } from './menu-items';

// ============================================================================
// Nav State Helper
// ============================================================================

/**
 * Get the mutable state for a navigation instance.
 * Lazily initializes if the entry doesn't exist yet.
 */
function getNavState(navId: string): NavState {
  const navs = navigationStore.state._navs;
  if (!navs[navId]) {
    navs[navId] = {
      isMobile: false,
      activeSubmenuId: null,
    };
  }
  return navs[navId];
}

// ============================================================================
// Store Definition
// ============================================================================

const navigationStore = store('aggressive-apparel/navigation', {
  state: {
    get isMobile() {
      try {
        const { navId } = getContext<NavigationContext>();
        return getNavState(navId).isMobile;
      } catch {
        return false;
      }
    },
    get activeSubmenuId() {
      try {
        const { navId } = getContext<NavigationContext>();
        return getNavState(navId).activeSubmenuId;
      } catch {
        return null;
      }
    },

    _navs: {} as Record<string, NavState>,
  } as NavigationState,

  actions: {
    /**
     * Open a specific submenu by ID.
     */
    openSubmenu(): void {
      try {
        const context = getContext<
          NavigationContext & { submenuId?: string }
        >();
        if (!context) {
          return;
        }

        const ns = getNavState(context.navId);
        clearHoverTimeouts(hoverIntent);
        ns.activeSubmenuId = context.submenuId ?? null;

        if (context.submenuId) {
          expandIndicatorForSubmenu(
            context.navId,
            context.submenuId,
            ns.isMobile
          );
          announce('Submenu opened', { navId: context.navId });
        }
      } catch (error) {
        logError('openSubmenu: Failed to open submenu', error);
      }
    },

    /**
     * Close the active submenu.
     */
    closeSubmenu(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          return;
        }

        const ns = getNavState(context.navId);
        clearHoverTimeouts(hoverIntent);
        resetIndicatorOnClose(context.navId, ns.isMobile);
        ns.activeSubmenuId = null;

        announce('Submenu closed', { navId: context.navId });
      } catch (error) {
        logError('closeSubmenu: Failed to close submenu', error);
      }
    },

    /**
     * Toggle a submenu open/closed.
     */
    toggleSubmenu: withSyncEvent((event?: Event): void => {
      try {
        const context = getContext<
          NavigationContext & { submenuId?: string; menuType?: string }
        >();
        if (!context) {
          return;
        }

        if (event) {
          event.preventDefault();
        }

        const ns = getNavState(context.navId);
        clearHoverTimeouts(hoverIntent);

        const wasOpen = ns.activeSubmenuId === context.submenuId;
        ns.activeSubmenuId = wasOpen ? null : (context.submenuId ?? null);
        if (wasOpen) {
          resetIndicatorOnClose(context.navId, ns.isMobile);
        } else if (context.submenuId) {
          expandIndicatorForSubmenu(
            context.navId,
            context.submenuId,
            ns.isMobile
          );
        }

        if (wasOpen) {
          announce('Submenu closed', { navId: context.navId });
        } else if (ns.activeSubmenuId) {
          announce('Submenu opened', { navId: context.navId });
        }
      } catch (error) {
        logError('toggleSubmenu: Failed to toggle submenu', error);
      }
    }),

    /**
     * Close all submenus (used when clicking outside).
     */
    closeAllSubmenus(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          return;
        }

        const ns = getNavState(context.navId);
        clearHoverTimeouts(hoverIntent);
        resetIndicatorOnClose(context.navId, ns.isMobile);
        ns.activeSubmenuId = null;
      } catch (error) {
        logError('closeAllSubmenus: Failed to close submenus', error);
      }
    },
  },

  callbacks: {
    /**
     * Initialize navigation on mount (runs on the <nav> element).
     */
    init(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          logWarning('init: No context available');
          return;
        }

        const breakpoint = context.breakpoint ?? DEFAULT_BREAKPOINT;
        const element = getElement();

        if (!isValidNavId(context.navId)) {
          logWarning('init: Invalid navId, generating new one');
          context.navId = generateNavId();
        }

        // Reset state on load.
        const ns = getNavState(context.navId);
        ns.activeSubmenuId = null;

        if (!element?.ref) {
          logWarning('init: No element reference available');
          return;
        }

        // Use matchMedia for efficient breakpoint detection.
        const mql = window.matchMedia(`(max-width: ${breakpoint - 1}px)`);

        const handler = (e: MediaQueryListEvent | MediaQueryList) => {
          const navState = getNavState(context.navId);
          const wasMobile = navState.isMobile;
          navState.isMobile = e.matches;

          // Reset the active submenu when switching to desktop.
          if (wasMobile && !navState.isMobile) {
            navState.activeSubmenuId = null;
          }
        };

        handler(mql);
        mql.addEventListener('change', handler);
        mediaQueryRegistry.set(element.ref, { mql, handler });

        // Desktop sliding indicator (hover/focus/resize wiring).
        setupDesktopIndicator(context.navId, element.ref, getNavState);
      } catch (error) {
        logError('init: Failed to initialize navigation', error);
      }
    },

    /**
     * Handle the Escape key — closes an open submenu.
     */
    onEscape(event: KeyboardEvent): void {
      if (event.key !== KEYS.escape) {
        return;
      }

      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          return;
        }

        const ns = getNavState(context.navId);
        if (ns.activeSubmenuId) {
          navigationStore.actions.closeSubmenu();
        }
      } catch (error) {
        logError('onEscape: Failed to handle escape key', error);
      }
    },

    /**
     * Handle arrow key navigation for WAI-ARIA menubar compliance.
     */
    onArrowKey(event: KeyboardEvent): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          return;
        }

        const ns = getNavState(context.navId);

        const key = event.key;
        if (!ARROW_KEYS.includes(key as (typeof ARROW_KEYS)[number])) {
          return;
        }

        const activeElement = document.activeElement as HTMLElement | null;
        if (!activeElement) {
          return;
        }

        const submenuPanel = activeElement.closest(SELECTORS.submenuPanel);
        const menubar = activeElement.closest(SELECTORS.menubar);
        const isInSubmenu = !!submenuPanel;

        let items: HTMLElement[] = [];
        if (isInSubmenu && submenuPanel) {
          items = getSubmenuItems(submenuPanel);
        } else if (menubar) {
          items = getMenuItems(menubar);
        }

        if (items.length === 0) {
          return;
        }

        const currentIndex = items.indexOf(activeElement);
        if (currentIndex === -1) {
          return;
        }

        event.preventDefault();

        switch (key) {
          case KEYS.arrowRight:
            if (!isInSubmenu) {
              focusMenuItem(items, (currentIndex + 1) % items.length);
            } else {
              const submenuTrigger = activeElement.closest(
                SELECTORS.navSubmenu
              );
              if (submenuTrigger) {
                navigationStore.actions.toggleSubmenu();
              }
            }
            break;

          case KEYS.arrowLeft:
            if (!isInSubmenu) {
              focusMenuItem(
                items,
                (currentIndex - 1 + items.length) % items.length
              );
            } else {
              navigationStore.actions.closeSubmenu();
              const parentSubmenu = submenuPanel?.closest(SELECTORS.navSubmenu);
              if (parentSubmenu) {
                const trigger = safeQuerySelector<HTMLElement>(
                  parentSubmenu,
                  SELECTORS.submenuLink,
                  false
                );
                trigger?.focus();
              }
            }
            break;

          case KEYS.arrowDown:
            if (isInSubmenu) {
              focusMenuItem(items, (currentIndex + 1) % items.length);
            } else {
              const submenuTrigger = activeElement.closest(
                SELECTORS.navSubmenu
              );
              if (submenuTrigger) {
                const panel = safeQuerySelector<HTMLElement>(
                  submenuTrigger,
                  SELECTORS.submenuPanel,
                  false
                );
                if (panel && ns.activeSubmenuId !== panel.id) {
                  navigationStore.actions.openSubmenu();
                  announce('Submenu opened', { navId: context.navId });
                  setTimeout(() => {
                    const submenuItems = getSubmenuItems(panel);
                    if (submenuItems.length > 0) {
                      focusMenuItem(submenuItems, 0);
                    }
                  }, 50);
                }
              }
            }
            break;

          case KEYS.arrowUp:
            if (isInSubmenu) {
              focusMenuItem(
                items,
                (currentIndex - 1 + items.length) % items.length
              );
            }
            break;

          case KEYS.home:
            focusMenuItem(items, 0);
            break;

          case KEYS.end:
            focusMenuItem(items, items.length - 1);
            break;
        }
      } catch (error) {
        logError('onArrowKey: Failed to handle arrow key', error);
      }
    },

    /**
     * Handle hover enter with intent delay.
     */
    onHoverEnter(): void {
      try {
        const context = getContext<
          NavigationContext & { submenuId?: string }
        >();
        if (!context) {
          return;
        }

        const ns = getNavState(context.navId);

        if (ns.isMobile || context.openOn !== 'hover') {
          return;
        }

        if (hoverIntent.closeTimeout) {
          clearTimeout(hoverIntent.closeTimeout);
          hoverIntent.closeTimeout = null;
        }

        if (hoverIntent.openTimeout) {
          clearTimeout(hoverIntent.openTimeout);
          hoverIntent.openTimeout = null;
        }

        const hasActiveSubmenu = !!hoverIntent.activeId;
        const openDelay = prefersReducedMotion()
          ? HOVER_INTENT.reducedMotion[
              hasActiveSubmenu ? 'switchDelay' : 'openDelay'
            ]
          : hasActiveSubmenu
            ? HOVER_INTENT.switchDelay
            : HOVER_INTENT.openDelay;

        hoverIntent.openTimeout = setTimeout(() => {
          try {
            const navState = getNavState(context.navId);
            const previousId = navState.activeSubmenuId;

            if (previousId && previousId !== context.submenuId) {
              navState.activeSubmenuId = null;

              setTimeout(() => {
                try {
                  const ns2 = getNavState(context.navId);
                  ns2.activeSubmenuId = context.submenuId ?? null;
                  hoverIntent.activeId = context.submenuId ?? null;
                  if (context.submenuId) {
                    expandIndicatorForSubmenu(
                      context.navId,
                      context.submenuId,
                      ns2.isMobile
                    );
                  }
                } catch {
                  // Context may no longer be valid.
                }
              }, INDICATOR_DURATION_MS);
            } else {
              navState.activeSubmenuId = context.submenuId ?? null;
              hoverIntent.activeId = context.submenuId ?? null;
              if (context.submenuId) {
                expandIndicatorForSubmenu(
                  context.navId,
                  context.submenuId,
                  navState.isMobile
                );
              }
            }
          } catch {
            // Context may no longer be valid.
          }
        }, openDelay);
      } catch (error) {
        logError('onHoverEnter: Failed to handle hover enter', error);
      }
    },

    /**
     * Handle hover/focus leave with delay.
     */
    onHoverLeave: withSyncEvent((event: MouseEvent | FocusEvent): void => {
      try {
        const context = getContext<
          NavigationContext & { submenuId?: string }
        >();
        if (!context) {
          return;
        }

        const ns = getNavState(context.navId);

        if (ns.isMobile || context.openOn !== 'hover') {
          return;
        }

        const relatedTarget = event.relatedTarget as HTMLElement | null;
        const currentTarget = event.currentTarget as HTMLElement;
        const submenuContainer = currentTarget.closest(SELECTORS.navSubmenu);

        if (submenuContainer?.contains(relatedTarget)) {
          return;
        }

        if (hoverIntent.openTimeout) {
          clearTimeout(hoverIntent.openTimeout);
          hoverIntent.openTimeout = null;
        }

        const closeDelay = prefersReducedMotion()
          ? HOVER_INTENT.reducedMotion.closeDelay
          : HOVER_INTENT.closeDelay;

        hoverIntent.closeTimeout = setTimeout(() => {
          try {
            const navState = getNavState(context.navId);
            if (navState.activeSubmenuId === context.submenuId) {
              resetIndicatorOnClose(context.navId, navState.isMobile);
              navState.activeSubmenuId = null;
              hoverIntent.activeId = null;
            }
          } catch {
            // Context may no longer be valid.
          }
        }, closeDelay);
      } catch (error) {
        logError('onHoverLeave: Failed to handle hover leave', error);
      }
    }),

    /**
     * Handle native Popover API toggle events.
     */
    onPopoverToggle(event: ToggleEvent): void {
      try {
        const context = getContext<
          NavigationContext & { submenuId?: string }
        >();
        if (!context) {
          return;
        }

        const ns = getNavState(context.navId);
        const isOpening = event.newState === 'open';
        const submenuId = context.submenuId;

        if (isOpening) {
          if (submenuId) {
            ns.activeSubmenuId = submenuId;
            hoverIntent.activeId = submenuId;
            expandIndicatorForSubmenu(context.navId, submenuId, ns.isMobile);
          }
          announce('Submenu opened', { navId: context.navId });
        } else {
          if (ns.activeSubmenuId === submenuId) {
            resetIndicatorOnClose(context.navId, ns.isMobile);
            ns.activeSubmenuId = null;
            hoverIntent.activeId = null;
          }
          announce('Submenu closed', { navId: context.navId });
        }

        clearHoverTimeouts(hoverIntent);
      } catch (error) {
        logError('onPopoverToggle: Failed to handle popover toggle', error);
      }
    },

    /**
     * Check if a specific submenu is currently open.
     */
    isSubmenuOpen(): boolean {
      try {
        const context = getContext<
          NavigationContext & { submenuId?: string }
        >();
        if (!context) {
          return false;
        }
        const ns = getNavState(context.navId);
        return ns.activeSubmenuId === context.submenuId;
      } catch {
        return false;
      }
    },
  },
});

export default navigationStore;
export { generateNavId };
