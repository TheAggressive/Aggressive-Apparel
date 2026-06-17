/**
 * Navigation Block Types
 *
 * Shared type definitions for the desktop navigation block.
 *
 * @package Aggressive_Apparel
 */

export type BorderStyle = 'solid' | 'dashed' | 'dotted' | 'none';
export type ScrollBehavior =
  | 'none'
  | 'sticky'
  | 'hide-on-scroll'
  | 'transparent-to-opaque';

export interface NavigationAttributes {
  breakpoint: number;
  ariaLabel: string;
  openOn: 'hover' | 'click';
  navId?: string;
  scrollBehavior: ScrollBehavior;
  // Indicator
  indicatorColor?: string;
  // Submenu styling
  submenuBackgroundColor?: string;
  submenuTextColor?: string;
  submenuBorderRadius?: string;
  submenuBorderWidth?: string;
  submenuBorderColor?: string;
  submenuBorderStyle?: BorderStyle;
}

/**
 * Immutable per-instance config passed via data-wp-context.
 * Mutable state lives in the global store (state._navs[navId]).
 */
export interface NavigationContext {
  navId: string;
  breakpoint: number;
  openOn: 'hover' | 'click';
}

/**
 * Per-navigation mutable state, stored in state._navs[navId].
 * - isMobile disables hover-based submenu opening below the breakpoint.
 * - activeSubmenuId tracks which desktop dropdown/mega is open.
 */
export interface NavState {
  isMobile: boolean;
  activeSubmenuId: string | null;
}

export interface NavigationState {
  isMobile: boolean;
  activeSubmenuId: string | null;
  /** Per-nav-instance mutable state keyed by navId. */
  _navs: Record<string, NavState>;
}
