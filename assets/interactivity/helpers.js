/**
 * Shared Helpers — Interactivity API Utilities.
 *
 * Common utilities used across multiple interactivity stores.
 * Registered as @aggressive-apparel/helpers script module.
 *
 * @package Aggressive_Apparel
 * @since 1.22.0
 */

/**
 * Parse a Store API raw price (minor units) into display strings.
 *
 * @param {Object} prices - The `prices` object from the Store API.
 * @return {Object} `{ current, regular, onSale }`.
 */
export function parsePrice(prices) {
  const result = { current: '', regular: '', onSale: false };
  if (!prices || !prices.price) {
    return result;
  }

  const minorUnit = prices.currency_minor_unit ?? 2;
  const divisor = Math.pow(10, minorUnit);
  const prefix = prices.currency_prefix ?? '$';
  const suffix = prices.currency_suffix ?? '';

  const currentVal = (parseInt(prices.price, 10) / divisor).toFixed(minorUnit);
  result.current = `${prefix}${currentVal}${suffix}`;

  if (
    prices.regular_price &&
    prices.sale_price &&
    prices.regular_price !== prices.sale_price
  ) {
    const regularVal = (parseInt(prices.regular_price, 10) / divisor).toFixed(
      minorUnit
    );
    result.regular = `${prefix}${regularVal}${suffix}`;
    result.onSale = true;
  }
  return result;
}

/**
 * Strip HTML tags and return trimmed plain text.
 *
 * @param {string} html - HTML string.
 * @return {string} Plain text.
 */
export function stripTags(html) {
  if (!html) {
    return '';
  }
  const div = document.createElement('div');
  div.innerHTML = html;
  return (div.textContent || '').trim();
}

/**
 * Setup focus trap within a container element.
 *
 * @param {HTMLElement} container - The container to trap focus within.
 * @return {Function} Cleanup function to remove the trap.
 */
export function setupFocusTrap(container) {
  const FOCUSABLE_SELECTOR =
    'a[href], button:not([disabled]), input:not([disabled]), ' +
    'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  const handleKeydown = e => {
    if (e.key !== 'Tab') {
      return;
    }

    const focusable = Array.from(
      container.querySelectorAll(FOCUSABLE_SELECTOR)
    ).filter(el => !el.closest('[hidden]') && !el.closest('[inert]'));

    if (focusable.length === 0) {
      e.preventDefault();
      return;
    }

    const currentIndex = focusable.indexOf(document.activeElement);
    let nextIndex;

    if (e.shiftKey) {
      nextIndex = currentIndex <= 0 ? focusable.length - 1 : currentIndex - 1;
    } else {
      nextIndex = currentIndex >= focusable.length - 1 ? 0 : currentIndex + 1;
    }

    e.preventDefault();
    focusable[nextIndex].focus();
  };

  container.addEventListener('keydown', handleKeydown);

  return () => {
    container.removeEventListener('keydown', handleKeydown);
  };
}
