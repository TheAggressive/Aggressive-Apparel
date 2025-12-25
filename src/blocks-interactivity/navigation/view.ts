/**
 * Ultimate Navigation Block - Interactivity API Store
 *
 * Frontend interactions for navigation, mobile menu, and sticky behavior.
 * Submenu-specific actions are defined in navigation-submenu/view.ts.
 *
 * @package Aggressive Apparel
 */

/// <reference types="@wordpress/interactivity" />
import { getContext, getElement, store } from '@wordpress/interactivity';
import type { NavigationContext } from './types';

// =============================================================================
// CONSTANTS
// =============================================================================

const SCROLL_THRESHOLD = 100;

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

/**
 * Check if viewport is mobile based on breakpoint
 */
function checkIsMobile(breakpoint: number): boolean {
  return window.innerWidth < breakpoint;
}

/**
 * Get focusable elements within a container
 */
function getFocusableElements(container: HTMLElement): HTMLElement[] {
  const selector =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
  return Array.from(container.querySelectorAll<HTMLElement>(selector));
}

/**
 * Trap focus within an element
 */
function trapFocus(container: HTMLElement, event: KeyboardEvent): void {
  const focusable = getFocusableElements(container);
  if (focusable.length === 0) return;

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault();
    first.focus();
  }
}

// =============================================================================
// INTERACTIVITY STORE
// =============================================================================

const { state, actions } = store('aggressive-apparel/navigation', {
  state: {
    get isMobileMenuOpen(): boolean {
      const ctx = getContext<NavigationContext>();
      return ctx.isMobileMenuOpen;
    },

    get isSticky(): boolean {
      const ctx = getContext<NavigationContext>();
      return ctx.isSticky;
    },

    get isMobile(): boolean {
      const ctx = getContext<NavigationContext>();
      return ctx.isMobile;
    },

    get activeSubmenuStack(): string[] {
      const ctx = getContext<NavigationContext>();
      return ctx.activeSubmenuStack;
    },

    // Placeholder for WooCommerce cart count
    get cartCount(): number {
      // Could be enhanced to read from WC mini-cart data
      return 0;
    },
  },

  actions: {
    /**
     * Toggle mobile menu open/closed
     */
    toggleMobileMenu(): void {
      const ctx = getContext<NavigationContext>();
      ctx.isMobileMenuOpen = !ctx.isMobileMenuOpen;

      // Toggle body scroll lock
      document.body.classList.toggle(
        'aa-navigation-menu-open',
        ctx.isMobileMenuOpen
      );

      // Focus first menu item when opening
      if (ctx.isMobileMenuOpen) {
        const element = getElement();
        const firstLink = element.ref?.querySelector<HTMLElement>(
          '.aa-navigation__items a, .aa-navigation__items button'
        );
        if (firstLink) {
          setTimeout(() => firstLink.focus(), 100);
        }
      }
    },

    /**
     * Open mobile menu
     */
    openMobileMenu(): void {
      const ctx = getContext<NavigationContext>();
      if (!ctx.isMobileMenuOpen) {
        actions.toggleMobileMenu();
      }
    },

    /**
     * Close mobile menu
     */
    closeMobileMenu(): void {
      const ctx = getContext<NavigationContext>();
      if (ctx.isMobileMenuOpen) {
        ctx.isMobileMenuOpen = false;
        document.body.classList.remove('aa-navigation-menu-open');
        ctx.activeSubmenuStack = [];
      }
    },

    /**
     * Close all menus (mobile + submenus)
     */
    closeAllMenus(): void {
      const ctx = getContext<NavigationContext>();
      ctx.isMobileMenuOpen = false;
      ctx.activeSubmenuStack = [];
      document.body.classList.remove('aa-navigation-menu-open');
    },

    /**
     * Navigate back one level (drill-down mode)
     */
    navigateDrillUp(): void {
      const ctx = getContext<NavigationContext>();
      if (ctx.activeSubmenuStack.length > 0) {
        ctx.activeSubmenuStack = ctx.activeSubmenuStack.slice(0, -1);
      }
    },

    /**
     * Handle Escape key to close menus
     */
    handleEscapeKey(event: KeyboardEvent): void {
      if (event.key === 'Escape') {
        actions.closeAllMenus();
      }
    },

    /**
     * Handle Tab key for focus trapping
     */
    handleFocusTrap(event: KeyboardEvent): void {
      if (event.key !== 'Tab') return;

      const ctx = getContext<NavigationContext>();
      if (!ctx.isMobileMenuOpen) return;

      const element = getElement();
      const menu = element.ref?.querySelector<HTMLElement>(
        '.aa-navigation__menu'
      );
      if (menu) {
        trapFocus(menu, event);
      }
    },

    /**
     * Toggle search (placeholder for future implementation)
     */
    toggleSearch(): void {
      // TODO: Implement search toggle functionality
    },
  },

  callbacks: {
    /**
     * Initialize navigation on mount
     */
    init(): void {
      const ctx = getContext<NavigationContext>();
      const element = getElement();

      // Initial mobile check
      ctx.isMobile = checkIsMobile(ctx.mobileBreakpoint);

      // Set initial scroll position
      ctx.lastScrollY = window.scrollY;

      // Add keyboard listener for focus trapping
      element.ref?.addEventListener('keydown', (event: KeyboardEvent) => {
        if (event.key === 'Tab') {
          actions.handleFocusTrap(event);
        }
      });

      // Click outside handler - close all submenus
      document.addEventListener('click', (event: MouseEvent) => {
        const target = event.target as HTMLElement;
        const nav = element.ref;

        if (nav && !nav.contains(target)) {
          // Close mobile menu when clicking outside
          if (ctx.isMobileMenuOpen) {
            actions.closeMobileMenu();
          }
        }
      });
    },

    /**
     * Handle window resize
     */
    handleResize(): void {
      const ctx = getContext<NavigationContext>();
      const wasMobile = ctx.isMobile;
      ctx.isMobile = checkIsMobile(ctx.mobileBreakpoint);

      // Close mobile menu when switching to desktop
      if (wasMobile && !ctx.isMobile && ctx.isMobileMenuOpen) {
        actions.closeMobileMenu();
      }
    },

    /**
     * Handle window scroll for sticky behavior
     */
    handleScroll(): void {
      const ctx = getContext<NavigationContext>();
      const currentScrollY = window.scrollY;

      // Determine scroll direction
      ctx.scrollDirection = currentScrollY > ctx.lastScrollY ? 'down' : 'up';

      // Update sticky state based on behavior
      switch (ctx.stickyBehavior) {
        case 'always':
          ctx.isSticky = currentScrollY > ctx.stickyOffset;
          break;

        case 'scroll-up':
          ctx.isSticky =
            currentScrollY > SCROLL_THRESHOLD && ctx.scrollDirection === 'up';
          break;

        case 'none':
        default:
          ctx.isSticky = false;
          break;
      }

      ctx.lastScrollY = currentScrollY;
    },

    /**
     * Cleanup on unmount
     */
    cleanup(): void {
      document.body.classList.remove('aa-navigation-menu-open');
    },
  },
});

export { actions, state };
