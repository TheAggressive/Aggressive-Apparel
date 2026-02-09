/**
 * Recently Viewed — Interactivity API Store.
 *
 * Stores viewed product IDs in localStorage and fetches product cards
 * via the WooCommerce Store API.
 *
 * The `restBase` value comes from the PHP context via wp_json_encode().
 *
 * @package Aggressive_Apparel
 * @since 1.17.0
 */

import { store, getContext } from '@wordpress/interactivity';

const STORAGE_KEY = 'aggressive_apparel_recently_viewed';
const MAX_STORED = 12;

/**
 * Escape a string for safe use in HTML attributes and text content.
 *
 * @param {string} str - Raw string.
 * @return {string} Escaped string.
 */
function escapeHtml(str) {
  if (!str) {
    return '';
  }
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

/**
 * Format a Store API raw price into a display string.
 *
 * @param {Object} prices - The `prices` object from the Store API response.
 * @return {string} Formatted price string.
 */
function formatPrice(prices) {
  if (!prices || !prices.price) {
    return '';
  }
  const minorUnit = prices.currency_minor_unit ?? 2;
  const divisor = Math.pow(10, minorUnit);
  const value = (parseInt(prices.price, 10) / divisor).toFixed(minorUnit);
  const prefix = prices.currency_prefix ?? '$';
  const suffix = prices.currency_suffix ?? '';
  return `${prefix}${value}${suffix}`;
}

store('aggressive-apparel/recently-viewed', {
  state: {
    get productsHtml() {
      const ctx = getContext();
      if (!ctx.products || ctx.products.length === 0) {
        return '';
      }
      return ctx.products
        .map(p => {
          const image =
            p.images && p.images.length > 0 ? escapeHtml(p.images[0].src) : '';
          const name = escapeHtml(p.name || '');
          const price = escapeHtml(formatPrice(p.prices));
          const link = escapeHtml(p.permalink || '#');

          let html = '<div class="aggressive-apparel-recently-viewed__item">';
          if (image) {
            html += `<a href="${link}"><img src="${image}" alt="${name}" loading="lazy" /></a>`;
          }
          html += `<a href="${link}" class="aggressive-apparel-recently-viewed__name">${name}</a>`;
          if (price) {
            html += `<span class="aggressive-apparel-recently-viewed__price">${price}</span>`;
          }
          html += '</div>';
          return html;
        })
        .join('');
    },
  },

  callbacks: {
    init() {
      const ctx = getContext();

      // Record current product.
      if (ctx.currentProductId) {
        try {
          const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
          const filtered = stored.filter(id => id !== ctx.currentProductId);
          filtered.unshift(ctx.currentProductId);
          localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify(filtered.slice(0, MAX_STORED))
          );
        } catch {
          // Ignore storage errors.
        }
      }

      // Fetch recently viewed (excluding current product).
      let ids;
      try {
        ids = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]').filter(
          id => id !== ctx.currentProductId
        );
      } catch {
        ids = [];
      }

      const displayIds = ids.slice(0, ctx.maxDisplay || 4);
      if (displayIds.length === 0) {
        return;
      }

      const base = ctx.restBase || '/wp-json/wc/store/v1/products';
      const param = displayIds.join(',');

      fetch(`${base}?include=${param}`)
        .then(res => res.json())
        .then(data => {
          if (Array.isArray(data) && data.length > 0) {
            ctx.products = data;
            ctx.loaded = true;
          }
        })
        .catch(() => {
          // Silently fail — section stays hidden.
        });
    },
  },
});
