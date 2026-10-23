/** Guest persistence and the public WooCommerce Shopper Lists transport. */
import type { StoreApiPrices } from '@aggressive-apparel/helpers';
import { requestJson } from './request';

export const STORAGE_KEY = 'aggressive_apparel_wishlist';

export interface WishlistProduct {
  id: number;
  key?: string;
  product_id?: number;
  variation_id?: number;
  variation?: { raw_attribute: string; attribute: string; value: string }[];
  name: string;
  permalink: string | null;
  type?: string;
  images?: { src: string }[];
  prices: StoreApiPrices | null;
  add_to_cart?: { url?: string };
  is_purchasable?: boolean;
  is_in_stock?: boolean;
}

export interface WishlistConfig {
  account?: boolean;
  itemsUrl?: string;
  productsUrl?: string;
  restNonce?: string;
  nonce?: string;
}

export function readGuestItems(): number[] {
  // A denied read must not masquerade as an empty wishlist. Only malformed JSON
  // is recoverable without telling the shopper that storage is unavailable.
  const serialized = localStorage.getItem(STORAGE_KEY);
  try {
    const value: unknown = JSON.parse(serialized || '[]');
    return Array.isArray(value)
      ? [
          ...new Set(
            value.filter(
              (id): id is number => Number.isSafeInteger(id) && id > 0
            )
          ),
        ]
      : [];
  } catch {
    return [];
  }
}

export function writeGuestItems(items: number[]): void {
  localStorage.setItem(STORAGE_KEY, JSON.stringify([...new Set(items)]));
}

/** Validate the adapter boundary before remote data reaches templates. */
export function isWishlistProduct(value: unknown): value is WishlistProduct {
  if (!value || typeof value !== 'object') return false;
  const product = value as WishlistProduct;
  return (
    Number.isSafeInteger(product.id) &&
    product.id > 0 &&
    typeof product.name === 'string' &&
    (product.permalink === null || typeof product.permalink === 'string') &&
    (product.type === undefined || typeof product.type === 'string') &&
    (product.key === undefined || typeof product.key === 'string') &&
    ['is_purchasable', 'is_in_stock'].every(
      key =>
        product[key as 'is_purchasable' | 'is_in_stock'] === undefined ||
        typeof product[key as 'is_purchasable' | 'is_in_stock'] === 'boolean'
    ) &&
    (product.add_to_cart === undefined ||
      (product.add_to_cart !== null &&
        typeof product.add_to_cart === 'object' &&
        (product.add_to_cart.url === undefined ||
          typeof product.add_to_cart.url === 'string'))) &&
    (product.prices === null ||
      (typeof product.prices === 'object' &&
        product.prices !== undefined &&
        typeof product.prices.price === 'string' &&
        /^\d*$/.test(product.prices.price) &&
        (product.prices.currency_minor_unit === undefined ||
          (Number.isInteger(product.prices.currency_minor_unit) &&
            product.prices.currency_minor_unit >= 0 &&
            product.prices.currency_minor_unit <= 6)))) &&
    (product.images === undefined ||
      (Array.isArray(product.images) &&
        product.images.every(
          image => image && typeof image.src === 'string'
        ))) &&
    (product.variation === undefined ||
      (Array.isArray(product.variation) &&
        product.variation.every(
          attribute =>
            attribute &&
            typeof attribute.raw_attribute === 'string' &&
            typeof attribute.attribute === 'string' &&
            typeof attribute.value === 'string'
        ))) &&
    ['product_id', 'variation_id'].every(key => {
      const id = product[key as 'product_id' | 'variation_id'];
      return id === undefined || (Number.isSafeInteger(id) && id >= 0);
    })
  );
}

/** Own no account data: all authenticated reads and writes go to WooCommerce. */
export class WishlistAccount {
  /** A failed write may have committed remotely. Re-read before the next write. */
  needsReconcile = true;
  importFailed = false;

  constructor(private config: WishlistConfig) {}

  async request(
    path = '',
    method = 'GET',
    body?: unknown,
    retry = true
  ): Promise<unknown> {
    if (!this.config.itemsUrl) throw new Error('Wishlist API unavailable');
    // Append path before the query so plain-permalink REST URLs remain valid.
    const url = new URL(this.config.itemsUrl, location.href);
    if (url.searchParams.has('rest_route')) {
      url.searchParams.set(
        'rest_route',
        url.searchParams.get('rest_route') + path
      );
    } else {
      url.pathname += path;
    }
    if (method !== 'GET') this.needsReconcile = true;
    const { response, data } = await requestJson(url.href, {
      method,
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': this.config.restNonce || '',
        Nonce: this.config.nonce || '',
      },
      ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
    const nonce = response.headers.get('Nonce');
    if (nonce) this.config.nonce = nonce;
    if (!response.ok) {
      const error = (data && typeof data === 'object' ? data : {}) as {
        code?: string;
        message?: string;
      };
      if (
        retry &&
        nonce &&
        [
          'woocommerce_rest_invalid_nonce',
          'woocommerce_rest_missing_nonce',
        ].includes(error.code || '')
      ) {
        return this.request(path, method, body, false);
      }
      throw Object.assign(
        new Error(error.message || 'Wishlist request failed'),
        { code: error.code }
      );
    }
    return data;
  }

  async list(): Promise<WishlistProduct[]> {
    const data = await this.request();
    if (
      !Array.isArray(data) ||
      !data.every(
        item =>
          isWishlistProduct(item) &&
          typeof item.key === 'string' &&
          item.key.length > 0
      )
    )
      throw new Error('Invalid wishlist response');
    this.needsReconcile = false;
    return data;
  }

  async add(id: number): Promise<WishlistProduct> {
    const data = await this.request('', 'POST', { product_id: id });
    if (!isWishlistProduct(data) || typeof data.key !== 'string' || !data.key)
      throw new Error('Invalid wishlist response');
    this.needsReconcile = false;
    return data;
  }

  async remove(key: string): Promise<void> {
    await this.request(`/${encodeURIComponent(key)}`, 'DELETE');
    this.needsReconcile = false;
  }

  /** Clear only confirmed imports. Unsupported parents remain saved locally. */
  async merge(
    items: WishlistProduct[],
    products: Pick<WishlistProduct, 'id' | 'type'>[]
  ): Promise<WishlistProduct[]> {
    this.importFailed = false;
    const merged = [...items];
    for (const id of readGuestItems()) {
      if (merged.some(item => item.id === id || item.product_id === id)) {
        try {
          writeGuestItems(readGuestItems().filter(saved => saved !== id));
        } catch {
          this.importFailed = true;
          break;
        }
        continue;
      }
      // The public product lookup excludes unpublished/deleted items. Variable
      // parents need explicit options; never submit a known-invalid request.
      const product = products.find(candidate => candidate.id === id);
      if (!product || product.type === 'variable') continue;
      try {
        merged.push(await this.add(id));
        writeGuestItems(readGuestItems().filter(saved => saved !== id));
      } catch {
        this.importFailed = true;
        // An uncertain POST must be reconciled before another import is tried.
        // Preserve all remaining browser IDs and let the next page load retry.
        break;
      }
    }
    return merged;
  }
}
