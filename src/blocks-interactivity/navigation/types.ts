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
  /** When true the desktop nav auto-renders the mobile panel template part. */
  autoLoadMobilePanel: boolean;
  /** Slug of the template part holding the mobile navigation panel. */
  mobileNavPart: string;
  // Indicator
  indicatorColor?: string;
  // Submenu styling
  submenuBackgroundColor?: string;
  submenuTextColor?: string;
  submenuLinkHoverColor?: string;
  submenuLinkHoverBg?: string;
  submenuBorderRadius?: string;
  submenuBorderWidth?: string;
  submenuBorderColor?: string;
  submenuBorderStyle?: BorderStyle;
}

/**
 * Immutable per-instance config set by the navigation block via data-wp-context.
 * Mutable state lives in the global store (state._navs[navId]).
 *
 * Context inheritance chain:
 *   navigation/render.php sets:       { navId, breakpoint, openOn }
 *   nav-submenu-{type}/render.php adds: { submenuId, menuType }
 *   store callbacks call getContext<NavigationContext & SubmenuContext>() and
 *   see both — the Interactivity API merges ancestor context into child context.
 *   Fields cannot be reactive; all mutable per-nav state goes into _navs[navId].
 */
export interface NavigationContext {
  navId: string;
  breakpoint: number;
  openOn: 'hover' | 'click';
}

/**
 * Extra context fields injected by nav-submenu-dropdown and nav-submenu-mega.
 * These blocks use data-wp-context to add submenuId and menuType on top of the
 * inherited navigation context. Store callbacks read both by widening the type:
 *   getContext<NavigationContext & SubmenuContext>()
 */
export interface SubmenuContext {
  /** Matches the id attribute on the .wp-block-...-nav-submenu panel element. */
  submenuId: string;
  /** Selects the close/indicator behaviour: 'dropdown' clips; 'mega' spans viewport. */
  menuType: 'dropdown' | 'mega';
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

export interface NavigationI18n {
  submenuOpened?: string;
  submenuClosed?: string;
}

export interface NavigationState {
  isMobile: boolean;
  activeSubmenuId: string | null;
  /** Per-nav-instance mutable state keyed by navId. */
  _navs: Record<string, NavState>;
  i18n?: NavigationI18n;
}
