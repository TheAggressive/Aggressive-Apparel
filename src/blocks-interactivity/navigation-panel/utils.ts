/**
 * Navigation Panel Block — Shared Utilities
 *
 * Error handling, DOM helpers, focus management, and inert/announcement
 * helpers for the mobile navigation panel.
 *
 * @package Aggressive_Apparel
 */

// Type declaration for process in webpack environment.
declare const process:
  | {
      env: {
        NODE_ENV: string;
      };
    }
  | undefined;

import {
  ALL_BODY_CLASSES,
  BODY_CLASSES,
  FOCUSABLE_SELECTOR,
  TRANSITION_DURATION_MS,
  getAnnouncerId,
  getTriggerId,
  type BodyClassKey,
} from './constants';

// ============================================================================
// Error Handling
// ============================================================================

/**
 * Log a warning message in development mode.
 */
export function logWarning(
  message: string,
  context?: Record<string, unknown>
): void {
  if (typeof process !== 'undefined' && process.env.NODE_ENV !== 'production') {
    console.warn(`[NavigationPanel] ${message}`, context ?? '');
  }
}

/**
 * Log an error message.
 */
export function logError(message: string, error?: unknown): void {
  console.error(`[NavigationPanel] ${message}`, error ?? '');
}

/**
 * Safely get an element by ID.
 */
export function safeGetElementById<T extends HTMLElement = HTMLElement>(
  id: string,
  warnIfMissing = true
): T | null {
  if (!id) {
    if (warnIfMissing) {
      logWarning('safeGetElementById called with empty ID');
    }
    return null;
  }

  const element = document.getElementById(id);
  if (!element && warnIfMissing) {
    logWarning(`Element not found: #${id}`);
  }

  return element as T | null;
}

/**
 * Safely query for an element within a container.
 */
export function safeQuerySelector<T extends HTMLElement = HTMLElement>(
  container: Element | Document,
  selector: string,
  warnIfMissing = false
): T | null {
  try {
    const element = container.querySelector<T>(selector);
    if (!element && warnIfMissing) {
      logWarning(`Element not found: ${selector}`);
    }
    return element;
  } catch (error) {
    logError(`Invalid selector: ${selector}`, error);
    return null;
  }
}

/**
 * Safely query for all elements matching a selector.
 */
export function safeQuerySelectorAll<T extends HTMLElement = HTMLElement>(
  container: Element | Document,
  selector: string
): T[] {
  try {
    return Array.from(container.querySelectorAll<T>(selector));
  } catch (error) {
    logError(`Invalid selector: ${selector}`, error);
    return [];
  }
}

// ============================================================================
// Reduced Motion
// ============================================================================

let _prefersReducedMotion: boolean | null = null;

/**
 * Check if user prefers reduced motion.
 */
export function prefersReducedMotion(): boolean {
  if (_prefersReducedMotion === null) {
    const mql = window.matchMedia('(prefers-reduced-motion: reduce)');
    _prefersReducedMotion = mql.matches;
    mql.addEventListener('change', e => {
      _prefersReducedMotion = e.matches;
    });
  }
  return _prefersReducedMotion;
}

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
    focusable[nextIndex].focus();
  };

  const handleFocusin = (e: FocusEvent) => {
    const target = e.target as HTMLElement;
    if (!container.contains(target)) {
      const focusable = getFocusableElements(container);
      if (focusable.length > 0) {
        focusable[0].focus();
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
    trigger.focus();
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
    main.focus();
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

  items[targetIndex].focus();
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
      firstFocusable?.focus();
    });
  });
}

// ============================================================================
// Drilldown Focus Management
// ============================================================================

/**
 * Update inert state on drilldown panels based on the current drill stack.
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

  const drilldownPanels = safeQuerySelectorAll<HTMLElement>(
    container,
    '.wp-block-aggressive-apparel-nav-submenu-drilldown .wp-block-aggressive-apparel-nav-submenu-drilldown__panel'
  );

  const currentActiveId = drillStack[drillStack.length - 1] ?? null;

  drilldownPanels.forEach(panel => {
    panel.inert = panel.id !== currentActiveId;
  });
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
      firstFocusable?.focus();
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
  const submenu = safeQuerySelector<HTMLElement>(
    container,
    `.wp-block-aggressive-apparel-nav-submenu-drilldown:has(#${CSS.escape(previousId)})`,
    false
  );

  if (!submenu) {
    return;
  }

  const trigger = safeQuerySelector<HTMLElement>(
    submenu,
    '.wp-block-aggressive-apparel-nav-submenu-drilldown__link',
    false
  );

  requestAnimationFrame(() => {
    trigger?.focus();
  });
}
