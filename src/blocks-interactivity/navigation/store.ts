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
  focusDrilldownPanel,
  focusDrilldownTrigger,
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
  updateDrilldownInertState,
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

// Event delegation handlers registry for cleanup.
interface EventDelegationEntry {
  panelClickHandler: (e: MouseEvent) => void;
  stateChangeHandler: (e: Event) => void;
}
const eventDelegationRegistry = new WeakMap<Element, EventDelegationEntry>();

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

// IMPORTANT: Use a global registry on window to ensure all bundled modules share the same state.
// Each block (navigation, menu-toggle, navigation-panel) is bundled separately, so module-level
// variables like `const registry = new Map()` would create separate instances per bundle.
// By attaching to window, all bundles share the same registry.
declare global {
  interface Window {
    __aaNavSharedStateRegistry?: Map<string, SharedNavState>;
  }
}

/**
 * Get the global shared state registry, creating it if needed.
 */
function getSharedStateRegistry(): Map<string, SharedNavState> {
  if (!window.__aaNavSharedStateRegistry) {
    window.__aaNavSharedStateRegistry = new Map<string, SharedNavState>();
  }
  return window.__aaNavSharedStateRegistry;
}

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

  const registry = getSharedStateRegistry();

  if (!registry.has(navId)) {
    registry.set(navId, { ...DEFAULT_SHARED_STATE });
  }
  return registry.get(navId)!;
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
// Panel Close/DrillBack Helpers
// ============================================================================
// These functions handle panel operations that need to work both from
// Interactivity API actions (with context) and from event delegation (without).

/**
 * Close the navigation panel with full cleanup.
 * Used by both event delegation and Interactivity API actions.
 */
function closePanelWithCleanup(
  navId: string,
  panel: HTMLElement,
  shared: SharedNavState,
  context: NavigationContext
): void {
  // Reset navigation state.
  resetNavigationState(shared, context, { preserveIsMobile: true });
  setBodyOverflow(false);

  // Trigger panel close animation first (removes .is-open class).
  // This starts the CSS transitions for the panel sliding out.
  setPanelVisibility(panel, false);

  // Remove body classes for push/reveal animations.
  // This happens immediately so .wp-site-blocks animates back simultaneously.
  removeAllBodyClasses();

  // Clean up focus trap.
  const existingCleanup = focusTrapRegistry.get(panel);
  if (existingCleanup) {
    existingCleanup();
    focusTrapRegistry.delete(panel);
  }

  // Remove inert from main content.
  const supportsInert =
    typeof HTMLElement !== 'undefined' && 'inert' in HTMLElement.prototype;
  if (supportsInert) {
    const mainContent = document.querySelector(
      '.wp-site-blocks'
    ) as HTMLElement | null;
    if (mainContent) {
      mainContent.inert = false;
    }
  }

  // Reset drilldown panels.
  updateDrilldownInertState(panel, []);

  // Clean up CSS variable after transition completes.
  setTimeout(() => {
    document.body.style.removeProperty('--push-panel-width');
  }, getTransitionDuration());

  // Announce and sync.
  announce('Navigation menu closed', { assertive: true, navId });
  syncSharedStateToElements(navId);
  restoreFocus(navId);
}

/**
 * Open the navigation panel with full setup.
 * Used by event delegation for portal-rendered panels.
 */
function openPanelWithSetup(
  navId: string,
  panel: HTMLElement,
  shared: SharedNavState
): void {
  // Get animation settings first.
  const animationStyle = panel.getAttribute('data-animation-style') || 'slide';
  const position = panel.getAttribute('data-position') || 'right';

  // Set body classes for push/reveal BEFORE triggering panel animation.
  // This ensures .wp-site-blocks has the body class so it can animate.
  if (animationStyle === 'push' || animationStyle === 'reveal') {
    const panelWidth =
      window.getComputedStyle(panel).getPropertyValue('--panel-width').trim() ||
      'min(320px, 85vw)';
    document.body.style.setProperty('--push-panel-width', panelWidth);
    addBodyClass(animationStyle, position);
  }

  // Set up panel visibility (triggers CSS animations via .is-open class).
  // Note: setPanelVisibility already adds .is-open, no need for redundant call.
  setPanelVisibility(panel, true);

  // Set up focus trap.
  const existingCleanup = focusTrapRegistry.get(panel);
  if (existingCleanup) {
    existingCleanup();
    focusTrapRegistry.delete(panel);
  }
  const cleanup = setupFocusTrap(panel);
  focusTrapRegistry.set(panel, cleanup);

  // Apply inert to main content.
  const supportsInert =
    typeof HTMLElement !== 'undefined' && 'inert' in HTMLElement.prototype;
  if (supportsInert) {
    const mainContent = document.querySelector(
      '.wp-site-blocks'
    ) as HTMLElement | null;
    if (mainContent) {
      mainContent.inert = true;
    }
  }

  // Initialize inert state for drilldown panels.
  updateDrilldownInertState(panel, shared.drillStack);

  // Focus first focusable element in panel.
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

/**
 * Perform drill back navigation with full cleanup.
 * Used by both event delegation and Interactivity API actions.
 */
function performDrillBack(
  navId: string,
  panel: HTMLElement,
  shared: SharedNavState,
  context: NavigationContext
): void {
  const currentStack = shared.drillStack;

  if (currentStack.length === 0) {
    return; // Nothing to drill back from.
  }

  // Get the ID of the panel we're leaving (for focus restoration).
  const leavingPanelId = currentStack[currentStack.length - 1];
  const newStack = currentStack.slice(0, -1);

  // Update shared state and context.
  shared.drillStack = newStack;
  context.drillStack = newStack;

  // Announce navigation back.
  if (newStack.length === 0) {
    announce('Back to main menu', { navId });
  } else {
    announce(`Back to level ${newStack.length}`, { navId });
  }

  syncSharedStateToElements(navId);

  // Update inert state on drilldown panels.
  updateDrilldownInertState(panel, newStack);

  // Focus the trigger that opened the panel we just left.
  if (leavingPanelId) {
    focusDrilldownTrigger(panel, leavingPanelId);
  }
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

          // Update inert state on drilldown panels to trap focus in current level.
          const panelId = getPanelId(context.navId);
          const panel = safeGetElementById(panelId, false);
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

        const panelId = getPanelId(context.navId);
        const panel = safeGetElementById(panelId, false);
        if (!panel) {
          return;
        }

        performDrillBack(
          context.navId,
          panel,
          getSharedState(context.navId),
          context
        );
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

        // ================================================================
        // Event delegation for portal-rendered panel
        // ================================================================
        // The navigation-panel is rendered via wp_footer (outside the normal
        // block tree) for push/reveal animations. Because the Interactivity API
        // initializes on DOMContentLoaded before wp_footer runs, the panel's
        // data-wp-on--click directives are never processed.
        //
        // We use event delegation to handle clicks on panel buttons.
        // This listener is scoped to this navigation instance via navId.
        const panelId = getPanelId(context.navId);

        const handlePanelClick = (e: MouseEvent) => {
          const target = e.target as HTMLElement;
          if (!target) return;

          // Only handle clicks within this navigation's panel.
          const panel = target.closest(`#${CSS.escape(panelId)}`);
          if (!panel) return;

          // Check for close button or overlay click.
          const closeButton = target.closest(SELECTORS.panelClose);
          const overlay = target.closest(SELECTORS.panelOverlay);
          // Check for drilldown header click (the entire header is a clickable back button).
          const drilldownHeader = target.closest(SELECTORS.drilldownHeader);

          // Check for drilldown trigger click.
          // Only handle triggers inside drilldown-type submenus.
          const drilldownSubmenu = target.closest(
            `.${SELECTORS.submenuDrilldown}`
          );
          const drilldownTrigger = drilldownSubmenu
            ? target.closest(SELECTORS.submenuTrigger)
            : null;

          if (closeButton || overlay) {
            e.preventDefault();
            closePanelWithCleanup(
              context.navId,
              panel as HTMLElement,
              getSharedState(context.navId),
              context
            );
          } else if (drilldownHeader) {
            e.preventDefault();
            performDrillBack(
              context.navId,
              panel as HTMLElement,
              getSharedState(context.navId),
              context
            );
          } else if (drilldownTrigger && drilldownSubmenu) {
            e.preventDefault();

            // Extract submenuId from the data-wp-context attribute.
            const contextAttr =
              drilldownSubmenu.getAttribute('data-wp-context');
            if (!contextAttr) return;

            // Parse context with robust error handling.
            let submenuContext: { submenuId?: string } | null = null;
            try {
              submenuContext = JSON.parse(contextAttr);
            } catch (parseError) {
              logError('handlePanelClick: Invalid JSON in submenu context', {
                contextAttr,
                error: parseError,
              });
              return;
            }

            // Validate the parsed context structure.
            if (
              !submenuContext ||
              typeof submenuContext !== 'object' ||
              typeof submenuContext.submenuId !== 'string' ||
              !submenuContext.submenuId
            ) {
              logWarning('handlePanelClick: Missing or invalid submenuId', {
                submenuContext,
              });
              return;
            }

            const shared = getSharedState(context.navId);
            const newStack = [...shared.drillStack, submenuContext.submenuId];
            shared.drillStack = newStack;
            context.drillStack = newStack;

            // Get the submenu label for announcement.
            const labelEl = drilldownSubmenu.querySelector(
              SELECTORS.submenuLabel
            );
            const submenuLabel = labelEl?.textContent?.trim() || 'submenu';

            // Announce navigation.
            announce(`Opened ${submenuLabel}, level ${newStack.length}`, {
              navId: context.navId,
            });

            syncSharedStateToElements(context.navId);

            // Update inert state and focus.
            updateDrilldownInertState(panel as HTMLElement, newStack);
            focusDrilldownPanel(submenuContext.submenuId);
          }
        };

        // Clean up any existing event handlers before adding new ones.
        // This prevents accumulation when navigation blocks are re-initialized.
        const existingDelegation = eventDelegationRegistry.get(element.ref);
        if (existingDelegation) {
          document.removeEventListener(
            'click',
            existingDelegation.panelClickHandler
          );
          window.removeEventListener(
            EVENTS.stateChange,
            existingDelegation.stateChangeHandler
          );
        }

        document.addEventListener('click', handlePanelClick);

        // ================================================================
        // State change listener for portal-rendered panel
        // ================================================================
        // Listen for state changes and update the panel accordingly.
        // This handles both opening and closing the panel.
        let wasOpen = false;

        // Retry configuration for panel initialization.
        const PANEL_RETRY_DELAY = 50;
        const PANEL_MAX_RETRIES = 20; // 1 second total

        const handleStateChange = (e: Event) => {
          const event = e as CustomEvent<{ navId: string }>;
          if (event.detail?.navId !== context.navId) {
            return;
          }

          const shared = getSharedState(context.navId);
          let panel = safeGetElementById<HTMLElement>(panelId, false);

          // If panel not found and we're trying to open, retry with exponential backoff.
          // This handles the race condition where toggle is clicked before wp_footer renders the panel.
          if (!panel && shared.isOpen) {
            let retryCount = 0;

            const retryFindPanel = () => {
              panel = safeGetElementById<HTMLElement>(panelId, false);
              if (panel) {
                // Panel found, apply the state.
                applyPanelState(panel);
              } else if (retryCount < PANEL_MAX_RETRIES) {
                retryCount++;
                setTimeout(retryFindPanel, PANEL_RETRY_DELAY);
              } else {
                logError('handleStateChange: Panel not found after retries', {
                  panelId,
                  retriesAttempted: retryCount,
                });
                // Reset state since panel couldn't be found.
                shared.isOpen = false;
                context.isOpen = false;
                setBodyOverflow(false);
              }
            };

            retryFindPanel();
            return;
          }

          if (!panel) {
            // Panel doesn't exist and we're not trying to open, safe to skip.
            return;
          }

          applyPanelState(panel);

          function applyPanelState(panelEl: HTMLElement) {
            const isOpen = shared.isOpen;

            // Only act on state changes.
            if (wasOpen !== isOpen) {
              // Update wasOpen BEFORE calling setup/cleanup functions to prevent
              // infinite recursion. These functions call syncSharedStateToElements()
              // which dispatches events synchronously.
              wasOpen = isOpen;

              if (isOpen) {
                openPanelWithSetup(context.navId, panelEl, shared);
              } else {
                // Handle closing from any source (actions.close, escape key, etc.)
                closePanelWithCleanup(context.navId, panelEl, shared, context);
              }
            }

            // Update drilldown state when drill stack changes.
            if (isOpen) {
              updateDrilldownInertState(panelEl, shared.drillStack);
            }
          }
        };

        window.addEventListener(EVENTS.stateChange, handleStateChange);

        // Store handlers for cleanup on re-initialization.
        eventDelegationRegistry.set(element.ref, {
          panelClickHandler: handlePanelClick,
          stateChangeHandler: handleStateChange,
        });

        // Track that we've set up delegation.
        element.ref.setAttribute('data-panel-delegation', 'true');
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

        // Determine menu orientation with robust fallback.
        // Check for explicit horizontal class first, then vertical class.
        // If neither is found, check if we're in a panel body (vertical) or use computed styles.
        let isHorizontal = true; // Default fallback for desktop nav.
        if (navMenu) {
          if (navMenu.classList.contains(SELECTORS.menuHorizontal)) {
            isHorizontal = true;
          } else if (navMenu.classList.contains(SELECTORS.menuVertical)) {
            isHorizontal = false;
          } else {
            // Fallback: Check if menu is in a navigation panel (always vertical).
            const isInPanel = !!navMenu.closest(SELECTORS.navigationPanel);
            if (isInPanel) {
              isHorizontal = false;
            } else {
              // Last resort: check computed flex-direction.
              const computedStyle = window.getComputedStyle(navMenu);
              isHorizontal =
                computedStyle.flexDirection === 'row' ||
                computedStyle.flexDirection === 'row-reverse';
            }
          }
        }

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

        // Clear any pending open timeout before setting a new one.
        if (hoverIntent.openTimeout) {
          clearTimeout(hoverIntent.openTimeout);
          hoverIntent.openTimeout = null;
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
     *
     * Note: Panel-specific updates (visibility, focus trap, inert, body classes)
     * are now handled via event delegation in the navigation block's init callback.
     * This is necessary because the panel is rendered via wp_footer, and the
     * Interactivity API doesn't process directives for content added after
     * DOMContentLoaded.
     *
     * This callback is still used by menu-toggle and navigation blocks to sync
     * their context with shared state for reactive bindings (e.g., aria-expanded).
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

        // Sync context with shared state for reactive bindings.
        syncContextFromShared(context, getSharedState(context.navId));
      } catch (error) {
        logError('onStateChange: Failed to handle state change', error);
      }
    },

    /**
     * Watch panel state - syncs context with shared state.
     *
     * Note: This callback is defined for the panel's data-wp-watch directive,
     * but since the panel is rendered via wp_footer, the directive is never
     * processed. Panel state is now managed via event delegation in the
     * navigation block's init callback. This callback is kept for potential
     * future use if the panel is rendered within the normal block tree.
     */
    watchPanelState(): void {
      try {
        const context = getContext<NavigationContext>();
        if (!context || !isValidNavId(context.navId)) {
          return;
        }

        // Sync context with shared state for reactive bindings.
        syncContextFromShared(context, getSharedState(context.navId));
      } catch (error) {
        logError('watchPanelState: Failed to sync panel state', error);
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
