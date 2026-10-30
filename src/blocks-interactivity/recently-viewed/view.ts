/** Recently viewed products, stored on this device and resolved via Store API. */
import { store, getContext, getElement } from '@wordpress/interactivity';
import {
  historyIds,
  httpUrl,
  isRecentProduct,
  MAX_STORED,
  renderProducts,
  STORAGE_KEY,
  type RecentProduct,
} from './products';

interface RecentlyViewedContext {
  currentProductId: number;
  maxDisplay: number;
  restBase: string;
  products: RecentProduct[];
  loaded: boolean;
}

store('aggressive-apparel/recently-viewed', {
  callbacks: {
    renderProducts(): void {
      const { ref } = getElement();
      if (ref instanceof HTMLElement) {
        renderProducts(ref, getContext<RecentlyViewedContext>().products);
      }
    },

    init(): () => void {
      const ctx = getContext<RecentlyViewedContext>();
      let ids: number[] = [];
      try {
        ids = historyIds(JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'));
      } catch {
        // Storage can be disabled or contain malformed data.
      }
      ids = ids.filter(id => id !== ctx.currentProductId);
      if (
        Number.isSafeInteger(ctx.currentProductId) &&
        ctx.currentProductId > 0
      ) {
        try {
          localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify([ctx.currentProductId, ...ids].slice(0, MAX_STORED))
          );
        } catch {
          // A denied write must not stop previously read history from rendering.
        }
      }

      const controller = new AbortController();
      let timer: ReturnType<typeof setTimeout> | undefined;
      const cleanup = (): void => {
        controller.abort();
        clearTimeout(timer);
      };
      const maxDisplay = Number.isFinite(ctx.maxDisplay)
        ? Math.max(1, Math.min(MAX_STORED, Math.floor(ctx.maxDisplay)))
        : 4;
      const displayIds = ids.slice(0, maxDisplay);
      const base = httpUrl(ctx.restBase || '/wp-json/wc/store/v1/products');
      if (!displayIds.length || !base) return cleanup;
      const url = new URL(base);
      if (url.origin !== location.origin) return cleanup;
      url.searchParams.set('include', displayIds.join(','));
      url.searchParams.set('orderby', 'include');
      url.searchParams.set('per_page', String(displayIds.length));

      timer = setTimeout(() => controller.abort(), 10_000);
      fetch(url.href, { mode: 'same-origin', signal: controller.signal })
        .then((response: Response) => {
          if (!response.ok) throw new Error('Recently viewed request failed');
          return response.json();
        })
        .then((data: unknown) => {
          if (controller.signal.aborted || !Array.isArray(data)) return;
          ctx.products = data
            .filter(isRecentProduct)
            .filter(product => displayIds.includes(product.id))
            .slice(0, maxDisplay);
          ctx.loaded = ctx.products.some(product => httpUrl(product.permalink));
        })
        .catch(() => {
          // This optional section stays hidden when the Store API is unavailable.
        })
        .finally(() => clearTimeout(timer));
      return cleanup;
    },
  },
});
