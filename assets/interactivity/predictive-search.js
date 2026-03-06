/**
 * Predictive Search — Interactivity API Store
 *
 * Debounced search against WooCommerce Store API with keyboard navigation.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import { parsePrice, decodeEntities } from '@aggressive-apparel/helpers';

let debounceTimer = null;
let abortController = null;

function clearFocusClasses() {
  document.querySelectorAll('.aa-predictive-search .is-focused').forEach(el => {
    el.classList.remove('is-focused');
  });
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
          clearFocusClasses();
          return;
      }

      // Highlight the focused result item, sync aria-selected, and scroll into view.
      const container = event.target.closest('.aa-predictive-search');
      if (!container) return;
      const items = container.querySelectorAll('[role="option"]');
      items.forEach((item, i) => {
        const isFocused = i === state.focusedIndex;
        item.classList.toggle('is-focused', isFocused);
        item.setAttribute('aria-selected', isFocused ? 'true' : 'false');
      });
      if (state.focusedIndex >= 0 && items[state.focusedIndex]) {
        items[state.focusedIndex].scrollIntoView({ block: 'nearest' });
      }
    },

    handleClickOutside(event) {
      if (!state.isOpen) return;

      const container = event.target.closest('.aa-predictive-search');
      if (!container) {
        state.isOpen = false;
        state.focusedIndex = -1;
        clearFocusClasses();
      }
    },

    performSearch() {
      if (abortController) abortController.abort();
      abortController = new AbortController();

      const url = `${state.restBase}?search=${encodeURIComponent(state.query)}&per_page=6`;

      fetch(url, { signal: abortController.signal })
        .then(res => {
          if (!res.ok) throw new Error('Search failed');
          return res.json();
        })
        .then(products => {
          const seenCategories = new Map();

          state.products = products.map(p => {
            const priceData = parsePrice(p.prices);
            return {
              name: decodeEntities(p.name),
              permalink: p.permalink,
              thumbnail: p.images?.[0]?.thumbnail || p.images?.[0]?.src || '',
              price: priceData.current,
              regularPrice: priceData.regular,
              onSale: priceData.onSale,
            };
          });

          // Extract unique categories from results.
          products.forEach(p => {
            if (p.categories) {
              p.categories.forEach(cat => {
                if (!seenCategories.has(cat.id)) {
                  seenCategories.set(cat.id, {
                    name: decodeEntities(cat.name),
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

  callbacks: {
    syncResultImage() {
      const ctx = getContext();
      const { ref } = getElement();
      if (!ref || !ctx.item) return;
      if (ctx.item.thumbnail) {
        ref.src = ctx.item.thumbnail;
        ref.alt = ctx.item.name || '';
      } else {
        ref.removeAttribute('src');
        ref.alt = '';
        ref.classList.add('no-image');
      }
    },
    syncOptionAttrs() {
      const { ref } = getElement();
      if (!ref) return;
      // Assign a stable ID based on DOM position so aria-activedescendant can reference it.
      const listbox = ref.closest('[role="listbox"]');
      if (!listbox) return;
      const allOptions = listbox.querySelectorAll('[role="option"]');
      const index = Array.prototype.indexOf.call(allOptions, ref);
      ref.id = `ps-result-${index}`;
      ref.setAttribute(
        'aria-selected',
        index === state.focusedIndex ? 'true' : 'false'
      );
    },
    highlightName() {
      const ctx = getContext();
      const { ref } = getElement();
      if (!ref || !ctx.item) return;
      const name = ctx.item.name;
      const query = state.query;
      if (!query || query.length < 2) {
        ref.textContent = name;
        return;
      }
      // Escape HTML in the name, then wrap query matches in <mark>.
      const safe = name
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
      const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      const regex = new RegExp(`(${escaped})`, 'gi');
      ref.innerHTML = safe.replace(regex, '<mark>$1</mark>');
    },
  },
});
