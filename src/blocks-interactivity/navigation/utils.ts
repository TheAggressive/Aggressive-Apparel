/**
 * Navigation Block System — Shared Utilities
 *
 * Error handling, DOM helpers, and focus/announcement helpers for the desktop
 * navigation block.
 *
 * @package Aggressive_Apparel
 */

// Type declaration for process in webpack environment.
declare const process:
  | {
      env: {
        NODE_ENV: string;
      };
    }
  | undefined;

import { ID_PREFIXES } from './constants';

// ============================================================================
// Error Handling
// ============================================================================

/**
 * Log a warning message in development mode.
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
 */
export function logError(message: string, error?: unknown): void {
  console.error(`[Navigation] ${message}`, error ?? '');
}

/**
 * Safely get an element by ID.
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

let _prefersReducedMotion: boolean | null = null;

/**
 * Check if user prefers reduced motion.
 */
export function prefersReducedMotion(): boolean {
  if (_prefersReducedMotion === null) {
    const mql = window.matchMedia('(prefers-reduced-motion: reduce)');
    _prefersReducedMotion = mql.matches;
    mql.addEventListener('change', e => {
      _prefersReducedMotion = e.matches;
    });
  }
  return _prefersReducedMotion;
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
// Focus Management
// ============================================================================

/**
 * Move focus to a menu item by index with roving tabindex.
 */
export function focusMenuItem(items: HTMLElement[], index: number): void {
  if (items.length === 0) {
    return;
  }

  const targetIndex = Math.max(0, Math.min(index, items.length - 1));

  items.forEach((item, i) => {
    item.setAttribute('tabindex', i === targetIndex ? '0' : '-1');
  });

  items[targetIndex].focus();
}

// ============================================================================
// Announcements
// ============================================================================

let announcementClearTimeout: ReturnType<typeof setTimeout> | null = null;

/**
 * Announce message to screen readers via a per-nav live region.
 */
export function announce(
  message: string,
  options: { assertive?: boolean; navId?: string } = {}
): void {
  const { assertive = false, navId } = options;
  const id = getAnnouncerId(navId ?? '');

  let announcer = safeGetElementById(id, false);
  if (!announcer) {
    announcer = safeGetElementById('navigation-announcer', false);
  }

  if (!announcer) {
    logWarning('announce: Announcer element not found', { id });
    return;
  }

  if (announcementClearTimeout) {
    clearTimeout(announcementClearTimeout);
  }

  announcer.setAttribute('aria-live', assertive ? 'assertive' : 'polite');
  announcer.textContent = message;

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
