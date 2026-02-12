/**
 * Predictive Search — Interactivity API Store
 *
 * Debounced search against WooCommerce Store API with keyboard navigation.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

import { store } from '@wordpress/interactivity';

let debounceTimer = null;
let abortController = null;

/**
 * Parse a Store API price (minor units) to display string.
 *
 * @param {Object} prices Store API prices object.
 * @return {string} Formatted price string.
 */
function parsePrice(prices) {
  if (!prices) return '';
  const minorUnit = prices.currency_minor_unit ?? 2;
  const divisor = Math.pow(10, minorUnit);
  const prefix = prices.currency_prefix ?? '$';
  const suffix = prices.currency_suffix ?? '';
  const val = (parseInt(prices.price, 10) / divisor).toFixed(minorUnit);
  return `${prefix}${val}${suffix}`;
}

const { state, actions } = store('aggressive-apparel/predictive-search', {
  state: {
    get isClosed() {
      return !state.isOpen;
    },
    get isNotLoading() {
      return !state.isLoading;
    },
    get hasNoProducts() {
      return state.products.length === 0;
    },
    get hasNoCategories() {
      return state.categories.length === 0;
    },
    get hasResults() {
      return state.products.length > 0 || state.categories.length > 0;
    },
    get ariaExpanded() {
      return state.isOpen ? 'true' : 'false';
    },
    get activeDescendant() {
      if (state.focusedIndex < 0) return '';
      return `ps-result-${state.focusedIndex}`;
    },
    get viewAllUrl() {
      return `${state.searchUrl}?s=${encodeURIComponent(state.query)}&post_type=product`;
    },
    get resultAnnouncement() {
      if (state.isLoading) return '';
      const count = state.products.length;
      if (count === 0 && state.query.length >= 2) return 'No products found.';
      if (count === 1) return '1 product found.';
      if (count > 1) return `${count} products found.`;
      return '';
    },
  },

  actions: {
    handleInput(event) {
      const query = event.target.value.trim();
      state.query = query;

      if (debounceTimer) clearTimeout(debounceTimer);

      if (query.length < 2) {
        state.isOpen = false;
        state.products = [];
        state.categories = [];
        state.focusedIndex = -1;
        return;
      }

      state.isLoading = true;
      state.isOpen = true;

      debounceTimer = setTimeout(() => {
        actions.performSearch();
      }, 300);
    },

    handleFocus() {
      if (state.products.length > 0 && state.query.length >= 2) {
        state.isOpen = true;
      }
    },

    handleKeydown(event) {
      if (!state.isOpen) return;

      const totalItems = state.products.length + state.categories.length;

      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault();
          state.focusedIndex = Math.min(state.focusedIndex + 1, totalItems - 1);
          break;

        case 'ArrowUp':
          event.preventDefault();
          state.focusedIndex = Math.max(state.focusedIndex - 1, -1);
          break;

        case 'Enter':
          if (state.focusedIndex >= 0) {
            event.preventDefault();
            const allItems = [...state.products, ...state.categories];
            const item = allItems[state.focusedIndex];
            if (item && item.permalink) {
              window.location.href = item.permalink;
            }
          }
          break;

        case 'Escape':
          state.isOpen = false;
          state.focusedIndex = -1;
          break;
      }
    },

    handleClickOutside(event) {
      if (!state.isOpen) return;

      const container = event.target.closest('.aa-predictive-search');
      if (!container) {
        state.isOpen = false;
        state.focusedIndex = -1;
      }
    },

    performSearch() {
      if (abortController) abortController.abort();
      abortController = new AbortController();

      const url = `${state.restBase}?search=${encodeURIComponent(state.query)}&per_page=6&type=simple,variable`;

      fetch(url, { signal: abortController.signal })
        .then(res => {
          if (!res.ok) throw new Error('Search failed');
          return res.json();
        })
        .then(products => {
          const seenCategories = new Map();

          state.products = products.map(p => ({
            name: p.name,
            permalink: p.permalink,
            thumbnail: p.images?.[0]?.thumbnail || p.images?.[0]?.src || '',
            price: parsePrice(p.prices),
          }));

          // Extract unique categories from results.
          products.forEach(p => {
            if (p.categories) {
              p.categories.forEach(cat => {
                if (!seenCategories.has(cat.id)) {
                  seenCategories.set(cat.id, {
                    name: cat.name,
                    permalink: cat.link || cat.permalink || '#',
                  });
                }
              });
            }
          });

          state.categories = Array.from(seenCategories.values()).slice(0, 4);
          state.totalResults = products.length;
          state.isLoading = false;
          state.focusedIndex = -1;
        })
        .catch(err => {
          if (err.name === 'AbortError') return;
          state.isLoading = false;
          state.products = [];
          state.categories = [];
        });
    },
  },
});
