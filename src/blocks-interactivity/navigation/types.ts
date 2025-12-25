/**
 * Ultimate Navigation Block - TypeScript Types
 *
 * Shared type definitions for the navigation block and its child blocks.
 *
 * @package Aggressive Apparel
 */

// =============================================================================
// ENUM TYPES
// =============================================================================

/**
 * Sticky header behavior options
 */
export type StickyBehavior = 'none' | 'always' | 'scroll-up';

/**
 * Mobile menu presentation style
 */
export type MobileMenuType = 'drawer' | 'fullscreen' | 'dropdown';

/**
 * How submenus open on desktop
 */
export type SubmenuOpenBehavior = 'hover' | 'click';

/**
 * Submenu expansion animation type
 */
export type SubmenuExpandType = 'flyout' | 'accordion' | 'drill-down';

// =============================================================================
// ATTRIBUTE INTERFACES
// =============================================================================

/**
 * Main navigation block attributes
 */
export interface NavigationAttributes {
  // Layout & Behavior
  mobileBreakpoint: number;
  stickyBehavior: StickyBehavior;
  stickyOffset: number;
  mobileMenuType: MobileMenuType;
  submenuOpenBehavior: SubmenuOpenBehavior;
  submenuExpandType: SubmenuExpandType;
  animationDuration: number;
  showSearch: boolean;
  showCart: boolean;
  overlayOpacity: number;

  // Hover State Colors (custom attributes)
  hoverTextColor?: string;
  hoverBackgroundColor?: string;
  activeTextColor?: string;
  activeBackgroundColor?: string;

  // Submenu Colors
  submenuBackgroundColor?: string;
  submenuTextColor?: string;
  submenuHoverTextColor?: string;
  submenuHoverBackgroundColor?: string;

  // Mobile Menu Colors
  mobileMenuBackgroundColor?: string;
  mobileMenuTextColor?: string;
}

/**
 * Navigation item block attributes
 */
export interface NavigationItemAttributes {
  label: string;
  url: string;
  opensInNewTab: boolean;
  isCurrentPage: boolean;
  description?: string;
  icon?: string;
  rel?: string;
  title?: string;
}

/**
 * Navigation submenu block attributes
 */
export interface NavigationSubmenuAttributes {
  label: string;
  url?: string;
  alignment: 'left' | 'center' | 'right';
  width: 'auto' | 'full' | '200px' | '250px' | '300px';
  listBackgroundColor?: string;
  listTextColor?: string;
  listPadding?: { top: string; right: string; bottom: string; left: string };
  listMargin?: { top: string; right: string; bottom: string; left: string };
  icon?: string;
}

/**
 * Navigation mega menu block attributes
 */
export interface NavigationMegaMenuAttributes {
  label: string;
  columns: number;
  showFeaturedImage: boolean;
  featuredImage?: {
    id: number;
    url: string;
    alt: string;
  };
  contentBackgroundColor?: string;
  contentTextColor?: string;
  contentPadding?: {
    top?: string;
    right?: string;
    bottom?: string;
    left?: string;
  };
  contentMargin?: {
    top?: string;
    right?: string;
    bottom?: string;
    left?: string;
  };
}

// =============================================================================
// CONTEXT INTERFACES (Interactivity API)
// =============================================================================

/**
 * Main navigation runtime context
 */
export interface NavigationContext extends NavigationAttributes {
  // Runtime state
  isMobileMenuOpen: boolean;
  activeSubmenuStack: string[]; // For drill-down navigation
  isMobile: boolean;
  isSticky: boolean;
  lastScrollY: number;
  scrollDirection: 'up' | 'down';
  instanceId: string;
}

/**
 * Submenu/MegaMenu runtime context (shared by both block types)
 */
export interface SubmenuContext {
  submenuId: string;
  isOpen: boolean;
  expandType: SubmenuExpandType;
  hasChildren: boolean;
}

/**
 * Mega menu runtime context (extends submenu context)
 */
export interface MegaMenuContext {
  megaMenuId: string;
  isOpen: boolean;
}

// =============================================================================
// STORE INTERFACES
// =============================================================================

/**
 * Actions for the navigation store
 */
export interface NavigationActions {
  toggleMobileMenu: () => void;
  openMobileMenu: () => void;
  closeMobileMenu: () => void;
  closeAllMenus: () => void;
  navigateDrillUp: () => void;
  handleEscapeKey: () => void;
  handleFocusTrap: () => void;
  toggleSearch: () => void;
}

/**
 * Actions for submenu store (used by both submenu and mega-menu)
 */
export interface SubmenuActions {
  toggleSubmenu: () => void;
  openSubmenu: () => void;
  closeSubmenu: () => void;
  toggleAccordion: () => void;
  handleSubmenuHover: () => void;
  scheduleSubmenuClose: () => void;
  navigateDrillDown: () => void;
}

/**
 * Callbacks for the navigation store
 */
export interface NavigationCallbacks {
  init: () => void;
  handleScroll: () => void;
  handleResize: () => void;
  cleanup: () => void;
}

// =============================================================================
// TYPE GUARDS
// =============================================================================

/**
 * Type guard for StickyBehavior
 */
export function isStickyBehavior(value: unknown): value is StickyBehavior {
  return ['none', 'always', 'scroll-up'].includes(value as string);
}

/**
 * Type guard for MobileMenuType
 */
export function isMobileMenuType(value: unknown): value is MobileMenuType {
  return ['drawer', 'fullscreen', 'dropdown'].includes(value as string);
}

/**
 * Type guard for SubmenuOpenBehavior
 */
export function isSubmenuOpenBehavior(
  value: unknown
): value is SubmenuOpenBehavior {
  return ['hover', 'click'].includes(value as string);
}

/**
 * Type guard for SubmenuExpandType
 */
export function isSubmenuExpandType(
  value: unknown
): value is SubmenuExpandType {
  return ['flyout', 'accordion', 'drill-down'].includes(value as string);
}

// =============================================================================
// UTILITY TYPES
// =============================================================================

/**
 * CSS custom properties for navigation styling
 */
export interface NavigationCSSVars {
  '--nav-height': string;
  '--nav-height-sticky': string;
  '--nav-transition': string;
  '--nav-overlay-opacity': string;
  '--nav-hover-text': string;
  '--nav-hover-bg': string;
  '--nav-active-text': string;
  '--nav-active-bg': string;
  '--submenu-bg': string;
  '--submenu-text': string;
  '--submenu-hover-text': string;
  '--submenu-hover-bg': string;
}
