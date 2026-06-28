/// <reference types="@wordpress/interactivity" />

/**
 * Grid / List Toggle — Interactivity API Store
 *
 * Switches between grid and list view on shop archive pages.
 * Visitor preference is persisted in localStorage.
 *
 * @package Aggressive_Apparel
 */

import { store } from '@wordpress/interactivity';

type ViewMode = 'grid' | 'list';

const STORAGE_KEY = 'aa_view_mode';

function readViewMode(): ViewMode {
  try {
    const val = localStorage.getItem(STORAGE_KEY);
    return val === 'list' ? 'list' : 'grid';
  } catch {
    return 'grid';
  }
}

function writeViewMode(mode: ViewMode): void {
  try {
    localStorage.setItem(STORAGE_KEY, mode);
  } catch {
    // Private browsing or quota exceeded.
  }
}

function applyViewMode(mode: ViewMode): void {
  const isList = mode === 'list';
  // List-view layout is driven entirely by the body class — the rules target the
  // native `.wp-block-woocommerce-product-template` grid, which is shared by the
  // initial archive and the filtered/sorted results.
  document.body.classList.toggle('aa-list-view', isList);
  document.body.classList.toggle('aa-grid-view', !isList);
}

interface GridListState {
  viewMode: ViewMode;
  readonly isGridView: boolean;
  readonly isListView: boolean;
}

interface GridListStore {
  state: GridListState;
  actions: {
    setGrid: () => void;
    setList: () => void;
  };
  callbacks: {
    init: () => void;
  };
}

const { state } = store<GridListStore>('aggressive-apparel/grid-list-toggle', {
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
