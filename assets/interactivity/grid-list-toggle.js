/**
 * Grid/List View Toggle — Interactivity API Store
 *
 * Switches between grid and list view on shop archive pages.
 * Preference is persisted in localStorage.
 *
 * @package Aggressive_Apparel
 * @since 1.51.0
 */

import { store } from '@wordpress/interactivity';

const STORAGE_KEY = 'aa_view_mode';

/**
 * Read view mode from localStorage.
 *
 * @returns {'grid'|'list'}
 */
function readViewMode() {
  try {
    const val = localStorage.getItem(STORAGE_KEY);
    return val === 'list' ? 'list' : 'grid';
  } catch {
    return 'grid';
  }
}

/**
 * Write view mode to localStorage.
 *
 * @param {'grid'|'list'} mode
 */
function writeViewMode(mode) {
  try {
    localStorage.setItem(STORAGE_KEY, mode);
  } catch {
    // Private browsing or quota exceeded.
  }
}

/**
 * Apply view mode CSS classes to grid containers and body.
 *
 * @param {'grid'|'list'} mode
 */
function applyViewMode(mode) {
  const isList = mode === 'list';

  document.body.classList.toggle('aa-list-view', isList);
  document.body.classList.toggle('aa-grid-view', !isList);

  // Toggle on product filters container (AJAX grid).
  const filters = document.querySelector('.aa-product-filters');
  if (filters) {
    filters.classList.toggle('is-list-view', isList);
    filters.classList.toggle('is-grid-view', !isList);
  }
}

const { state } = store('aggressive-apparel/grid-list-toggle', {
  state: {
    viewMode: 'grid',

    get isGridView() {
      return state.viewMode === 'grid';
    },

    get isListView() {
      return state.viewMode === 'list';
    },
  },

  actions: {
    setGrid() {
      state.viewMode = 'grid';
      writeViewMode('grid');
      applyViewMode('grid');
    },

    setList() {
      state.viewMode = 'list';
      writeViewMode('list');
      applyViewMode('list');
    },
  },

  callbacks: {
    init() {
      const mode = readViewMode();
      state.viewMode = mode;
      applyViewMode(mode);
    },
  },
});
