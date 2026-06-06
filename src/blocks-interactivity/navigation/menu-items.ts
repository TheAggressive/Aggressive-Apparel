/**
 * Navigation Block — Menu Item Helpers
 *
 * Small DOM queries for collecting focusable menu items, shared by the
 * keyboard navigation logic in the store.
 *
 * @package Aggressive_Apparel
 */

import {
  SUBMENU_ITEM_SELECTOR,
  TOP_LEVEL_MENU_ITEM_SELECTOR,
} from './constants';
import { safeQuerySelectorAll } from './utils';

/**
 * Collect the top-level (or panel) menu items within a container.
 */
export function getMenuItems(container: Element): HTMLElement[] {
  return safeQuerySelectorAll<HTMLElement>(
    container,
    TOP_LEVEL_MENU_ITEM_SELECTOR
  );
}

/**
 * Collect the focusable items within a submenu panel.
 */
export function getSubmenuItems(panel: Element): HTMLElement[] {
  return safeQuerySelectorAll<HTMLElement>(panel, SUBMENU_ITEM_SELECTOR);
}
