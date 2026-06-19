/**
 * Shared Navigation DOM & Logging Utilities
 *
 * Pure helpers shared by both navigation subsystems — the desktop `navigation`
 * block and the mobile `navigation-panel` block. Each subsystem's `utils.ts`
 * re-exports these so existing `from './utils'` imports keep working unchanged.
 *
 * Subsystem-specific helpers (announce, focusMenuItem) intentionally stay in
 * each subsystem's utils.ts because their behavior differs.
 *
 * Logging uses a single `[Nav]` prefix; the message text already carries the
 * context, so a per-subsystem label isn't needed.
 *
 * @package Aggressive_Apparel
 */

// Type declaration for process in webpack environment.
declare const process: { env: { NODE_ENV: string } } | undefined;

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
    console.warn(`[Nav] ${message}`, context ?? '');
  }
}

/**
 * Log an error message.
 */
export function logError(message: string, error?: unknown): void {
  console.error(`[Nav] ${message}`, error ?? '');
}

// ============================================================================
// DOM Helpers
// ============================================================================

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
