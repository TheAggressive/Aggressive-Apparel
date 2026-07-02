/** Owns cancellation and debounce state for product-filter requests. */
export class FilterRequestManager {
  private productsController: AbortController | null = null;
  private facetsController: AbortController | null = null;
  private facetsTimer: ReturnType<typeof setTimeout> | null = null;

  /** Cancel the prior product request and return a signal for the next one. */
  beginProducts(): AbortSignal {
    this.productsController?.abort();
    this.productsController = new AbortController();
    return this.productsController.signal;
  }

  /** Cancel stale facet work and debounce the next availability request. */
  scheduleFacets(callback: () => void, delay = 200): void {
    this.cancelFacets();
    this.facetsTimer = setTimeout(callback, delay);
  }

  /** Start a facet request after clearing its debounce handle. */
  beginFacets(): AbortSignal {
    if (this.facetsTimer) clearTimeout(this.facetsTimer);
    this.facetsTimer = null;
    this.facetsController?.abort();
    this.facetsController = new AbortController();
    return this.facetsController.signal;
  }

  /** Cancel pending and in-flight facet work. */
  cancelFacets(): void {
    if (this.facetsTimer) clearTimeout(this.facetsTimer);
    this.facetsTimer = null;
    this.facetsController?.abort();
    this.facetsController = null;
  }

  /** Cancel every request before a hard page navigation. */
  cancelAll(): void {
    this.cancelFacets();
    this.productsController?.abort();
    this.productsController = null;
  }
}
