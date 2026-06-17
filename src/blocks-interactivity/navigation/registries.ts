/**
 * Navigation Block — Module Registries
 *
 * Process-wide registries shared by the navigation store and its helper
 * modules. Kept separate from the store so panel, indicator, and keyboard
 * helpers can be unit-reasoned without importing the reactive store.
 *
 * @package Aggressive_Apparel
 */

import { createHoverIntent, type HoverIntentState } from './utils';

// Hover intent state (prevents race conditions between open/close timers).
export const hoverIntent: HoverIntentState = createHoverIntent();

/**
 * MediaQueryList reference plus its change handler, stored per <nav> element
 * so listeners can be cleaned up on unmount.
 */
export interface MediaQueryRegistryEntry {
  mql: MediaQueryList;
  handler: (event: MediaQueryListEvent | MediaQueryList) => void;
}

export const mediaQueryRegistry = new WeakMap<
  Element,
  MediaQueryRegistryEntry
>();

/**
 * Per-navigation indicator references and helpers.
 * Stored so actions (openSubmenu, closeSubmenu, hover) can update the indicator.
 */
export interface IndicatorInstance {
  menubar: HTMLElement;
  indicator: HTMLElement;
  /** Move indicator underline to match an item's bounds. */
  updateToItem: (item: HTMLElement) => void;
  /** Widen indicator to match a submenu panel's width. */
  widenToPanel: (panel: HTMLElement) => void;
  /** Reset indicator to .is-current item or hide. */
  reset: () => void;
}

export const indicatorRegistry = new Map<string, IndicatorInstance>();
