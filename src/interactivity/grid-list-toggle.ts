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

type ViewMode = 'grid' | 'list';

const STORAGE_KEY = 'aa_view_mode';

/**
 * Read view mode from localStorage.
 */
function readViewMode(): ViewMode {
  try {
    const val = localStorage.getItem(STORAGE_KEY);
    return val === 'list' ? 'list' : 'grid';
  } catch {
    return 'grid';
  }
}

/**
 * Write view mode to localStorage.
 */
function writeViewMode(mode: ViewMode): void {
  try {
    localStorage.setItem(STORAGE_KEY, mode);
  } catch {
    // Private browsing or quota exceeded.
  }
}

/**
 * Apply view mode CSS classes to grid containers and body.
 */
function applyViewMode(mode: ViewMode): void {
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
    viewMode: 'grid' as ViewMode,

    get isGridView(): boolean {
      return state.viewMode === 'grid';
    },

    get isListView(): boolean {
      return state.viewMode === 'list';
    },
  },

  actions: {
    setGrid(): void {
      state.viewMode = 'grid';
      writeViewMode('grid');
      applyViewMode('grid');
    },

    setList(): void {
      state.viewMode = 'list';
      writeViewMode('list');
      applyViewMode('list');
    },
  },

  callbacks: {
    init(): void {
      const mode = readViewMode();
      state.viewMode = mode;
      applyViewMode(mode);
    },
  },
});
