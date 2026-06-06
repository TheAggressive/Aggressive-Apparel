/**
 * Navigation Block — Mobile Panel Helpers
 *
 * Open/close lifecycle for the portaled mobile panel, including focus trap
 * setup, inert management, and body scroll locking. Functions receive the
 * resolved PanelState so this module stays independent of the reactive store.
 *
 * @package Aggressive_Apparel
 */

import { PANEL_MENU_ITEM_SELECTOR } from './constants';
import type { PanelState } from './types';
import {
  addBodyClass,
  announce,
  getTransitionDuration,
  removeAllBodyClasses,
  restoreFocus,
  safeGetElementById,
  safeQuerySelector,
  setBodyOverflow,
  setPanelVisibility,
  setupFocusTrap,
  updateDrilldownInertState,
  updateMegaContentInertState,
} from './utils';
import { focusTrapRegistry } from './registries';

/**
 * Find the panel element for this navigation instance.
 * Panel is portaled to wp_footer, found by ID (DOM-position independent).
 */
export function findPanel(navId: string): HTMLElement | null {
  return safeGetElementById(`${navId}-panel`, false);
}

/**
 * Close the navigation panel with full cleanup.
 */
export function closePanelWithCleanup(
  navId: string,
  panel: HTMLElement,
  ns: PanelState
): void {
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

  // Reset drilldown and mega-content inert state.
  updateDrilldownInertState(panel, []);
  updateMegaContentInertState(panel, null);

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
export function openPanelWithSetup(
  navId: string,
  panel: HTMLElement,
  ns: PanelState
): void {
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

  // Focus first focusable element after panel animation starts.
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
