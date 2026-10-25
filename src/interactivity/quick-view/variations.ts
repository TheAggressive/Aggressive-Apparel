/**
 * Quick View — variation details from the Store API.
 *
 * @package Aggressive_Apparel
 */

import type { StoreApiProduct } from './types';

/** The Store API's page-size cap. */
const PAGE_SIZE = 100;

/** Pages followed before giving up on a pathological product. */
const MAX_PAGES = 10;

/**
 * Fetch every variation of a product from the Store API, which returns each
 * one's prices (in the store's tax display), stock and image. A simple
 * product just yields an empty list. Anything but an abort degrades to what
 * was fetched so far: the modal still works, with parent prices and no
 * sold-out dimming for the missing variations.
 *
 * @param endpoint  The Store API products route, with or without a trailing
 *                  slash, in either permalink style.
 * @param productId Parent product ID.
 * @param signal    Aborts with the product request.
 */
export async function fetchVariations(
  endpoint: string,
  productId: number,
  signal?: AbortSignal
): Promise<StoreApiProduct[]> {
  const variations: StoreApiProduct[] = [];

  try {
    for (let page = 1, pages = 1; page <= pages && page <= MAX_PAGES; page++) {
      const url = new URL(endpoint.replace(/\/$/, ''), location.href);
      url.searchParams.set('type', 'variation');
      url.searchParams.set('parent', String(productId));
      url.searchParams.set('per_page', String(PAGE_SIZE));
      url.searchParams.set('page', String(page));

      const res = await fetch(url.toString(), { signal });
      if (!res.ok) break;
      const data: unknown = await res.json();
      if (!Array.isArray(data)) break;
      variations.push(...(data as StoreApiProduct[]));
      pages = parseInt(res.headers.get('X-WP-TotalPages') || '1', 10) || 1;
    }
  } catch (err) {
    if ((err as Error)?.name === 'AbortError') throw err;
  }

  return variations;
}
