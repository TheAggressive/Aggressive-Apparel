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
  PANEL_MENU_ITEM_SELECTOR,
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
 * Handles the delayed hiding to allow exit animations.
 */
export function setPanelVisibility(panel: HTMLElement, isOpen: boolean): void {
  if (isOpen) {
    panel.classList.add('is-open');
    panel.style.visibility = 'visible';
    panel.style.opacity = '1';
    panel.style.pointerEvents = 'auto';
  } else {
    panel.classList.remove('is-open');
    panel.style.pointerEvents = 'none';

    // Wait for animation to complete before hiding.
    const duration = getTransitionDuration();
    setTimeout(() => {
      // Only hide if still closed (user might have reopened).
      if (!panel.classList.contains('is-open')) {
        panel.style.visibility = 'hidden';
        panel.style.opacity = '0';
      }
    }, duration);
  }
}

// ============================================================================
// Focus Management
// ============================================================================

/**
 * Get all focusable elements within a container.
 */
export function getFocusableElements(container: HTMLElement): HTMLElement[] {
  return safeQuerySelectorAll<HTMLElement>(container, FOCUSABLE_SELECTOR);
}

/**
 * Setup focus trap within a container element.
 * Returns cleanup function to remove the trap.
 */
export function setupFocusTrap(container: HTMLElement): () => void {
  const focusable = getFocusableElements(container);

  if (focusable.length === 0) {
    logWarning('setupFocusTrap: No focusable elements found', {
      container: container.id,
    });
    return () => {};
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  // Find first actual menu item (skip header buttons like close/back).
  const firstMenuItem = safeQuerySelector<HTMLElement>(
    container,
    PANEL_MENU_ITEM_SELECTOR
  );

  // Focus first menu item if found, otherwise first focusable element.
  if (firstMenuItem) {
    firstMenuItem.focus();
  } else {
    first.focus();
  }

  const handler = (e: KeyboardEvent) => {
    if (e.key !== 'Tab') {
      return;
    }

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  };

  container.addEventListener('keydown', handler);

  return () => container.removeEventListener('keydown', handler);
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
