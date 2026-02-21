/**
 * Navigation Block System — Shared Constants (v2)
 *
 * Centralized constants and selectors for the consolidated 3-block navigation.
 * Uses aa-nav__ prefix for elements rendered by navigation/render.php.
 *
 * @package Aggressive_Apparel
 */

// ============================================================================
// Block Selectors
// ============================================================================

/**
 * CSS selectors for navigation block elements.
 */
export const SELECTORS = {
  // Block root
  navigation: '.wp-block-aggressive-apparel-navigation',

  // Desktop menubar (rendered by navigation/render.php)
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

  // Toggle (rendered by navigation/render.php)
  toggle: '.aa-nav__toggle',

  // Panel (portaled to wp_footer by navigation/render.php)
  panel: '.aa-nav__panel',
  panelContent: '.aa-nav__panel-content',
  panelHeader: '.aa-nav__panel-header',
  panelBody: '.aa-nav__panel-body',
  panelMenu: '.aa-nav__panel-menu',
  panelOverlay: '.aa-nav__panel-overlay',
  panelClose: '.aa-nav__panel-close',

  // Drilldown
  drilldownHeader: '.wp-block-aggressive-apparel-nav-submenu__drilldown-header',
  drilldownBack: '.wp-block-aggressive-apparel-nav-submenu__back',
  drilldownTitle: '.wp-block-aggressive-apparel-nav-submenu__drilldown-title',

  // State classes
  isOpen: 'is-open',
  isActive: 'is-active',
  isCurrent: 'is-current',
  hasDrillStack: 'has-drill-stack',

  // Submenu type classes
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
 * Selector for menu items in the mobile panel.
 */
export const PANEL_MENU_ITEM_SELECTOR =
  `${SELECTORS.panelMenu} ${SELECTORS.navLinkAnchor}, ` +
  `${SELECTORS.panelMenu} ${SELECTORS.submenuLink}`;

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
 * Default transition duration in milliseconds (matches CSS).
 */
export const TRANSITION_DURATION_MS = 300;

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
// Type Exports
// ============================================================================

export type BodyClassKey = keyof typeof BODY_CLASSES;
