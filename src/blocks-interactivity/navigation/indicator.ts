/**
 * Navigation Block — Desktop Sliding Indicator
 *
 * Owns the animated underline that tracks the hovered/active top-level menu
 * item and widens to match an open submenu panel. Functions receive the
 * resolved nav state (or a getNavState resolver) so this module never imports
 * the reactive store directly.
 *
 * @package Aggressive_Apparel
 */

import { SELECTORS } from './constants';
import type { NavState } from './types';
import { clearHoverTimeouts } from './utils';
import { hoverIntent, indicatorRegistry } from './registries';

/**
 * Widen the indicator to match the submenu panel when it opens.
 * Works for both dropdown and mega menu panels — the indicator
 * stretches to match the panel's rendered width.
 *
 * For mega menus, also positions the panel at the viewport's left
 * edge by setting --mega-panel-left on the panel element.
 *
 * @param navId     Navigation instance id.
 * @param submenuId Submenu panel element id.
 * @param isMobile  Whether the nav is currently in its mobile breakpoint.
 */
export function expandIndicatorForSubmenu(
  navId: string,
  submenuId: string,
  isMobile: boolean
): void {
  if (isMobile) return;

  const panel = document.getElementById(submenuId) as HTMLElement | null;
  if (!panel) return;

  // Position mega menu panels to span the full viewport width.
  // Uses clientWidth instead of 100vw to exclude scrollbar width
  // and prevent horizontal overflow.
  const megaSubmenu = panel.closest(
    `.${SELECTORS.submenuMega}`
  ) as HTMLElement | null;
  if (megaSubmenu) {
    const liRect = megaSubmenu.getBoundingClientRect();
    const viewportWidth = document.documentElement.clientWidth;
    panel.style.setProperty('--mega-panel-left', `${-liRect.left}px`);
    panel.style.setProperty('--mega-panel-width', `${viewportWidth}px`);
  }

  const inst = indicatorRegistry.get(navId);
  if (!inst) return;

  // Measure after the browser has laid out the .is-open panel.
  requestAnimationFrame(() => {
    inst.widenToPanel(panel);
  });
}

/**
 * Reset the desktop indicator to its underline position when a submenu
 * closes.
 *
 * @param navId    Navigation instance id.
 * @param isMobile Whether the nav is currently in its mobile breakpoint.
 */
export function resetIndicatorOnClose(navId: string, isMobile: boolean): void {
  if (isMobile) return;

  const inst = indicatorRegistry.get(navId);
  if (!inst) return;

  inst.reset();
}

/**
 * Wire up the desktop sliding indicator for a navigation instance.
 *
 * Registers the indicator instance, binds hover/focus/resize listeners on the
 * menubar, and sets the initial underline position. No-op when the nav has no
 * menubar/indicator markup.
 *
 * @param navId       Navigation instance id.
 * @param nav         The <nav> root element.
 * @param getNavState Resolver for the instance's mutable panel state.
 * @return void
 */
export function setupDesktopIndicator(
  navId: string,
  nav: HTMLElement,
  getNavState: (navId: string) => NavState
): void {
  const menubar = nav.querySelector(SELECTORS.menubar) as HTMLElement | null;
  const indicator = nav.querySelector(
    SELECTORS.indicator
  ) as HTMLElement | null;

  if (!menubar || !indicator) {
    return;
  }

  // Slide indicator underline to match a menu item.
  const updateToItem = (item: HTMLElement) => {
    const menubarRect = menubar.getBoundingClientRect();
    const itemRect = item.getBoundingClientRect();
    indicator.style.setProperty(
      '--indicator-x',
      `${itemRect.left - menubarRect.left}px`
    );
    indicator.style.setProperty('--indicator-width', `${itemRect.width}px`);
    indicator.style.setProperty('--indicator-opacity', '1');
  };

  // Widen indicator to match a submenu panel's width.
  const widenToPanel = (panelEl: HTMLElement) => {
    const menubarRect = menubar.getBoundingClientRect();
    const panelRect = panelEl.getBoundingClientRect();
    indicator.style.setProperty(
      '--indicator-x',
      `${panelRect.left - menubarRect.left}px`
    );
    indicator.style.setProperty('--indicator-width', `${panelRect.width}px`);
  };

  // Reset to .is-current item or hide completely.
  const reset = () => {
    const currentLi = menubar.querySelector(
      ':scope > li.is-current'
    ) as HTMLElement | null;
    if (currentLi) {
      updateToItem(currentLi);
    } else {
      indicator.style.setProperty('--indicator-opacity', '0');
    }
  };

  // Register instance for actions to update the indicator.
  indicatorRegistry.set(navId, {
    menubar,
    indicator,
    updateToItem,
    widenToPanel,
    reset,
  });

  // Set initial position.
  reset();

  // Close active submenu and slide indicator to the new item.
  const closeAndSlideTo = (targetLi: HTMLElement) => {
    const navState = getNavState(navId);
    navState.activeSubmenuId = null;
    hoverIntent.activeId = null;
    clearHoverTimeouts(hoverIntent);
    updateToItem(targetLi);
  };

  // Shared handler for mouseenter/focusin on top-level items.
  const handleItemEnter = (li: HTMLElement) => {
    const navState = getNavState(navId);
    if (
      navState.activeSubmenuId &&
      !li.classList.contains('wp-block-aggressive-apparel-nav-submenu')
    ) {
      closeAndSlideTo(li);
      return;
    }
    updateToItem(li);
  };

  const topLevelItems = menubar.querySelectorAll(':scope > li');
  topLevelItems.forEach(li => {
    const link = li.querySelector('a, button') as HTMLElement | null;
    if (!link || li.classList.contains('aa-nav__indicator-wrap')) return;

    li.addEventListener('mouseenter', () => handleItemEnter(li as HTMLElement));
    li.addEventListener('focusin', () => handleItemEnter(li as HTMLElement));
  });

  menubar.addEventListener('mouseleave', () => {
    const navState = getNavState(navId);
    // Only reset if no submenu is open.
    if (!navState.activeSubmenuId) {
      reset();
    }
  });
  menubar.addEventListener('focusout', (e: FocusEvent) => {
    const related = e.relatedTarget as HTMLElement | null;
    if (!related || !menubar.contains(related)) {
      const navState = getNavState(navId);
      if (!navState.activeSubmenuId) {
        reset();
      }
    }
  });

  // Update on window resize: reset indicator and recalculate
  // mega panel width if a mega submenu is open.
  const onResize = () => {
    reset();
    const navState = getNavState(navId);
    if (navState.activeSubmenuId) {
      expandIndicatorForSubmenu(
        navId,
        navState.activeSubmenuId,
        navState.isMobile
      );
    }
  };
  window.addEventListener('resize', onResize, { passive: true });
}
