/**
 * Navigation Panel Block — Open/Close Lifecycle Helpers
 *
 * Open/close lifecycle for the portaled mobile panel, including focus trap
 * setup, inert management, and body scroll locking. Functions receive the
 * resolved PanelState so this module stays independent of the reactive store.
 *
 * @package Aggressive_Apparel
 */

import { PANEL_MENU_ITEM_SELECTOR, SELECTORS, getPanelId } from './constants';
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
  setPanelInert,
  setPanelVisibility,
  setupFocusTrap,
  setupSwipeToClose,
  updateDrilldownInertState,
  updateMegaContentInertState,
} from './utils';
import { focusTrapRegistry, swipeRegistry } from './registries';

/**
 * Find the panel element for this panel slug.
 * Panel is portaled to wp_footer, found by ID (DOM-position independent).
 */
export function findPanel(panelSlug: string): HTMLElement | null {
  return safeGetElementById(getPanelId(panelSlug), false);
}

/**
 * Close the navigation panel with full cleanup.
 */
export function closePanelWithCleanup(
  panelSlug: string,
  panel: HTMLElement,
  ps: PanelState
): void {
  ps.isOpen = false;
  ps.activeSubmenuId = null;
  const hadDrillStack = ps.drillStack.length > 0;
  ps.drillStack = [];
  setBodyOverflow(false);

  // Notify drilldown submenu elements to remove their is-open class. The
  // reactive data-wp-class binding doesn't cross the portal boundary reliably,
  // so submenus update via this custom event. Without this dispatch, closing the
  // panel leaves is-open on any expanded drilldown — it appears open next open.
  if (hadDrillStack) {
    window.dispatchEvent(
      new CustomEvent('aa-nav-panel-state-change', {
        detail: { panelSlug },
      })
    );
  }

  setPanelVisibility(panel, false);
  setPanelInert(panel, true);

  // Reset scroll position so the next open always starts at the top.
  const panelBody = safeQuerySelector<HTMLElement>(
    panel,
    '.aa-nav__panel-body',
    false
  );
  if (panelBody) {
    panelBody.scrollTop = 0;
  }

  // Clean up focus trap.
  const existingCleanup = focusTrapRegistry.get(panel);
  if (existingCleanup) {
    existingCleanup();
    focusTrapRegistry.delete(panel);
  }

  // Clean up swipe-to-close listeners.
  const existingSwipeCleanup = swipeRegistry.get(panel);
  if (existingSwipeCleanup) {
    existingSwipeCleanup();
    swipeRegistry.delete(panel);
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

  // Defer push/reveal body class and CSS variable removal until after the exit
  // transition completes. Removing the class synchronously would snap the page
  // content back to its original position before the panel finishes sliding out.
  setTimeout(() => {
    if (!panel.classList.contains('is-open')) {
      removeAllBodyClasses();
      document.body.style.removeProperty('--push-panel-width');
    }
  }, getTransitionDuration());

  announce('Navigation menu closed', { assertive: true, panelSlug });
  restoreFocus(panelSlug);
}

/**
 * Open the navigation panel with full setup.
 */
export function openPanelWithSetup(
  panelSlug: string,
  panel: HTMLElement,
  ps: PanelState,
  onClose?: () => void
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
  setPanelInert(panel, false);

  // Set up focus trap.
  const existingCleanup = focusTrapRegistry.get(panel);
  if (existingCleanup) {
    existingCleanup();
    focusTrapRegistry.delete(panel);
  }
  const cleanup = setupFocusTrap(panel);
  focusTrapRegistry.set(panel, cleanup);

  // Set up swipe-to-close.
  const existingSwipeCleanup = swipeRegistry.get(panel);
  if (existingSwipeCleanup) {
    existingSwipeCleanup();
    swipeRegistry.delete(panel);
  }
  if (onClose) {
    const swipeCleanup = setupSwipeToClose(panel, onClose);
    swipeRegistry.set(panel, swipeCleanup);
  }

  // Apply inert to main content. The panel is portaled outside .wp-site-blocks
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
  updateDrilldownInertState(panel, ps.drillStack);

  // Put focus on the close button once the panel animation starts. This gives
  // keyboard and screen-reader users an immediate, predictable way back out.
  // Fall back to the first menu item if custom markup omits the close button.
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      const initialFocus =
        safeQuerySelector<HTMLElement>(panel, SELECTORS.panelClose, false) ??
        safeQuerySelector<HTMLElement>(
          panel,
          PANEL_MENU_ITEM_SELECTOR,
          false
        );
      // preventScroll: the panel slides in from off-screen; letting focus
      // scroll it into view cancels the slide animation.
      initialFocus?.focus({ preventScroll: true });
    });
  });
}
