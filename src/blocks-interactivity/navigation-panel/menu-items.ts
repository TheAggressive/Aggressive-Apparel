/**
 * Navigation Panel Block — Menu Item Helpers
 *
 * Small DOM queries for collecting focusable menu items, shared by the
 * keyboard navigation logic in the panel store.
 *
 * @package Aggressive_Apparel
 */

import {
  SELECTORS,
  SUBMENU_ITEM_SELECTOR,
  TOP_LEVEL_MENU_ITEM_SELECTOR,
} from './constants';
import { safeQuerySelectorAll } from './utils';

/**
 * Collect the top-level (panel) menu items within a container.
 */
export function getMenuItems(container: Element): HTMLElement[] {
  return safeQuerySelectorAll<HTMLElement>(
    container,
    TOP_LEVEL_MENU_ITEM_SELECTOR
  );
}

/**
 * Collect the top-level items of every menu list in the panel body.
 *
 * Content sections can split the panel menu into several lists; arrow keys
 * move through them as one menu.
 */
export function getPanelMenuItems(menu: Element): HTMLElement[] {
  const body = menu.closest(SELECTORS.panelBody);
  if (!body) {
    return getMenuItems(menu);
  }
  return safeQuerySelectorAll<HTMLElement>(
    body,
    `:scope > ${SELECTORS.panelMenu}`
  ).flatMap(getMenuItems);
}

/**
 * Collect the focusable items within a submenu panel.
 */
export function getSubmenuItems(panel: Element): HTMLElement[] {
  return safeQuerySelectorAll<HTMLElement>(panel, SUBMENU_ITEM_SELECTOR);
}
