/**
 * Navigation Block System — Shared Constants (v3)
 *
 * Centralized constants and selectors for the desktop navigation block.
 *
 * @package Aggressive_Apparel
 */

// Shared across both navigation subsystems — re-exported so existing
// `from './constants'` imports keep resolving.
export {
  ARROW_KEYS,
  FOCUSABLE_SELECTOR,
  KEYS,
  TRANSITION_DURATION_MS,
} from '../nav-shared/keys';

// ============================================================================
// Block Selectors
// ============================================================================

export const SELECTORS = {
  // Block root
  navigation: '.wp-block-aggressive-apparel-navigation',

  // Desktop menubar
  menubar: '.aa-nav__menubar',
  indicator: '.aa-nav__indicator',

  // Nav-link elements
  navLink: '.wp-block-aggressive-apparel-nav-link',
  navLinkAnchor: '.wp-block-aggressive-apparel-nav-link__link',

  // Nav-submenu elements
  navSubmenu: '.wp-block-aggressive-apparel-nav-submenu',
  submenuLink: '.wp-block-aggressive-apparel-nav-submenu__link',
  submenuTrigger: '.wp-block-aggressive-apparel-nav-submenu__trigger',
  submenuPanel: '.wp-block-aggressive-apparel-nav-submenu__panel',
  submenuLabel: '.wp-block-aggressive-apparel-nav-submenu__label',

  // State classes
  isOpen: 'is-open',
  isActive: 'is-active',
  isCurrent: 'is-current',

  // Submenu type classes
  submenuDropdown: 'wp-block-aggressive-apparel-nav-submenu--dropdown',
  submenuMega: 'wp-block-aggressive-apparel-nav-submenu--mega',
} as const;

/**
 * Selector for top-level menu items in the desktop menubar.
 */
export const TOP_LEVEL_MENU_ITEM_SELECTOR =
  `:scope > li > ${SELECTORS.navLinkAnchor}, ` +
  `:scope > li > ${SELECTORS.submenuTrigger} ${SELECTORS.submenuLink}`;

/**
 * Selector for submenu items.
 */
export const SUBMENU_ITEM_SELECTOR = `${SELECTORS.navLinkAnchor}, ${SELECTORS.submenuLink}`;

// ============================================================================
// Timing Constants
// ============================================================================

/**
 * Indicator animation timing.
 */
export const INDICATOR_DURATION_MS = 250;

/**
 * Hover intent delays.
 */
export const HOVER_INTENT = {
  /** Delay before opening on initial hover — requires dwell (ms) */
  openDelay: 200,
  /** Shorter delay when switching between open submenus (ms) */
  switchDelay: 80,
  /** Delay before closing on hover leave (ms) */
  closeDelay: 300,
  /** Reduced motion variants */
  reducedMotion: {
    openDelay: 0,
    switchDelay: 0,
    closeDelay: 50,
  },
} as const;

// ============================================================================
// Default Values
// ============================================================================

/**
 * Default breakpoint for mobile navigation (px).
 */
export const DEFAULT_BREAKPOINT = 1024;

// ============================================================================
// ID Generation
// ============================================================================

/**
 * Prefixes for generated IDs.
 */
export const ID_PREFIXES = {
  navigation: 'nav-',
  announcer: 'navigation-announcer-',
} as const;
