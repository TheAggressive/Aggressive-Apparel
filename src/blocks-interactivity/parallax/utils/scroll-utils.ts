/**
 * Scroll utilities for the Parallax block
 *
 * @package Aggressive Apparel
 */

/**
 * Get current scroll position (cross-browser compatible)
 */
export const getScrollPosition = (): { top: number; left: number } => {
  return {
    top: window.pageYOffset || document.documentElement.scrollTop,
    left: window.pageXOffset || document.documentElement.scrollLeft,
  };
};

/**
 * Get current scroll Y position (shorthand)
 */
export const getScrollY = (): number => {
  return window.pageYOffset || document.documentElement.scrollTop;
};
