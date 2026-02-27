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
 * Decode common HTML entities to their plain-text equivalents.
 *
 * @param {string} str - String with HTML entities.
 * @return {string} Decoded string.
 */
export function decodeEntities(str) {
  if (!str) {
    return '';
  }
  return str
    .replace(/&#x([0-9a-f]+);/gi, (_, hex) =>
      String.fromCodePoint(parseInt(hex, 16))
    )
    .replace(/&#(\d+);/g, (_, dec) => String.fromCodePoint(parseInt(dec, 10)))
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&nbsp;/g, ' ');
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
  return decodeEntities(html.replace(/<[^>]*>/g, '')).trim();
}

/**
 * Find a variation matching the selected attributes.
 *
 * Handles both data formats:
 * - Store API (quick view): `{ attributes: [{attribute, name, taxonomy, value}] }`
 * - PHP state (sticky cart): `{ attributes: {attribute_pa_color: "red"} }`
 *
 * Matching iterates from the **variation's** perspective: every non-empty
 * variation attribute must be satisfied by the selection. Empty variation
 * values mean "any" and always match.
 *
 * @param {Array}  variations - Array of variation objects with `id` and `attributes`.
 * @param {Object} selected   - Map of attribute key → selected value.
 * @return {Object|null} Matching variation or null.
 */
export function matchVariation(variations, selected) {
  const activeEntries = Object.entries(selected).filter(([, v]) => v);
  if (activeEntries.length === 0) {
    return null;
  }

  // Build normalised lookup: lowercase keys in both bare and attribute_-prefixed forms.
  const norm = {};
  for (const [key, value] of activeEntries) {
    const lower = key.toLowerCase();
    norm[lower] = value;
    // Ensure attribute_-prefixed form exists for object-format matching.
    if (!lower.startsWith('attribute_')) {
      norm[`attribute_${lower}`] = value;
    }
  }

  return (
    variations.find(v => {
      const attrs = v.attributes;

      // Array format (Store API): [{attribute, name, taxonomy, value}]
      if (Array.isArray(attrs)) {
        return attrs.every(attr => {
          if (!attr.value) return true;

          const possibleKeys = [
            attr.attribute,
            attr.name,
            attr.taxonomy,
          ].filter(Boolean);
          let val;
          for (const k of possibleKeys) {
            val = norm[k.toLowerCase()];
            if (val) break;
          }

          return val && val.toLowerCase() === attr.value.toLowerCase();
        });
      }

      // Object format (PHP state): {attribute_pa_color: "red"}
      return Object.entries(attrs).every(([key, vValue]) => {
        if (!vValue) return true;
        const selectedValue = norm[key.toLowerCase()];
        if (!selectedValue) return false;
        return selectedValue.toLowerCase() === vValue.toLowerCase();
      });
    }) || null
  );
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
