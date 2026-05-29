/**
 * Predictive Search — Interactivity API Store
 *
 * Debounced search against WooCommerce Store API with keyboard navigation.
 * Supports multiple search instances on the same page via per-element context.
 *
 * @package Aggressive_Apparel
 * @since 1.18.0
 */

import type {
  InteractivityActions,
  InteractivityCallbacks,
} from '../../types/interactivity-shared';

import {
  store,
  getContext,
  getElement,
  withScope,
} from '@wordpress/interactivity';
import { parsePrice, decodeEntities } from '@aggressive-apparel/helpers';

interface PredictiveSearchProduct {
  name: string;
  permalink: string;
  thumbnail: string;
  price: string;
  regularPrice: string;
  onSale: boolean;
}

interface PredictiveSearchCategory {
  name: string;
  permalink: string;
}

interface PredictiveSearchContext {
  instanceId: string;
  query: string;
  products: PredictiveSearchProduct[];
  categories: PredictiveSearchCategory[];
  isOpen: boolean;
  isLoading: boolean;
  focusedIndex: number;
  totalResults: number;
  item: PredictiveSearchProduct | PredictiveSearchCategory;
}

interface StoreApiImage {
  thumbnail?: string;
  src?: string;
}

interface StoreApiCategory {
  id: number;
  name: string;
  link?: string;
  permalink?: string;
}

interface StoreApiPrices {
  price: string;
  regular_price: string;
  sale_price: string;
  currency_minor_unit: number;
  currency_prefix: string;
  currency_suffix: string;
}

interface StoreApiProduct {
  name: string;
  permalink: string;
  images?: StoreApiImage[];
  prices: StoreApiPrices;
  categories?: StoreApiCategory[];
}

interface PredictiveSearchStoreState {
  // Server-injected via wp_interactivity_state().
  restBase: string;
  searchUrl: string;
  // Getters.
  readonly isNotLoading: boolean;
  readonly hasNoProducts: boolean;
  readonly hasNoCategories: boolean;
  readonly hasResults: boolean;
  readonly ariaExpanded: string;
  readonly activeDescendant: string;
  readonly viewAllUrl: string;
  readonly resultAnnouncement: string;
  readonly query: string;
}

interface PredictiveSearchStore {
  state: PredictiveSearchStoreState;
  actions: InteractivityActions;
  callbacks: InteractivityCallbacks;
}

/** Per-instance timers and abort controllers, keyed by instanceId. */
const timers = new Map<string, ReturnType<typeof setTimeout>>();
const controllers = new Map<string, AbortController>();

function clearFocusClasses(container: Element | null): void {
  if (!container) return;
  container.querySelectorAll('.is-focused').forEach(el => {
    el.classList.remove('is-focused');
  });
}

const { state, actions } = store<PredictiveSearchStore>(
  'aggressive-apparel/predictive-search',
  {
    state: {
      get isNotLoading(): boolean {
        const ctx = getContext<PredictiveSearchContext>();
        return !ctx.isLoading;
      },
      get hasNoProducts(): boolean {
        const ctx = getContext<PredictiveSearchContext>();
        return ctx.products.length === 0;
      },
      get hasNoCategories(): boolean {
        const ctx = getContext<PredictiveSearchContext>();
        return ctx.categories.length === 0;
      },
      get hasResults(): boolean {
        const ctx = getContext<PredictiveSearchContext>();
        return ctx.products.length > 0 || ctx.categories.length > 0;
      },
      get ariaExpanded(): string {
        const ctx = getContext<PredictiveSearchContext>();
        return ctx.isOpen ? 'true' : 'false';
      },
      get activeDescendant(): string {
        const ctx = getContext<PredictiveSearchContext>();
        if (ctx.focusedIndex < 0) return '';
        return `ps-result-${ctx.instanceId}-${ctx.focusedIndex}`;
      },
      get viewAllUrl(): string {
        const ctx = getContext<PredictiveSearchContext>();
        return `${state.searchUrl}?s=${encodeURIComponent(ctx.query)}&post_type=product`;
      },
      get resultAnnouncement(): string {
        const ctx = getContext<PredictiveSearchContext>();
        if (ctx.isLoading) return '';
        const count = ctx.products.length;
        if (count === 0 && ctx.query.length >= 2) return 'No products found.';
        if (count === 1) return '1 product found.';
        if (count > 1) return `${count} products found.`;
        return '';
      },
      get query(): string {
        const ctx = getContext<PredictiveSearchContext>();
        return ctx.query;
      },
    },

    actions: {
      handleInput(event: Event): void {
        const ctx = getContext<PredictiveSearchContext>();
        const query = (event.target as HTMLInputElement).value.trim();
        ctx.query = query;

        const existing = timers.get(ctx.instanceId);
        if (existing) clearTimeout(existing);

        if (query.length < 2) {
          ctx.isOpen = false;
          ctx.products = [];
          ctx.categories = [];
          ctx.focusedIndex = -1;
          return;
        }

        ctx.isLoading = true;
        ctx.isOpen = true;

        timers.set(
          ctx.instanceId,
          setTimeout(
            withScope(() => {
              actions.performSearch();
            }),
            300
          )
        );
      },

      handleFocus(): void {
        const ctx = getContext<PredictiveSearchContext>();
        if (ctx.products.length > 0 && ctx.query.length >= 2) {
          ctx.isOpen = true;
        }
      },

      handleKeydown(event: KeyboardEvent): void {
        const ctx = getContext<PredictiveSearchContext>();
        if (!ctx.isOpen) return;

        const totalItems = ctx.products.length + ctx.categories.length;

        switch (event.key) {
          case 'ArrowDown':
            event.preventDefault();
            ctx.focusedIndex = Math.min(ctx.focusedIndex + 1, totalItems - 1);
            break;

          case 'ArrowUp':
            event.preventDefault();
            ctx.focusedIndex = Math.max(ctx.focusedIndex - 1, -1);
            break;

          case 'Enter':
            if (ctx.focusedIndex >= 0) {
              event.preventDefault();
              const allItems = [...ctx.products, ...ctx.categories];
              const item = allItems[ctx.focusedIndex];
              if (item && item.permalink) {
                window.location.href = item.permalink;
              }
            }
            break;

          case 'Escape':
            ctx.isOpen = false;
            ctx.focusedIndex = -1;
            clearFocusClasses(
              (event.target as HTMLElement).closest('.aa-predictive-search')
            );
            return;
        }

        // Highlight the focused result item, sync aria-selected, and scroll into view.
        const container = (event.target as HTMLElement).closest(
          '.aa-predictive-search'
        );
        if (!container) return;
        const items = container.querySelectorAll('[role="option"]');
        items.forEach((item, i) => {
          const isFocused = i === ctx.focusedIndex;
          item.classList.toggle('is-focused', isFocused);
          item.setAttribute('aria-selected', isFocused ? 'true' : 'false');
        });
        if (ctx.focusedIndex >= 0 && items[ctx.focusedIndex]) {
          items[ctx.focusedIndex].scrollIntoView({ block: 'nearest' });
        }
      },

      handleClickOutside(event: Event): void {
        const ctx = getContext<PredictiveSearchContext>();
        if (!ctx.isOpen) return;

        const container = (event.target as HTMLElement).closest(
          '.aa-predictive-search'
        );
        if (!container) {
          ctx.isOpen = false;
          ctx.focusedIndex = -1;
          // Clear focused classes on all instances since click is outside all of them.
          document.querySelectorAll('.aa-predictive-search').forEach(el => {
            clearFocusClasses(el);
          });
        }
      },

      performSearch(): void {
        const ctx = getContext<PredictiveSearchContext>();

        const prev = controllers.get(ctx.instanceId);
        if (prev) prev.abort();

        const ac = new AbortController();
        controllers.set(ctx.instanceId, ac);

        const url = `${state.restBase}?search=${encodeURIComponent(ctx.query)}&per_page=6`;

        fetch(url, { signal: ac.signal })
          .then((res: Response) => {
            if (!res.ok) throw new Error('Search failed');
            return res.json();
          })
          .then(
            withScope((products: StoreApiProduct[]) => {
              const seenCategories = new Map<
                number,
                PredictiveSearchCategory
              >();

              ctx.products = products.map(
                (p: StoreApiProduct): PredictiveSearchProduct => {
                  const priceData = parsePrice(p.prices);
                  return {
                    name: decodeEntities(p.name),
                    permalink: p.permalink,
                    thumbnail:
                      p.images?.[0]?.thumbnail || p.images?.[0]?.src || '',
                    price: priceData.current,
                    regularPrice: priceData.regular,
                    onSale: priceData.onSale,
                  };
                }
              );

              // Extract unique categories from results.
              products.forEach((p: StoreApiProduct) => {
                if (p.categories) {
                  p.categories.forEach((cat: StoreApiCategory) => {
                    if (!seenCategories.has(cat.id)) {
                      seenCategories.set(cat.id, {
                        name: decodeEntities(cat.name),
                        permalink: cat.link || cat.permalink || '#',
                      });
                    }
                  });
                }
              });

              ctx.categories = Array.from(seenCategories.values()).slice(0, 4);
              ctx.totalResults = products.length;
              ctx.isLoading = false;
              ctx.focusedIndex = -1;
            })
          )
          .catch(
            withScope((err: Error) => {
              if (err.name === 'AbortError') return;
              ctx.isLoading = false;
              ctx.products = [];
              ctx.categories = [];
            })
          );
      },
    },

    callbacks: {
      syncResultImage(): void {
        const ctx = getContext<PredictiveSearchContext>();
        const { ref } = getElement();
        if (!ref || !ctx.item) return;
        const item = ctx.item as PredictiveSearchProduct;
        if (item.thumbnail) {
          (ref as HTMLImageElement).src = item.thumbnail;
          (ref as HTMLImageElement).alt = ctx.item.name || '';
        } else {
          ref.removeAttribute('src');
          (ref as HTMLImageElement).alt = '';
          ref.classList.add('no-image');
        }
      },
      syncOptionAttrs(): void {
        const ctx = getContext<PredictiveSearchContext>();
        const { ref } = getElement();
        if (!ref) return;
        // Assign a stable ID based on DOM position so aria-activedescendant can reference it.
        const listbox = ref.closest('[role="listbox"]');
        if (!listbox) return;
        const allOptions = listbox.querySelectorAll('[role="option"]');
        const index = Array.prototype.indexOf.call(allOptions, ref);
        ref.id = `ps-result-${ctx.instanceId}-${index}`;
        ref.setAttribute(
          'aria-selected',
          index === ctx.focusedIndex ? 'true' : 'false'
        );
      },
      syncResultsVisibility(): void {
        const ctx = getContext<PredictiveSearchContext>();
        const { ref } = getElement();
        if (!ref) return;
        if (ctx.isOpen) {
          ref.removeAttribute('hidden');
          ref.style.display = '';
          ref.classList.add('is-open');
        } else {
          ref.classList.remove('is-open');
          ref.setAttribute('hidden', '');
          ref.style.display = 'none';
        }
      },
      highlightName(): void {
        const ctx = getContext<PredictiveSearchContext>();
        const { ref } = getElement();
        if (!ref || !ctx.item) return;
        const name = ctx.item.name;
        const query = ctx.query;
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
  }
);
