/**
 * Navigation Block System — Shared Constants
 *
 * Centralized constants and selectors for the navigation block system.
 * This file ensures consistency across all navigation blocks and simplifies maintenance.
 *
 * @package Aggressive_Apparel
 */

// ============================================================================
// Block Selectors
// ============================================================================

/**
 * CSS selectors for navigation block elements.
 * Use data attributes where possible for stability.
 */
export const SELECTORS = {
  // Block root elements
  navigation: '.wp-block-aggressive-apparel-navigation',
  navigationPanel: '.wp-block-aggressive-apparel-navigation-panel',
  menuToggle: '.wp-block-aggressive-apparel-menu-toggle',
  navMenu: '.wp-block-aggressive-apparel-nav-menu',
  navLink: '.wp-block-aggressive-apparel-nav-link',
  navSubmenu: '.wp-block-aggressive-apparel-nav-submenu',
  megaMenuContent: '.wp-block-aggressive-apparel-mega-menu-content',

  // Interactive elements
  navLinkAnchor: '.wp-block-aggressive-apparel-nav-link__link',
  submenuLink: '.wp-block-aggressive-apparel-nav-submenu__link',
  submenuTrigger: '.wp-block-aggressive-apparel-nav-submenu__trigger',
  submenuPanel: '.wp-block-aggressive-apparel-nav-submenu__panel',
  submenuLabel: '.wp-block-aggressive-apparel-nav-submenu__label',

  // Panel elements
  panelContent: '.wp-block-aggressive-apparel-navigation-panel__content',
  panelHeader: '.wp-block-aggressive-apparel-navigation-panel__header',
  panelBody: '.wp-block-aggressive-apparel-panel-body',
  panelOverlay: '.wp-block-aggressive-apparel-navigation-panel__overlay',
  panelClose: '.wp-block-aggressive-apparel-navigation-panel__close',
  drilldownHeader: '.wp-block-aggressive-apparel-nav-submenu__drilldown-header',
  drilldownBack: '.wp-block-aggressive-apparel-nav-submenu__back',
  drilldownTitle: '.wp-block-aggressive-apparel-nav-submenu__drilldown-title',

  // State classes
  isOpen: 'is-open',
  isActive: 'is-active',
  hasDrillStack: 'has-drill-stack',

  // Orientation classes
  menuHorizontal: 'wp-block-aggressive-apparel-nav-menu--horizontal',
  menuVertical: 'wp-block-aggressive-apparel-nav-menu--vertical',

  // Menu type classes
  submenuDropdown: 'wp-block-aggressive-apparel-nav-submenu--dropdown',
  submenuMega: 'wp-block-aggressive-apparel-nav-submenu--mega',
  submenuDrilldown: 'wp-block-aggressive-apparel-nav-submenu--drilldown',
} as const;

/**
 * Selector for all focusable elements.
 */
export const FOCUSABLE_SELECTOR =
  'a[href], button:not([disabled]), input:not([disabled]), ' +
  'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Selector for menu items in the panel body.
 */
export const PANEL_MENU_ITEM_SELECTOR =
  `${SELECTORS.panelBody} ${SELECTORS.navLinkAnchor}, ` +
  `${SELECTORS.panelBody} ${SELECTORS.submenuLink}`;

/**
 * Selector for top-level menu items (direct children).
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
 * Default transition duration in milliseconds (matches CSS).
 */
export const TRANSITION_DURATION_MS = 300;

/**
 * Hover intent delays.
 */
export const HOVER_INTENT = {
  /** Delay before opening on hover (ms) */
  openDelay: 50,
  /** Delay before closing on hover leave (ms) */
  closeDelay: 150,
  /** Reduced motion variants */
  reducedMotion: {
    openDelay: 0,
    closeDelay: 50,
  },
} as const;

/**
 * Announcement clear delay for screen readers (ms).
 */
export const ANNOUNCEMENT_CLEAR_DELAY = 5000;

// ============================================================================
// Body Classes
// ============================================================================

/**
 * Body classes for push/reveal animations.
 */
export const BODY_CLASSES = {
  pushLeft: 'has-navigation-panel-open--push-left',
  pushRight: 'has-navigation-panel-open--push-right',
  revealLeft: 'has-navigation-panel-open--reveal-left',
  revealRight: 'has-navigation-panel-open--reveal-right',
} as const;

/**
 * All body animation classes as an array for easy removal.
 */
export const ALL_BODY_CLASSES = Object.values(BODY_CLASSES);

// ============================================================================
// Keyboard Keys
// ============================================================================

/**
 * Keyboard keys used for navigation.
 */
export const KEYS = {
  escape: 'Escape',
  tab: 'Tab',
  enter: 'Enter',
  space: ' ',
  arrowUp: 'ArrowUp',
  arrowDown: 'ArrowDown',
  arrowLeft: 'ArrowLeft',
  arrowRight: 'ArrowRight',
  home: 'Home',
  end: 'End',
} as const;

/**
 * Arrow keys for menu navigation.
 */
export const ARROW_KEYS = [
  KEYS.arrowUp,
  KEYS.arrowDown,
  KEYS.arrowLeft,
  KEYS.arrowRight,
  KEYS.home,
  KEYS.end,
] as const;

// ============================================================================
// Default Values
// ============================================================================

/**
 * Default breakpoint for mobile navigation (px).
 */
export const DEFAULT_BREAKPOINT = 1024;

/**
 * Default panel width.
 */
export const DEFAULT_PANEL_WIDTH = 'min(320px, 85vw)';

// ============================================================================
// ID Generation
// ============================================================================

/**
 * Prefixes for generated IDs.
 */
export const ID_PREFIXES = {
  navigation: 'nav-',
  panel: '-panel',
  menuToggle: 'menu-toggle-',
  announcer: 'navigation-announcer-',
} as const;

// ============================================================================
// Custom Events
// ============================================================================

/**
 * Custom event names for navigation state sync.
 */
export const EVENTS = {
  stateChange: 'aa-nav-state-change',
} as const;

// ============================================================================
// Type Exports
// ============================================================================

export type BodyClassKey = keyof typeof BODY_CLASSES;
export type SelectorKey = keyof typeof SELECTORS;
