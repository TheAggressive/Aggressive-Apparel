/**
 * Navigation Block System — Shared Utilities
 *
 * Common utility functions for the navigation block system.
 * Includes error handling, DOM helpers, and focus management.
 *
 * @package Aggressive_Apparel
 */

// Type declaration for process in webpack environment
declare const process:
  | {
      env: {
        NODE_ENV: string;
      };
    }
  | undefined;

import {
  ALL_BODY_CLASSES,
  BODY_CLASSES,
  FOCUSABLE_SELECTOR,
  ID_PREFIXES,
  TRANSITION_DURATION_MS,
  type BodyClassKey,
} from './constants';

// ============================================================================
// Error Handling
// ============================================================================

/**
 * Log a warning message in development mode.
 * Silent in production to avoid console noise.
 */
export function logWarning(
  message: string,
  context?: Record<string, unknown>
): void {
  if (typeof process !== 'undefined' && process.env.NODE_ENV !== 'production') {
    console.warn(`[Navigation] ${message}`, context ?? '');
  }
}

/**
 * Log an error message.
 * Always logs errors regardless of environment.
 */
export function logError(message: string, error?: unknown): void {
  console.error(`[Navigation] ${message}`, error ?? '');
}

/**
 * Safely get an element by ID with type checking.
 * Returns null and logs warning if not found.
 */
export function safeGetElementById<T extends HTMLElement = HTMLElement>(
  id: string,
  warnIfMissing = true
): T | null {
  if (!id) {
    if (warnIfMissing) {
      logWarning('safeGetElementById called with empty ID');
    }
    return null;
  }

  const element = document.getElementById(id);
  if (!element && warnIfMissing) {
    logWarning(`Element not found: #${id}`);
  }

  return element as T | null;
}

/**
 * Safely query for an element within a container.
 */
export function safeQuerySelector<T extends HTMLElement = HTMLElement>(
  container: Element | Document,
  selector: string,
  warnIfMissing = false
): T | null {
  try {
    const element = container.querySelector<T>(selector);
    if (!element && warnIfMissing) {
      logWarning(`Element not found: ${selector}`);
    }
    return element;
  } catch (error) {
    logError(`Invalid selector: ${selector}`, error);
    return null;
  }
}

/**
 * Safely query for all elements matching a selector.
 */
export function safeQuerySelectorAll<T extends HTMLElement = HTMLElement>(
  container: Element | Document,
  selector: string
): T[] {
  try {
    return Array.from(container.querySelectorAll<T>(selector));
  } catch (error) {
    logError(`Invalid selector: ${selector}`, error);
    return [];
  }
}

// ============================================================================
// Reduced Motion
// ============================================================================

/** Cached reduced motion preference. */
let _prefersReducedMotion: boolean | null = null;

/**
 * Check if user prefers reduced motion.
 * Caches the result and updates on change.
 */
export function prefersReducedMotion(): boolean {
  if (_prefersReducedMotion === null) {
    const mql = window.matchMedia('(prefers-reduced-motion: reduce)');
    _prefersReducedMotion = mql.matches;

    // Update on change
    mql.addEventListener('change', e => {
      _prefersReducedMotion = e.matches;
    });
  }
  return _prefersReducedMotion;
}

/**
 * Get the effective transition duration respecting reduced motion preference.
 */
export function getTransitionDuration(): number {
  return prefersReducedMotion() ? 0 : TRANSITION_DURATION_MS;
}

// ============================================================================
// ID Generation & Validation
// ============================================================================

/**
 * Generate a unique navigation ID.
 */
export function generateNavId(): string {
  return `${ID_PREFIXES.navigation}${Math.random().toString(36).slice(2, 9)}`;
}

/**
 * Get the panel ID for a navigation ID.
 */
export function getPanelId(navId: string): string {
  if (!navId) {
    logWarning('getPanelId called with empty navId');
    return 'navigation-panel';
  }
  return `${navId}${ID_PREFIXES.panel}`;
}

/**
 * Get the toggle ID for a navigation ID.
 */
export function getToggleId(navId: string): string {
  if (!navId) {
    return '';
  }
  return `${ID_PREFIXES.menuToggle}${navId}`;
}

/**
 * Get the announcer ID for a navigation ID.
 */
export function getAnnouncerId(navId: string): string {
  return navId ? `${ID_PREFIXES.announcer}${navId}` : 'navigation-announcer';
}

/**
 * Validate a navigation ID.
 */
export function isValidNavId(navId: unknown): navId is string {
  return typeof navId === 'string' && navId.length > 0;
}

// ============================================================================
// Body Class Management
// ============================================================================

/**
 * Remove all navigation body classes.
 */
export function removeAllBodyClasses(): void {
  document.body.classList.remove(...ALL_BODY_CLASSES);
}

/**
 * Add the appropriate body class for push/reveal animations.
 */
export function addBodyClass(animationStyle: string, position: string): void {
  const key =
    `${animationStyle}${position.charAt(0).toUpperCase()}${position.slice(1)}` as BodyClassKey;
  const className = BODY_CLASSES[key];
  if (className) {
    document.body.classList.add(className);
  }
}

/**
 * Set body overflow to prevent scrolling.
 */
export function setBodyOverflow(hidden: boolean): void {
  document.body.style.overflow = hidden ? 'hidden' : '';
}

// ============================================================================
// Panel Visibility
// ============================================================================

/**
 * Manage panel visibility with animation support.
 *
 * The panel is always visible in the DOM but positioned off-screen via CSS transform.
 * The .is-open class triggers the transform animation to slide/push/reveal the panel.
 * We don't use visibility:hidden because it prevents CSS transitions from working.
 *
 * For opening: Remove pointer-events:none and add .is-open to animate in.
 * For closing: Remove .is-open to animate out, then restore pointer-events:none.
 */
export function setPanelVisibility(panel: HTMLElement, isOpen: boolean): void {
  if (isOpen) {
    // Enable pointer events and add class to trigger CSS transition animation.
    panel.style.removeProperty('pointer-events');
    panel.classList.add('is-open');
  } else {
    // Remove class to trigger exit animation.
    panel.classList.remove('is-open');

    // Wait for animation to complete before disabling pointer events.
    const duration = getTransitionDuration();
    setTimeout(() => {
      // Only disable if still closed (user might have reopened).
      if (!panel.classList.contains('is-open')) {
        panel.style.pointerEvents = 'none';
      }
    }, duration);
  }
}

// ============================================================================
// Focus Management
// ============================================================================

/**
 * Get all focusable elements within a container.
 * Filters out elements inside inert ancestors since they can't receive focus.
 */
export function getFocusableElements(container: HTMLElement): HTMLElement[] {
  const elements = safeQuerySelectorAll<HTMLElement>(
    container,
    FOCUSABLE_SELECTOR
  );

  // Filter out elements that are inside an inert ancestor.
  return elements.filter(el => {
    let parent: HTMLElement | null = el.parentElement;
    while (parent && parent !== container) {
      if (parent.inert) {
        return false;
      }
      parent = parent.parentElement;
    }
    return true;
  });
}

/**
 * Setup focus trap within a container element.
 * Returns cleanup function to remove the trap.
 *
 * Best practices implemented:
 * - ALWAYS intercepts Tab and manages focus manually (bulletproof approach)
 * - Dynamically queries focusable elements on each Tab (handles DOM changes)
 * - Uses focusin listener as backup to catch any focus escape
 * - Capture phase ensures we handle events before they bubble
 */
export function setupFocusTrap(container: HTMLElement): () => void {
  /**
   * Handle Tab key - ALWAYS prevent default and manage focus manually.
   * This is the bulletproof approach: instead of only intercepting at boundaries,
   * we intercept ALL Tab presses and calculate the next focus target ourselves.
   */
  const handleKeydown = (e: KeyboardEvent) => {
    if (e.key !== 'Tab') {
      return;
    }

    // Dynamically get focusable elements on each Tab press.
    const focusable = getFocusableElements(container);

    if (focusable.length === 0) {
      e.preventDefault();
      return;
    }

    const active = document.activeElement as HTMLElement | null;

    // Find current index in focusable list.
    const currentIndex = active ? focusable.indexOf(active) : -1;

    // Calculate next focus target.
    let nextIndex: number;

    if (e.shiftKey) {
      // Shift+Tab: move backwards, wrap to last if at start or outside.
      if (currentIndex <= 0) {
        nextIndex = focusable.length - 1;
      } else {
        nextIndex = currentIndex - 1;
      }
    } else {
      // Tab: move forwards, wrap to first if at end or outside.
      if (currentIndex === -1 || currentIndex >= focusable.length - 1) {
        nextIndex = 0;
      } else {
        nextIndex = currentIndex + 1;
      }
    }

    // Always prevent default and manually focus the next element.
    e.preventDefault();
    focusable[nextIndex].focus();
  };

  /**
   * Backup: If focus somehow escapes (click, programmatic focus, etc.),
   * bring it back into the container.
   */
  const handleFocusin = (e: FocusEvent) => {
    const target = e.target as HTMLElement;

    // If focus moved to something outside the container, redirect it back.
    if (!container.contains(target)) {
      const focusable = getFocusableElements(container);
      if (focusable.length > 0) {
        // Focus the first element in the container.
        focusable[0].focus();
      }
    }
  };

  // Capture phase ensures we handle events before they bubble.
  document.addEventListener('keydown', handleKeydown, true);
  document.addEventListener('focusin', handleFocusin, true);

  return () => {
    document.removeEventListener('keydown', handleKeydown, true);
    document.removeEventListener('focusin', handleFocusin, true);
  };
}

/**
 * Restore focus to toggle button or fallback element after closing panel.
 */
export function restoreFocus(navId: string): void {
  const toggleId = getToggleId(navId);

  if (toggleId) {
    const toggle = safeGetElementById(toggleId, false);
    if (toggle) {
      toggle.focus();
      return;
    }
  }

  // Fallback: focus main content area.
  const main = safeQuerySelector<HTMLElement>(
    document,
    'main, [role="main"], .wp-site-blocks',
    false
  );

  if (main) {
    const hadTabindex = main.hasAttribute('tabindex');
    if (!hadTabindex) {
      main.setAttribute('tabindex', '-1');
    }
    main.focus();
    if (!hadTabindex) {
      main.removeAttribute('tabindex');
    }
  }
}

/**
 * Move focus to a menu item by index with roving tabindex.
 */
export function focusMenuItem(items: HTMLElement[], index: number): void {
  if (items.length === 0) {
    return;
  }

  // Clamp index to valid range.
  const targetIndex = Math.max(0, Math.min(index, items.length - 1));

  // Update tabindex: remove from all, add to target.
  items.forEach((item, i) => {
    item.setAttribute('tabindex', i === targetIndex ? '0' : '-1');
  });

  // Focus the target item.
  items[targetIndex].focus();
}

// ============================================================================
// Announcements
// ============================================================================

/** Timeout ID for clearing announcements. */
let announcementClearTimeout: ReturnType<typeof setTimeout> | null = null;

/**
 * Announce message to screen readers via live region.
 */
export function announce(
  message: string,
  options: { assertive?: boolean; navId?: string } = {}
): void {
  const { assertive = false, navId } = options;
  const id = getAnnouncerId(navId ?? '');

  // Try to find specific announcer first, then fallback.
  let announcer = safeGetElementById(id, false);
  if (!announcer) {
    announcer = safeGetElementById('navigation-announcer', false);
  }

  if (!announcer) {
    logWarning('announce: Announcer element not found', { id });
    return;
  }

  // Clear any pending clear timeout.
  if (announcementClearTimeout) {
    clearTimeout(announcementClearTimeout);
  }

  // Set aria-live mode based on importance.
  announcer.setAttribute('aria-live', assertive ? 'assertive' : 'polite');
  announcer.textContent = message;

  // Clear after a delay to allow re-announcements.
  announcementClearTimeout = setTimeout(() => {
    if (announcer) {
      announcer.textContent = '';
    }
    announcementClearTimeout = null;
  }, 5000);
}

// ============================================================================
// Hover Intent
// ============================================================================

export interface HoverIntentState {
  openTimeout: ReturnType<typeof setTimeout> | null;
  closeTimeout: ReturnType<typeof setTimeout> | null;
  activeId: string | null;
}

/**
 * Create a hover intent manager.
 */
export function createHoverIntent(): HoverIntentState {
  return {
    openTimeout: null,
    closeTimeout: null,
    activeId: null,
  };
}

/**
 * Clear all hover intent timeouts.
 */
export function clearHoverTimeouts(hoverIntent: HoverIntentState): void {
  if (hoverIntent.openTimeout) {
    clearTimeout(hoverIntent.openTimeout);
    hoverIntent.openTimeout = null;
  }
  if (hoverIntent.closeTimeout) {
    clearTimeout(hoverIntent.closeTimeout);
    hoverIntent.closeTimeout = null;
  }
}

// ============================================================================
// Drilldown Focus Management
// ============================================================================

/**
 * Update inert state on drilldown panels based on the current drill stack.
 * Panels not in the current view should be inert to prevent focus escape.
 *
 * @param container - The navigation panel container
 * @param drillStack - Array of currently active submenu IDs
 */
export function updateDrilldownInertState(
  container: HTMLElement,
  drillStack: string[]
): void {
  // Check if inert is supported.
  const supportsInert =
    typeof HTMLElement !== 'undefined' && 'inert' in HTMLElement.prototype;

  if (!supportsInert) {
    return;
  }

  // Get all drilldown submenu panels.
  const drilldownPanels = safeQuerySelectorAll<HTMLElement>(
    container,
    '.wp-block-aggressive-apparel-nav-submenu--drilldown .wp-block-aggressive-apparel-nav-submenu__panel'
  );

  // Current active panel is the last in the stack (or none if stack is empty).
  const currentActiveId = drillStack[drillStack.length - 1] ?? null;

  drilldownPanels.forEach(panel => {
    const panelId = panel.id;
    // Panel should be inert if it's NOT the current active panel.
    // If no panel is active (drillStack empty), all panels should be inert.
    const shouldBeInert = panelId !== currentActiveId;
    panel.inert = shouldBeInert;
  });
}

/**
 * Focus the first focusable item in a drilldown panel.
 *
 * @param panelId - The ID of the panel to focus into
 */
export function focusDrilldownPanel(panelId: string): void {
  const panel = safeGetElementById(panelId, false);
  if (!panel) {
    return;
  }

  // Use double rAF to ensure CSS transitions have started
  // and the panel is visible before focusing.
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      const firstFocusable = safeQuerySelector<HTMLElement>(
        panel,
        FOCUSABLE_SELECTOR,
        false
      );
      firstFocusable?.focus();
    });
  });
}

/**
 * Focus the trigger that opened the current drilldown level when going back.
 *
 * @param container - The navigation panel container
 * @param previousId - The ID of the panel we're leaving
 */
export function focusDrilldownTrigger(
  container: HTMLElement,
  previousId: string
): void {
  // Find the submenu element that has this panel.
  const submenu = safeQuerySelector<HTMLElement>(
    container,
    `.wp-block-aggressive-apparel-nav-submenu--drilldown:has(#${CSS.escape(previousId)})`,
    false
  );

  if (!submenu) {
    return;
  }

  // Focus the trigger link/button.
  const trigger = safeQuerySelector<HTMLElement>(
    submenu,
    '.wp-block-aggressive-apparel-nav-submenu__link',
    false
  );

  requestAnimationFrame(() => {
    trigger?.focus();
  });
}
