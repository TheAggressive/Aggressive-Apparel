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
 * Measure the real scrollbar width using a probe element.
 *
 * This is more reliable than `window.innerWidth - clientWidth` which can
 * return incorrect non-zero values on mobile devices and in responsive
 * dev-tools due to browser chrome, viewport scaling, or safe-area insets.
 *
 * @return {number} Scrollbar width in pixels (0 on overlay-scrollbar systems).
 */
function measureScrollbarWidth() {
  const probe = document.createElement('div');
  probe.style.cssText =
    'position:fixed;top:0;left:0;right:0;overflow-y:scroll;visibility:hidden;pointer-events:none;';
  document.body.appendChild(probe);
  const width = probe.offsetWidth - probe.clientWidth;
  probe.remove();
  return width;
}

/**
 * Lock body scroll and compensate for scrollbar removal.
 *
 * Safe to call multiple times (nested overlays). The body stays locked
 * until unlockScroll() has been called the same number of times.
 */
export function lockScroll() {
  lockCount++;
  if (lockCount !== 1) return;

  const scrollbarWidth = measureScrollbarWidth();

  document.body.style.overflow = 'hidden';

  // Only compensate for the scrollbar if there actually is one.
  // Mobile browsers use overlay scrollbars (width 0), so adding
  // padding-right there is unnecessary and can cause layout issues.
  if (scrollbarWidth > 0) {
    document.documentElement.style.setProperty(
      '--scrollbar-width',
      `${scrollbarWidth}px`
    );
    document.body.style.paddingRight = `${scrollbarWidth}px`;
  }
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
