/** Public product lookup, separate from the authenticated Shopper Lists schema. */
import { isWishlistProduct, type WishlistProduct } from './account';
import { requestJson } from './request';

/** Fetch in bounded batches so lists larger than the Store API page size survive. */
export async function fetchWishlistProducts(
  ids: number[],
  endpoint?: string
): Promise<WishlistProduct[]> {
  const products: WishlistProduct[] = [];
  for (let start = 0; start < ids.length; start += 100) {
    if (!endpoint) throw new Error('Wishlist products API unavailable');
    const url = new URL(endpoint, location.href);
    url.searchParams.set('include', ids.slice(start, start + 100).join(','));
    url.searchParams.set('per_page', '100');
    const { response, data } = await requestJson(url.href);
    if (!response.ok) throw new Error('Product request failed');
    if (!Array.isArray(data)) throw new Error('Invalid product response');
    // Public products expose a human-readable variation string; Shopper Lists
    // exposes an attribute array. Normalize only the public response here.
    const batch: unknown[] = data.map(value =>
      value && typeof value === 'object' && typeof value.variation === 'string'
        ? { ...value, variation: [] }
        : value
    );
    if (!batch.every(isWishlistProduct))
      throw new Error('Invalid product response');
    products.push(...batch);
  }
  return products;
}
