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
  PANEL_MENU_ITEM_SELECTOR,
  SELECTORS,
  SUBMENU_ITEM_SELECTOR,
  TOP_LEVEL_MENU_ITEM_SELECTOR,
} from './constants';
import type { NavigationContext, NavigationState, PanelState } from './types';
import {
  addBodyClass,
  announce,
  clearHoverTimeouts,
  createHoverIntent,
  focusDrilldownPanel,
  focusDrilldownTrigger,
  focusMenuItem,
  generateNavId,
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

// ============================================================================
// Nav State Helper
// ============================================================================

/**
 * Get the mutable panel state for a navigation instance.
 * Lazily initializes if the entry doesn't exist yet.
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
// Indicator Registry
// ============================================================================

/**
 * Per-navigation indicator references and helpers.
 * Stored so actions (openSubmenu, closeSubmenu, hover) can update the indicator.
 */
interface IndicatorInstance {
  menubar: HTMLElement;
  indicator: HTMLElement;
  /** Move indicator to match an item's bounds. */
  updateToItem: (item: HTMLElement) => void;
  /** Expand indicator to match a submenu panel's bounds. */
  expandToPanel: (panel: HTMLElement) => void;
  /** Reset indicator to .is-current item or hide. */
  reset: () => void;
}

const indicatorRegistry = new Map<string, IndicatorInstance>();

/**
 * Sync the sliding indicator with the active submenu.
 *
 * Opening: expands the underline to match the dropdown panel, creating a
 * visual bridge between trigger and content.
 *
 * Closing: hands the background back to the panel (removing data-indicator-bg),
 * hides the indicator, and lets the panel's CSS transition fade everything.
 * After the fade, snaps the indicator back to its resting position.
 */
function syncIndicatorWithSubmenu(
  navId: string,
  submenuId: string | null
): void {
  // Desktop-only: the mobile panel uses a separate vertical accent bar
  // managed by initPanel's focusin/touchstart listeners. Running this on
  // mobile would set data-indicator-bg (making panels transparent) and
  // try to expand the hidden desktop indicator to a portaled element.
  const ns = getNavState(navId);
  if (ns.isMobile) return;

  const inst = indicatorRegistry.get(navId);
  if (!inst) return;

  if (!submenuId) {
    // ── Submenu closing ──────────────────────────────────────────
    // Foolproof approach: hand the background back to the panel,
    // hide the indicator instantly, and let the panel's OWN CSS
    // transition (opacity 1→0) fade everything out in one go.
    // No JS timing coordination between indicator and items needed.

    const nav = inst.menubar.closest('.wp-block-aggressive-apparel-navigation');
    if (nav) {
      // Stop stagger animation so items revert to base opacity: 0.
      nav.querySelectorAll('.is-items-revealed').forEach(el => {
        el.classList.remove('is-items-revealed');
      });

      // Give the panel its own background back (white, shadow, etc.)
      // so the panel's CSS close transition fades it all as one unit.
      nav.querySelectorAll('[data-indicator-bg]').forEach(el => {
        el.removeAttribute('data-indicator-bg');
      });
    }

    // Hide the indicator instantly — the panel now has its own bg,
    // so removing the indicator causes no visual change.
    inst.indicator.style.setProperty('--indicator-opacity', '0');
    inst.indicator.classList.remove('is-expanded');
    inst.indicator.classList.remove('has-accent');

    // After the panel's CSS close transition finishes (~250ms),
    // snap indicator back to its resting position without animation.
    setTimeout(() => {
      const ind = inst.indicator;
      ind.style.transition = 'none';
      ind.style.setProperty('--indicator-height', '3px');
      ind.style.setProperty('--indicator-y', '-3px');
      ind.style.setProperty('--indicator-radius', '1.5px');

      // Position at hovered item or current-page item.
      const hoveredLi = inst.menubar.querySelector(
        ':scope > li:hover'
      ) as HTMLElement | null;
      const targetLi =
        hoveredLi && !hoveredLi.classList.contains('aa-nav__indicator-wrap')
          ? hoveredLi
          : (inst.menubar.querySelector(
              ':scope > li.is-current'
            ) as HTMLElement | null);

      if (targetLi) {
        const menubarRect = inst.menubar.getBoundingClientRect();
        const itemRect = targetLi.getBoundingClientRect();
        ind.style.setProperty(
          '--indicator-x',
          `${itemRect.left - menubarRect.left}px`
        );
        ind.style.setProperty('--indicator-width', `${itemRect.width}px`);
      }

      // Re-enable transitions next frame, then fade back in if needed.
      requestAnimationFrame(() => {
        ind.style.removeProperty('transition');
        if (targetLi) {
          ind.style.setProperty('--indicator-opacity', '1');
        }
      });
    }, INDICATOR_DURATION_MS);

    return;
  }

  // Find the panel by its ID (submenuId is used as the panel's id attribute).
  const panel = document.getElementById(submenuId) as HTMLElement | null;
  if (!panel) return;

  // Immediately strip the panel's own background/shadow so it doesn't
  // flash white before the indicator expands to replace it.
  panel.setAttribute('data-indicator-bg', '');

  // Brief pause so the underline settles at the item before expanding.
  // The indicator already slid here during the hover intent delay, so
  // we just need a short beat before morphing into the panel shape.
  setTimeout(() => {
    // Guard: submenu may have closed while we waited.
    if (!panel.closest('.wp-block-aggressive-apparel-nav-submenu.is-open'))
      return;

    requestAnimationFrame(() => {
      const panelRect = panel.getBoundingClientRect();
      if (panelRect.width > 0) {
        inst.expandToPanel(panel);
      }
    });
  }, 100);
}

// ============================================================================
// Panel Helpers
// ============================================================================

/**
 * Find the panel element for this navigation instance.
 * Panel is portaled to wp_footer, found by ID (DOM-position independent).
 */
function findPanel(navId: string): HTMLElement | null {
  return safeGetElementById(`${navId}-panel`, false);
}

/**
 * Close the navigation panel with full cleanup.
 */
function closePanelWithCleanup(navId: string, panel: HTMLElement): void {
  const ns = getNavState(navId);
  ns.isOpen = false;
  ns.activeSubmenuId = null;
  ns.drillStack = [];
  setBodyOverflow(false);

  setPanelVisibility(panel, false);
  removeAllBodyClasses();

  // Clean up focus trap.
  const existingCleanup = focusTrapRegistry.get(panel);
  if (existingCleanup) {
    existingCleanup();
    focusTrapRegistry.delete(panel);
  }

  // Remove inert from main content.
  if ('inert' in HTMLElement.prototype) {
    const mainContent = document.querySelector(
      '.wp-site-blocks'
    ) as HTMLElement | null;
    if (mainContent) {
      mainContent.inert = false;
    }
  }

  // Reset drilldown panels.
  updateDrilldownInertState(panel, []);

  // Clean up CSS variable after transition.
  setTimeout(() => {
    document.body.style.removeProperty('--push-panel-width');
  }, getTransitionDuration());

  announce('Navigation menu closed', { assertive: true, navId });
  restoreFocus(navId);
}

/**
 * Open the navigation panel with full setup.
 */
function openPanelWithSetup(navId: string, panel: HTMLElement): void {
  const ns = getNavState(navId);
  const animationStyle = panel.getAttribute('data-animation-style') || 'slide';
  const position = panel.getAttribute('data-position') || 'right';

  // Set body classes for push/reveal BEFORE panel animation.
  if (animationStyle === 'push' || animationStyle === 'reveal') {
    const panelWidth =
      window.getComputedStyle(panel).getPropertyValue('--panel-width').trim() ||
      'min(320px, 85vw)';
    document.body.style.setProperty('--push-panel-width', panelWidth);
    addBodyClass(animationStyle, position);
  }

  setPanelVisibility(panel, true);

  // Set up focus trap.
  const existingCleanup = focusTrapRegistry.get(panel);
  if (existingCleanup) {
    existingCleanup();
    focusTrapRegistry.delete(panel);
  }
  const cleanup = setupFocusTrap(panel);
  focusTrapRegistry.set(panel, cleanup);

  // Apply inert to main content. Panel is outside .wp-site-blocks
  // so it won't be affected by the inert attribute.
  if ('inert' in HTMLElement.prototype) {
    const mainContent = document.querySelector(
      '.wp-site-blocks'
    ) as HTMLElement | null;
    if (mainContent) {
      mainContent.inert = true;
    }
  }

  // Initialize inert state for drilldown panels.
  updateDrilldownInertState(panel, ns.drillStack);

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

// ============================================================================
// Menu Item Helpers
// ============================================================================

function getMenuItems(container: Element): HTMLElement[] {
  return safeQuerySelectorAll<HTMLElement>(
    container,
    TOP_LEVEL_MENU_ITEM_SELECTOR
  );
}

function getSubmenuItems(panel: Element): HTMLElement[] {
  return safeQuerySelectorAll<HTMLElement>(panel, SUBMENU_ITEM_SELECTOR);
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
          closePanelWithCleanup(context.navId, panel);
        } else {
          ns.isOpen = true;
          setBodyOverflow(true);
          openPanelWithSetup(context.navId, panel);
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

        closePanelWithCleanup(context.navId, panel);
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
        syncIndicatorWithSubmenu(context.navId, ns.activeSubmenuId);

        if (context.submenuId) {
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
        clearHoverTimeouts(hoverIntent);
        // Hide items BEFORE removing .is-open so they disappear
        // before the panel's CSS close transition starts.
        syncIndicatorWithSubmenu(context.navId, null);
        ns.activeSubmenuId = null;
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

        if (event) {
          event.preventDefault();
        }

        const ns = getNavState(context.navId);
        clearHoverTimeouts(hoverIntent);

        const wasOpen = ns.activeSubmenuId === context.submenuId;
        ns.activeSubmenuId = wasOpen ? null : (context.submenuId ?? null);
        syncIndicatorWithSubmenu(context.navId, ns.activeSubmenuId);

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
        // Hide items BEFORE removing .is-open so they disappear
        // before the panel's CSS close transition starts.
        syncIndicatorWithSubmenu(context.navId, null);
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

        // ================================================================
        // Desktop Sliding Indicator
        // ================================================================
        const nav = element.ref;
        const menubar = nav.querySelector(
          SELECTORS.menubar
        ) as HTMLElement | null;
        const indicator = nav.querySelector(
          SELECTORS.indicator
        ) as HTMLElement | null;

        if (menubar && indicator) {
          // Contract indicator to match a menu item (3px underline).
          const updateToItem = (item: HTMLElement) => {
            const menubarRect = menubar.getBoundingClientRect();
            const itemRect = item.getBoundingClientRect();
            indicator.style.setProperty(
              '--indicator-x',
              `${itemRect.left - menubarRect.left}px`
            );
            indicator.style.setProperty('--indicator-y', '-3px');
            indicator.style.setProperty(
              '--indicator-width',
              `${itemRect.width}px`
            );
            indicator.style.setProperty('--indicator-height', '3px');
            indicator.style.setProperty('--indicator-opacity', '1');
            indicator.style.setProperty('--indicator-radius', '1.5px');
            indicator.classList.remove('is-expanded');
          };

          // Expand indicator to morph into the submenu panel background.
          // Grows from a 3px line into a full rectangle matching the panel,
          // creating the illusion that the underline becomes the dropdown.
          const expandToPanel = (panelEl: HTMLElement) => {
            const menubarRect = menubar.getBoundingClientRect();
            const panelRect = panelEl.getBoundingClientRect();

            // Keep top edge aligned with the underline position (-3px from
            // menubar bottom). Grow downward to cover the full panel.
            const x = panelRect.left - menubarRect.left;
            const height = panelRect.bottom - menubarRect.bottom + 3;

            indicator.style.setProperty('--indicator-x', `${x}px`);
            indicator.style.setProperty('--indicator-y', '-3px');
            indicator.style.setProperty(
              '--indicator-width',
              `${panelRect.width}px`
            );
            indicator.style.setProperty('--indicator-height', `${height}px`);
            indicator.style.setProperty('--indicator-opacity', '1');
            indicator.style.setProperty(
              '--indicator-radius',
              `0 0 ${window.getComputedStyle(panelEl).borderRadius || '8px'}`
            );
            indicator.classList.add('is-expanded');

            // Toggle accent line based on the submenu's setting.
            const submenuEl = panelEl.closest(
              '.wp-block-aggressive-apparel-nav-submenu'
            );
            indicator.classList.toggle(
              'has-accent',
              !!submenuEl?.classList.contains('has-indicator-accent')
            );

            // Make the panel transparent so the indicator shows through.
            panelEl.setAttribute('data-indicator-bg', '');

            // Set stagger indices for sequential item reveal.
            const panelInner = panelEl.querySelector(
              '.wp-block-aggressive-apparel-nav-submenu__panel-inner'
            ) as HTMLElement | null;
            if (panelInner) {
              Array.from(panelInner.children).forEach((child, i) => {
                (child as HTMLElement).style.setProperty(
                  '--item-index',
                  String(i)
                );
              });
            }

            // After the expansion transition is nearly complete, reveal
            // items with a stagger cascade.
            const submenu = panelEl.closest(
              '.wp-block-aggressive-apparel-nav-submenu'
            );
            if (submenu) {
              setTimeout(() => {
                if (submenu.classList.contains('is-open')) {
                  submenu.classList.add('is-items-revealed');
                }
              }, 200);
            }
          };

          // Reset to .is-current item or hide completely.
          const reset = () => {
            indicator.classList.remove('is-expanded');

            const currentLi = menubar.querySelector(
              ':scope > li.is-current'
            ) as HTMLElement | null;
            if (currentLi) {
              updateToItem(currentLi);
            } else {
              indicator.style.setProperty('--indicator-opacity', '0');
              indicator.style.setProperty('--indicator-height', '3px');
              indicator.style.setProperty('--indicator-y', '-3px');
              indicator.style.setProperty('--indicator-radius', '1.5px');
            }
          };

          // Register instance so actions can expand/contract the indicator.
          indicatorRegistry.set(context.navId, {
            menubar,
            indicator,
            updateToItem,
            expandToPanel,
            reset,
          });

          // Set initial position.
          reset();

          // Listen for hover/focus on top-level items.
          // Close active submenu and slide indicator from the trigger
          // to a new (non-submenu) item. Hands the background back to
          // the panel so its CSS transition fades content, then snaps
          // the indicator to underline at the trigger and slides it.
          const closeAndSlideTo = (targetLi: HTMLElement) => {
            const navState = getNavState(context.navId);

            // Find the active submenu trigger <li> via the panel element.
            const activePanel = navState.activeSubmenuId
              ? document.getElementById(navState.activeSubmenuId)
              : null;
            const activeTrigger = activePanel
              ? (activePanel.closest(
                  '.wp-block-aggressive-apparel-nav-submenu'
                ) as HTMLElement | null)
              : null;

            // Hand background back to panel.
            nav.querySelectorAll('.is-items-revealed').forEach(el => {
              el.classList.remove('is-items-revealed');
            });
            nav.querySelectorAll('[data-indicator-bg]').forEach(el => {
              el.removeAttribute('data-indicator-bg');
            });

            // Snap indicator to underline at the trigger position (no transition).
            indicator.style.transition = 'none';
            indicator.classList.remove('is-expanded');
            indicator.classList.remove('has-accent');
            indicator.style.setProperty('--indicator-height', '3px');
            indicator.style.setProperty('--indicator-y', '-3px');
            indicator.style.setProperty('--indicator-radius', '1.5px');
            indicator.style.setProperty('--indicator-opacity', '1');

            if (activeTrigger) {
              const mbRect = menubar.getBoundingClientRect();
              const triggerRect = activeTrigger.getBoundingClientRect();
              indicator.style.setProperty(
                '--indicator-x',
                `${triggerRect.left - mbRect.left}px`
              );
              indicator.style.setProperty(
                '--indicator-width',
                `${triggerRect.width}px`
              );
            }

            // Force reflow so the snap is committed before we animate.
            void indicator.offsetHeight;

            // Re-enable transitions and slide to the new item.
            indicator.style.removeProperty('transition');
            const mbRect = menubar.getBoundingClientRect();
            const targetRect = targetLi.getBoundingClientRect();
            indicator.style.setProperty(
              '--indicator-x',
              `${targetRect.left - mbRect.left}px`
            );
            indicator.style.setProperty(
              '--indicator-width',
              `${targetRect.width}px`
            );

            // Close the submenu (reactive — removes .is-open).
            navState.activeSubmenuId = null;
            hoverIntent.activeId = null;
            clearHoverTimeouts(hoverIntent);
          };

          const topLevelItems = menubar.querySelectorAll(':scope > li');
          topLevelItems.forEach(li => {
            const link = li.querySelector('a, button') as HTMLElement | null;
            if (!link || li.classList.contains('aa-nav__indicator-wrap'))
              return;

            li.addEventListener('mouseenter', () => {
              const navState = getNavState(context.navId);
              if (
                navState.activeSubmenuId &&
                !li.classList.contains(
                  'wp-block-aggressive-apparel-nav-submenu'
                )
              ) {
                closeAndSlideTo(li as HTMLElement);
                return;
              }
              updateToItem(li as HTMLElement);
            });
            li.addEventListener('focusin', () => {
              const navState = getNavState(context.navId);
              if (
                navState.activeSubmenuId &&
                !li.classList.contains(
                  'wp-block-aggressive-apparel-nav-submenu'
                )
              ) {
                closeAndSlideTo(li as HTMLElement);
                return;
              }
              updateToItem(li as HTMLElement);
            });
          });

          menubar.addEventListener('mouseleave', () => {
            const navState = getNavState(context.navId);
            // Only reset if no submenu is open.
            if (!navState.activeSubmenuId) {
              reset();
            }
          });
          menubar.addEventListener('focusout', (e: FocusEvent) => {
            const related = e.relatedTarget as HTMLElement | null;
            if (!related || !menubar.contains(related)) {
              const navState = getNavState(context.navId);
              if (!navState.activeSubmenuId) {
                reset();
              }
            }
          });

          // Update on window resize.
          window.addEventListener('resize', reset, { passive: true });
        }
      } catch (error) {
        logError('init: Failed to initialize navigation', error);
      }
    },

    /**
     * Initialize panel (runs on the portal wrapper element).
     * Sets up mobile indicator and stagger indices.
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
        // Mobile Vertical Accent Bar
        // ================================================================
        const panelMenu = panel.querySelector(
          SELECTORS.panelMenu
        ) as HTMLElement | null;
        const mobileIndicator = panel.querySelector(
          SELECTORS.mobileIndicator
        ) as HTMLElement | null;

        if (panelMenu && mobileIndicator) {
          const TRIGGER_SELECTOR =
            '.wp-block-aggressive-apparel-nav-submenu__link, .wp-block-aggressive-apparel-nav-link__link';

          const updateMobileIndicator = (topLevelLi: HTMLElement) => {
            // Measure the trigger link/button, not the full <li>. For submenus
            // the <li> grows when the accordion expands — the indicator should
            // stay at the trigger's fixed height (44px), not stretch.
            const trigger = topLevelLi.querySelector(
              TRIGGER_SELECTOR
            ) as HTMLElement | null;
            const target = trigger || topLevelLi;
            const menuRect = panelMenu.getBoundingClientRect();
            const itemRect = target.getBoundingClientRect();
            mobileIndicator.style.setProperty(
              '--mobile-indicator-y',
              `${itemRect.top - menuRect.top}px`
            );
            mobileIndicator.style.setProperty(
              '--mobile-indicator-height',
              `${itemRect.height}px`
            );
            mobileIndicator.style.setProperty(
              '--mobile-indicator-opacity',
              '1'
            );
          };

          // Track the indicator through accordion transitions.
          // When toggling submenus, the collapsing accordion shifts items
          // vertically over ~250ms. Recalculate each frame so the indicator
          // follows the target smoothly instead of jumping to a stale position.
          let trackingRAF: number | null = null;

          const trackMobileIndicator = (topLevelLi: HTMLElement) => {
            if (trackingRAF !== null) {
              cancelAnimationFrame(trackingRAF);
            }

            // Disable CSS transition during tracking so the indicator
            // snaps to each rAF position instead of lagging behind and
            // overshooting when accordion reflows shift items vertically.
            mobileIndicator.style.transition = 'opacity 200ms ease';

            updateMobileIndicator(topLevelLi);

            const startTime = performance.now();
            const trackDuration = 350; // slightly longer than --navigation-transition (250ms)

            const track = () => {
              updateMobileIndicator(topLevelLi);
              if (performance.now() - startTime < trackDuration) {
                trackingRAF = requestAnimationFrame(track);
              } else {
                // Re-enable CSS transitions for normal item-to-item slides.
                mobileIndicator.style.removeProperty('transition');
                trackingRAF = null;
              }
            };
            trackingRAF = requestAnimationFrame(track);
          };

          const resetMobileIndicator = () => {
            if (trackingRAF !== null) {
              cancelAnimationFrame(trackingRAF);
              trackingRAF = null;
            }
            mobileIndicator.style.setProperty(
              '--mobile-indicator-opacity',
              '0'
            );
          };

          // Find the top-level <li> in the panel menu that contains the target.
          // Even when focus/touch is on a child item inside an expanded accordion,
          // the indicator stays on the trigger's <li>.
          const findTopLevelItem = (
            target: HTMLElement
          ): HTMLElement | null => {
            for (const child of panelMenu.children) {
              if (child instanceof HTMLElement && child.contains(target)) {
                return child;
              }
            }
            return null;
          };

          panelMenu.addEventListener('focusin', (e: FocusEvent) => {
            const li = findTopLevelItem(e.target as HTMLElement);
            if (li) {
              trackMobileIndicator(li);
            }
          });

          panelMenu.addEventListener('focusout', (e: FocusEvent) => {
            const related = e.relatedTarget as HTMLElement | null;
            if (!related || !panelMenu.contains(related)) {
              resetMobileIndicator();
            }
          });

          // Touch: highlight on touch.
          panelMenu.addEventListener(
            'touchstart',
            (e: TouchEvent) => {
              const li = findTopLevelItem(e.target as HTMLElement);
              if (li) {
                trackMobileIndicator(li);
              }
            },
            { passive: true }
          );
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
          announce('Submenu closed', { navId: context.navId });
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
              announce('Submenu closed', { navId: context.navId });
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
            navState.activeSubmenuId = context.submenuId ?? null;
            hoverIntent.activeId = context.submenuId ?? null;
            syncIndicatorWithSubmenu(context.navId, navState.activeSubmenuId);
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
              // Hide items BEFORE removing .is-open so they disappear
              // before the panel's CSS close transition starts.
              syncIndicatorWithSubmenu(context.navId, null);
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
          }
          syncIndicatorWithSubmenu(context.navId, ns.activeSubmenuId);
          announce('Submenu opened', { navId: context.navId });
        } else {
          if (ns.activeSubmenuId === submenuId) {
            ns.activeSubmenuId = null;
            hoverIntent.activeId = null;
          }
          syncIndicatorWithSubmenu(context.navId, ns.activeSubmenuId);
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
