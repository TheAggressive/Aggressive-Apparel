/**
 * Scroll Lock Utility
 *
 * Prevents body scrolling for modals/overlays while compensating for the
 * scrollbar width to avoid layout shift. Sets a --scrollbar-width CSS
 * custom property on <html> so fixed-position elements can also compensate.
 *
 * @package Aggressive_Apparel
 * @since 1.20.0
 */

let lockCount = 0;

/**
 * Lock body scroll and compensate for scrollbar removal.
 *
 * Safe to call multiple times (nested overlays). The body stays locked
 * until unlockScroll() has been called the same number of times.
 */
export function lockScroll() {
  lockCount++;
  if (lockCount !== 1) return;

  const scrollbarWidth =
    window.innerWidth - document.documentElement.clientWidth;

  document.documentElement.style.setProperty(
    '--scrollbar-width',
    `${scrollbarWidth}px`
  );
  document.body.style.overflow = 'hidden';
  document.body.style.paddingRight = `${scrollbarWidth}px`;
}

/**
 * Unlock body scroll and remove scrollbar compensation.
 *
 * Only actually unlocks when every matching lockScroll() has been undone.
 */
export function unlockScroll() {
  lockCount = Math.max(0, lockCount - 1);
  if (lockCount !== 0) return;

  document.documentElement.style.removeProperty('--scrollbar-width');
  document.body.style.overflow = '';
  document.body.style.paddingRight = '';
}
