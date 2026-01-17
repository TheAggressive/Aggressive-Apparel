/**
 * Navigation Block Types
 *
 * Shared type definitions for the navigation block system.
 *
 * @package Aggressive_Apparel
 */

export interface NavigationAttributes {
  breakpoint: number;
  ariaLabel: string;
  openOn: 'hover' | 'click';
  navId?: string;
}

export interface NavigationContext {
  breakpoint: number;
  openOn: 'hover' | 'click';
  navId: string;
  // Mutable state in context
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
}

// HoverIntent is now defined in utils.ts
