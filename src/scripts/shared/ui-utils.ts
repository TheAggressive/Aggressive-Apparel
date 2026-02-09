/**
 * Shared UI Utilities
 *
 * Reusable modal, drawer, focus-trap, scroll-lock, and announcement helpers.
 * Extracted from the navigation block system for cross-feature consumption.
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

// ============================================================================
// Constants
// ============================================================================

/** Default transition duration in milliseconds. */
const TRANSITION_DURATION_MS = 300;

/** Selector for focusable elements. */
const FOCUSABLE_SELECTOR = [
  'a[href]:not([tabindex="-1"])',
  'button:not([disabled]):not([tabindex="-1"])',
  'input:not([disabled]):not([tabindex="-1"])',
  'select:not([disabled]):not([tabindex="-1"])',
  'textarea:not([disabled]):not([tabindex="-1"])',
  '[tabindex]:not([tabindex="-1"])',
].join(', ');

// ============================================================================
// Reduced Motion
// ============================================================================

let _prefersReducedMotion: boolean | null = null;

/**
 * Check if the user prefers reduced motion.
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
 * Get the effective transition duration respecting reduced motion.
 */
export function getTransitionDuration(): number {
  return prefersReducedMotion() ? 0 : TRANSITION_DURATION_MS;
}

// ============================================================================
// Body Scroll Lock
// ============================================================================

let scrollLockCount = 0;

/**
 * Lock or unlock body scroll. Supports nested calls via a reference counter
 * so multiple open modals don't fight each other.
 */
export function setBodyOverflow(hidden: boolean): void {
  if (hidden) {
    scrollLockCount++;
    document.body.style.overflow = 'hidden';
  } else {
    scrollLockCount = Math.max(0, scrollLockCount - 1);
    if (scrollLockCount === 0) {
      document.body.style.overflow = '';
    }
  }
}

// ============================================================================
// Focus Management
// ============================================================================

/**
 * Get all focusable elements within a container.
 */
export function getFocusableElements(container: HTMLElement): HTMLElement[] {
  const elements = Array.from(
    container.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)
  );
  return elements.filter(el => {
    let parent: HTMLElement | null = el.parentElement;
    while (parent && parent !== container) {
      if ((parent as HTMLElement).inert) {
        return false;
      }
      parent = parent.parentElement;
    }
    return true;
  });
}

/**
 * Setup a focus trap within a container.
 * Returns a cleanup function to remove the trap.
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
 * Restore focus to a specific element or fall back to the main content area.
 */
export function restoreFocus(triggerElement?: HTMLElement | null): void {
  if (triggerElement) {
    triggerElement.focus();
    return;
  }

  const main = document.querySelector<HTMLElement>(
    'main, [role="main"], .wp-site-blocks'
  );
  if (main) {
    const had = main.hasAttribute('tabindex');
    if (!had) {
      main.setAttribute('tabindex', '-1');
    }
    main.focus();
    if (!had) {
      main.removeAttribute('tabindex');
    }
  }
}

// ============================================================================
// Panel / Modal Visibility
// ============================================================================

/**
 * Toggle panel open/close with CSS transition support.
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
// Screen Reader Announcements
// ============================================================================

let clearAnnouncementTimeout: ReturnType<typeof setTimeout> | null = null;

/**
 * Announce a message to screen readers via a live region.
 * Creates the live-region element if it does not yet exist.
 */
export function announce(
  message: string,
  options: { assertive?: boolean } = {}
): void {
  const id = 'aggressive-apparel-announcer';
  let announcer = document.getElementById(id);

  if (!announcer) {
    announcer = document.createElement('div');
    announcer.id = id;
    announcer.setAttribute('aria-live', 'polite');
    announcer.setAttribute('aria-atomic', 'true');
    announcer.setAttribute('role', 'status');
    Object.assign(announcer.style, {
      position: 'absolute',
      width: '1px',
      height: '1px',
      overflow: 'hidden',
      clip: 'rect(0 0 0 0)',
      clipPath: 'inset(50%)',
      whiteSpace: 'nowrap',
    });
    document.body.appendChild(announcer);
  }

  if (clearAnnouncementTimeout) {
    clearTimeout(clearAnnouncementTimeout);
  }

  announcer.setAttribute(
    'aria-live',
    options.assertive ? 'assertive' : 'polite'
  );
  announcer.textContent = message;

  clearAnnouncementTimeout = setTimeout(() => {
    if (announcer) {
      announcer.textContent = '';
    }
    clearAnnouncementTimeout = null;
  }, 5000);
}
