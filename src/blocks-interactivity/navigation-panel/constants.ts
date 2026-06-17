/**
 * Navigation Panel Block — Shared Constants
 *
 * Centralized constants and selectors for the mobile navigation panel.
 *
 * @package Aggressive_Apparel
 */

// ============================================================================
// Block Selectors
// ============================================================================

export const SELECTORS = {
  // Nav-link elements
  navLink: '.wp-block-aggressive-apparel-nav-link',
  navLinkAnchor: '.wp-block-aggressive-apparel-nav-link__link',

  // Nav-submenu elements — selectors cover the legacy nav-submenu block AND
  // the new dedicated accordion/drilldown blocks.
  navSubmenu:
    ':is(.wp-block-aggressive-apparel-nav-submenu, .wp-block-aggressive-apparel-nav-submenu-accordion, .wp-block-aggressive-apparel-nav-submenu-drilldown)',
  submenuLink:
    ':is(.wp-block-aggressive-apparel-nav-submenu__link, .wp-block-aggressive-apparel-nav-submenu-accordion__link, .wp-block-aggressive-apparel-nav-submenu-drilldown__link)',
  submenuTrigger:
    ':is(.wp-block-aggressive-apparel-nav-submenu__trigger, .wp-block-aggressive-apparel-nav-submenu-accordion__trigger, .wp-block-aggressive-apparel-nav-submenu-drilldown__trigger)',
  submenuPanel:
    ':is(.wp-block-aggressive-apparel-nav-submenu__panel, .wp-block-aggressive-apparel-nav-submenu-accordion__panel, .wp-block-aggressive-apparel-nav-submenu-drilldown__panel)',
  submenuLabel:
    ':is(.wp-block-aggressive-apparel-nav-submenu__label, .wp-block-aggressive-apparel-nav-submenu-accordion__label, .wp-block-aggressive-apparel-nav-submenu-drilldown__label)',

  // Panel (portaled to wp_footer by navigation-panel/render.php)
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
  hasDrillStack: 'has-drill-stack',

  // Submenu type classes
  submenuDrilldown:
    ':is(.wp-block-aggressive-apparel-nav-submenu--drilldown, .wp-block-aggressive-apparel-nav-submenu-drilldown)',
  submenuMegaContent: 'wp-block-aggressive-apparel-nav-submenu--mega-content',
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
 * Selector for top-level (vertical) panel menu items.
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
export const TRANSITION_DURATION_MS = 400;

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
// ID Helpers
// ============================================================================

/**
 * Build the panel element ID from a panel slug.
 */
export function getPanelId(panelSlug: string): string {
  return `${panelSlug}-panel`;
}

/**
 * Build the trigger button ID from a panel slug.
 */
export function getTriggerId(panelSlug: string): string {
  return `nav-trigger-${panelSlug}`;
}

/**
 * Build the announcer element ID from a panel slug.
 */
export function getAnnouncerId(panelSlug: string): string {
  return `${panelSlug}-announcer`;
}

// ============================================================================
// Type Exports
// ============================================================================

export type BodyClassKey = keyof typeof BODY_CLASSES;
