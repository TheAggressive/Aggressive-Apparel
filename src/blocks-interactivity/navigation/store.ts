/**
 * Navigation Block System — Interactivity Store (v3)
 *
 * Portal architecture: the mobile panel renders outside .wp-site-blocks
 * via wp_footer so position: fixed is not trapped by ancestor stacking
 * contexts. Mutable state lives in state._panels[navId] (global store),
 * shared between the <nav> and the portaled panel.
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
import type { NavigationContext, NavigationState, PanelState } from './types';
import {
  announce,
  clearHoverTimeouts,
  focusDrilldownPanel,
  focusDrilldownTrigger,
  focusMegaContentPanel,
  focusMenuItem,
  generateNavId,
  isValidNavId,
  logError,
  logWarning,
  prefersReducedMotion,
  removeAllBodyClasses,
  safeQuerySelector,
  setBodyOverflow,
  setPanelVisibility,
  updateDrilldownInertState,
  updateMegaContentInertState,
} from './utils';
import {
  focusTrapRegistry,
  hoverIntent,
  mediaQueryRegistry,
} from './registries';
import {
  expandIndicatorForSubmenu,
  resetIndicatorOnClose,
  setupDesktopIndicator,
} from './indicator';
import { getMenuItems, getSubmenuItems } from './menu-items';
import { closePanelWithCleanup, findPanel, openPanelWithSetup } from './panel';

// ============================================================================
// Nav State Helper
// ============================================================================

/**
 * Get the mutable panel state for a navigation instance.
 * Lazily initializes if the entry doesn't exist yet.
 *
 * Lives here (rather than a helper module) because it reads the reactive
 * store state; the panel/indicator helpers receive the resolved PanelState
 * so they stay decoupled from the store.
 */
function getNavState(navId: string): PanelState {
  const panels = navigationStore.state._panels;
  if (!panels[navId]) {
    panels[navId] = {
      isOpen: false,
      isMobile: false,
      activeSubmenuId: null,
      drillStack: [],
    };
  }
  return panels[navId];
}

// ============================================================================
// Store Definition
// ============================================================================

const navigationStore = store('aggressive-apparel/navigation', {
  state: {
    get isOpen() {
      try {
        const { navId } = getContext<NavigationContext>();
        return getNavState(navId).isOpen;
      } catch {
        return false;
      }
    },
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
    get drillStack() {
      try {
        const { navId } = getContext<NavigationContext>();
        return getNavState(navId).drillStack;
      } catch {
        return [];
      }
    },

    announcement: '',
    _panels: {} as Record<string, PanelState>,
  } as NavigationState,

  actions: {
    /**
     * Toggle mobile panel open/closed.
     */
    toggle(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context || !isValidNavId(context.navId)) {
          logWarning('toggle: Invalid context or navId');
          return;
        }

        const panel = findPanel(context.navId);
        if (!panel) {
          logWarning('toggle: Panel not found');
          return;
        }

        const ns = getNavState(context.navId);
        if (ns.isOpen) {
          closePanelWithCleanup(context.navId, panel, ns);
          window.dispatchEvent(
            new CustomEvent('aa-nav-state-change', {
              detail: { navId: context.navId },
            })
          );
        } else {
          ns.isOpen = true;
          setBodyOverflow(true);
          openPanelWithSetup(context.navId, panel, ns);
          announce('Navigation menu opened', {
            assertive: true,
            navId: context.navId,
          });
        }
      } catch (error) {
        logError('toggle: Failed to toggle navigation', error);
      }
    },

    /**
     * Close mobile panel.
     */
    close(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context || !isValidNavId(context.navId)) {
          return;
        }

        const panel = findPanel(context.navId);
        if (!panel) {
          return;
        }

        const ns = getNavState(context.navId);
        closePanelWithCleanup(context.navId, panel, ns);
        window.dispatchEvent(
          new CustomEvent('aa-nav-state-change', {
            detail: { navId: context.navId },
          })
        );
      } catch (error) {
        logError('close: Failed to close navigation', error);
      }
    },

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
     * Close active submenu.
     */
    closeSubmenu(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          return;
        }

        const ns = getNavState(context.navId);
        const closingSubmenuId = ns.activeSubmenuId;

        clearHoverTimeouts(hoverIntent);
        resetIndicatorOnClose(context.navId, ns.isMobile);
        ns.activeSubmenuId = null;

        // Mobile: restore inert and return focus to the trigger.
        if (ns.isMobile && closingSubmenuId) {
          const navPanel = findPanel(context.navId);
          if (navPanel) {
            updateMegaContentInertState(navPanel, null);

            // Focus the trigger that opened this submenu.
            const trigger = safeQuerySelector<HTMLElement>(
              navPanel,
              `[aria-controls="${CSS.escape(closingSubmenuId)}"]`,
              false
            );
            requestAnimationFrame(() => {
              trigger?.focus();
            });
          }
        }

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

        if (ns.isMobile) {
          // Mega-content overlays: manage inert state and focus.
          // Only mega submenus open as full-screen overlays — dropdown-
          // turned-drilldown items are inline accordions that must NOT
          // inert siblings (the mobile clone rewrites the class/context
          // but not the click handler, so both types call toggleSubmenu).
          if (context.menuType === 'mega') {
            const navPanel = findPanel(context.navId);
            if (navPanel) {
              if (wasOpen) {
                // Closing: restore inert state so all items are reachable.
                updateMegaContentInertState(navPanel, null);
              } else if (context.submenuId) {
                // Opening: inert everything outside the overlay and move
                // focus to the back button inside the overlay panel.
                updateMegaContentInertState(navPanel, context.submenuId);
                focusMegaContentPanel(context.submenuId);
              }
            }
          }
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
     * Drill into a submenu (mobile drill-down navigation).
     */
    drillInto: withSyncEvent((event?: Event): void => {
      try {
        const context = getContext<
          NavigationContext & { submenuId?: string }
        >();
        if (!context) {
          return;
        }

        if (event) {
          event.preventDefault();
        }

        if (context.submenuId) {
          const ns = getNavState(context.navId);
          const newStack = [...ns.drillStack, context.submenuId];
          ns.drillStack = newStack;
          window.dispatchEvent(
            new CustomEvent('aa-nav-state-change', {
              detail: { navId: context.navId },
            })
          );

          // Get the submenu label for announcement.
          let submenuLabel = 'submenu';
          if (event?.target) {
            const trigger = (event.target as HTMLElement).closest(
              SELECTORS.navSubmenu
            );
            if (trigger) {
              const labelEl = safeQuerySelector(
                trigger,
                SELECTORS.submenuLabel,
                false
              );
              if (labelEl?.textContent) {
                submenuLabel = labelEl.textContent.trim();
              }
            }
          }

          announce(`Opened ${submenuLabel}, level ${newStack.length}`, {
            navId: context.navId,
          });

          // Update inert state on drilldown panels.
          const panel = findPanel(context.navId);
          if (panel) {
            updateDrilldownInertState(panel, newStack);
          }

          // Focus the first item in the newly opened drilldown panel.
          focusDrilldownPanel(context.submenuId);
        }
      } catch (error) {
        logError('drillInto: Failed to drill into submenu', error);
      }
    }),

    /**
     * Go back one level in drill-down navigation.
     */
    drillBack(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context || !isValidNavId(context.navId)) {
          return;
        }

        const ns = getNavState(context.navId);

        if (ns.drillStack.length === 0) {
          return;
        }

        const leavingPanelId = ns.drillStack[ns.drillStack.length - 1];
        const newStack = ns.drillStack.slice(0, -1);
        ns.drillStack = newStack;
        window.dispatchEvent(
          new CustomEvent('aa-nav-state-change', {
            detail: { navId: context.navId },
          })
        );

        if (newStack.length === 0) {
          announce('Back to main menu', { navId: context.navId });
        } else {
          announce(`Back to level ${newStack.length}`, {
            navId: context.navId,
          });
        }

        const panel = findPanel(context.navId);
        if (panel) {
          updateDrilldownInertState(panel, newStack);
          if (leavingPanelId) {
            focusDrilldownTrigger(panel, leavingPanelId);
          }
        }
      } catch (error) {
        logError('drillBack: Failed to drill back', error);
      }
    },

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
        ns.isOpen = false;
        ns.isMobile = false;
        ns.activeSubmenuId = null;
        ns.drillStack = [];
        setBodyOverflow(false);

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

          // Reset state when switching to desktop.
          if (wasMobile && !navState.isMobile) {
            navState.isOpen = false;
            navState.activeSubmenuId = null;
            navState.drillStack = [];
            setBodyOverflow(false);
            removeAllBodyClasses();

            const panel = findPanel(context.navId);
            if (panel) {
              setPanelVisibility(panel, false);
              const existingCleanup = focusTrapRegistry.get(panel);
              if (existingCleanup) {
                existingCleanup();
                focusTrapRegistry.delete(panel);
              }
            }

            if ('inert' in HTMLElement.prototype) {
              const mainContent = document.querySelector(
                '.wp-site-blocks'
              ) as HTMLElement | null;
              if (mainContent) {
                mainContent.inert = false;
              }
            }
          }
        };

        // Set initial state and listen for changes.
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
     * Initialize panel (runs on the portal wrapper element).
     * Sets up stagger indices for menu items.
     */
    initPanel(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context || !isValidNavId(context.navId)) {
          return;
        }

        const panel = findPanel(context.navId);
        if (!panel) {
          return;
        }

        // ================================================================
        // Stagger index: set --item-index on each panel menu item
        // ================================================================
        const panelItems = panel.querySelectorAll(
          `${SELECTORS.panelMenu} > li`
        );
        panelItems.forEach((li, index) => {
          (li as HTMLElement).style.setProperty('--item-index', String(index));
        });
      } catch (error) {
        logError('initPanel: Failed to initialize panel', error);
      }
    },

    /**
     * Handle Escape key.
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
        const { actions } = navigationStore;

        if (ns.drillStack.length > 0) {
          actions.drillBack();
        } else if (ns.activeSubmenuId) {
          actions.closeSubmenu();
        } else if (ns.isOpen) {
          actions.close();
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

        // Determine if we're in a submenu or the top-level menubar.
        const submenuPanel = activeElement.closest(SELECTORS.submenuPanel);
        const menubar = activeElement.closest(SELECTORS.menubar);
        const panelMenu = activeElement.closest(SELECTORS.panelMenu);
        const isInSubmenu = !!submenuPanel;

        // Determine orientation: menubar is horizontal, panel menu is vertical.
        let isHorizontal = true;
        if (panelMenu) {
          isHorizontal = false;
        } else if (menubar) {
          isHorizontal = true;
        } else {
          // Fallback: check computed style.
          const parent = activeElement.closest(
            'ul, [role="menubar"], [role="menu"]'
          );
          if (parent) {
            const computedStyle = window.getComputedStyle(parent);
            isHorizontal =
              computedStyle.flexDirection === 'row' ||
              computedStyle.flexDirection === 'row-reverse';
          }
        }

        // Get the appropriate item list.
        let items: HTMLElement[] = [];
        if (isInSubmenu && submenuPanel) {
          items = getSubmenuItems(submenuPanel);
        } else if (menubar) {
          items = getMenuItems(menubar);
        } else if (panelMenu) {
          items = getMenuItems(panelMenu);
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
            if (isHorizontal && !isInSubmenu) {
              focusMenuItem(items, (currentIndex + 1) % items.length);
            } else if (isInSubmenu) {
              const submenuTrigger = activeElement.closest(
                SELECTORS.navSubmenu
              );
              if (submenuTrigger) {
                navigationStore.actions.toggleSubmenu();
              }
            }
            break;

          case KEYS.arrowLeft:
            if (isHorizontal && !isInSubmenu) {
              focusMenuItem(
                items,
                (currentIndex - 1 + items.length) % items.length
              );
            } else if (isInSubmenu) {
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
            if (isInSubmenu || !isHorizontal) {
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
            if (isInSubmenu || !isHorizontal) {
              const isInDrilldown = activeElement.closest(
                `.${SELECTORS.submenuDrilldown}`
              );
              if (
                isInDrilldown &&
                currentIndex === 0 &&
                ns.drillStack.length > 0
              ) {
                navigationStore.actions.drillBack();
              } else {
                focusMenuItem(
                  items,
                  (currentIndex - 1 + items.length) % items.length
                );
              }
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

        // Use a shorter delay when switching between submenus (one is
        // already open) so navigation feels responsive. Use the longer
        // delay for the initial open so passing through doesn't trigger.
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

            // If switching between submenus, close the current one first
            // and wait for its exit animation before opening the new one.
            if (previousId && previousId !== context.submenuId) {
              navState.activeSubmenuId = null;

              // Wait for the panel close transition before opening the new one.
              setTimeout(() => {
                try {
                  const ns = getNavState(context.navId);
                  ns.activeSubmenuId = context.submenuId ?? null;
                  hoverIntent.activeId = context.submenuId ?? null;
                  if (context.submenuId) {
                    expandIndicatorForSubmenu(
                      context.navId,
                      context.submenuId,
                      ns.isMobile
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
     * Handle state change events (for toggle button aria-expanded sync).
     */
    onStateChange(event: CustomEvent<{ navId: string }>): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context || event.detail?.navId !== context.navId) {
          return;
        }
        // State is shared via state._panels — no sync needed.
      } catch (error) {
        logError('onStateChange: Failed to handle state change', error);
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

    /**
     * Check if drill-down has history.
     */
    hasDrillHistory(): boolean {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          return false;
        }
        const ns = getNavState(context.navId);
        return ns.drillStack.length > 0;
      } catch {
        return false;
      }
    },

    /**
     * Check if this submenu is in the drill stack.
     */
    isInDrillStack(): boolean {
      try {
        const context = getContext<
          NavigationContext & { submenuId?: string }
        >();
        if (!context) {
          return false;
        }
        const ns = getNavState(context.navId);
        return ns.drillStack.includes(context.submenuId ?? '');
      } catch {
        return false;
      }
    },

    /**
     * Check if this is the current drill level.
     */
    isCurrentDrillLevel(): boolean {
      try {
        const context = getContext<
          NavigationContext & { submenuId?: string }
        >();
        if (!context) {
          return false;
        }
        const ns = getNavState(context.navId);
        return ns.drillStack[ns.drillStack.length - 1] === context.submenuId;
      } catch {
        return false;
      }
    },

    /**
     * Handle state sync for drilldown submenus.
     */
    onSubmenuStateChange(event: CustomEvent<{ navId: string }>): void {
      try {
        const context = getContext<
          NavigationContext & { submenuId?: string; menuType?: string }
        >();
        if (!context || event.detail?.navId !== context.navId) {
          return;
        }
        if (context.menuType !== 'drilldown') {
          return;
        }

        const element = getElement();
        if (!element?.ref) {
          return;
        }

        const ns = getNavState(context.navId);
        const isInStack = ns.drillStack.includes(context.submenuId ?? '');
        element.ref.classList.toggle(SELECTORS.isOpen, isInStack);
      } catch (error) {
        logError('onSubmenuStateChange: Failed to handle state change', error);
      }
    },
  },
});

export default navigationStore;
export { generateNavId };
