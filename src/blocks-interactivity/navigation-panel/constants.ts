/**
 * Navigation Panel Block — Shared Constants
 *
 * Centralized constants and selectors for the mobile navigation panel.
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
  // Nav-link elements
  navLink: '.wp-block-aggressive-apparel-nav-link',
  navLinkAnchor: '.wp-block-aggressive-apparel-nav-link__link',

  // Nav-submenu elements — accordion and drilldown are the only submenu blocks
  // allowed in the navigation panel.
  navSubmenu:
    ':is(.wp-block-aggressive-apparel-nav-submenu-accordion, .wp-block-aggressive-apparel-nav-submenu-drilldown)',
  submenuLink:
    ':is(.wp-block-aggressive-apparel-nav-submenu-accordion__link, .wp-block-aggressive-apparel-nav-submenu-drilldown__link)',
  submenuTrigger:
    ':is(.wp-block-aggressive-apparel-nav-submenu-accordion__trigger, .wp-block-aggressive-apparel-nav-submenu-drilldown__trigger)',
  submenuPanel:
    ':is(.wp-block-aggressive-apparel-nav-submenu-accordion__panel, .wp-block-aggressive-apparel-nav-submenu-drilldown__panel)',
  submenuLabel:
    ':is(.wp-block-aggressive-apparel-nav-submenu-accordion__label, .wp-block-aggressive-apparel-nav-submenu-drilldown__label)',

  // Panel (portaled to wp_footer by navigation-panel/render.php)
  panel: '.aa-nav__panel',
  panelContent: '.aa-nav__panel-content',
  panelHeader: '.aa-nav__panel-header',
  panelBody: '.aa-nav__panel-body',
  panelMenu: '.aa-nav__panel-menu',
  panelOverlay: '.aa-nav__panel-overlay',
  panelClose: '.aa-nav__panel-close',

  // Drilldown
  drilldownBackButton:
    '.wp-block-aggressive-apparel-nav-submenu-drilldown__back-button',
  drilldownBackLabel:
    '.wp-block-aggressive-apparel-nav-submenu-drilldown__back-label',

  // State classes
  isOpen: 'is-open',
  isActive: 'is-active',
  hasDrillStack: 'has-drill-stack',

  // Submenu type classes
  submenuDrilldown: '.wp-block-aggressive-apparel-nav-submenu-drilldown',
  submenuMegaContent: 'wp-block-aggressive-apparel-nav-submenu--mega-content',
} as const;

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
