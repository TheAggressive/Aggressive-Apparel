/**
 * Navigation Block Types
 *
 * Shared type definitions for the navigation block system.
 *
 * @package Aggressive_Apparel
 */

export type BorderStyle = 'solid' | 'dashed' | 'dotted' | 'none';
export type ToggleIconStyle =
  | 'hamburger'
  | 'dots'
  | 'squeeze'
  | 'arrow'
  | 'collapse';
export type ToggleAnimationType =
  | 'to-x'
  | 'spin'
  | 'squeeze'
  | 'arrow-left'
  | 'arrow-right'
  | 'collapse'
  | 'none';
export type PanelPosition = 'left' | 'right';
export type PanelAnimationStyle = 'slide' | 'push' | 'reveal' | 'fade';
export type MobileSyncMode = 'auto' | 'custom';
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
  // Toggle (absorbed from menu-toggle)
  toggleLabel: string;
  toggleIconStyle: ToggleIconStyle;
  toggleAnimationType: ToggleAnimationType;
  showToggleLabel: boolean;
  // Panel (absorbed from navigation-panel)
  panelPosition: PanelPosition;
  panelAnimationStyle: PanelAnimationStyle;
  panelWidth: string;
  showPanelOverlay: boolean;
  // Mobile sync
  mobileSyncMode: MobileSyncMode;
  // Scroll behavior
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
  // Panel colors
  panelBackgroundColor?: string;
  panelTextColor?: string;
  panelLinkHoverColor?: string;
  panelLinkHoverBg?: string;
  panelOverlayColor?: string;
  panelOverlayOpacity?: number;
}

/**
 * Immutable per-instance config passed via data-wp-context.
 * Mutable state lives in the global store (state._panels[navId]).
 */
export interface NavigationContext {
  navId: string;
  breakpoint: number;
  openOn: 'hover' | 'click';
}

/**
 * Per-navigation mutable state, stored in state._panels[navId].
 * Shared between the <nav> element and the portaled panel.
 */
export interface PanelState {
  isOpen: boolean;
  isMobile: boolean;
  activeSubmenuId: string | null;
  drillStack: string[];
}

export interface NavigationState {
  isOpen: boolean;
  isMobile: boolean;
  activeSubmenuId: string | null;
  drillStack: string[];
  /** For screen reader announcements. */
  announcement: string;
  /** Per-nav-instance mutable state keyed by navId. */
  _panels: Record<string, PanelState>;
}
