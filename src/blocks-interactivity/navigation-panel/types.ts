/**
 * Navigation Panel Block Types
 *
 * Shared type definitions for the mobile navigation panel.
 *
 * @package Aggressive_Apparel
 */

export type PanelPosition = 'left' | 'right';
export type PanelAnimationStyle = 'slide' | 'push' | 'reveal' | 'fade';
export type MenuStyle = 'panel' | 'fullscreen';

export interface NavigationPanelAttributes {
  panelSlug: string;
  position: PanelPosition;
  animationStyle: PanelAnimationStyle;
  menuStyle: MenuStyle;
  panelWidth: string;
  showOverlay: boolean;
  panelLinkHoverColor?: string;
  panelLinkHoverBg?: string;
  overlayColor?: string;
  overlayOpacity?: number;
  indicatorColor?: string;
}

/**
 * Immutable per-panel config passed via data-wp-context.
 * Mutable state lives in the global store (state._panels[panelSlug]).
 */
export interface PanelContext {
  panelSlug: string;
  /** Trigger-only: width below which the trigger button is shown. */
  breakpoint?: number;
}

/**
 * Per-panel mutable state, stored in state._panels[panelSlug].
 * Shared between the trigger button and the portaled panel.
 */
export interface PanelState {
  isOpen: boolean;
  activeSubmenuId: string | null;
  drillStack: string[];
}

export interface NavigationPanelStoreState {
  isOpen: boolean;
  activeSubmenuId: string | null;
  drillStack: string[];
  /** Per-panel mutable state keyed by panelSlug. */
  _panels: Record<string, PanelState>;
}
