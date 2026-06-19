/**
 * Navigation Panel Block — Shared Utilities
 *
 * Error handling, DOM helpers, focus management, and inert/announcement
 * helpers for the mobile navigation panel.
 *
 * @package Aggressive_Apparel
 */

import {
  ALL_BODY_CLASSES,
  BODY_CLASSES,
  FOCUSABLE_SELECTOR,
  TRANSITION_DURATION_MS,
  getAnnouncerId,
  getTriggerId,
  type BodyClassKey,
} from './constants';

import {
  logError,
  logWarning,
  prefersReducedMotion,
  safeGetElementById,
  safeQuerySelector,
  safeQuerySelectorAll,
} from '../nav-shared/dom';

// Re-export the shared DOM/logging helpers so existing `from './utils'` imports
// across this subsystem keep resolving unchanged.
export {
  logError,
  logWarning,
  prefersReducedMotion,
  safeGetElementById,
  safeQuerySelector,
  safeQuerySelectorAll,
};

/**
 * Get the effective transition duration respecting reduced motion preference.
 */
export function getTransitionDuration(): number {
  return prefersReducedMotion() ? 0 : TRANSITION_DURATION_MS;
}

// ============================================================================
// Validation
// ============================================================================

/**
 * Validate a panel slug.
 */
export function isValidPanelSlug(slug: unknown): slug is string {
  return typeof slug === 'string' && slug.length > 0;
}

// ============================================================================
// Body Class Management
// ============================================================================

/**
 * Remove all navigation body classes.
 */
export function removeAllBodyClasses(): void {
  document.body.classList.remove(...ALL_BODY_CLASSES);
}

/**
 * Add the appropriate body class for push/reveal animations.
 */
export function addBodyClass(animationStyle: string, position: string): void {
  const key =
    `${animationStyle}${position.charAt(0).toUpperCase()}${position.slice(1)}` as BodyClassKey;
  const className = BODY_CLASSES[key];
  if (className) {
    document.body.classList.add(className);
  }
}

/**
 * Set body overflow to prevent scrolling.
 */
export function setBodyOverflow(hidden: boolean): void {
  document.body.style.overflow = hidden ? 'hidden' : '';
}

// ============================================================================
// Panel Visibility
// ============================================================================

/**
 * Manage panel visibility with animation support.
 */
export function setPanelVisibility(panel: HTMLElement, isOpen: boolean): void {
  if (isOpen) {
    panel.style.removeProperty('pointer-events');
    panel.classList.add('is-open');
  } else {
    panel.classList.remove('is-open');

    const duration = getTransitionDuration();
    setTimeout(() => {
      if (!panel.classList.contains('is-open')) {
        panel.style.pointerEvents = 'none';
      }
    }, duration);
  }
}

// ============================================================================
// Focus Management
// ============================================================================

/**
 * Get all focusable elements within a container, excluding inert subtrees.
 */
export function getFocusableElements(container: HTMLElement): HTMLElement[] {
  const elements = safeQuerySelectorAll<HTMLElement>(
    container,
    FOCUSABLE_SELECTOR
  );

  return elements.filter(el => {
    let parent: HTMLElement | null = el.parentElement;
    while (parent && parent !== container) {
      if (parent.inert) {
        return false;
      }
      parent = parent.parentElement;
    }
    return true;
  });
}

/**
 * Setup focus trap within a container element.
 */
export function setupFocusTrap(container: HTMLElement): () => void {
  const handleKeydown = (e: KeyboardEvent) => {
    if (e.key !== 'Tab') {
      return;
    }

    const focusable = getFocusableElements(container);

    if (focusable.length === 0) {
      e.preventDefault();
      return;
    }

    const active = document.activeElement as HTMLElement | null;
    const currentIndex = active ? focusable.indexOf(active) : -1;

    let nextIndex: number;

    if (e.shiftKey) {
      nextIndex = currentIndex <= 0 ? focusable.length - 1 : currentIndex - 1;
    } else {
      nextIndex =
        currentIndex === -1 || currentIndex >= focusable.length - 1
          ? 0
          : currentIndex + 1;
    }

    e.preventDefault();
    focusable[nextIndex].focus({ preventScroll: true });
  };

  const handleFocusin = (e: FocusEvent) => {
    const target = e.target as HTMLElement;
    if (!container.contains(target)) {
      const focusable = getFocusableElements(container);
      if (focusable.length > 0) {
        focusable[0].focus({ preventScroll: true });
      }
    }
  };

  document.addEventListener('keydown', handleKeydown, true);
  document.addEventListener('focusin', handleFocusin, true);

  return () => {
    document.removeEventListener('keydown', handleKeydown, true);
    document.removeEventListener('focusin', handleFocusin, true);
  };
}

/**
 * Restore focus to the trigger button after closing the panel.
 * Falls back to the main content area when the trigger is unavailable.
 */
export function restoreFocus(panelSlug: string): void {
  const triggerId = getTriggerId(panelSlug);
  const trigger = safeGetElementById(triggerId, false);
  if (trigger) {
    trigger.focus({ preventScroll: true });
    return;
  }

  const main = safeQuerySelector<HTMLElement>(
    document,
    'main, [role="main"], .wp-site-blocks',
    false
  );

  if (main) {
    const hadTabindex = main.hasAttribute('tabindex');
    if (!hadTabindex) {
      main.setAttribute('tabindex', '-1');
    }
    main.focus({ preventScroll: true });
    if (!hadTabindex) {
      main.removeAttribute('tabindex');
    }
  }
}

/**
 * Move focus to a menu item by index with roving tabindex.
 */
export function focusMenuItem(items: HTMLElement[], index: number): void {
  if (items.length === 0) {
    return;
  }

  const targetIndex = Math.max(0, Math.min(index, items.length - 1));

  items.forEach((item, i) => {
    item.setAttribute('tabindex', i === targetIndex ? '0' : '-1');
  });

  items[targetIndex].focus({ preventScroll: true });
}

// ============================================================================
// Swipe-to-Close
// ============================================================================

const SWIPE_THRESHOLD_PX = 72;
const SWIPE_MAX_VERTICAL_PX = 120;

/**
 * Attach swipe-to-close touch listeners to a panel element.
 * Returns a cleanup function that removes the listeners.
 *
 * Swipe direction depends on panel position:
 *  - right-side panel: swipe right (positive X delta) to close
 *  - left-side panel:  swipe left  (negative X delta) to close
 */
export function setupSwipeToClose(
  panel: HTMLElement,
  onClose: () => void
): () => void {
  if (prefersReducedMotion()) {
    return () => {};
  }

  let startX = 0;
  let startY = 0;
  let tracking = false;

  const onTouchStart = (e: TouchEvent) => {
    if (e.touches.length !== 1) return;
    startX = e.touches[0].clientX;
    startY = e.touches[0].clientY;
    tracking = true;
  };

  const onTouchEnd = (e: TouchEvent) => {
    if (!tracking) return;
    tracking = false;

    const touch = e.changedTouches[0];
    if (!touch) return;

    const deltaX = touch.clientX - startX;
    const deltaY = Math.abs(touch.clientY - startY);

    if (deltaY > SWIPE_MAX_VERTICAL_PX) return;

    const position = panel.getAttribute('data-position') ?? 'right';
    const shouldClose =
      position === 'right'
        ? deltaX > SWIPE_THRESHOLD_PX
        : deltaX < -SWIPE_THRESHOLD_PX;

    if (shouldClose) {
      onClose();
    }
  };

  const onTouchCancel = () => {
    tracking = false;
  };

  panel.addEventListener('touchstart', onTouchStart, { passive: true });
  panel.addEventListener('touchend', onTouchEnd, { passive: true });
  panel.addEventListener('touchcancel', onTouchCancel, { passive: true });

  return () => {
    panel.removeEventListener('touchstart', onTouchStart);
    panel.removeEventListener('touchend', onTouchEnd);
    panel.removeEventListener('touchcancel', onTouchCancel);
  };
}

// ============================================================================
// Announcements
// ============================================================================

let announcementClearTimeout: ReturnType<typeof setTimeout> | null = null;

/**
 * Announce a message to screen readers via a per-panel live region.
 */
export function announce(
  message: string,
  options: { assertive?: boolean; panelSlug?: string } = {}
): void {
  const { assertive = false, panelSlug } = options;
  const id = panelSlug ? getAnnouncerId(panelSlug) : '';

  const announcer = id ? safeGetElementById(id, false) : null;
  if (!announcer) {
    logWarning('announce: Announcer element not found', { id });
    return;
  }

  if (announcementClearTimeout) {
    clearTimeout(announcementClearTimeout);
  }

  announcer.setAttribute('aria-live', assertive ? 'assertive' : 'polite');
  announcer.textContent = message;

  announcementClearTimeout = setTimeout(() => {
    if (announcer) {
      announcer.textContent = '';
    }
    announcementClearTimeout = null;
  }, 5000);
}

// ============================================================================
// Mega-Content Focus & Inert Management
// ============================================================================

/**
 * Update inert state when a mega-content overlay is active.
 */
export function updateMegaContentInertState(
  panel: HTMLElement,
  activeSubmenuId: string | null
): void {
  if (!('inert' in HTMLElement.prototype)) {
    return;
  }

  const panelHeader = safeQuerySelector<HTMLElement>(
    panel,
    '.aa-nav__panel-header',
    false
  );
  const menuItems = safeQuerySelectorAll<HTMLElement>(
    panel,
    '.aa-nav__panel-menu > li'
  );

  if (!activeSubmenuId) {
    if (panelHeader) {
      panelHeader.inert = false;
    }
    menuItems.forEach(li => {
      li.inert = false;
      const trigger = safeQuerySelector<HTMLElement>(
        li,
        ':is(.wp-block-aggressive-apparel-nav-submenu-accordion__trigger, .wp-block-aggressive-apparel-nav-submenu-drilldown__trigger)',
        false
      );
      if (trigger) {
        trigger.inert = false;
      }
    });
    return;
  }

  const activePanel = safeGetElementById(activeSubmenuId, false);
  if (!activePanel) {
    return;
  }
  const activeLi = activePanel.closest(
    '.aa-nav__panel-menu > li'
  ) as HTMLElement | null;
  if (!activeLi) {
    return;
  }

  if (panelHeader) {
    panelHeader.inert = true;
  }

  menuItems.forEach(li => {
    if (li === activeLi) {
      const trigger = safeQuerySelector<HTMLElement>(
        li,
        ':is(.wp-block-aggressive-apparel-nav-submenu-accordion__trigger, .wp-block-aggressive-apparel-nav-submenu-drilldown__trigger)',
        false
      );
      if (trigger) {
        trigger.inert = true;
      }
    } else {
      li.inert = true;
    }
  });
}

/**
 * Move focus into a mega-content overlay panel.
 */
export function focusMegaContentPanel(submenuId: string): void {
  const panel = safeGetElementById(submenuId, false);
  if (!panel) {
    return;
  }

  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      const firstFocusable = safeQuerySelector<HTMLElement>(
        panel,
        FOCUSABLE_SELECTOR,
        false
      );
      firstFocusable?.focus({ preventScroll: true });
    });
  });
}

// ============================================================================
// Drilldown Focus Management
// ============================================================================

/**
 * Update inert state based on the current drill stack.
 *
 * Only the active (deepest) drilldown panel should be interactive; everything
 * behind it — sibling menu items, the triggers that opened each level, and the
 * back buttons / lists of ancestor panels — must be inert so keyboard and
 * screen-reader focus can't leak to content hidden by the slide-over.
 *
 * `inert` is inherited and cannot be undone on a descendant of an inert
 * subtree, so we never inert an ancestor of the active panel. Two passes:
 *
 *  1. Every drilldown panel that is NOT currently open (its id is not in the
 *     stack) is made inert. Closed panels are positioned off-screen with a
 *     transform, which hides them visually but leaves their contents in the tab
 *     order and reading order — inert removes them. Ancestors and the active
 *     panel are in the stack, so they are never inerted (no inheritance trap),
 *     and this also covers nested closed panels inside the active one.
 *  2. When a panel is open, walk up from it and inert every sibling on the
 *     active path (sibling menu links, the triggers, ancestor back buttons),
 *     stopping at the panel body so the header close button stays reachable.
 *
 * Inerted elements are tagged with `data-aa-inert` so they can be cleared on
 * the next update.
 */
export function updateDrilldownInertState(
  container: HTMLElement,
  drillStack: string[]
): void {
  const supportsInert =
    typeof HTMLElement !== 'undefined' && 'inert' in HTMLElement.prototype;

  if (!supportsInert) {
    return;
  }

  // Clear anything we previously made inert.
  container.querySelectorAll<HTMLElement>('[data-aa-inert]').forEach(el => {
    el.inert = false;
    el.removeAttribute('data-aa-inert');
  });

  // Pass 1: inert every closed (not-open) drilldown panel.
  const openIds = new Set(drillStack);
  container
    .querySelectorAll<HTMLElement>(
      '.wp-block-aggressive-apparel-nav-submenu-drilldown__panel'
    )
    .forEach(panel => {
      if (!openIds.has(panel.id)) {
        panel.inert = true;
        panel.setAttribute('data-aa-inert', '');
      }
    });

  if (drillStack.length === 0) {
    return;
  }

  const activeId = drillStack[drillStack.length - 1];
  const activePanel = activeId ? safeGetElementById(activeId, false) : null;
  if (!activePanel) {
    return;
  }

  let node: HTMLElement = activePanel;
  let parent = node.parentElement;

  while (parent && container.contains(parent)) {
    for (const child of Array.from(parent.children)) {
      if (child !== node) {
        const el = child as HTMLElement;
        el.inert = true;
        el.setAttribute('data-aa-inert', '');
      }
    }

    // Stop at the scroll container; the header (close button) lives outside it
    // and must stay reachable.
    if (parent.classList.contains('aa-nav__panel-body')) {
      break;
    }

    node = parent;
    parent = node.parentElement;
  }
}

/**
 * Focus the first focusable item in a drilldown panel.
 */
export function focusDrilldownPanel(panelId: string): void {
  const panel = safeGetElementById(panelId, false);
  if (!panel) {
    return;
  }

  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      const firstFocusable = safeQuerySelector<HTMLElement>(
        panel,
        FOCUSABLE_SELECTOR,
        false
      );
      firstFocusable?.focus({ preventScroll: true });
    });
  });
}

/**
 * Focus the trigger that opened the current drilldown level when going back.
 */
export function focusDrilldownTrigger(
  container: HTMLElement,
  previousId: string
): void {
  // Resolve the panel by id, then its owning drilldown's own trigger. Using
  // closest() (not :has) is nesting-safe: :has would match every ancestor
  // drilldown and querySelector would return the outermost one's trigger.
  const panel = safeGetElementById(previousId, false);
  const submenu = panel?.closest<HTMLElement>(
    '.wp-block-aggressive-apparel-nav-submenu-drilldown'
  );

  if (!submenu || !container.contains(submenu)) {
    return;
  }

  const trigger = submenu.querySelector<HTMLElement>(
    ':scope > .wp-block-aggressive-apparel-nav-submenu-drilldown__trigger > .wp-block-aggressive-apparel-nav-submenu-drilldown__link'
  );

  requestAnimationFrame(() => {
    trigger?.focus({ preventScroll: true });
  });
}
