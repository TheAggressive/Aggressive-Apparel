/**
 * Navigation Block System — Interactivity Store
 *
 * IMPORTANT: This is the ONLY file that defines the navigation store.
 * All other navigation blocks import from here. Never extend or redefine the store elsewhere.
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
  EVENTS,
  HOVER_INTENT,
  KEYS,
  PANEL_MENU_ITEM_SELECTOR,
  SELECTORS,
  SUBMENU_ITEM_SELECTOR,
  TOP_LEVEL_MENU_ITEM_SELECTOR,
} from './constants';
import type { NavigationContext, NavigationState } from './types';
import {
  addBodyClass,
  announce,
  clearHoverTimeouts,
  createHoverIntent,
  focusMenuItem,
  generateNavId,
  getPanelId,
  getTransitionDuration,
  isValidNavId,
  logError,
  logWarning,
  prefersReducedMotion,
  removeAllBodyClasses,
  restoreFocus,
  safeGetElementById,
  safeQuerySelector,
  safeQuerySelectorAll,
  setBodyOverflow,
  setPanelVisibility,
  setupFocusTrap,
  type HoverIntentState,
} from './utils';

// ============================================================================
// Module State
// ============================================================================

// Hover intent state (prevents race conditions).
const hoverIntent: HoverIntentState = createHoverIntent();

// Focus trap cleanup registry using WeakMap for proper garbage collection.
const focusTrapRegistry = new WeakMap<Element, () => void>();

// MediaQueryList reference for cleanup.
interface MediaQueryRegistryEntry {
  mql: MediaQueryList;
  handler: (event: MediaQueryListEvent | MediaQueryList) => void;
}
const mediaQueryRegistry = new WeakMap<Element, MediaQueryRegistryEntry>();

// ============================================================================
// Shared State Registry
// ============================================================================

// This allows the navigation and its panel (which may be separate DOM elements) to share state.
interface SharedNavState {
  isOpen: boolean;
  isMobile: boolean;
  activeSubmenuId: string | null;
  drillStack: string[];
}

const sharedStateRegistry = new Map<string, SharedNavState>();

/** Default shared state values. */
const DEFAULT_SHARED_STATE: SharedNavState = {
  isOpen: false,
  isMobile: false,
  activeSubmenuId: null,
  drillStack: [],
};

/**
 * Get or create shared state for a navigation instance.
 */
function getSharedState(navId: string): SharedNavState {
  if (!isValidNavId(navId)) {
    logWarning('getSharedState: Invalid navId provided', { navId });
    return { ...DEFAULT_SHARED_STATE };
  }

  if (!sharedStateRegistry.has(navId)) {
    sharedStateRegistry.set(navId, { ...DEFAULT_SHARED_STATE });
  }
  return sharedStateRegistry.get(navId)!;
}

/**
 * Reset navigation state to defaults.
 * Updates both shared state and local context.
 */
function resetNavigationState(
  shared: SharedNavState,
  context: NavigationContext,
  options: { preserveIsMobile?: boolean } = {}
): void {
  const isMobile = options.preserveIsMobile ? shared.isMobile : false;

  shared.isOpen = false;
  shared.isMobile = isMobile;
  shared.activeSubmenuId = null;
  shared.drillStack = [];

  context.isOpen = false;
  context.isMobile = isMobile;
  context.activeSubmenuId = null;
  context.drillStack = [];
}

/**
 * Sync context values from shared state.
 */
function syncContextFromShared(
  context: NavigationContext,
  shared: SharedNavState
): void {
  context.isOpen = shared.isOpen;
  context.isMobile = shared.isMobile;
  context.activeSubmenuId = shared.activeSubmenuId;
  context.drillStack = shared.drillStack;
}

/**
 * Get a value from shared state or fall back to context.
 */
function getSharedOrContextValue<K extends keyof SharedNavState>(
  context: NavigationContext,
  key: K
): SharedNavState[K] {
  return isValidNavId(context.navId)
    ? getSharedState(context.navId)[key]
    : context[key];
}

/**
 * Sync shared state to all elements with the same navId.
 * Dispatches a custom event synchronously to avoid race conditions.
 */
function syncSharedStateToElements(navId: string): void {
  if (!isValidNavId(navId)) {
    return;
  }

  // Dispatch synchronously to avoid race conditions.
  // Using requestAnimationFrame only for DOM updates that need it.
  window.dispatchEvent(
    new CustomEvent(EVENTS.stateChange, {
      detail: { navId },
    })
  );
}

// ============================================================================
// Menu Item Helpers
// ============================================================================

/**
 * Get all focusable menu items within a menu container.
 */
function getMenuItems(container: Element): HTMLElement[] {
  return safeQuerySelectorAll<HTMLElement>(
    container,
    TOP_LEVEL_MENU_ITEM_SELECTOR
  );
}

/**
 * Get all focusable items within a submenu panel.
 */
function getSubmenuItems(panel: Element): HTMLElement[] {
  return safeQuerySelectorAll<HTMLElement>(panel, SUBMENU_ITEM_SELECTOR);
}

// ============================================================================
// Store Definition
// ============================================================================

const navigationStore = store('aggressive-apparel/navigation', {
  state: {
    // State getters read from context (which is synced via onStateChange).
    // This ensures reactivity works with the Interactivity API.
    get isOpen() {
      try {
        const context = getContext<NavigationContext>();
        return context?.isOpen ?? false;
      } catch {
        return false;
      }
    },
    get isMobile() {
      try {
        const context = getContext<NavigationContext>();
        return context?.isMobile ?? false;
      } catch {
        return false;
      }
    },
    get activeSubmenuId() {
      try {
        const context = getContext<NavigationContext>();
        return context?.activeSubmenuId ?? null;
      } catch {
        return null;
      }
    },
    get drillStack() {
      try {
        const context = getContext<NavigationContext>();
        return context?.drillStack ?? [];
      } catch {
        return [];
      }
    },

    announcement: '',
  } as NavigationState,

  actions: {
    /**
     * Toggle mobile panel open/closed.
     */
    toggle(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          logWarning('toggle: No context available');
          return;
        }

        if (!isValidNavId(context.navId)) {
          logWarning('toggle: Invalid navId', { navId: context.navId });
          return;
        }

        const shared = getSharedState(context.navId);
        const willBeOpen = !shared.isOpen;

        if (willBeOpen) {
          shared.isOpen = true;
          context.isOpen = true;
          setBodyOverflow(true);
          announce('Navigation menu opened', {
            assertive: true,
            navId: context.navId,
          });
        } else {
          resetNavigationState(shared, context, { preserveIsMobile: true });
          setBodyOverflow(false);
          announce('Navigation menu closed', {
            assertive: true,
            navId: context.navId,
          });
        }

        syncSharedStateToElements(context.navId);
      } catch (error) {
        logError('toggle: Failed to toggle navigation', error);
      }
    },

    /**
     * Open mobile panel.
     */
    open(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context || !isValidNavId(context.navId)) {
          logWarning('open: Invalid context or navId');
          return;
        }

        const shared = getSharedState(context.navId);
        shared.isOpen = true;
        context.isOpen = true;
        setBodyOverflow(true);

        syncSharedStateToElements(context.navId);
      } catch (error) {
        logError('open: Failed to open navigation', error);
      }
    },

    /**
     * Close mobile panel and reset state.
     */
    close(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context || !isValidNavId(context.navId)) {
          logWarning('close: Invalid context or navId');
          return;
        }

        const shared = getSharedState(context.navId);
        resetNavigationState(shared, context, { preserveIsMobile: true });
        setBodyOverflow(false);
        announce('Navigation menu closed', {
          assertive: true,
          navId: context.navId,
        });

        syncSharedStateToElements(context.navId);
        restoreFocus(context.navId);
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

        clearHoverTimeouts(hoverIntent);

        const submenuId = context.submenuId ?? null;
        if (isValidNavId(context.navId)) {
          const shared = getSharedState(context.navId);
          shared.activeSubmenuId = submenuId;
        }
        context.activeSubmenuId = submenuId;

        if (submenuId) {
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

        clearHoverTimeouts(hoverIntent);

        if (isValidNavId(context.navId)) {
          const shared = getSharedState(context.navId);
          shared.activeSubmenuId = null;
        }
        context.activeSubmenuId = null;
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
          NavigationContext & { submenuId?: string }
        >();
        if (!context) {
          return;
        }

        // Prevent default link navigation when toggling submenu.
        if (event) {
          event.preventDefault();
        }

        clearHoverTimeouts(hoverIntent);

        const currentActiveId = isValidNavId(context.navId)
          ? getSharedState(context.navId).activeSubmenuId
          : context.activeSubmenuId;

        const wasOpen = currentActiveId === context.submenuId;
        const newActiveId = wasOpen ? null : (context.submenuId ?? null);

        if (isValidNavId(context.navId)) {
          const shared = getSharedState(context.navId);
          shared.activeSubmenuId = newActiveId;
        }
        context.activeSubmenuId = newActiveId;

        // Announce state change.
        if (wasOpen) {
          announce('Submenu closed', { navId: context.navId });
        } else if (newActiveId) {
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
          const currentStack = isValidNavId(context.navId)
            ? getSharedState(context.navId).drillStack
            : context.drillStack;
          const newStack = [...currentStack, context.submenuId];

          if (isValidNavId(context.navId)) {
            const shared = getSharedState(context.navId);
            shared.drillStack = newStack;
          }
          context.drillStack = newStack;

          // Get the submenu label from the clicked element for announcement.
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

          // Announce with context and drill depth.
          const depth = newStack.length;
          announce(`Opened ${submenuLabel}, level ${depth}`, {
            navId: context.navId,
          });

          syncSharedStateToElements(context.navId);
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
        if (!context) {
          return;
        }

        const currentStack = isValidNavId(context.navId)
          ? getSharedState(context.navId).drillStack
          : context.drillStack;
        const newStack = currentStack.slice(0, -1);

        if (isValidNavId(context.navId)) {
          const shared = getSharedState(context.navId);
          shared.drillStack = newStack;
        }
        context.drillStack = newStack;

        // Announce navigation back.
        if (newStack.length === 0) {
          announce('Back to main menu', { navId: context.navId });
        } else {
          announce(`Back to level ${newStack.length}`, {
            navId: context.navId,
          });
        }

        syncSharedStateToElements(context.navId);
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

        clearHoverTimeouts(hoverIntent);

        if (isValidNavId(context.navId)) {
          const shared = getSharedState(context.navId);
          shared.activeSubmenuId = null;
        }
        context.activeSubmenuId = null;
      } catch (error) {
        logError('closeAllSubmenus: Failed to close submenus', error);
      }
    },
  },

  callbacks: {
    /**
     * Initialize navigation on mount.
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

        const shared = getSharedState(context.navId);

        // Reset state on load.
        resetNavigationState(shared, context);
        setBodyOverflow(false);

        if (!element?.ref) {
          logWarning('init: No element reference available');
          return;
        }

        // Use matchMedia for efficient breakpoint detection.
        const mql = window.matchMedia(`(max-width: ${breakpoint - 1}px)`);

        const handler = (e: MediaQueryListEvent | MediaQueryList) => {
          const wasMobile = context.isMobile;
          context.isMobile = e.matches;
          shared.isMobile = e.matches;

          // Reset state when switching to desktop.
          if (wasMobile && !context.isMobile) {
            resetNavigationState(shared, context, { preserveIsMobile: true });
            setBodyOverflow(false);
          }

          syncSharedStateToElements(context.navId);
        };

        // Set initial state and listen for changes.
        handler(mql);
        mql.addEventListener('change', handler);

        // Store cleanup info.
        mediaQueryRegistry.set(element.ref, { mql, handler });
      } catch (error) {
        logError('init: Failed to initialize navigation', error);
      }
    },

    /**
     * Handle Escape key to close navigation elements.
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

        const { actions } = navigationStore;

        // Close in order: drill stack → submenu → panel.
        if (context.drillStack.length > 0) {
          actions.drillBack();
        } else if (context.activeSubmenuId) {
          actions.closeSubmenu();
          announce('Submenu closed', { navId: context.navId });
        } else if (context.isOpen) {
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

        const key = event.key;

        if (!ARROW_KEYS.includes(key as (typeof ARROW_KEYS)[number])) {
          return;
        }

        const activeElement = document.activeElement as HTMLElement | null;
        if (!activeElement) {
          return;
        }

        // Determine if we're in a horizontal menu (top-level) or vertical menu (submenu/panel).
        const submenuPanel = activeElement.closest(SELECTORS.submenuPanel);
        const navMenu = activeElement.closest(SELECTORS.navMenu);
        const isInSubmenu = !!submenuPanel;
        const isHorizontal =
          navMenu?.classList.contains(SELECTORS.menuHorizontal) ?? true;

        // Get the appropriate item list.
        let items: HTMLElement[] = [];
        if (isInSubmenu && submenuPanel) {
          items = getSubmenuItems(submenuPanel);
        } else if (navMenu) {
          items = getMenuItems(navMenu);
        }

        if (items.length === 0) {
          return;
        }

        // Find current index.
        const currentIndex = items.indexOf(activeElement);
        if (currentIndex === -1) {
          return;
        }

        event.preventDefault();

        switch (key) {
          case KEYS.arrowRight:
            if (isHorizontal && !isInSubmenu) {
              // Move to next item in horizontal menu.
              const nextIndex = (currentIndex + 1) % items.length;
              focusMenuItem(items, nextIndex);
            } else if (isInSubmenu) {
              // Open nested submenu if present.
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
              // Move to previous item in horizontal menu.
              const prevIndex =
                (currentIndex - 1 + items.length) % items.length;
              focusMenuItem(items, prevIndex);
            } else if (isInSubmenu) {
              // Close submenu and return focus to parent.
              navigationStore.actions.closeSubmenu();
              announce('Submenu closed', { navId: context.navId });
              // Focus should return to the trigger that opened this submenu.
              const parentSubmenu = submenuPanel?.closest(SELECTORS.navSubmenu);
              if (parentSubmenu) {
                const trigger = safeQuerySelector<HTMLElement>(
                  parentSubmenu,
                  SELECTORS.submenuLink,
                  false
                );
                if (trigger) {
                  trigger.focus();
                }
              }
            }
            break;

          case KEYS.arrowDown:
            if (isInSubmenu || !isHorizontal) {
              // Move to next item in vertical menu/submenu.
              const nextIndex = (currentIndex + 1) % items.length;
              focusMenuItem(items, nextIndex);
            } else {
              // Open submenu if on a submenu trigger.
              const submenuTrigger = activeElement.closest(
                SELECTORS.navSubmenu
              );
              if (submenuTrigger) {
                // Check if submenu is not already open.
                const panel = safeQuerySelector<HTMLElement>(
                  submenuTrigger,
                  SELECTORS.submenuPanel,
                  false
                );
                if (panel && context.activeSubmenuId !== panel.id) {
                  navigationStore.actions.openSubmenu();
                  announce('Submenu opened', { navId: context.navId });
                  // Focus first item in submenu after a brief delay.
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
              // In drill-down mode, pressing ArrowUp on first item goes back.
              const isInDrilldown = activeElement.closest(
                SELECTORS.submenuDrilldown
              );
              if (
                isInDrilldown &&
                currentIndex === 0 &&
                context.drillStack.length > 0
              ) {
                navigationStore.actions.drillBack();
              } else {
                // Move to previous item in vertical menu/submenu.
                const prevIndex =
                  (currentIndex - 1 + items.length) % items.length;
                focusMenuItem(items, prevIndex);
              }
            }
            break;

          case KEYS.home:
            // Jump to first item.
            focusMenuItem(items, 0);
            break;

          case KEYS.end:
            // Jump to last item.
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

        // Check isMobile from shared state.
        const isMobile = isValidNavId(context.navId)
          ? getSharedState(context.navId).isMobile
          : context.isMobile;

        // Skip hover behavior on mobile or if openOn is click.
        if (isMobile || context.openOn !== 'hover') {
          return;
        }

        // Clear any pending close timeout.
        if (hoverIntent.closeTimeout) {
          clearTimeout(hoverIntent.closeTimeout);
          hoverIntent.closeTimeout = null;
        }

        // Set a small delay before opening (hover intent).
        const openDelay = prefersReducedMotion()
          ? HOVER_INTENT.reducedMotion.openDelay
          : HOVER_INTENT.openDelay;

        hoverIntent.openTimeout = setTimeout(() => {
          // Re-check context validity inside timeout
          try {
            const submenuId = context.submenuId ?? null;
            if (isValidNavId(context.navId)) {
              const shared = getSharedState(context.navId);
              shared.activeSubmenuId = submenuId;
            }
            context.activeSubmenuId = submenuId;
            hoverIntent.activeId = submenuId;
          } catch {
            // Context may no longer be valid
          }
        }, openDelay);
      } catch (error) {
        logError('onHoverEnter: Failed to handle hover enter', error);
      }
    },

    /**
     * Handle hover/focus leave with delay to prevent flicker.
     */
    onHoverLeave: withSyncEvent((event: MouseEvent | FocusEvent): void => {
      try {
        const context = getContext<
          NavigationContext & { submenuId?: string }
        >();
        if (!context) {
          return;
        }

        // Check isMobile from shared state.
        const isMobile = isValidNavId(context.navId)
          ? getSharedState(context.navId).isMobile
          : context.isMobile;

        // Skip hover behavior on mobile or if openOn is click.
        if (isMobile || context.openOn !== 'hover') {
          return;
        }

        // Check if moving within the same submenu.
        const relatedTarget = event.relatedTarget as HTMLElement | null;
        const currentTarget = event.currentTarget as HTMLElement;
        const submenuContainer = currentTarget.closest(SELECTORS.navSubmenu);

        if (submenuContainer?.contains(relatedTarget)) {
          return;
        }

        // Clear any pending open timeout.
        if (hoverIntent.openTimeout) {
          clearTimeout(hoverIntent.openTimeout);
          hoverIntent.openTimeout = null;
        }

        // Delay closing to allow moving to submenu panel.
        const closeDelay = prefersReducedMotion()
          ? HOVER_INTENT.reducedMotion.closeDelay
          : HOVER_INTENT.closeDelay;

        hoverIntent.closeTimeout = setTimeout(() => {
          try {
            const currentActiveId = isValidNavId(context.navId)
              ? getSharedState(context.navId).activeSubmenuId
              : context.activeSubmenuId;

            if (currentActiveId === context.submenuId) {
              if (isValidNavId(context.navId)) {
                const shared = getSharedState(context.navId);
                shared.activeSubmenuId = null;
              }
              context.activeSubmenuId = null;
              hoverIntent.activeId = null;
            }
          } catch {
            // Context may no longer be valid
          }
        }, closeDelay);
      } catch (error) {
        logError('onHoverLeave: Failed to handle hover leave', error);
      }
    }),

    /**
     * Handle native Popover API toggle events.
     * Syncs popover state with Interactivity API state.
     * This allows native popover behavior (light-dismiss, focus management)
     * while keeping state in sync with our store.
     */
    onPopoverToggle(event: ToggleEvent): void {
      try {
        const context = getContext<
          NavigationContext & { submenuId?: string }
        >();
        if (!context) {
          return;
        }

        const isOpening = event.newState === 'open';
        const submenuId = context.submenuId;

        if (isOpening) {
          // Sync opening state with shared registry
          if (isValidNavId(context.navId) && submenuId) {
            const shared = getSharedState(context.navId);
            shared.activeSubmenuId = submenuId;
          }
          if (submenuId) {
            context.activeSubmenuId = submenuId;
            hoverIntent.activeId = submenuId;
          }
          announce('Submenu opened', { navId: context.navId });
        } else {
          // Sync closing state - only clear if this is the currently active submenu
          if (isValidNavId(context.navId) && submenuId) {
            const shared = getSharedState(context.navId);
            if (shared.activeSubmenuId === submenuId) {
              shared.activeSubmenuId = null;
            }
          }
          if (context.activeSubmenuId === submenuId) {
            context.activeSubmenuId = null;
            hoverIntent.activeId = null;
          }
          announce('Submenu closed', { navId: context.navId });
        }

        // Clear any pending hover timeouts since popover handled the interaction
        clearHoverTimeouts(hoverIntent);
      } catch (error) {
        logError('onPopoverToggle: Failed to handle popover toggle', error);
      }
    },

    /**
     * Initialize panel and sync with navigation's shared state.
     */
    initPanel(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          logWarning('initPanel: No context available');
          return;
        }

        if (!isValidNavId(context.navId)) {
          logWarning('initPanel: Invalid navId');
          return;
        }

        syncContextFromShared(context, getSharedState(context.navId));
      } catch (error) {
        logError('initPanel: Failed to initialize panel', error);
      }
    },

    /**
     * Handle state sync event from other navigation elements.
     */
    onStateChange(event: CustomEvent<{ navId: string }>): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          return;
        }

        // Only sync if this element belongs to the same navigation.
        if (event.detail?.navId !== context.navId) {
          return;
        }

        const element = getElement();
        const wasOpen = context.isOpen;
        const shared = getSharedState(context.navId);

        // Sync context with shared state.
        syncContextFromShared(context, shared);

        // Handle panel-specific updates.
        if (
          element?.ref?.classList.contains(SELECTORS.navigationPanel.slice(1))
        ) {
          const panel = element.ref as HTMLElement;

          // Manage panel visibility with animation.
          setPanelVisibility(panel, context.isOpen);

          // Manage back button visibility.
          const backButton = safeQuerySelector<HTMLButtonElement>(
            panel,
            SELECTORS.panelBack,
            false
          );
          if (backButton) {
            backButton.hidden = shared.drillStack.length === 0;
          }

          // Manage body classes for push/reveal animations.
          if (wasOpen !== context.isOpen) {
            const animationStyle =
              panel.getAttribute('data-animation-style') || 'slide';
            const position = panel.getAttribute('data-position') || 'right';

            if (
              context.isOpen &&
              (animationStyle === 'push' || animationStyle === 'reveal')
            ) {
              const panelWidth =
                window
                  .getComputedStyle(panel)
                  .getPropertyValue('--panel-width')
                  .trim() || 'min(320px, 85vw)';
              document.body.style.setProperty('--push-panel-width', panelWidth);
              addBodyClass(animationStyle, position);
            } else if (!context.isOpen) {
              removeAllBodyClasses();

              // Clean up CSS variable after transition completes.
              setTimeout(() => {
                document.body.style.removeProperty('--push-panel-width');
              }, getTransitionDuration());
            }
          }
        }
      } catch (error) {
        logError('onStateChange: Failed to handle state change', error);
      }
    },

    /**
     * Watch panel state for focus trap AND body classes (push/reveal animations).
     */
    watchPanelState(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          return;
        }

        // Sync context with shared state for reactive bindings.
        if (isValidNavId(context.navId)) {
          syncContextFromShared(context, getSharedState(context.navId));
        }

        const isOpen = context.isOpen;
        const panelId = getPanelId(context.navId);
        const panel = safeGetElementById(panelId, false);

        if (!panel) {
          return;
        }

        const animationStyle =
          panel.getAttribute('data-animation-style') || 'slide';
        const position = panel.getAttribute('data-position') || 'right';

        // Check if native inert attribute is supported (better focus management).
        const supportsInert =
          typeof HTMLElement !== 'undefined' &&
          'inert' in HTMLElement.prototype;

        if (supportsInert) {
          // Use native inert attribute for focus management.
          // Apply inert to main content when panel is open.
          const mainContent = document.querySelector(
            '.wp-site-blocks'
          ) as HTMLElement | null;
          if (mainContent) {
            mainContent.inert = isOpen;
          }

          // Focus first focusable element in panel when opening.
          // Use double rAF to ensure aria-hidden binding has been updated
          // before moving focus (prevents "Blocked aria-hidden" console error).
          if (isOpen) {
            requestAnimationFrame(() => {
              requestAnimationFrame(() => {
                const firstFocusable = safeQuerySelector<HTMLElement>(
                  panel,
                  PANEL_MENU_ITEM_SELECTOR,
                  false
                );
                firstFocusable?.focus();
              });
            });
          }
        } else {
          // Fallback: use custom focus trap for older browsers.
          const existingCleanup = focusTrapRegistry.get(panel);
          if (existingCleanup) {
            existingCleanup();
            focusTrapRegistry.delete(panel);
          }

          if (isOpen) {
            const cleanup = setupFocusTrap(panel);
            focusTrapRegistry.set(panel, cleanup);
          }
        }

        panel.classList.toggle(SELECTORS.isOpen, isOpen);

        // Manage body classes for push/reveal animations.
        removeAllBodyClasses();

        if (
          isOpen &&
          (animationStyle === 'push' || animationStyle === 'reveal')
        ) {
          const panelWidth =
            window
              .getComputedStyle(panel)
              .getPropertyValue('--panel-width')
              .trim() || 'min(320px, 85vw)';
          document.body.style.setProperty('--push-panel-width', panelWidth);
          addBodyClass(animationStyle, position);
        } else {
          document.body.style.removeProperty('--push-panel-width');
        }
      } catch (error) {
        logError('watchPanelState: Failed to watch panel state', error);
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
        return (
          getSharedOrContextValue(context, 'activeSubmenuId') ===
          context.submenuId
        );
      } catch {
        return false;
      }
    },

    /**
     * Check if drill-down has history (for back button visibility).
     */
    hasDrillHistory(): boolean {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          return false;
        }
        return getSharedOrContextValue(context, 'drillStack').length > 0;
      } catch {
        return false;
      }
    },

    /**
     * Get the current drill level menu ID.
     */
    getCurrentDrillId(): string | undefined {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          return undefined;
        }
        const drillStack = getSharedOrContextValue(context, 'drillStack');
        return drillStack[drillStack.length - 1];
      } catch {
        return undefined;
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
        return getSharedOrContextValue(context, 'drillStack').includes(
          context.submenuId ?? ''
        );
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
        const drillStack = getSharedOrContextValue(context, 'drillStack');
        return drillStack[drillStack.length - 1] === context.submenuId;
      } catch {
        return false;
      }
    },

    /**
     * Get the drill depth for animation offset.
     */
    getDrillDepth(): number {
      try {
        const context = getContext<NavigationContext>();
        if (!context) {
          return 0;
        }
        return getSharedOrContextValue(context, 'drillStack').length;
      } catch {
        return 0;
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
        if (!context) {
          return;
        }

        // Only process if navId matches and this is a drilldown submenu.
        if (event.detail?.navId !== context.navId) {
          return;
        }
        if (context.menuType !== 'drilldown') {
          return;
        }

        const element = getElement();
        if (!element?.ref) {
          return;
        }

        // Get the drillStack from shared state.
        const shared = getSharedState(context.navId);
        const isInStack = shared.drillStack.includes(context.submenuId ?? '');

        // Manually toggle is-open class.
        element.ref.classList.toggle(SELECTORS.isOpen, isInStack);
      } catch (error) {
        logError(
          'onSubmenuStateChange: Failed to handle submenu state change',
          error
        );
      }
    },
  },
});

export default navigationStore;
export { generateNavId };
