/**
 * Shared Navigation Key & Focus Constants
 *
 * Identical across both navigation subsystems (desktop `navigation` and mobile
 * `navigation-panel`). Each subsystem's `constants.ts` re-exports these so
 * existing `from './constants'` imports keep working unchanged.
 *
 * Subsystem-specific constants (SELECTORS, state classes, timing, ID helpers)
 * stay in each subsystem's constants.ts because their values differ.
 *
 * @package Aggressive_Apparel
 */

/**
 * Selector matching all natively focusable elements.
 */
export const FOCUSABLE_SELECTOR =
  'a[href], button:not([disabled]), input:not([disabled]), ' +
  'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/**
 * Keyboard key values (KeyboardEvent.key).
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
 * Arrow/navigation keys handled by roving-tabindex menus.
 */
export const ARROW_KEYS = [
  KEYS.arrowUp,
  KEYS.arrowDown,
  KEYS.arrowLeft,
  KEYS.arrowRight,
  KEYS.home,
  KEYS.end,
] as const;

/**
 * Panel/menu transition duration (ms) — JS fallback for scroll-lock cleanup.
 */
export const TRANSITION_DURATION_MS = 400;
