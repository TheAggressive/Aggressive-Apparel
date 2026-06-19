/**
 * Navigation Block System — Shared Utilities
 *
 * Error handling, DOM helpers, and focus/announcement helpers for the desktop
 * navigation block.
 *
 * @package Aggressive_Apparel
 */

import { ID_PREFIXES } from './constants';
import {
  logError,
  logWarning,
  prefersReducedMotion,
  safeGetElementById,
  safeQuerySelector,
  safeQuerySelectorAll,
} from '../nav-shared/dom';

// Re-export the shared DOM/logging helpers so existing `from './utils'` imports
// across this subsystem keep resolving unchanged.
export {
  logError,
  logWarning,
  prefersReducedMotion,
  safeGetElementById,
  safeQuerySelector,
  safeQuerySelectorAll,
};

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
