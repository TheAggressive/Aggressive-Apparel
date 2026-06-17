/**
 * Navigation Panel Block — Interactivity Store
 *
 * The mobile panel store. The panel is portaled outside .wp-site-blocks via
 * wp_footer so position: fixed is not trapped by ancestor stacking contexts.
 * Mutable state lives in state._panels[panelSlug] (global store), shared
 * between the trigger button (in the navigation block) and the portaled panel.
 *
 * @package Aggressive_Apparel
 */

import {
  getContext,
  getElement,
  store,
  withSyncEvent,
} from '@wordpress/interactivity';
import { ARROW_KEYS, KEYS, SELECTORS } from './constants';
import type {
  NavigationPanelStoreState,
  PanelContext,
  PanelState,
} from './types';
import {
  announce,
  focusDrilldownPanel,
  focusDrilldownTrigger,
  focusMegaContentPanel,
  focusMenuItem,
  isValidPanelSlug,
  logError,
  logWarning,
  safeQuerySelector,
  setBodyOverflow,
  updateDrilldownInertState,
  updateMegaContentInertState,
} from './utils';
import { getMenuItems, getSubmenuItems } from './menu-items';
import { closePanelWithCleanup, findPanel, openPanelWithSetup } from './panel';

// ============================================================================
// Panel State Helper
// ============================================================================

/**
 * Get the mutable state for a panel instance.
 * Lazily initializes if the entry doesn't exist yet.
 */
function getPanelState(panelSlug: string): PanelState {
  const panels = panelStore.state._panels;
  if (!panels[panelSlug]) {
    panels[panelSlug] = {
      isOpen: false,
      activeSubmenuId: null,
      drillStack: [],
    };
  }
  return panels[panelSlug];
}

// ============================================================================
// Store Definition
// ============================================================================

const panelStore = store('aggressive-apparel/navigation-panel', {
  state: {
    get isOpen() {
      try {
        const { panelSlug } = getContext<PanelContext>();
        return getPanelState(panelSlug).isOpen;
      } catch {
        return false;
      }
    },
    get activeSubmenuId() {
      try {
        const { panelSlug } = getContext<PanelContext>();
        return getPanelState(panelSlug).activeSubmenuId;
      } catch {
        return null;
      }
    },
    get drillStack() {
      try {
        const { panelSlug } = getContext<PanelContext>();
        return getPanelState(panelSlug).drillStack;
      } catch {
        return [];
      }
    },

    _panels: {} as Record<string, PanelState>,
  } as NavigationPanelStoreState,

  actions: {
    /**
     * Toggle the panel open/closed.
     */
    toggle(): void {
      try {
        const context = getContext<PanelContext>();
        if (!context || !isValidPanelSlug(context.panelSlug)) {
          logWarning('toggle: Invalid context or panelSlug');
          return;
        }

        const panel = findPanel(context.panelSlug);
        if (!panel) {
          logWarning('toggle: Panel not found');
          return;
        }

        const ps = getPanelState(context.panelSlug);
        if (ps.isOpen) {
          closePanelWithCleanup(context.panelSlug, panel, ps);
        } else {
          ps.isOpen = true;
          setBodyOverflow(true);
          // Capture slug now (inside the Interactivity API context frame) so the
          // swipe callback can close the panel without needing getContext().
          const slugForSwipe = context.panelSlug;
          openPanelWithSetup(context.panelSlug, panel, ps, () => {
            const swipePanel = findPanel(slugForSwipe);
            const swipePs = getPanelState(slugForSwipe);
            if (swipePanel && swipePs.isOpen) {
              closePanelWithCleanup(slugForSwipe, swipePanel, swipePs);
            }
          });
          announce('Navigation menu opened', {
            assertive: true,
            panelSlug: context.panelSlug,
          });
        }
      } catch (error) {
        logError('toggle: Failed to toggle panel', error);
      }
    },

    /**
     * Open the panel.
     */
    open(): void {
      try {
        const context = getContext<PanelContext>();
        if (!context || !isValidPanelSlug(context.panelSlug)) {
          return;
        }

        const panel = findPanel(context.panelSlug);
        if (!panel) {
          return;
        }

        const ps = getPanelState(context.panelSlug);
        if (ps.isOpen) {
          return;
        }

        ps.isOpen = true;
        setBodyOverflow(true);
        const slugForSwipe = context.panelSlug;
        openPanelWithSetup(context.panelSlug, panel, ps, () => {
          const swipePanel = findPanel(slugForSwipe);
          const swipePs = getPanelState(slugForSwipe);
          if (swipePanel && swipePs.isOpen) {
            closePanelWithCleanup(slugForSwipe, swipePanel, swipePs);
          }
        });
        announce('Navigation menu opened', {
          assertive: true,
          panelSlug: context.panelSlug,
        });
      } catch (error) {
        logError('open: Failed to open panel', error);
      }
    },

    /**
     * Close the panel.
     */
    close(): void {
      try {
        const context = getContext<PanelContext>();
        if (!context || !isValidPanelSlug(context.panelSlug)) {
          return;
        }

        const panel = findPanel(context.panelSlug);
        if (!panel) {
          return;
        }

        const ps = getPanelState(context.panelSlug);
        closePanelWithCleanup(context.panelSlug, panel, ps);
      } catch (error) {
        logError('close: Failed to close panel', error);
      }
    },

    /**
     * Drill into a submenu (mobile drill-down navigation).
     */
    drillInto: withSyncEvent((event?: Event): void => {
      try {
        const context = getContext<PanelContext & { submenuId?: string }>();
        if (!context) {
          return;
        }

        if (event) {
          event.preventDefault();
        }

        if (!context.submenuId) {
          return;
        }

        const ps = getPanelState(context.panelSlug);
        const newStack = [...ps.drillStack, context.submenuId];
        ps.drillStack = newStack;
        window.dispatchEvent(
          new CustomEvent('aa-nav-panel-state-change', {
            detail: { panelSlug: context.panelSlug },
          })
        );

        // Get the submenu label for the announcement.
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
          panelSlug: context.panelSlug,
        });

        const panel = findPanel(context.panelSlug);
        if (panel) {
          updateDrilldownInertState(panel, newStack);
        }

        focusDrilldownPanel(context.submenuId);
      } catch (error) {
        logError('drillInto: Failed to drill into submenu', error);
      }
    }),

    /**
     * Go back one level in drill-down navigation.
     */
    drillBack(): void {
      try {
        const context = getContext<PanelContext>();
        if (!context || !isValidPanelSlug(context.panelSlug)) {
          return;
        }

        const ps = getPanelState(context.panelSlug);

        if (ps.drillStack.length === 0) {
          return;
        }

        const leavingPanelId = ps.drillStack[ps.drillStack.length - 1];
        const newStack = ps.drillStack.slice(0, -1);
        ps.drillStack = newStack;
        window.dispatchEvent(
          new CustomEvent('aa-nav-panel-state-change', {
            detail: { panelSlug: context.panelSlug },
          })
        );

        if (newStack.length === 0) {
          announce('Back to main menu', { panelSlug: context.panelSlug });
        } else {
          announce(`Back to level ${newStack.length}`, {
            panelSlug: context.panelSlug,
          });
        }

        const panel = findPanel(context.panelSlug);
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
     * Toggle a mega-content submenu overlay open/closed.
     */
    toggleSubmenu: withSyncEvent((event?: Event): void => {
      try {
        const context = getContext<
          PanelContext & { submenuId?: string; menuType?: string }
        >();
        if (!context) {
          return;
        }

        if (event) {
          event.preventDefault();
        }

        const ps = getPanelState(context.panelSlug);
        const wasOpen = ps.activeSubmenuId === context.submenuId;
        ps.activeSubmenuId = wasOpen ? null : (context.submenuId ?? null);

        // Mega-content overlays manage inert state and focus.
        if (context.menuType === 'mega') {
          const panel = findPanel(context.panelSlug);
          if (panel) {
            if (wasOpen) {
              updateMegaContentInertState(panel, null);
            } else if (context.submenuId) {
              updateMegaContentInertState(panel, context.submenuId);
              focusMegaContentPanel(context.submenuId);
            }
          }
        }

        if (wasOpen) {
          announce('Submenu closed', { panelSlug: context.panelSlug });
        } else if (ps.activeSubmenuId) {
          announce('Submenu opened', { panelSlug: context.panelSlug });
        }
      } catch (error) {
        logError('toggleSubmenu: Failed to toggle submenu', error);
      }
    }),

    /**
     * Close the active mega-content submenu overlay.
     */
    closeSubmenu(): void {
      try {
        const context = getContext<PanelContext>();
        if (!context) {
          return;
        }

        const ps = getPanelState(context.panelSlug);
        const closingSubmenuId = ps.activeSubmenuId;
        ps.activeSubmenuId = null;

        if (closingSubmenuId) {
          const panel = findPanel(context.panelSlug);
          if (panel) {
            updateMegaContentInertState(panel, null);

            const trigger = safeQuerySelector<HTMLElement>(
              panel,
              `[aria-controls="${CSS.escape(closingSubmenuId)}"]`,
              false
            );
            requestAnimationFrame(() => {
              trigger?.focus();
            });
          }
        }

        announce('Submenu closed', { panelSlug: context.panelSlug });
      } catch (error) {
        logError('closeSubmenu: Failed to close submenu', error);
      }
    },

    /**
     * Close all submenus (drill stack and mega overlay).
     */
    closeAllSubmenus(): void {
      try {
        const context = getContext<PanelContext>();
        if (!context) {
          return;
        }

        const ps = getPanelState(context.panelSlug);
        ps.activeSubmenuId = null;
        ps.drillStack = [];

        const panel = findPanel(context.panelSlug);
        if (panel) {
          updateDrilldownInertState(panel, []);
          updateMegaContentInertState(panel, null);
        }
        window.dispatchEvent(
          new CustomEvent('aa-nav-panel-state-change', {
            detail: { panelSlug: context.panelSlug },
          })
        );
      } catch (error) {
        logError('closeAllSubmenus: Failed to close submenus', error);
      }
    },
  },

  callbacks: {
    /**
     * Initialize the panel (runs on the portal wrapper element).
     * Resets state and sets up stagger indices for menu items.
     */
    initPanel(): void {
      try {
        const context = getContext<PanelContext>();
        if (!context || !isValidPanelSlug(context.panelSlug)) {
          return;
        }

        // Remove the `hidden` attribute added by PHP so the portal is visible.
        // PHP sets hidden as a no-JS fallback to prevent the panel from
        // appearing as in-flow content before CSS loads.
        const portalEl = getElement()?.ref as HTMLElement | null;
        if (portalEl?.hasAttribute('hidden')) {
          portalEl.removeAttribute('hidden');
        }

        // Reset state on load.
        const ps = getPanelState(context.panelSlug);
        ps.isOpen = false;
        ps.activeSubmenuId = null;
        ps.drillStack = [];

        const panel = findPanel(context.panelSlug);
        if (!panel) {
          return;
        }

        // Set --item-index on each panel menu item for the stagger animation.
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
     * Handle the Escape key.
     */
    onEscape(event: KeyboardEvent): void {
      if (event.key !== KEYS.escape) {
        return;
      }

      try {
        const context = getContext<PanelContext>();
        if (!context) {
          return;
        }

        const ps = getPanelState(context.panelSlug);
        const { actions } = panelStore;

        if (ps.drillStack.length > 0) {
          actions.drillBack();
        } else if (ps.activeSubmenuId) {
          actions.closeSubmenu();
        } else if (ps.isOpen) {
          actions.close();
        }
      } catch (error) {
        logError('onEscape: Failed to handle escape key', error);
      }
    },

    /**
     * Handle vertical arrow key navigation inside the panel.
     */
    onArrowKey(event: KeyboardEvent): void {
      try {
        const context = getContext<PanelContext>();
        if (!context) {
          return;
        }

        const ps = getPanelState(context.panelSlug);

        const key = event.key;
        if (!ARROW_KEYS.includes(key as (typeof ARROW_KEYS)[number])) {
          return;
        }

        const activeElement = document.activeElement as HTMLElement | null;
        if (!activeElement) {
          return;
        }

        const submenuPanel = activeElement.closest(SELECTORS.submenuPanel);
        const panelMenu = activeElement.closest(SELECTORS.panelMenu);
        const isInSubmenu = !!submenuPanel;

        let items: HTMLElement[] = [];
        if (isInSubmenu && submenuPanel) {
          items = getSubmenuItems(submenuPanel);
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
          case KEYS.arrowDown:
            focusMenuItem(items, (currentIndex + 1) % items.length);
            break;

          case KEYS.arrowUp: {
            const isInDrilldown = activeElement.closest(
              SELECTORS.submenuDrilldown
            );
            if (
              isInDrilldown &&
              currentIndex === 0 &&
              ps.drillStack.length > 0
            ) {
              panelStore.actions.drillBack();
            } else {
              focusMenuItem(
                items,
                (currentIndex - 1 + items.length) % items.length
              );
            }
            break;
          }

          case KEYS.arrowRight:
            if (isInSubmenu) {
              break;
            }
            // On a submenu trigger, open/drill into it.
            if (activeElement.closest(SELECTORS.navSubmenu)) {
              panelStore.actions.drillInto();
            }
            break;

          case KEYS.arrowLeft:
            if (isInSubmenu && ps.drillStack.length > 0) {
              panelStore.actions.drillBack();
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
     * Sync the open/closed class on drilldown submenus when the stack changes.
     * Required because the shared state registry isn't reactive across the
     * portal boundary for these class bindings.
     */
    onSubmenuStateChange(event: CustomEvent<{ panelSlug: string }>): void {
      try {
        const context = getContext<
          PanelContext & { submenuId?: string; menuType?: string }
        >();
        if (!context || event.detail?.panelSlug !== context.panelSlug) {
          return;
        }
        if (context.menuType !== 'drilldown') {
          return;
        }

        const element = getElement();
        if (!element?.ref) {
          return;
        }

        const ps = getPanelState(context.panelSlug);
        const isInStack = ps.drillStack.includes(context.submenuId ?? '');
        element.ref.classList.toggle(SELECTORS.isOpen, isInStack);
      } catch (error) {
        logError('onSubmenuStateChange: Failed to handle state change', error);
      }
    },

    /**
     * Whether the panel has any drill history (back navigation available).
     */
    hasDrillHistory(): boolean {
      try {
        const context = getContext<PanelContext>();
        if (!context) {
          return false;
        }
        return getPanelState(context.panelSlug).drillStack.length > 0;
      } catch {
        return false;
      }
    },

    /**
     * Whether this submenu is in the current drill stack.
     */
    isInDrillStack(): boolean {
      try {
        const context = getContext<PanelContext & { submenuId?: string }>();
        if (!context) {
          return false;
        }
        return getPanelState(context.panelSlug).drillStack.includes(
          context.submenuId ?? ''
        );
      } catch {
        return false;
      }
    },

    /**
     * Whether this submenu is the current (deepest) drill level.
     */
    isCurrentDrillLevel(): boolean {
      try {
        const context = getContext<PanelContext & { submenuId?: string }>();
        if (!context) {
          return false;
        }
        const ps = getPanelState(context.panelSlug);
        return ps.drillStack[ps.drillStack.length - 1] === context.submenuId;
      } catch {
        return false;
      }
    },

    /**
     * Whether this mega-content submenu overlay is open.
     */
    isSubmenuOpen(): boolean {
      try {
        const context = getContext<PanelContext & { submenuId?: string }>();
        if (!context) {
          return false;
        }
        return (
          getPanelState(context.panelSlug).activeSubmenuId ===
          context.submenuId
        );
      } catch {
        return false;
      }
    },
  },
});

export default panelStore;
